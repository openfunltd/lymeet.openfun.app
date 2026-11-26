<?php
/**
 * 立委：第 8–11 屆每屆的名單（立法院開放資料 legislators）、個人頁、他的那一輪、發言。
 */
class LegislatorHelper
{
    const TERMS = [11, 10, 9, 8];
    const YEARS = [11 => '2024–2028', 10 => '2020–2024', 9 => '2016–2020', 8 => '2012–2016'];

    /** 某屆全部立委（含離職、遞補） */
    public static function listTerm($term)
    {
        static $cache = [];
        if (isset($cache[$term])) {
            return $cache[$term];
        }
        $qs = LYAPI::qs([
            '屆' => $term,
            'limit' => 300,
            'output_fields' => ['委員姓名', '黨籍', '黨團', '選區名稱', '照片位址', '是否離職', '離職日期', '離職原因', '委員會', '到職日'],
        ]);
        $res = LYAPI::apiQuery("/legislators?{$qs}", "第 {$term} 屆立委名單", 86400);
        $people = [];
        foreach ($res->legislators ?? [] as $l) {
            $name = trim($l->委員姓名);
            $people[$name] = [
                'name' => $name,
                'term' => intval($term),
                'party' => trim($l->黨籍 ?? '') ?: '無黨籍',
                'caucus' => trim($l->黨團 ?? ''),
                'area' => trim($l->選區名稱 ?? ''),
                'committees' => self::lastCommittees((array)($l->委員會 ?? [])),
                'left' => ($l->是否離職 ?? '') === '是',
                'leftDate' => trim($l->離職日期 ?? ''),
                'leftReason' => trim($l->離職原因 ?? ''),
                'photo' => $l->照片位址 ?? null,
                'count' => 0,
            ];
        }
        $cache[$term] = $people;
        return $people;
    }

    /** 委員會取該屆最後一個會期的（API 的寫法是「第11屆第2會期：教育及文化委員會」） */
    public static function lastCommittees(array $list)
    {
        $by_session = [];
        foreach ($list as $c) {
            if (preg_match('#^第(\d+)屆第(\d+)會期：(.+)$#u', trim($c), $m)) {
                $by_session[intval($m[2])][] = $m[3];
            } elseif (trim($c) !== '') {
                $by_session[0][] = trim($c);
            }
        }
        if (!$by_session) {
            return [];
        }
        krsort($by_session);
        // 程序委員會是另外指派的，和常設委員會一起列
        return array_values(array_unique(reset($by_session)));
    }

