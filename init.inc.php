<?php

define('MINI_ENGINE_LIBRARY', true);
define('MINI_ENGINE_ROOT', __DIR__);
require_once(__DIR__ . '/mini-engine.php');
if (file_exists(__DIR__ . '/config.inc.php')) {
    include(__DIR__ . '/config.inc.php');
} elseif (file_exists('/srv/config/lymeet.openfun.app.inc.php')) {
    include('/srv/config/lymeet.openfun.app.inc.php');
}
set_include_path(
    __DIR__ . '/libraries'
    . PATH_SEPARATOR . __DIR__ . '/models'
);
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once(__DIR__ . '/vendor/autoload.php');
}
if (!getenv('APP_NAME') or getenv('APP_NAME') == 'Mini Engine sample application') {
    putenv('APP_NAME=LyMeet 立法院會議逐字稿');
}
if (!getenv('LYAPI_HOST')) {
    putenv('LYAPI_HOST=v2.ly.govapi.tw');
}
if (!getenv('LYAPI_SCHEME')) {
    putenv('LYAPI_SCHEME=https');
}
if (!getenv('DEFAULT_TERM')) {
    putenv('DEFAULT_TERM=11');
}
date_default_timezone_set('Asia/Taipei');
MiniEngine::initEnv();
