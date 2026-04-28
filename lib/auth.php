<?php

function mcp_auth_tables(): array
{
    return mcp_config('auth.tables', [
        'users' => 'mcp_users',
        'pages' => 'mcp_pages',
        'access' => 'mcp_user_page_access',
    ]);
}

function mcp_auth_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (!headers_sent()) {
        session_name((string)mcp_config('auth.session_name', 'mcp_session'));
        session_set_cookie_params([
            'lifetime' => (int)mcp_config('auth.session_lifetime', 86400),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

function mcp_auth_bootstrap_schema(): void
{
    $tables = mcp_auth_tables();
    $users = $tables['users'];
    $pages = $tables['pages'];
    $access = $tables['access'];

    mysqly::exec("
        CREATE TABLE IF NOT EXISTS `{$users}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(190) NOT NULL DEFAULT '',
            role VARCHAR(32) NOT NULL DEFAULT 'user',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_login_at DATETIME NULL,
            UNIQUE KEY uniq_login (login),
            KEY idx_role_active (role, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mysqly::exec("
        CREATE TABLE IF NOT EXISTS `{$pages}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            path VARCHAR(255) NOT NULL,
            title VARCHAR(190) NOT NULL DEFAULT '',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_path (path),
            KEY idx_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mysqly::exec("
        CREATE TABLE IF NOT EXISTS `{$access}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            page_id INT UNSIGNED NOT NULL,
            can_view TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_user_page (user_id, page_id),
            KEY idx_page_id (page_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mcp_auth_upsert_page('/', 'Dashboard');
    mcp_auth_upsert_page('/access', 'Access manager');

    $admin = mcp_config('auth.bootstrap_admin', []);
    $login = trim((string)($admin['login'] ?? 'admin'));
    $password = (string)($admin['password'] ?? 'admin');
    $name = trim((string)($admin['name'] ?? 'Administrator'));

    if ($login !== '' && !mcp_auth_find_user_by_login($login)) {
        mcp_auth_create_user($login, $password, $name ?: $login, 'admin');
    }
}

function mcp_auth_find_user_by_login(string $login): array
{
    $users = mcp_auth_tables()['users'];
    return mysqly::row("SELECT * FROM `{$users}` WHERE login = :login LIMIT 1", [':login' => $login]);
}

function mcp_auth_find_user(int $id): array
{
    $users = mcp_auth_tables()['users'];
    return mysqly::row("SELECT * FROM `{$users}` WHERE id = :id LIMIT 1", [':id' => $id]);
}

function mcp_auth_create_user(string $login, string $password, string $name = '', string $role = 'user'): int
{
    $login = trim($login);
    $name = trim($name) ?: $login;
    $role = $role === 'admin' ? 'admin' : 'user';

    if ($login === '') {
        throw new InvalidArgumentException('Login is required.');
    }

    if ($password === '') {
        throw new InvalidArgumentException('Password is required.');
    }

    $users = mcp_auth_tables()['users'];
    return (int)mysqly::insert($users, [
        'login' => $login,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name,
        'role' => $role,
        'active' => 1,
        'created_at' => mcp_now(),
        'updated_at' => mcp_now(),
    ]);
}

function mcp_auth_update_user(int $userId, array $data): void
{
    $users = mcp_auth_tables()['users'];
    $data['updated_at'] = mcp_now();
    mysqly::update($users, ['id' => $userId], $data);
}

function mcp_auth_upsert_page(string $path, string $title, int $active = 1): int
{
    $path = mcp_normalize_path($path);
    $title = trim($title) ?: $path;
    $pages = mcp_auth_tables()['pages'];
    $existing = mysqly::row("SELECT id FROM `{$pages}` WHERE path = :path LIMIT 1", [':path' => $path]);

    if ($existing) {
        mysqly::update($pages, ['id' => (int)$existing['id']], [
            'title' => $title,
            'active' => $active ? 1 : 0,
            'updated_at' => mcp_now(),
        ]);
        return (int)$existing['id'];
    }

    return (int)mysqly::insert($pages, [
        'path' => $path,
        'title' => $title,
        'active' => $active ? 1 : 0,
        'created_at' => mcp_now(),
        'updated_at' => mcp_now(),
    ]);
}

function mcp_auth_users(): array
{
    $users = mcp_auth_tables()['users'];
    return mysqly::fetch("SELECT id, login, name, role, active, created_at, last_login_at FROM `{$users}` ORDER BY role = 'admin' DESC, login ASC");
}

function mcp_auth_pages(): array
{
    $pages = mcp_auth_tables()['pages'];
    return mysqly::fetch("SELECT * FROM `{$pages}` ORDER BY path ASC");
}

function mcp_auth_user_page_ids(int $userId): array
{
    $access = mcp_auth_tables()['access'];
    $ids = mysqly::array("SELECT page_id FROM `{$access}` WHERE user_id = :user_id AND can_view = 1", [':user_id' => $userId]);
    return array_map('intval', $ids ?: []);
}

function mcp_auth_save_user_access(int $userId, array $pageIds): void
{
    $access = mcp_auth_tables()['access'];
    mysqly::remove($access, ['user_id' => $userId]);

    $pageIds = array_values(array_unique(array_map('intval', $pageIds)));
    foreach ($pageIds as $pageId) {
        if ($pageId <= 0) {
            continue;
        }

        mysqly::insert($access, [
            'user_id' => $userId,
            'page_id' => $pageId,
            'can_view' => 1,
            'created_at' => mcp_now(),
            'updated_at' => mcp_now(),
        ], true);
    }
}

function mcp_auth_login(string $login, string $password): bool
{
    mcp_auth_session_start();
    $user = mcp_auth_find_user_by_login(trim($login));

    if (!$user || !(int)$user['active'] || !password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['mcp_user_id'] = (int)$user['id'];

    $users = mcp_auth_tables()['users'];
    mysqly::update($users, ['id' => (int)$user['id']], ['last_login_at' => mcp_now(), 'updated_at' => mcp_now()]);
    return true;
}

function mcp_auth_logout(): void
{
    mcp_auth_session_start();
    $_SESSION = [];
    session_destroy();
}

function mcp_auth_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user ?: null;
    }

    mcp_auth_session_start();
    $id = (int)($_SESSION['mcp_user_id'] ?? 0);
    if ($id <= 0) {
        $user = null;
        return null;
    }

    $row = mcp_auth_find_user($id);
    $user = ($row && (int)$row['active']) ? $row : null;
    return $user ?: null;
}

function mcp_auth_can_access(string $path, ?array $user = null): bool
{
    $user = $user ?: mcp_auth_user();
    if (!$user) {
        return false;
    }

    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    $path = mcp_normalize_path($path);
    $tables = mcp_auth_tables();

    $row = mysqly::row("
        SELECT p.id
        FROM `{$tables['pages']}` p
        JOIN `{$tables['access']}` a ON a.page_id = p.id AND a.can_view = 1
        WHERE p.path = :path AND p.active = 1 AND a.user_id = :user_id
        LIMIT 1
    ", [
        ':path' => $path,
        ':user_id' => (int)$user['id'],
    ]);

    return (bool)$row;
}

function mcp_auth_login_required_path(string $path): bool
{
    return !in_array(mcp_normalize_path($path), ['/login', '/css.css', '/js.js'], true);
}
