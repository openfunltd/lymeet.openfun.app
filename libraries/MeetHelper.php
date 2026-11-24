<?php
/**
 * 會議相關的資料取得：清單（含狀態）、單場、影片、公報發言。
 */
class MeetHelper
{
    const TYPE_ORDER = ['院會', '全院委員會', '委員會', '聯席會議', '公聽會', '黨團協商', '考察'];

    const STATUS_LABEL = [
        'gazette' => '公報定稿',
        'ai' => 'AI 轉錄稿',
        'processing' => 'AI 轉錄稿產生中',
        'scheduled' => '已排定',
        'novideo' => '沒有影片',
    ];

    public static function defaultTerm()
    {
        return intval(getenv('DEFAULT_TERM') ?: 11);
    }

    /** 委員會代號 → 名稱 */
    public static function committees()
    {
        static $map = null;
        if (!is_null($map)) {
            return $map;
        }
        $map = [];
        try {
            $res = LYAPI::apiQuery('/committees?limit=100', '委員會清單', 86400);
            foreach ($res->committees ?? [] as $c) {
                $map[intval($c->委員會代號)] = $c->委員會名稱;
            }
        } catch (Exception $e) {
        }
        return $map;
    }

    /**
     * 某段日期的會議清單，每場附狀態與 Full 影片。
     * @return array of stdClass（見 decorate()）
     */
    public static function listMeets($from, $to, $types = [])
    {
        $qs = LYAPI::qs([
            '日期:' => "{$from},{$to}",
            '會議種類' => $types,
            'limit' => 500,
            'sort' => '日期<',
            'output_fields' => ['會議代碼', '會議種類', '日期', '會議標題', '會議資料', '委員會代號', '公報發言紀錄', '議事網資料'],
        ]);
        $res = LYAPI::apiQuery("/meets?{$qs}", "{$from}～{$to} 的會議", 600);
        $fulls = self::fullVideosByMeet($from, $to);
        $with_speeches = self::meetsWithSpeeches($from, $to);
        $meets = [];
        foreach ($res->meets ?? [] as $m) {
            $o = self::decorate($m, $fulls[$m->會議代碼] ?? []);
            if ($o->status !== 'gazette' and isset($with_speeches[$o->id])) {
                // 黨團協商的紀錄不經公報目錄（沒有 公報發言紀錄 欄位），但發言索引有
                [$o->status, $o->status_label] = ['gazette', self::STATUS_LABEL['gazette']];
            }
            $meets[] = $o;
        }
        return $meets;
    }

    /** 某段日期已有公報發言索引的會議代碼（agg 最多 100 個 bucket） */
    public static function meetsWithSpeeches($from, $to)
    {
        $qs = LYAPI::qs(['日期:' => "{$from},{$to}", 'limit' => 0, 'agg' => '會議代碼']);
        $res = LYAPI::speeches($qs, "{$from}～{$to} 已有公報發言的會議", 600);
        $ids = [];
        foreach ($res->aggs[0]->buckets ?? [] as $b) {
            foreach ((array)$b->會議代碼 as $id) {
                $ids[$id] = intval($b->count);
            }
        }
        return $ids;
    }

    /** 某段日期所有 Full 影片，依會議代碼分組 */
    public static function fullVideosByMeet($from, $to)
    {
        $qs = LYAPI::qs([
            '日期:' => "{$from},{$to}",
            '影片種類' => 'Full',
            'limit' => 1000,
            'output_fields' => ['IVOD_ID', '會議資料', '支援功能', '日期', '開始時間', '結束時間', '影片長度', 'video_url'],
        ]);
        $res = LYAPI::apiQuery("/ivods?{$qs}", "{$from}～{$to} 的完整會議影片", 600);
        $by_meet = [];
        foreach ($res->ivods ?? [] as $v) {
            $mid = $v->會議資料->會議代碼 ?? null;
            if ($mid) {
                $by_meet[$mid][] = $v;
            }
        }
        return $by_meet;
    }

    /** 清單依日期展開（跨日的會議每一天都列） */
    public static function groupByDay(array $meets, $from, $to)
    {
        $days = [];
        foreach ($meets as $m) {
            foreach ($m->dates as $d) {
                if ($d < $from or $d > $to) {
                    continue;
                }
                $days[$d][] = $m;
            }
        }
        ksort($days);
        foreach ($days as $d => &$list) {
            usort($list, function($a, $b) {
                $ta = array_search($a->type, self::TYPE_ORDER);
                $tb = array_search($b->type, self::TYPE_ORDER);
                if ($ta !== $tb) {
                    return $ta <=> $tb;
                }
                return strcmp($a->start_time ?? '', $b->start_time ?? '');
            });
        }
        return $days;
    }

