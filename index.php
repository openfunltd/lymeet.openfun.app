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
    if ($path == '/laws') {
        return ['law', 'index'];
    }
    if (preg_match('#^/law/([^/]+)$#u', $path, $m)) {
        return ['law', 'show', [$m[1]]];
    }
    if ($path == '/depts') {
        return ['dept', 'index'];
    }
    if (preg_match('#^/dept/([^/]+)$#u', $path, $m)) {
        return ['dept', 'show', [$m[1]]];
    }
    if ($path == '/search') {
        return ['search', 'index'];
    }
    if ($path == '/learn') {
        return ['learn', 'index'];
    }
    if ($path == '/about') {
        return ['learn', 'about'];
    }
    if ($path == '/about-us') {
        return ['learn', 'team'];
    }
    return null;
});
