<?php
include(__DIR__ . '/init.inc.php');

/**
 * 整頁快取：首頁、各列表、說明頁對所有人都一樣，整頁存 60 秒（/tmp/lymeet-page-*.html，只有幾個檔），
 * 爬蟲整站掃一遍時不會每次都重打 LYAPI。會議頁、搜尋、立委頁這些依參數變的頁面不快取。
 */
$page_cache = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' and empty($_GET['nocache'])) {
    $path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    $query = $_GET;
    unset($query['nocache']);
    if (in_array($path, ['/', '/meets', '/legislators', '/laws', '/depts', '/learn', '/about', '/about-us']) and count($query) <= 1) {
        ksort($query);
        $page_cache = sprintf('/tmp/lymeet-page-%s.html', md5($path . '?' . http_build_query($query)));
        if (file_exists($page_cache) and time() - filemtime($page_cache) < 60) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Page-Cache: hit');
            readfile($page_cache);
            exit;
        }
        // 用輸出回呼存檔：controller 可能自己 exit，放在 dispatch 之後的程式不一定會跑到
        ob_start(function($html) use ($page_cache) {
            if (http_response_code() === 200 and $html !== '' and strpos($html, '資料暫時讀不到') === false) {
                @file_put_contents($page_cache, $html);
            }
            return $html;
        });
    }
}

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
