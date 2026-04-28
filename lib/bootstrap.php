<?php

define('MCP_ROOT', dirname(__DIR__));

function mcp_env(string $name, $default = null)
{
    if (array_key_exists($name, $_ENV)) {
        return $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER)) {
        return $_SERVER[$name];
    }

    $value = getenv($name);
    return $value === false ? $default : $value;
}

function mcp_is_assoc(array $array): bool
{
    return array_keys($array) !== range(0, count($array) - 1);
}

function mcp_config_merge(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if (
            isset($base[$key]) &&
            is_array($base[$key]) &&
            is_array($value) &&
            mcp_is_assoc($base[$key]) &&
            mcp_is_assoc($value)
        ) {
            $base[$key] = mcp_config_merge($base[$key], $value);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

function mcp_config(?string $key = null, $default = null)
{
    static $config = null;

    if ($config === null) {
        $configFile = MCP_ROOT . '/config/app.php';
        $localFile = MCP_ROOT . '/config/local.php';

        $config = is_file($configFile) ? include $configFile : [];
        if (is_file($localFile)) {
            $local = include $localFile;
            $config = mcp_config_merge($config, is_array($local) ? $local : []);
        }
    }

    if ($key === null || $key === '') {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function mcp_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
    $value = trim((string)$value, '_');
    return $value !== '' ? $value : 'mcp_app';
}

function mcp_random_secret(int $bytes = 24): string
{
    return bin2hex(random_bytes($bytes));
}

function mcp_export_config(array $config): string
{
    return "<?php\n\nreturn " . var_export($config, true) . ";\n";
}