    /**
     * 某屆立委依「該屆黨籍」分組：在任的依發言段數排序，已離職的留在原黨籍那組、排最後。
     * @return [parties => [短黨名 => ['full', 'people' => [...], 'gone' => n]], 'all' => people]
     */
    public static function byParty($term)
    {
        $people = self::listTerm($term);
        $counts = self::speechCounts($term);
        foreach ($people as &$p) {
            $p['count'] = $counts[$p['name']] ?? 0;
        }
        unset($p);
        uasort($people, function($a, $b) {
            if ($a['left'] !== $b['left']) {
                return $a['left'] <=> $b['left'];
            }
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }
            return strcmp($a['name'], $b['name']);
        });
        $groups = [];
        foreach ($people as $p) {
            $short = SiteHelper::partyShort($p['party']);
            if (!isset($groups[$short])) {
                $groups[$short] = ['short' => $short, 'full' => $p['party'], 'people' => [], 'gone' => 0, 'active' => 0];
            }
            $groups[$short]['people'][] = $p;
            if ($p['left']) {
                $groups[$short]['gone'] ++;
            } else {
                $groups[$short]['active'] ++;
            }
        }
        uasort($groups, function($a, $b) {
            if (($a['short'] === '無黨籍') !== ($b['short'] === '無黨籍')) {
                return ($a['short'] === '無黨籍') <=> ($b['short'] === '無黨籍');
            }
            return $b['active'] <=> $a['active'];
        });
        return $groups;
    }

    /** 這一屆每位委員的發言段數（公報發言索引），用來排序；agg 最多回 agg_size 個 */
    public static function speechCounts($term)
    {
        $qs = LYAPI::qs(['屆' => $term, '區塊種類' => '發言', '發言者身分' => '委員', 'limit' => 0, 'agg' => '發言者', 'agg_size' => 300]);
        $res = LYAPI::speeches($qs, "第 {$term} 屆每位委員的發言段數", 86400);
        $out = [];
        foreach ($res->aggs[0]->buckets ?? [] as $b) {
            $out[$b->發言者] = intval($b->count);
        }
        return $out;
    }

    /** 這個人在第 8–11 屆各屆的資料（看得出換黨）：屆 => info */
    public static function termsOf($name)
    {
        $out = [];
        foreach (self::TERMS as $t) {
            try {
                $list = self::listTerm($t);
            } catch (Exception $e) {
                continue;
            }
            if (isset($list[$name])) {
                $out[$t] = $list[$name];
            }
        }
        return $out;
    }

    /** 立委基本資料（最近一屆）；找不到丟 LYAPI_NotFound */
    public static function get($name)
    {
        $terms = self::termsOf($name);
        if (!$terms) {
            throw new LYAPI_NotFound("legislator {$name}");
        }
        $latest = max(array_keys($terms));
        $info = $terms[$latest];
        $info['terms'] = $terms;
        return $info;
    }

    /** 公報的發言者寫法：洪孟楷 → 洪委員孟楷（複姓的人用 q 片語比對也找得到） */
    public static function gazetteName($name)
    {
        return mb_substr($name, 0, 1) . '委員' . mb_substr($name, 1);
    }

    /**
     * 發言（公報發言索引），可加關鍵字與日期區間，依會議分組。
     * @return object { total, page, total_page, groups }
     */
    public static function speeches($name, $q = '', $page = 1, $from = null, $to = null)
    {
        $qs = LYAPI::qs([
            '發言者' => $name,
            '發言者身分' => '委員',
            '區塊種類' => '發言',
            '日期:' => $from ? "{$from}," . ($to ?: SiteHelper::today()) : null,
            'q' => $q !== '' ? '"' . trim($q, '"') . '"' : null,
            'limit' => 50,
            'page' => max(1, intval($page)),
            'sort' => '日期>',
            'output_fields' => ['發言編號', '文件編號', '會議代碼', '會議種類', '日期', '發言者', '發言者身分', '內文', '發言時間', '影片', '順序', '段落', '質詢輪次委員'],
        ]);
        $res = LYAPI::speeches($qs, "{$name} 的發言" . ($q !== '' ? "（{$q}）" : ''), 600);
        $out = new StdClass;
        $out->unavailable = is_null($res);
        $out->total = intval($res->total ?? 0);
        $out->page = max(1, intval($page));
        $out->total_page = intval($res->total_page ?? 1);
        $out->groups = SearchHelper::groupByMeet((array)($res->speeches ?? []));
        return $out;
    }

    /** 發言過的會議數（agg 最多 100 個 bucket，超過就寫 100+） */
    public static function meetCount($name)
    {
        $qs = LYAPI::qs(['發言者' => $name, '發言者身分' => '委員', '區塊種類' => '發言', 'limit' => 0, 'agg' => '會議代碼']);
        $res = LYAPI::speeches($qs, "{$name} 發言過的會議數", 3600);
        if (is_null($res)) {
            return [null, null];
        }
        $n = count($res->aggs[0]->buckets ?? []);
        return [$n >= 100 ? '100+' : $n, intval($res->total ?? 0)];
    }

    /**
     * 他的那一輪：已細分、以人為單位的段（國是論壇、總質詢、委員詢答、公聽會、協商）。
     * @return [meet_id => [ ['unit', 'label', 'anchor', 'doc', 'date'] ]]
     */
    public static function rounds($name)
    {
        $qs = LYAPI::qs([
            'q' => '"' . self::gazetteName($name) . '"',
            '段種類' => 'person',
            'limit' => 200,
            'sort' => '日期>',
            'output_fields' => ['會議代碼', '段名', '單元名稱', '日期', '文件編號', '發言起', '段序', '單元序'],
        ]);
        try {
            $res = LYAPI::apiQuery("/meet_sections?{$qs}", "{$name} 的那一輪", 3600);
        } catch (LYAPI_NotFound $e) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($res->meet_sections ?? [] as $s) {
            $mid = is_array($s->會議代碼) ? ($s->會議代碼[0] ?? '') : $s->會議代碼;
            $key = "{$mid}|{$s->單元序}|{$s->段序}";
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[$mid][] = [
                'unit' => $s->單元名稱,
                'label' => $s->段名,
                'anchor' => MeetHelper::sectionAnchor($s),
                'doc' => $s->文件編號 ?? null,
                'date' => $s->日期 ?? null,
            ];
        }
        return $out;
    }

    /** 搜尋用：第 8–11 屆名單裡名字符合的人 */
    public static function search($q)
    {
        $found = [];
        foreach (self::TERMS as $t) {
            try {
                foreach (self::listTerm($t) as $name => $p) {
                    if (isset($found[$name])) {
                        continue;
                    }
                    if (mb_strpos($name, $q) !== false or (mb_strlen($q) >= 2 and mb_strpos($q, $name) !== false)) {
                        $found[$name] = $p;
                    }
                }
            } catch (Exception $e) {
            }
        }
        return array_values($found);
    }
}
