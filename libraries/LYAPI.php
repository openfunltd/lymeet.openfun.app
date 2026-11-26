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
     * @param int    $cache  快取秒數；null（預設）不寫檔。只有全站共用、數量有限的查詢（委員會清單、各屆立委名單、
     *                       法律列表、部會場數、跑馬燈、首頁與會議列表的日期區間）才給秒數；
     *                       每場會議、每份公報文件、每個搜尋詞的查詢都不快取，否則 /tmp 會堆到幾萬個檔
     */
    public static function apiQuery($url, $reason, $cache = null)
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
        $curl_error = curl_error($curl);
        curl_close($curl);
        self::$log[] = [$full_url, $reason, sprintf('%.2fs', $delta)];
        if ($delta > 2) {
            @file_put_contents('/tmp/lymeet-slow-' . date('Ymd'), json_encode([
                'time' => date('c'), 'page' => $_SERVER['REQUEST_URI'] ?? '', 'url' => $full_url, 'delta' => $delta,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
        }
        if ($http_code == 0) {
            throw new Exception("無法連線到 LYAPI " . getenv('LYAPI_SCHEME') . '://' . getenv('LYAPI_HOST') . "（{$curl_error}）。請檢查 config.inc.php 的 LYAPI_HOST；若指到本機的 v2，要先把 v2 跑起來。");
        }
        if ($http_code == 404) {
            if (strpos($url, '/speeches?') === 0) {
                throw new LYAPI_Unavailable("LYAPI /speeches 尚未部署: {$url}");
            }
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
            self::pruneCache();
        }
        return $json;
    }

    /** 每寫 100 次快取清一次超過兩天沒更新的舊檔，避免越堆越多 */
    protected static function pruneCache()
    {
        if (mt_rand(1, 100) !== 1) {
            return;
        }
        foreach (glob('/tmp/lymeet-cache-*.json') ?: [] as $f) {
            if (time() - @filemtime($f) > 2 * 86400) {
                @unlink($f);
            }
        }
    }

    /**
     * /speeches 的查詢；端點還沒部署（404）時回 null，讓頁面降級顯示而不是整頁出錯。
     */
    public static function speeches($qs, $reason, $cache = null)
    {
        try {
            return self::apiQuery("/speeches?{$qs}", $reason, $cache);
        } catch (LYAPI_Unavailable $e) {
            self::$log[] = [getenv('LYAPI_SCHEME') . '://' . getenv('LYAPI_HOST') . "/speeches?{$qs}", $reason, '尚未部署'];
            return null;
        }
    }
}

class LYAPI_NotFound extends Exception
{
}

/** 整個端點不存在（例如 /speeches 還沒部署到正式 v2），不是單筆找不到 */
class LYAPI_Unavailable extends LYAPI_NotFound
{
}
