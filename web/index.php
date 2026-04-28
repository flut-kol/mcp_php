<?php

require_once __DIR__ . '/../boot.php';

phpy::on('/css.css', fn() => phpy::css());
phpy::on('/js.js', fn() => phpy::js());

phpy::on('/logout', function () {
    mcp_auth_logout();
    mcp_redirect('/login');
});

phpy::on('/.*', function () {
    $path = endpoint();

    if (!mcp_auth_login_required_path($path)) {
        return true;
    }

    try {
        $user = mcp_auth_user();
    } catch (Throwable $e) {
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><title>MCP setup required</title>';
        echo '<body style="font-family:system-ui;margin:40px;max-width:760px">';
        echo '<h1>MCP database is not ready</h1>';
        echo '<p>Run <code>php tasks/setup_db.php</code> and reload this page.</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</pre>';
        echo '</body>';
        return false;
    }

    if (!$user) {
        mcp_redirect('/login');
    }

    if (!mcp_auth_can_access($path, $user)) {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Access denied</title>';
        echo '<body style="font-family:system-ui;margin:40px;max-width:760px">';
        echo '<h1>Access denied</h1>';
        echo '<p>Your account does not have access to <code>' . htmlspecialchars($path, ENT_QUOTES) . '</code>.</p>';
        echo '<p><a href="/">Dashboard</a> <a href="/logout" style="margin-left:12px">Logout</a></p>';
        echo '</body>';
        return false;
    }

    return true;
});

echo phpy([
    '/' => __DIR__,
]);
