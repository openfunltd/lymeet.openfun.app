<?php
/**
 * v2 LYAPI（https://v2.ly.govapi.tw）的查詢包裝。本站沒有資料庫，所有資料都即時向 LYAPI 查，
 * 本機只做檔案快取（/tmp/lymeet-cache-*.json）。
 *
 * 環境變數：LYAPI_HOST（預設 v2.ly.govapi.tw）、LYAPI_SCHEME（預設 https）、LYAPI_TOKEN（選用）
 */
class LYAPI
{
    protected static $log = [];

    public static function getLogs()
    {
        return self::$log;
    }

    /**
     * 組 query string。v2 的篩選是 key=value（可重複）、區間是 key:from,to、排序是 sort=欄位< 或 欄位>。
     * 區間用 key 結尾加冒號表示：['日期:' => '2026-11-01,2026-11-07']
     */
    public static function qs(array $params)
    {
        $parts = [];
        foreach ($params as $k => $v) {
            if (is_null($v) or $v === '' or $v === []) {
                continue;
            }
            if (substr($k, -1) === ':') {
                $parts[] = rawurlencode(substr($k, 0, -1)) . ':' . $v;
                continue;
            }
            foreach ((array)$v as $vv) {
                $parts[] = rawurlencode($k) . '=' . rawurlencode($vv);
            }
        }
        return implode('&', $parts);
    }

    public static function path($segments)
    {
        return '/' . implode('/', array_map('rawurlencode', (array)$segments));
    }

    /**
     * @param string $url    路徑含 query string，例：/meets?limit=10
     * @param string $reason 顯示在頁尾「本頁使用的 API」
     * @param int    $cache  快取秒數；null 不快取
     */
    public static function apiQuery($url, $reason, $cache = 3600)
    {
        $full_url = getenv('LYAPI_SCHEME') . '://' . getenv('LYAPI_HOST') . $url;
        $cache_file = null;
        if (!is_null($cache)) {
            $cache_file = sprintf('/tmp/lymeet-cache-%s.json', md5($full_url));
            if (file_exists($cache_file) and (time() - filemtime($cache_file)) < $cache and !($_GET['nocache'] ?? false)) {
                $json = json_decode(file_get_contents($cache_file));
                if ($json) {
                    self::$log[] = [$full_url, $reason, 'cached'];
                    return $json;
                }
            }
        }

        $curl = curl_init();
        $headers = ['Accept: application/json'];
        if (getenv('LYAPI_TOKEN')) {
            $headers[] = 'Authorization: Bearer ' . getenv('LYAPI_TOKEN');
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $full_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_USERAGENT, 'lymeet.openfun.app (' . ($_SERVER['HTTP_HOST'] ?? 'cli') . ')');
        $start = microtime(true);
        $res = curl_exec($curl);
        $delta = microtime(true) - $start;
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        self::$log[] = [$full_url, $reason, sprintf('%.2fs', $delta)];
        if ($delta > 2) {
            @file_put_contents('/tmp/lymeet-slow-' . date('Ymd'), json_encode([
                'time' => date('c'), 'page' => $_SERVER['REQUEST_URI'] ?? '', 'url' => $full_url, 'delta' => $delta,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
        }
        if ($http_code == 404) {
            throw new LYAPI_NotFound("LYAPI 404: {$url}");
        }
        if ($http_code !== 200) {
            throw new Exception("LYAPI request failed ({$http_code}): {$url}");
        }
        $json = json_decode($res);
        if (is_null($json)) {
            throw new Exception("LYAPI invalid JSON: {$url}");
        }
        if ($cache_file) {
            file_put_contents($cache_file, $res);
        }
        return $json;
    }
}

class LYAPI_NotFound extends Exception
{
}
