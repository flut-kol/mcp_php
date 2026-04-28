<?php

$user = null;
try {
    $user = mcp_auth_user();
} catch (Throwable $e) {
    $user = null;
}

$title = (string)mcp_config('project.title', 'MCP');

return ['html' => [
    ':v' => '1',
    ':title' => $title,
    ':head' => '<meta name="viewport" content="width=device-width, initial-scale=1">',
    'div.shell' => [
        $user ? ['header.topbar' => [
            ['a.brand:/' => e($title)],
            ['nav' => [
                ['a:/' => 'Dashboard'],
                ($user['role'] ?? '') === 'admin' ? ['a:/access' => 'Access'] : [],
            ]],
            ['div.userbox' => [
                ['span' => e((string)($user['name'] ?: $user['login']))],
                ['a:/logout' => 'Logout'],
            ]],
        ]] : [],
        ['main.content' => phpy()],
    ],
]];
