<?php

require_once __DIR__ . '/lib/bootstrap.php';

date_default_timezone_set((string)mcp_config('project.timezone', 'UTC'));

require_once __DIR__ . '/lib/mysqly/mysqly.php';
require_once __DIR__ . '/lib/mysqly/manticory.php';
require_once __DIR__ . '/lib/phpy/phpy.php';
require_once __DIR__ . '/lib/clickhousy/clickhousy.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

mcp_configure_runtime();
