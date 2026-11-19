<?php
/**
 * 發言搜尋（/speeches?q=）。關鍵字一律當片語搜（加引號），否則 ES 會拆成單字。
 */
class SearchHelper
{
    const PAGE_SIZE = 50;

    /**
     * @return object { total, page, total_page, groups: [ [meet => object|null, meet_id, hits => [...] ], ... ] }
     */
    public static function speeches($q, array $opts = [])
    {
        $q = trim($q);
        $page = max(1, intval($opts['page'] ?? 1));
        $qs = LYAPI::qs([
            'q' => '"' . trim($q, '"') . '"',
            '屆' => $opts['term'] ?? MeetHelper::defaultTerm(),
            '區塊種類' => '發言',
            '發言者' => $opts['speaker'] ?? null,
            '會議代碼' => $opts['meet'] ?? null,
            '會議種類' => $opts['type'] ?? null,
            'limit' => self::PAGE_SIZE,
            'page' => $page,
            'sort' => '日期>',
            'output_fields' => ['發言編號', '文件編號', '會議代碼', '會議種類', '日期', '發言者', '發言者身分', '發言者職稱', '內文', '發言時間', '影片', '質詢輪次委員'],
        ]);
        $res = LYAPI::apiQuery("/speeches?{$qs}", "搜尋發言「{$q}」第 {$page} 頁", 600);
        $out = new StdClass;
        $out->total = intval($res->total ?? 0);
        $out->page = $page;
        $out->total_page = intval($res->total_page ?? 1);
        $out->groups = self::groupByMeet((array)($res->speeches ?? []));
        return $out;
    }

    /** 依會議分組（保持原本順序），並補上會議標題 */
    public static function groupByMeet(array $speeches)
    {
        $groups = [];
        foreach ($speeches as $s) {
            // 聯席會議的發言 會議代碼 是陣列
            $mid = is_array($s->會議代碼) ? ($s->會議代碼[0] ?? '') : $s->會議代碼;
            if (!isset($groups[$mid])) {
                $groups[$mid] = ['meet_id' => $mid, 'meet' => null, 'hits' => []];
            }
            $groups[$mid]['hits'][] = $s;
        }
        $meets = self::meetsByIds(array_keys($groups));
        foreach ($groups as $mid => &$g) {
            $g['meet'] = $meets[$mid] ?? null;
        }
        return array_values($groups);
    }

    /** 一次把多場會議的標題抓回來 */
    public static function meetsByIds(array $ids)
    {
        $ids = array_values(array_unique(array_filter($ids)));
        $meets = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $qs = LYAPI::qs(['會議代碼' => $chunk, 'limit' => count($chunk), 'output_fields' => ['會議代碼', '會議種類', '日期', '會議標題', '會議資料', '委員會代號']]);
            $res = LYAPI::apiQuery("/meets?{$qs}", '搜尋結果的會議標題', 3600);
            foreach ($res->meets ?? [] as $m) {
                $meets[$m->會議代碼] = MeetHelper::decorate($m);
            }
        }
        return $meets;
    }

    /** highlight 片段：先 escape 再把 <em> 還原 */
    public static function highlightHtml($speech, $max = 3)
    {
        $frags = (array)($speech->{'內文:highlight'} ?? []);
        if (!$frags) {
            $text = (string)($speech->內文 ?? '');
            return htmlspecialchars(mb_strlen($text) > 160 ? mb_substr($text, 0, 158) . '…' : $text, ENT_QUOTES, 'UTF-8');
        }
        $out = [];
        foreach (array_slice($frags, 0, $max) as $f) {
            $f = htmlspecialchars($f, ENT_QUOTES, 'UTF-8');
            $out[] = '…' . str_replace(['&lt;em&gt;', '&lt;/em&gt;'], ['<em>', '</em>'], $f) . '…';
        }
        return implode('<br>', $out);
    }
}
