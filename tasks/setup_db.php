<?php

require_once __DIR__ . '/../boot.php';

function mcp_setup_option(array $options, string $name, $default)
{
    return array_key_exists($name, $options) && $options[$name] !== false ? $options[$name] : $default;
}

function mcp_setup_identifier(string $value, string $label): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
        throw new InvalidArgumentException("{$label} may contain only letters, numbers and underscore.");
    }

    return '`' . str_replace('`', '``', $value) . '`';
}

function mcp_setup_admin_pdo(array $admin): PDO
{
    $dsn = 'mysql:host=' . ($admin['host'] ?? '127.0.0.1') .
        ';port=' . ($admin['port'] ?? 3306) .
        ';charset=utf8mb4';

    $pdo = new PDO(
        $dsn,
        (string)($admin['user'] ?? 'root'),
        (string)($admin['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ]
    );

    return $pdo;
}

$options = getopt('', [
    'admin-host::',
    'admin-port::',
    'admin-user::',
    'admin-password::',
    'db::',
    'user::',
    'password::',
    'grant-host::',
]);

$mysql = mcp_config('mysql', []);
$admin = mcp_config('mysql_admin', []);

$admin['host'] = mcp_setup_option($options, 'admin-host', $admin['host'] ?? '127.0.0.1');
$admin['port'] = (int)mcp_setup_option($options, 'admin-port', $admin['port'] ?? 3306);
$admin['user'] = mcp_setup_option($options, 'admin-user', $admin['user'] ?? 'root');
$admin['password'] = mcp_setup_option($options, 'admin-password', $admin['password'] ?? '');

$db = (string)mcp_setup_option($options, 'db', $mysql['database'] ?? '');
$user = (string)mcp_setup_option($options, 'user', $mysql['user'] ?? '');
$password = (string)mcp_setup_option($options, 'password', $mysql['password'] ?? '');
$grantHost = (string)mcp_setup_option($options, 'grant-host', $mysql['grant_host'] ?? '%');
$charset = (string)($mysql['charset'] ?? 'utf8mb4');
$collation = (string)($mysql['collation'] ?? 'utf8mb4_unicode_ci');

if ($db === '' || $user === '') {
    throw new RuntimeException('MySQL database and user must be configured.');
}

$pdo = mcp_setup_admin_pdo($admin);
$dbIdent = mcp_setup_identifier($db, 'Database name');
$charsetIdent = preg_replace('/[^a-zA-Z0-9_]/', '', $charset) ?: 'utf8mb4';
$collationIdent = preg_replace('/[^a-zA-Z0-9_]/', '', $collation) ?: 'utf8mb4_unicode_ci';

$pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbIdent} CHARACTER SET {$charsetIdent} COLLATE {$collationIdent}");
$pdo->exec(
    'CREATE USER IF NOT EXISTS ' . $pdo->quote($user) . '@' . $pdo->quote($grantHost) .
    ' IDENTIFIED BY ' . $pdo->quote($password)
);
$pdo->exec(
    'ALTER USER ' . $pdo->quote($user) . '@' . $pdo->quote($grantHost) .
    ' IDENTIFIED BY ' . $pdo->quote($password)
);
$pdo->exec('GRANT ALL PRIVILEGES ON ' . $dbIdent . '.* TO ' . $pdo->quote($user) . '@' . $pdo->quote($grantHost));
$pdo->exec('FLUSH PRIVILEGES');

mcp_configure_runtime();
mcp_auth_bootstrap_schema();

echo "Database ready: {$db}\n";
echo "User ready: {$user}@{$grantHost}\n";
echo "Auth tables ready.\n";
echo "Bootstrap admin login: " . mcp_config('auth.bootstrap_admin.login', 'admin') . "\n";
