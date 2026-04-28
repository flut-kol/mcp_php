<?php

$user = mcp_auth_user();
$pages = [];

try {
    foreach (mcp_auth_pages() as $page) {
        if ((int)$page['active'] && mcp_auth_can_access((string)$page['path'], $user)) {
            $pages[] = $page;
        }
    }
} catch (Throwable $e) {
    $pages = [];
}

return [
    'section.page-head' => [
        ['h1' => 'Dashboard'],
        ['p.muted' => 'MCP starter with MySQL, ClickHouse, Manticore, PHPy and web access control.'],
    ],
    'section.grid' => [
        ['div.panel' => [
            ['h2' => 'Project'],
            ['dl.meta' => [
                'Name' => e((string)mcp_config('project.name')),
                'MCP server' => e((string)mcp_config('mcp.server_name')),
                'Protocol' => e((string)mcp_config('mcp.protocol_version')),
            ]],
        ]],
        ['div.panel' => [
            ['h2' => 'Database'],
            ['dl.meta' => [
                'MySQL' => e((string)mcp_config('mysql.database')) . '@' . e((string)mcp_config('mysql.host')),
                'ClickHouse' => e((string)mcp_config('clickhouse.database')),
                'Manticore' => e((string)mcp_config('manticore.host')) . ':' . e((string)mcp_config('manticore.port')),
            ]],
        ]],
    ],
    'section.panel' => [
        ['h2' => 'Pages'],
        $pages ? ['div.link-list' => array_map(
            fn($page) => ['a:' . $page['path'] => e($page['title'] ?: $page['path'])],
            $pages
        )] : ['p.muted' => 'No pages are available for this account yet.'],
    ],
];
