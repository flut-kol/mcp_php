<?php

function mcp_server_stdio(): void
{
    while (($line = fgets(STDIN)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $request = json_decode($line, true);
        if (!is_array($request)) {
            mcp_server_write(mcp_server_error(null, -32700, 'Parse error'));
            continue;
        }

        $response = mcp_server_handle($request);
        if ($response !== null) {
            mcp_server_write($response);
        }
    }
}

function mcp_server_write(array $message): void
{
    echo json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    flush();
}

function mcp_server_handle(array $request): ?array
{
    $id = $request['id'] ?? null;
    $method = $request['method'] ?? '';
    $params = $request['params'] ?? [];

    if ($method === '' || !is_string($method)) {
        return mcp_server_error($id, -32600, 'Invalid request');
    }

    if (str_starts_with($method, 'notifications/')) {
        return null;
    }

    try {
        if ($method === 'initialize') {
            return mcp_server_result($id, mcp_server_initialize());
        }

        if ($method === 'tools/list') {
            return mcp_server_result($id, ['tools' => mcp_server_tools()]);
        }

        if ($method === 'tools/call') {
            return mcp_server_result($id, mcp_server_call_tool($params));
        }

        return mcp_server_error($id, -32601, 'Method not found');
    } catch (Throwable $e) {
        return mcp_server_error($id, -32603, $e->getMessage());
    }
}

function mcp_server_result($id, array $result): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => $result,
    ];
}

function mcp_server_error($id, int $code, string $message): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ];
}

function mcp_server_initialize(): array
{
    return [
        'protocolVersion' => (string)mcp_config('mcp.protocol_version', '2025-11-25'),
        'capabilities' => [
            'tools' => [
                'listChanged' => false,
            ],
        ],
        'serverInfo' => [
            'name' => (string)mcp_config('mcp.server_name', 'mcp'),
            'title' => (string)mcp_config('mcp.server_title', 'MCP'),
            'version' => (string)mcp_config('mcp.version', '0.1.0'),
        ],
    ];
}

function mcp_server_tools(): array
{
    return [
        [
            'name' => 'app_config',
            'title' => 'Application Config',
            'description' => 'Return non-secret project configuration.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => new stdClass(),
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'mysql_query',
            'title' => 'MySQL Query',
            'description' => 'Run a MySQL query through mysqly. Read-only SQL is allowed by default.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'sql' => ['type' => 'string'],
                    'params' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['sql'],
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'clickhouse_query',
            'title' => 'ClickHouse Query',
            'description' => 'Run a ClickHouse query through clickhousy.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'sql' => ['type' => 'string'],
                    'params' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['sql'],
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'manticore_query',
            'title' => 'Manticore Query',
            'description' => 'Run a Manticore SQL query through manticory.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'sql' => ['type' => 'string'],
                    'params' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['sql'],
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'access_list',
            'title' => 'Access List',
            'description' => 'Return users, pages and user-page access configured in the web manager.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => new stdClass(),
                'additionalProperties' => false,
            ],
        ],
    ];
}

function mcp_server_call_tool(array $params): array
{
    $name = (string)($params['name'] ?? '');
    $arguments = $params['arguments'] ?? [];
    $arguments = is_array($arguments) ? $arguments : [];

    try {
        if ($name === 'app_config') {
            $config = mcp_public_config();
            return mcp_tool_text($config, ['config' => $config]);
        }

        if ($name === 'mysql_query') {
            return mcp_server_mysql_query($arguments);
        }

        if ($name === 'clickhouse_query') {
            return mcp_server_clickhouse_query($arguments);
        }

        if ($name === 'manticore_query') {
            return mcp_server_manticore_query($arguments);
        }

        if ($name === 'access_list') {
            return mcp_server_access_list();
        }

        throw new InvalidArgumentException('Unknown tool: ' . $name);
    } catch (Throwable $e) {
        return mcp_tool_text($e->getMessage(), [], true);
    }
}

function mcp_server_sql_args(array $arguments): array
{
    $sql = trim((string)($arguments['sql'] ?? ''));
    if ($sql === '') {
        throw new InvalidArgumentException('sql is required.');
    }

    $params = $arguments['params'] ?? [];
    if (!is_array($params)) {
        throw new InvalidArgumentException('params must be an object.');
    }

    $bind = [];
    foreach ($params as $key => $value) {
        $key = ltrim((string)$key, ':');
        $bind[is_array($value) ? $key : ':' . $key] = $value;
    }

    return [$sql, $bind];
}

function mcp_server_mysql_query(array $arguments): array
{
    [$sql, $bind] = mcp_server_sql_args($arguments);

    if (!mcp_config('mcp.allow_unsafe_sql', false) && !mcp_is_readonly_sql($sql)) {
        throw new RuntimeException('Only read-only SQL is allowed. Set MCP_ALLOW_UNSAFE_SQL=1 to allow writes.');
    }

    if (mcp_is_readonly_sql($sql)) {
        $rows = mysqly::fetch($sql, $bind);
        return mcp_tool_text($rows, ['rows' => $rows, 'count' => count($rows)]);
    }

    $statement = mysqly::exec($sql, $bind);
    return mcp_tool_text('Query executed.', ['affected_rows' => $statement->rowCount()]);
}

function mcp_server_clickhouse_query(array $arguments): array
{
    [$sql, $bind] = mcp_server_sql_args($arguments);
    $rows = clickhousy::rows($sql, $bind);
    return mcp_tool_text($rows, ['rows' => $rows, 'count' => count($rows)]);
}

function mcp_server_manticore_query(array $arguments): array
{
    [$sql, $bind] = mcp_server_sql_args($arguments);
    $rows = manticory::fetch($sql, $bind);
    return mcp_tool_text($rows, ['rows' => $rows, 'count' => count($rows)]);
}

function mcp_server_access_list(): array
{
    $users = mcp_auth_users();
    $pages = mcp_auth_pages();
    $access = [];

    foreach ($users as $user) {
        $access[(string)$user['id']] = mcp_auth_user_page_ids((int)$user['id']);
    }

    return mcp_tool_text(
        ['users' => $users, 'pages' => $pages, 'access' => $access],
        ['users' => $users, 'pages' => $pages, 'access' => $access]
    );
}
