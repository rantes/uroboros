<?php
$dir = realpath('./../');
defined('INST_PATH') || define('INST_PATH', dirname($dir).'/');
set_include_path(
    '/etc/dumbophp'.PATH_SEPARATOR.
    '/etc/dumbophp/bin'.PATH_SEPARATOR.
    '/etc/dumbophp/lib'.PATH_SEPARATOR.
    INST_PATH.'vendor'.PATH_SEPARATOR.
    INST_PATH.'vendor/rantes/dumbophp'.PATH_SEPARATOR.
    INST_PATH.'vendor/rantes/dumbophp/bin'.PATH_SEPARATOR.
    INST_PATH.'vendor/rantes/dumbophp/lib'.PATH_SEPARATOR.
    INST_PATH.PATH_SEPARATOR.
    get_include_path().PATH_SEPARATOR.
    PEAR_EXTENSION_DIR.PATH_SEPARATOR.
    '/windows/dumbophp'.PATH_SEPARATOR.
    '/windows/dumbophp/bin'.PATH_SEPARATOR.
    '/windows/dumbophp/lib'.PATH_SEPARATOR.
    '/windows/system32/dumbophp'.PATH_SEPARATOR.
    '/windows/system32/dumbophp/bin'.PATH_SEPARATOR.
    '/windows/system32/dumbophp/lib'.PATH_SEPARATOR.
    INST_PATH.'DumboPHP'
);

require_once 'dumbophp.php';
require_once INST_PATH.'config/host.php';
if (file_exists(INST_PATH.'vendor/autoload.php')) require_once INST_PATH.'vendor/autoload.php';

if (!isset($_SESSION) or (session_status() === PHP_SESSION_NONE)):
    // session_set_save_handler($session);
    // empty($_COOKIE[COOKIE_ID]) or session_id($_COOKIE[COOKIE_ID]);
    // register_shutdown_function('session_write_close');
    session_start();
endif;

$index = new index();
$index->page->display();
