<?php
include(__DIR__ . '/init.inc.php');

MiniEngine::dispatch(function($uri) {
    $path = urldecode(parse_url($uri, PHP_URL_PATH));
    if ($path == '/robots.txt') {
        return ['index', 'robots'];
    }
    if ($path == '/meets') {
        return ['meet', 'index'];
    }
    if (preg_match('#^/meet/([^/]+)$#u', $path, $m)) {
        return ['meet', 'show', [$m[1]]];
    }
    if ($path == '/legislators') {
        return ['legislator', 'index'];
    }
    if (preg_match('#^/legislator/([^/]+)$#u', $path, $m)) {
        return ['legislator', 'show', [$m[1]]];
    }
    if ($path == '/search') {
        return ['search', 'index'];
    }
    if ($path == '/about') {
        return ['about', 'index'];
    }
    return null;
});
