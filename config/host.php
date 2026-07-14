<?php
/**
 * INST_PATH will define the absoulte path to use in the whole app
 */
defined('INST_PATH') or define(constant_name: 'INST_PATH', value: dirname(__FILE__, 2).'/');
$env_vars = parse_ini_file(INST_PATH.'.env');

ini_set('error_log', INST_PATH.'tmp/php-error.log');
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('max_execution_time',0);

defined('APP_ENV') or define('APP_ENV', $env_vars['APP_ENV']);
define('INST_URI', $env_vars['INST_URI']);
define('DOMAIN_NAME', parse_url(INST_URI)['host']);
define('SITE_NAME', $env_vars['SITE_NAME']);
define('SITE_STATUS', $env_vars['SITE_STATUS']);
define('LANDING_PAGE', $env_vars['LANDING_PAGE']);
define('LANDING_REPLACE', $env_vars['LANDING_REPLACE']);
define('COOKIE_ID', bin2hex(random_bytes(16)));

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'strict');
ini_set('session.cookie_path', '/');
ini_set('session.name', '__Host-sessid');

const DEF_CONTROLLER = 'index';
const DEF_ACTION = 'index';
define('USE_ALTER_URL', $env_vars['USE_ALTER_URL']);
define('ALTER_URL_CONTROLLER_ACTION', $env_vars['ALTER_URL_CONTROLLER_ACTION']);

define('SALT', $env_vars['SALT']);
/**
 * locale section
 */
const LOCALE = 'es_CO';
const TIMEZONE = 'America/Bogota';
date_default_timezone_set(TIMEZONE);
ini_set('date.timezone',TIMEZONE);
// putenv('LC_ALL='.LOCALE.'.utf8');
// putenv('LANGUAGE=');
// putenv('LANG='.LOCALE.'.utf8');
$res = setlocale(LC_ALL, 'es_CO.utf8');
// $res = bindtextdomain('translations', INST_PATH.'locale');
// $res = textdomain('translations');

define('GANALYTICS', $env_vars['GANALYTICS']);
$GLOBALS['env'] = APP_ENV;
