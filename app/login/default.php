<?php

$error = '';
$login = trim((string)($_POST['login'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (mcp_auth_login($login, (string)($_POST['password'] ?? ''))) {
            mcp_redirect('/');
        }

        $error = 'Invalid login or password.';
    } catch (Throwable $e) {
        $error = 'Database is not ready. Run php tasks/setup_db.php first.';
    }
}

try {
    if (mcp_auth_user()) {
        mcp_redirect('/');
    }
} catch (Throwable $e) {
    $error = $error ?: 'Database is not ready. Run php tasks/setup_db.php first.';
}

return [
    'section.login-page' => [
        ['div.login-panel' => [
            ['h1' => e((string)mcp_config('project.title', 'MCP'))],
            ['p.muted' => 'Sign in to manage this MCP project.'],
            $error ? ['div.notice.error' => e($error)] : [],
            ['form:/login' => [
                ':method' => 'post',
                ['label' => [
                    ['span' => 'Login'],
                    ['input:login' => [
                        ':value' => $login,
                        ':autocomplete' => 'username',
                        ':autofocus' => 'autofocus',
                    ]],
                ]],
                ['label' => [
                    ['span' => 'Password'],
                    ['input:password' => [
                        ':type' => 'password',
                        ':autocomplete' => 'current-password',
                    ]],
                ]],
                ['submit.primary' => 'Login'],
            ]],
        ]],
    ],
];
