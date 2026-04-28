<?php

function mcp_configure_runtime(): void
{
    $mysql = mcp_config('mysql', []);
    if (class_exists('mysqly')) {
        mysqly::auth(
            (string)($mysql['user'] ?? ''),
            (string)($mysql['password'] ?? ''),
            (string)($mysql['database'] ?? ''),
            (string)($mysql['host'] ?? '127.0.0.1'),
            (int)($mysql['port'] ?? 3306)
        );
    }

    $clickhouse = mcp_config('clickhouse', []);
    if (class_exists('clickhousy')) {
        clickhousy::set_url((string)($clickhouse['url'] ?? 'http://localhost:8123'));
        clickhousy::set_db((string)($clickhouse['database'] ?? 'default'));
    }

    $manticore = mcp_config('manticore', []);
    if (class_exists('manticory')) {
        manticory::auth(
            $manticore['user'] ?? null,
            $manticore['password'] ?? null,
            $manticore['database'] ?? null,
            (string)($manticore['host'] ?? '127.0.0.1'),
            (int)($manticore['port'] ?? 9306)
        );
    }
}

function mcp_now(): string
{
    return date('Y-m-d H:i:s');
}

function mcp_normalize_path(string $path): string
{
    $path = parse_url($path, PHP_URL_PATH) ?: '/';
    $path = '/' . ltrim($path, '/');
    return rtrim($path, '/') ?: '/';
}

function mcp_is_readonly_sql(string $sql): bool
{
    $sql = ltrim($sql);
    return (bool)preg_match('/^(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $sql);
}

function mcp_mask_secret($value)
{
    if ($value === null || $value === '') {
        return $value;
    }

    return '********';
}

function mcp_public_config(): array
{
    $config = mcp_config();

    foreach ([
        ['mysql', 'password'],
        ['mysql_admin', 'password'],
        ['manticore', 'password'],
        ['auth', 'bootstrap_admin', 'password'],
    ] as $path) {
        $cursor = &$config;
        foreach ($path as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                continue 2;
            }
            $cursor = &$cursor[$part];
        }
        $cursor = mcp_mask_secret($cursor);
        unset($cursor);
    }

    return $config;
}

function mcp_tool_text($text, array $structured = [], bool $isError = false): array
{
    $result = [
        'content' => [
            [
                'type' => 'text',
                'text' => is_string($text) ? $text : json_encode($text, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
        ],
        'isError' => $isError,
    ];

    if ($structured) {
        $result['structuredContent'] = $structured;
    }

    return $result;
}

function mcp_redirect(string $path): never
{
    if (function_exists('redirect')) {
        redirect($path);
    }

    header('Location: ' . $path, true, 302);
    exit;
}