    /** 把 API 的 meet 物件整理成 view 好用的樣子 */
    public static function decorate($m, array $fulls = [])
    {
        $o = new StdClass;
        $o->id = $m->會議代碼;
        $o->type = $m->會議種類 ?? '';
        $o->dates = (array)($m->日期 ?? []);
        sort($o->dates);
        $o->term = $m->屆 ?? null;
        $o->session = $m->會期 ?? null;
        $md = (array)($m->會議資料 ?? []);
        $md0 = $md[0] ?? null;
        $o->title = self::title($m);
        $o->subject = trim($md0->會議事由 ?? '');
        $o->start_time = $md0->開始時間 ?? null;
        $o->end_time = $md0->結束時間 ?? null;
        $o->room = $md0->會議地點 ?? null;
        $o->chair = $md0->委員會召集委員 ?? null;
        $o->attend = (array)($md0->出席委員 ?? []);
        $o->ppg_url = $md0->ppg_url ?? null;
        $committees = self::committees();
        $o->committees = [];
        foreach ((array)($m->委員會代號 ?? []) as $cid) {
            $o->committees[] = $m->{'委員會代號:str'}[array_search($cid, (array)$m->委員會代號)] ?? ($committees[intval($cid)] ?? "委員會 {$cid}");
        }
        $o->gazette_records = (array)($m->公報發言紀錄 ?? []);
        $o->bills = [];
        $o->attachments = [];
        $o->links = [];
        foreach ((array)($m->議事網資料 ?? []) as $pp) {
            foreach ((array)($pp->關係文書->議案 ?? []) as $bill) {
                $o->bills[$bill->議案編號 ?? ($bill->標題 ?? '')] = $bill;
            }
            foreach ((array)($pp->附件 ?? []) as $att) {
                if (!empty($att->連結)) {
                    $o->attachments[$att->連結] = $att;
                }
            }
            foreach ((array)($pp->連結 ?? []) as $link) {
                if (!empty($link->連結)) {
                    $o->links[$link->連結] = $link;
                }
            }
        }
        $o->bills = array_values($o->bills);
        $o->attachments = array_values($o->attachments);
        $o->links = array_values($o->links);
        usort($fulls, fn($a, $b) => strcmp($a->開始時間 ?? '', $b->開始時間 ?? ''));
        $o->fulls = $fulls;
        [$o->status, $o->status_label] = self::status($o, $fulls);
        $o->raw = $m;
        return $o;
    }

    public static function title($m)
    {
        $title = trim($m->會議標題 ?? ($m->name ?? ''));
        $type = $m->會議種類 ?? '';
        // 公聽會、黨團協商、考察的標題常是事由本身，前面補會議種類和委員會
        if (in_array($type, ['公聽會', '黨團協商', '考察']) and !preg_match('#^(立法院)?第\d+屆#u', $title)) {
            $title = preg_replace('#\s+#u', ' ', $title);
            if (mb_strlen($title) > 60) {
                $title = mb_substr($title, 0, 58) . '…';
            }
        }
        return $title ?: $m->會議代碼;
    }

    /** 狀態：公報定稿 > AI 轉錄稿 > 產生中 > 已排定 > 沒有影片 */
    public static function status($o, array $fulls)
    {
        if ($o->gazette_records) {
            return ['gazette', self::STATUS_LABEL['gazette']];
        }
        foreach ($fulls as $f) {
            if (in_array('ai-transcript', (array)($f->支援功能 ?? []))) {
                return ['ai', self::STATUS_LABEL['ai']];
            }
        }
        if ($fulls) {
            return ['processing', self::STATUS_LABEL['processing']];
        }
        $last = $o->dates ? max($o->dates) : null;
        // 會議結束後影片通常一兩天內上傳，三天內都還算「已排定／等影片」
        if (!$last or $last >= date('Y-m-d', strtotime('-3 days'))) {
            return ['scheduled', self::STATUS_LABEL['scheduled']];
        }
        return ['novideo', self::STATUS_LABEL['novideo']];
    }

