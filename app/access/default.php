<?php

$me = mcp_auth_user();
if (!$me || ($me['role'] ?? '') !== 'admin') {
    http_response_code(403);
    return [
        'section.panel' => [
            ['h1' => 'Access denied'],
            ['p.muted' => 'Only administrators can manage access.'],
        ],
    ];
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'create_user') {
            $userId = mcp_auth_create_user(
                (string)($_POST['login'] ?? ''),
                (string)($_POST['password'] ?? ''),
                (string)($_POST['name'] ?? ''),
                (string)($_POST['role'] ?? 'user')
            );
            mcp_auth_save_user_access($userId, (array)($_POST['page_ids'] ?? []));
            $notice = 'User created.';
        } elseif ($action === 'create_page') {
            mcp_auth_upsert_page(
                (string)($_POST['path'] ?? ''),
                (string)($_POST['title'] ?? ''),
                !empty($_POST['active']) ? 1 : 0
            );
            $notice = 'Page saved.';
        } elseif ($action === 'save_access') {
            mcp_auth_save_user_access((int)($_POST['user_id'] ?? 0), (array)($_POST['page_ids'] ?? []));
            $notice = 'Access updated.';
        } elseif ($action === 'toggle_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId === (int)$me['id']) {
                throw new RuntimeException('You cannot deactivate your own account.');
            }
            mcp_auth_update_user($userId, ['active' => !empty($_POST['active']) ? 1 : 0]);
            $notice = 'User updated.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$users = mcp_auth_users();
$pages = mcp_auth_pages();
$pageOptions = [];
foreach ($pages as $page) {
    $pageOptions[] = [
        'id' => (int)$page['id'],
        'label' => ($page['title'] ?: $page['path']) . ' (' . $page['path'] . ')',
        'active' => (int)$page['active'],
    ];
}

$accessByUser = [];
foreach ($users as $user) {
    $accessByUser[(int)$user['id']] = mcp_auth_user_page_ids((int)$user['id']);
}

$pageCheckboxes = function (array $checkedIds = []) use ($pageOptions): array {
    $checkedIds = array_map('intval', $checkedIds);
    $items = [];

    foreach ($pageOptions as $page) {
        $attrs = [
            ':type' => 'checkbox',
            ':value' => (string)$page['id'],
        ];
        if (in_array((int)$page['id'], $checkedIds, true)) {
            $attrs[':checked'] = 'checked';
        }

        $items[] = ['label.check' => [
            ['input:page_ids[]' => $attrs],
            ['span' => e($page['label']) . ((int)$page['active'] ? '' : ' [inactive]')],
        ]];
    }

    return $items ?: [['p.muted' => 'No pages created yet.']];
};

$userRows = [];
foreach ($users as $user) {
    $userId = (int)$user['id'];
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $isSelf = $userId === (int)$me['id'];

    $userRows[] = ['div.access-row' => [
        ['div.access-user' => [
            ['strong' => e($user['login'])],
            ['span.muted' => e($user['name'] ?: $user['login'])],
            ['span.badge' => e($user['role'])],
            (int)$user['active'] ? ['span.badge.ok' => 'active'] : ['span.badge.off' => 'disabled'],
        ]],
        ['form:/access' => [
            ':method' => 'post',
            ['hidden:action' => 'save_access'],
            ['hidden:user_id' => (string)$userId],
            ['div.check-grid' => $isAdmin ? [['p.muted' => 'Admin role has access to all pages.']] : $pageCheckboxes($accessByUser[$userId] ?? [])],
            $isAdmin ? [] : ['submit' => 'Save access'],
        ]],
        ['form:/access' => [
            ':method' => 'post',
            ['hidden:action' => 'toggle_user'],
            ['hidden:user_id' => (string)$userId],
            ['hidden:active' => [':value' => (int)$user['active'] ? '0' : '1']],
            $isSelf ? ['span.muted' => 'Current account'] : ['submit.secondary' => (int)$user['active'] ? 'Disable' : 'Enable'],
        ]],
    ]];
}

return [
    'section.page-head' => [
        ['h1' => 'Access'],
        ['p.muted' => 'Create users, register pages and give page-level access.'],
    ],
    $notice ? ['div.notice.ok' => e($notice)] : [],
    $error ? ['div.notice.error' => e($error)] : [],
    'section.grid' => [
        ['div.panel' => [
            ['h2' => 'New User'],
            ['form:/access' => [
                ':method' => 'post',
                ['hidden:action' => 'create_user'],
                ['label' => [['span' => 'Login'], ['input:login' => '']]],
                ['label' => [['span' => 'Name'], ['input:name' => '']]],
                ['label' => [['span' => 'Password'], ['input:password' => [':type' => 'password']]]],
                ['label' => [['span' => 'Role'], ['select:role:user' => ['user' => 'User', 'admin' => 'Admin']]]],
                ['div.check-grid' => $pageCheckboxes()],
                ['submit.primary' => 'Create user'],
            ]],
        ]],
        ['div.panel' => [
            ['h2' => 'New Page'],
            ['form:/access' => [
                ':method' => 'post',
                ['hidden:action' => 'create_page'],
                ['label' => [['span' => 'Path'], ['input:path:/reports' => '']]],
                ['label' => [['span' => 'Title'], ['input:title:Reports' => '']]],
                ['label.check' => [
                    ['input:active' => [':type' => 'checkbox', ':value' => '1', ':checked' => 'checked']],
                    ['span' => 'Active'],
                ]],
                ['submit.primary' => 'Save page'],
            ]],
        ]],
    ],
    'section.panel' => [
        ['h2' => 'Users'],
        ['div.access-list' => $userRows],
    ],
];
