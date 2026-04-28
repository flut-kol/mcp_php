#!/usr/bin/env php
<?php

require_once __DIR__ . '/lib/bootstrap.php';

function mcp_cli_usage(): void
{
    echo "MCP starter\n\n";
    echo "Usage:\n";
    echo "  php mcp.php init [target_dir] [project_name] [--force]\n";
    echo "  php mcp.php setup-db [setup_db_options]\n";
    echo "  php mcp.php serve\n\n";
}

function mcp_cli_arg(int $index, ?string $default = null): ?string
{
    global $argv;
    return isset($argv[$index]) && strpos($argv[$index], '--') !== 0 ? $argv[$index] : $default;
}

function mcp_cli_has_flag(string $flag): bool
{
    global $argv;
    return in_array($flag, $argv, true);
}

function mcp_cli_copy_tree(string $source, string $target): void
{
    $skip = ['.git', 'var', 'config/local.php'];
    $source = rtrim($source, '/');
    $target = rtrim($target, '/');

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        foreach ($skip as $skipPath) {
            if ($relative === $skipPath || str_starts_with($relative, $skipPath . '/')) {
                continue 2;
            }
        }

        $destination = $target . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0755, true)) {
                throw new RuntimeException("Unable to create {$destination}");
            }
            continue;
        }

        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0755, true)) {
            throw new RuntimeException('Unable to create ' . dirname($destination));
        }

        if (!copy($item->getPathname(), $destination)) {
            throw new RuntimeException("Unable to copy {$relative}");
        }
    }
}

function mcp_cli_init(): void
{
    $source = realpath(__DIR__);
    $target = mcp_cli_arg(2, getcwd());
    $projectName = mcp_cli_arg(3, basename($target));
    $force = mcp_cli_has_flag('--force');

    if (!$source || !$target) {
        throw new RuntimeException('Unable to resolve source or target path.');
    }

    if (!is_dir($target) && !mkdir($target, 0755, true)) {
        throw new RuntimeException("Unable to create {$target}");
    }

    $target = realpath($target);
    if (!$target) {
        throw new RuntimeException('Unable to resolve target path.');
    }

    $sameDir = $target === $source;
    if (!$sameDir) {
        $existing = array_values(array_diff(scandir($target) ?: [], ['.', '..']));
        if ($existing && !$force) {
            throw new RuntimeException("Target directory is not empty. Use --force to copy starter files anyway.");
        }
        mcp_cli_copy_tree($source, $target);
    }

    foreach (['app', 'config', 'lib', 'tasks', 'web', 'var'] as $dir) {
        if (!is_dir($target . '/' . $dir) && !mkdir($target . '/' . $dir, 0755, true)) {
            throw new RuntimeException("Unable to create {$dir}");
        }
    }

    $slug = mcp_slug($projectName);
    $localFile = $target . '/config/local.php';
    if (is_file($localFile) && !$force) {
        throw new RuntimeException("config/local.php already exists. Use --force to overwrite it.");
    }

    $adminPassword = mcp_random_secret(9);
    $dbPassword = mcp_random_secret(18);
    $title = strtoupper(str_replace('_', ' ', $slug));

    $localConfig = [
        'project' => [
            'name' => $slug,
            'title' => $title,
        ],
        'mysql' => [
            'database' => $slug,
            'user' => $slug,
            'password' => $dbPassword,
        ],
        'mcp' => [
            'server_name' => $slug,
            'server_title' => $title . ' MCP',
        ],
        'auth' => [
            'session_name' => $slug . '_session',
            'bootstrap_admin' => [
                'login' => 'admin',
                'password' => $adminPassword,
                'name' => 'Administrator',
            ],
        ],
    ];

    file_put_contents($localFile, mcp_export_config($localConfig));

    echo "MCP project initialized: {$target}\n\n";
    echo "Admin login: admin\n";
    echo "Admin password: {$adminPassword}\n\n";
    echo "Next steps:\n";
    echo "  cd {$target}\n";
    echo "  php tasks/setup_db.php --admin-user=root --admin-password=ROOT_PASSWORD\n";
    echo "  php mcp.php serve\n\n";
    echo "Nginx root: {$target}/web\n";
}

$command = $argv[1] ?? 'help';

try {
    if ($command === 'init') {
        mcp_cli_init();
    } elseif ($command === 'setup-db') {
        require __DIR__ . '/tasks/setup_db.php';
    } elseif ($command === 'serve') {
        require __DIR__ . '/boot.php';
        require __DIR__ . '/lib/mcp_server.php';
        mcp_server_stdio();
    } else {
        mcp_cli_usage();
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