    /** 單場會議（找不到丟 LYAPI_NotFound） */
    public static function getMeet($id)
    {
        $m = null;
        // 聯席會議的代碼含逗號（聯席會議-11-6-26,19-2），v2 的單筆 endpoint 會把逗號當多個 id 而失敗，改用清單查
        if (strpos($id, ',') === false) {
            $res = LYAPI::apiQuery(LYAPI::path(['meet', $id]), "會議 {$id}", 600);
            $m = $res->data ?? $res;
        }
        if (empty($m->會議代碼)) {
            $qs = LYAPI::qs(['會議代碼' => $id, 'limit' => 1]);
            $res = LYAPI::apiQuery("/meets?{$qs}", "會議 {$id}", 600);
            $m = $res->meets[0] ?? null;
        }
        if (empty($m->會議代碼)) {
            throw new LYAPI_NotFound("meet {$id}");
        }
        [$fulls] = self::getIvods($id);
        return self::decorate($m, $fulls);
    }

    /** @return [fulls, clips]，都依開始時間排序 */
    public static function getIvods($meet_id)
    {
        $qs = LYAPI::qs(['會議資料.會議代碼' => $meet_id, 'limit' => 500, 'output_fields' => ['IVOD_ID', 'IVOD_URL', '會議資料', '影片種類', '支援功能', '日期', '開始時間', '結束時間', '影片長度', '委員名稱', 'video_url']]);
        $res = LYAPI::apiQuery("/ivods?{$qs}", "會議 {$meet_id} 的影片", 600);
        $fulls = [];
        $clips = [];
        foreach ($res->ivods ?? [] as $v) {
            if (($v->影片種類 ?? '') === 'Full') {
                $fulls[] = $v;
            } else {
                $clips[] = $v;
            }
        }
        $by_start = fn($a, $b) => strcmp($a->開始時間 ?? '', $b->開始時間 ?? '');
        usort($fulls, $by_start);
        usort($clips, $by_start);
        return [$fulls, $clips];
    }

    /** 單支影片（含 AI 逐字稿） */
    public static function getIvod($ivod_id)
    {
        $res = LYAPI::apiQuery(LYAPI::path(['ivod', $ivod_id]), "影片 {$ivod_id}（含 AI 逐字稿）", 3600);
        return $res->data ?? $res;
    }

    /** 這場會議有哪些公報文件（文件編號 → 筆數），依文件編號排序（公報編號遞增大致就是時間順序） */
    public static function gazetteDocs($meet_id)
    {
        $qs = LYAPI::qs(['會議代碼' => $meet_id, 'limit' => 0, 'agg' => '文件編號']);
        $res = LYAPI::speeches($qs, "會議 {$meet_id} 的公報文件清單", 1800);
        $docs = [];
        foreach ($res->aggs[0]->buckets ?? [] as $b) {
            $docs[$b->文件編號] = intval($b->count);
        }
        ksort($docs);
        return $docs;
    }

    /**
     * 一份公報文件的發言（院會一場常有二十幾份文件、上萬筆，所以會議頁一次只顯示一份）。
     * @return array of speech objects（依順序）
     */
    public static function gazetteSpeeches($meet_id, $doc_id)
    {
        $qs = LYAPI::qs([
            '文件編號' => $doc_id,
            '會議代碼' => $meet_id,
            'limit' => 10000,
            'sort' => '順序<',
            'output_fields' => ['發言編號', '文件編號', '公報編號', '日期', '順序', '區塊種類', '段落', '發言者', '發言者身分', '發言者職稱', '質詢輪次', '質詢輪次委員', '內文', '發言時間', '時間來源', '影片', '公報起始頁', '公報結束頁', '來源網址'],
        ]);
        $r = LYAPI::speeches($qs, "公報文件 {$doc_id} 的發言", 1800);
        return (array)($r->speeches ?? []);
    }

    /** 文件的標籤：日期、第一個段落或標題，給會議頁的文件切換用 */
    public static function docLabel(array $blocks, $doc_id)
    {
        $date = null;
        $label = null;
        foreach ($blocks as $b) {
            $date = $date ?: ($b->日期 ?? null);
            $sec = trim((string)($b->段落 ?? ''));
            if ($sec !== '') {
                $label = $sec;
                break;
            }
            if (($b->區塊種類 ?? '') === '標題' and !$label) {
                $label = trim(preg_replace('#\s+#u', ' ', mb_substr($b->內文, 0, 40)));
            }
        }
        return trim(($date ? substr($date, 5, 5) . ' ' : '') . mb_substr($label ?: $doc_id, 0, 30));
    }

