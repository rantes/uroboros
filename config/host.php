<?php
define('INST_PATH', dirname(dirname(__FILE__)).'/');
$env_vars = parse_ini_file(INST_PATH.'.env');

define('APP_ENV', $env_vars['APP_ENV']);
define('INST_URI', $env_vars['INST_URI']);
define('SALT', $env_vars['SALT']);
define('APP_HASH', $env_vars['APP_HASH']);
define('APP_ID', $env_vars['APP_ID']);
define('REX_URL', $env_vars['REX_URL']);
define('RAPTOR_URL', $env_vars['RAPTOR_URL']);

//dbconstants
define('DB_DRIVER', $env_vars['DB_DRIVER']);
define('DB_HOST', $env_vars['DB_HOST']);
define('DB_CHARSET', $env_vars['DB_CHARSET']);
define('DB_DIALECT', $env_vars['DB_DIALECT']);
define('DB_PORT', $env_vars['DB_PORT']);
define('DB_SCHEMA', $env_vars['DB_SCHEMA']);
define('DB_USERNAME', $env_vars['DB_USERNAME']);
define('DB_PASSWORD', $env_vars['DB_PASSWORD']);
define('DB_UNIX_SOCKET', $env_vars['DB_UNIX_SOCKET']);

define('SITE_STATUS','LIVE');
define('LANDING_PAGE','index/landing');
define('LANDING_REPLACE','ALL');

define('DEF_CONTROLLER', 'index');
define('DEF_ACTION', 'index');
define('USE_ALTER_URL', false);
define('ALTER_URL_CONTROLLER_ACTION','index/router');

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('max_execution_time',0);
ini_set('upload_tmp_dir', INST_PATH.'uploaded');

define('APP_NAME', 'uroboros');
define('SITE_NAME', 'CI/CD');

define('CAN_USE_MEMCACHED', false);
$GLOBALS['env'] = APP_ENV;

?>