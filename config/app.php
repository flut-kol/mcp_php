<?php

$env = static function (string $key, $default = null) {
    return function_exists('mcp_env') ? mcp_env($key, $default) : (getenv($key) ?: $default);
};

$projectName = (string)$env('MCP_PROJECT_NAME', basename(dirname(__DIR__)));
$projectSlug = preg_replace('/[^a-z0-9_]+/', '_', strtolower($projectName));
$projectSlug = trim((string)$projectSlug, '_') ?: 'mcp_app';

return [
    'project' => [
        'name' => $projectSlug,
        'title' => (string)$env('MCP_PROJECT_TITLE', strtoupper($projectSlug)),
        'root' => dirname(__DIR__),
        'timezone' => (string)$env('MCP_TIMEZONE', 'Europe/Kyiv'),
    ],

    'mysql' => [
        'host' => (string)$env('MCP_MYSQL_HOST', '127.0.0.1'),
        'port' => (int)$env('MCP_MYSQL_PORT', 3306),
        'database' => (string)$env('MCP_MYSQL_DATABASE', $projectSlug),
        'user' => (string)$env('MCP_MYSQL_USER', $projectSlug),
        'password' => (string)$env('MCP_MYSQL_PASSWORD', $projectSlug),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'grant_host' => (string)$env('MCP_MYSQL_GRANT_HOST', '%'),
    ],

    'mysql_admin' => [
        'host' => (string)$env('MCP_MYSQL_ADMIN_HOST', $env('MCP_MYSQL_HOST', '127.0.0.1')),
        'port' => (int)$env('MCP_MYSQL_ADMIN_PORT', $env('MCP_MYSQL_PORT', 3306)),
        'user' => (string)$env('MCP_MYSQL_ADMIN_USER', 'root'),
        'password' => (string)$env('MCP_MYSQL_ADMIN_PASSWORD', ''),
    ],

    'clickhouse' => [
        'url' => (string)$env('MCP_CLICKHOUSE_URL', 'http://localhost:8123'),
        'database' => (string)$env('MCP_CLICKHOUSE_DATABASE', 'default'),
    ],

    'manticore' => [
        'host' => (string)$env('MCP_MANTICORE_HOST', '127.0.0.1'),
        'port' => (int)$env('MCP_MANTICORE_PORT', 9306),
        'database' => (string)$env('MCP_MANTICORE_DATABASE', ''),
        'user' => $env('MCP_MANTICORE_USER', null),
        'password' => $env('MCP_MANTICORE_PASSWORD', null),
    ],

    'mcp' => [
        'protocol_version' => (string)$env('MCP_PROTOCOL_VERSION', '2025-11-25'),
        'server_name' => (string)$env('MCP_SERVER_NAME', $projectSlug),
        'server_title' => (string)$env('MCP_SERVER_TITLE', strtoupper($projectSlug) . ' MCP'),
        'version' => (string)$env('MCP_SERVER_VERSION', '0.1.0'),
        'allow_unsafe_sql' => (bool)$env('MCP_ALLOW_UNSAFE_SQL', false),
    ],

    'auth' => [
        'session_name' => (string)$env('MCP_SESSION_NAME', $projectSlug . '_session'),
        'session_lifetime' => (int)$env('MCP_SESSION_LIFETIME', 86400),
        'tables' => [
            'users' => 'mcp_users',
            'pages' => 'mcp_pages',
            'access' => 'mcp_user_page_access',
        ],
        'bootstrap_admin' => [
            'login' => (string)$env('MCP_ADMIN_LOGIN', 'admin'),
            'password' => (string)$env('MCP_ADMIN_PASSWORD', 'admin'),
            'name' => (string)$env('MCP_ADMIN_NAME', 'Administrator'),
        ],
    ],

    'paths' => [
        'app' => dirname(__DIR__) . '/app',
        'web' => dirname(__DIR__) . '/web',
        'tasks' => dirname(__DIR__) . '/tasks',
        'var' => dirname(__DIR__) . '/var',
    ],
];