    /**
     * 這場會議的段落（單元 → 段），來自 LYAPI 的 meet_section（匯入端用 James 的切分器切的；院會、委員會預算審查、公聽會）。
     * 用清單端點而不用 /meet/{id}/meet_sections，因為聯席會議的代碼含逗號會讓單筆路由失敗。
     * @return array of section objects，依單元序、段序；還沒切分或端點不在就回空陣列
     */
    public static function meetSections($meet_id)
    {
        $qs = LYAPI::qs([
            '會議代碼' => $meet_id,
            'limit' => 1000,
            'sort' => ['單元序<', '段序<'],
            'output_fields' => ['段落編號', '切分器', '單元序', '單元名稱', '單元種類', '單元標籤', '段序', '段類型', '段名', '標籤', '段種類', '表決', '文件編號', '發言起', '發言迄', '發言數', '發言者'],
        ]);
        try {
            $res = LYAPI::apiQuery("/meet_sections?{$qs}", "會議 {$meet_id} 的段落目錄", 1800);
        } catch (LYAPI_NotFound $e) {
            return [];
        }
        return (array)($res->meet_sections ?? []);
    }

    /** 段的錨點：段起始那則發言的發言編號（{文件編號}-{順序 4 位}），對不到發言的段回 null */
    public static function sectionAnchor($s)
    {
        if (!isset($s->發言起) or !isset($s->文件編號)) {
            return null;
        }
        return sprintf('%s-%04d', $s->文件編號, intval($s->發言起));
    }

    /**
     * 段落目錄（整場會議，跨文件）：[['label', 'href', 'items' => [['label', 'kind', 'href', 'tags', 'here']]]]。
     * 目前看的文件內的段直接 #錨點，其他文件的段連到那份文件。
     * kind：vote（有表決）或 meet_section 的段種類（person／case／stage／group／procedure／attachment）。
     */
    public static function sectionToc(array $sections, $meet_id, $doc_id)
    {
        $units = [];
        foreach ($sections as $s) {
            $no = intval($s->單元序);
            $sec_no = intval($s->段序);
            if (!isset($units[$no])) {
                $units[$no] = ['label' => trim((string)$s->單元名稱), 'href' => null, 'items' => []];
            }
            $anchor = self::sectionAnchor($s);
            $here = ($s->文件編號 ?? null) === $doc_id;
            $href = null;
            if ($anchor) {
                $href = $here ? "#{$anchor}" : '/meet/' . rawurlencode($meet_id) . '?view=gazette&doc=' . rawurlencode($s->文件編號) . "#{$anchor}";
            }
            // 同一段跨好幾份文件（院會的逐款討論可以跨十份）時一段只列一次：目前文件裡有的那份優先，否則第一份
            if (isset($units[$no]['items'][$sec_no])) {
                if ($here and !$units[$no]['items'][$sec_no]['here'] and $href) {
                    $units[$no]['items'][$sec_no]['href'] = $href;
                    $units[$no]['items'][$sec_no]['here'] = true;
                }
                continue;
            }
            $units[$no]['items'][$sec_no] = [
                'label' => trim((string)$s->段名),
                'kind' => (!empty($s->表決) or in_array('表決', (array)($s->標籤 ?? []))) ? 'vote' : ($s->段種類 ?? 'stage'),
                'href' => $href,
                'tags' => (array)($s->標籤 ?? []),
                'here' => $here,
            ];
            if (!$units[$no]['href'] and $href) {
                $units[$no]['href'] = $href;
            }
        }
        foreach ($units as &$u) {
            $u['items'] = array_values($u['items']);
            // 單元底下只有一段、段名又跟單元同名（例如「報告事項」），不必重複列
            if (count($u['items']) == 1 and $u['items'][0]['label'] === $u['label']) {
                $u['items'] = [];
            }
        }
        return array_values($units);
    }

    /** 舊式目錄（沒有切分結果時用）：段落 → 單元，質詢輪次／表決 → 段，格式同 sectionToc() */
    public static function legacyToc(array $blocks)
    {
        $units = [];
        foreach (self::gazetteToc($blocks) as $t) {
            if ($t['kind'] === 'section' or !$units) {
                $units[] = ['label' => $t['kind'] === 'section' ? $t['label'] : '', 'href' => '#' . $t['anchor'], 'items' => []];
                if ($t['kind'] === 'section') {
                    continue;
                }
            }
            $units[count($units) - 1]['items'][] = ['label' => $t['label'], 'kind' => $t['kind'], 'href' => '#' . $t['anchor'], 'tags' => [], 'here' => true];
        }
        return $units;
    }

