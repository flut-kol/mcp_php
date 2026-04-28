# MCP PHP Starter

Starter project for future PHP MCP servers. It includes local copies of:

- `mysqly`
- `phpy`
- `clickhousy`
- `manticory`

It also includes a central config, MySQL database/user setup, web login, user manager and page-level access control.

## Create A New Project

```bash
git clone <repo-url> my_project
cd my_project
php mcp.php init . my_project
php tasks/setup_db.php --admin-user=root --admin-password=ROOT_PASSWORD
```

The `init` command creates `config/local.php` with a generated DB password and generated admin password.

You can also create a project from a cloned starter, like `phpy`:

```bash
git clone <repo-url> mcp
php mcp/mcp.php init /var/apps/my_project my_project
```

## Web

Point Nginx to:

```text
/var/apps/my_project/web
```

Minimal Nginx location:

```nginx
server {
  root /var/apps/my_project/web;
  index index.php;
  server_name my-project.local;

  location / {
    try_files $uri /index.php?$args;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php-fpm.sock;
  }
}
```

Login at `/login`, then open `/access` to create users, register pages and assign page access.

## MCP Server

Run the stdio MCP server:

```bash
php mcp.php serve
```

Available tools:

- `app_config`
- `mysql_query`
- `clickhouse_query`
- `manticore_query`
- `access_list`

`mysql_query` allows read-only SQL by default. Set `MCP_ALLOW_UNSAFE_SQL=1` only when you intentionally want write queries through MCP.

## Config

Main defaults live in `config/app.php`. Project overrides live in `config/local.php`, which is ignored by git.

Useful environment overrides:

- `MCP_MYSQL_HOST`
- `MCP_MYSQL_PORT`
- `MCP_MYSQL_DATABASE`
- `MCP_MYSQL_USER`
- `MCP_MYSQL_PASSWORD`
- `MCP_CLICKHOUSE_URL`
- `MCP_CLICKHOUSE_DATABASE`
- `MCP_MANTICORE_HOST`
- `MCP_MANTICORE_PORT`
- `MCP_ADMIN_LOGIN`
- `MCP_ADMIN_PASSWORD`

## Database Setup

The setup script creates:

- MySQL database
- MySQL user
- `mcp_users`
- `mcp_pages`
- `mcp_user_page_access`
- bootstrap admin user
- default pages `/` and `/access`

```bash
php tasks/setup_db.php --admin-user=root --admin-password=ROOT_PASSWORD
```