    /**
     * 內文要插標題的位置：順序 → 這則發言起頭的段（只看目前文件、對得到發言的段）。
     * 每筆 ['unit_no', 'unit_label', 'label', 'kind', 'show_label']；show_label false 表示段名跟單元同名、不重複印。
     */
    public static function sectionStarts(array $sections, $doc_id)
    {
        $starts = [];
        $count = [];
        foreach ($sections as $s) {
            $count[intval($s->單元序)] = ($count[intval($s->單元序)] ?? 0) + 1;
        }
        foreach ($sections as $s) {
            if (($s->文件編號 ?? null) !== $doc_id or !isset($s->發言起)) {
                continue;
            }
            $no = intval($s->單元序);
            $label = trim((string)$s->段名);
            $starts[intval($s->發言起)][] = [
                'unit_no' => $no,
                'unit_label' => trim((string)$s->單元名稱),
                'label' => $label,
                'kind' => (!empty($s->表決) or in_array('表決', (array)($s->標籤 ?? []))) ? 'vote' : ($s->段種類 ?? 'stage'),
                'show_label' => !($count[$no] == 1 and $label === trim((string)$s->單元名稱)),
            ];
        }
        return $starts;
    }

    /** 公報發言的段落目錄：議程項目（段落）、質詢輪次、表決 */
    public static function gazetteToc(array $blocks)
    {
        $toc = [];
        $section = null;
        $turn = null;
        foreach ($blocks as $b) {
            $sec = trim((string)($b->段落 ?? ''));
            if ($sec !== '' and $sec !== $section) {
                $section = $sec;
                $turn = null;
                $toc[] = ['kind' => 'section', 'label' => mb_strlen($sec) > 50 ? mb_substr($sec, 0, 48) . '…' : $sec, 'anchor' => $b->發言編號, 'time' => $b->發言時間 ?? null];
            }
            if (!empty($b->質詢輪次委員) and ($b->質詢輪次 ?? null) !== $turn) {
                $turn = $b->質詢輪次;
                $toc[] = ['kind' => 'turn', 'label' => $b->質詢輪次委員, 'anchor' => $b->發言編號, 'time' => $b->發言時間 ?? null];
            }
            if (($b->區塊種類 ?? '') === '宣讀表決') {
                $toc[] = ['kind' => 'vote', 'label' => '表決', 'anchor' => $b->發言編號, 'time' => $b->發言時間 ?? null];
            }
        }
        return $toc;
    }

    /** 公報 PDF 連結（公報發言紀錄裡有的話） */
    public static function gazettePdfs($o)
    {
        $pdfs = [];
        foreach ($o->gazette_records as $r) {
            $url = $r->pdf_url ?? null;
            if (!$url) {
                continue;
            }
            $pdfs[$url] = [
                'url' => $url,
                'gazette_id' => $r->gazette_id ?? null,
                'page_start' => $r->page_start ?? null,
                'page_end' => $r->page_end ?? null,
            ];
        }
        return array_values($pdfs);
    }

    public static function fmtDate($d)
    {
        if (!$d) {
            return '';
        }
        $t = strtotime($d);
        $w = ['日', '一', '二', '三', '四', '五', '六'][intval(date('w', $t))];
        return date('Y/m/d', $t) . "（{$w}）";
    }

    public static function fmtDates(array $dates)
    {
        if (!$dates) {
            return '';
        }
        if (count($dates) == 1) {
            return self::fmtDate($dates[0]);
        }
        return self::fmtDate($dates[0]) . ' 起，分 ' . count($dates) . ' 天';
    }

    public static function fmtDuration($seconds)
    {
        $seconds = intval($seconds);
        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /** "04:01:47" 或秒數 → 秒數 */
    public static function toSeconds($len)
    {
        if (is_numeric($len)) {
            return intval($len);
        }
        if (preg_match('#^(\d+):(\d+):(\d+)$#', (string)$len, $m)) {
            return $m[1] * 3600 + $m[2] * 60 + $m[3];
        }
        return 0;
    }
}
