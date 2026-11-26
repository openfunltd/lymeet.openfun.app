<?php
/**
 * 會議相關的資料取得：清單（含狀態、在做什麼、三讀表決標記）、單場、影片、公報發言、段落目錄（meet_section）。
 * 全部即時向 LYAPI v2 查，沒有自己的資料庫。
 */
class MeetHelper
{
    const TYPE_ORDER = ['院會', '委員會', '聯席會議', '全院委員會', '公聽會', '黨團協商', '考察'];

    /** 篩選「在做什麼」只用 James 的類別（2026-11-22 Claire） */
    const KINDS = ['院會', '審預算', '人事同意權', '公聽會', '院長主持', '委員主持'];

    const STATUS_LABEL = [
        'gazette' => '公報定稿',
        'ai' => 'AI 轉錄稿',
        'processing' => 'AI 轉錄稿產生中',
        'scheduled' => '已排定',
        'novideo' => '沒有影片',
    ];

    /** 這幾種會議 James 還沒細分：沒有段落目錄 */
    const NO_SEG_TYPES = ['考察', '程序委員會', '全院委員談話會'];

    const MEET_FIELDS = ['會議代碼', '會議種類', '會議屬性', '日期', '會議標題', '會議資料', '委員會代號', '公報發言紀錄', '議事網資料', '三讀法案'];
    /** 列表用：不帶 議事網資料（院會一場的關係文書有上百件議案，很肥；列表只要標題、事由、狀態） */
    const LIST_FIELDS = ['會議代碼', '會議種類', '會議屬性', '日期', '會議標題', '會議資料', '委員會代號', '公報發言紀錄', '三讀法案'];

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
     * 某段日期的會議清單，每場附狀態、Full 影片、在做什麼、三讀表決標記。
     * @return array of stdClass（見 decorate()）
     */
    public static function listMeets($from, $to, $types = [])
    {
        $qs = LYAPI::qs([
            '日期:' => "{$from},{$to}",
            '會議種類' => $types,
            'limit' => 500,
            'sort' => '日期<',
            'output_fields' => self::LIST_FIELDS,
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
        self::attachSections($meets, $from, $to);
        return $meets;
    }

    /**
     * 補上「在做什麼」（審預算：James 的預算審查名單＝meet_section 的切分器）、院會表決次數、有沒有段落目錄。
     * 用日期區間的 agg 一次查完；超過 100 個 bucket 時把區間對半再查。
     */
    public static function attachSections(array $meets, $from, $to)
    {
        $seg = self::sectionAgg(['日期:' => "{$from},{$to}"], '會議代碼,切分器');
        $votes = self::sectionAgg(['日期:' => "{$from},{$to}", '標籤' => '表決'], '會議代碼');
        $threads = self::sectionAgg(['日期:' => "{$from},{$to}", '標籤' => '三讀'], '會議代碼');
        self::applySectionAgg($meets, $seg, $votes, $threads);
    }

    /** 同上，但給一組會議代碼（搜尋結果、法律頁、立委頁的列表用） */
    public static function attachSectionsByIds(array $meets)
    {
        $ids = array_values(array_unique(array_map(fn($m) => $m->id, $meets)));
        if (!$ids) {
            return;
        }
        $seg = $votes = $threads = [];
        // 一批 60 場：會議代碼串在網址上，再多會超過伺服器的網址長度（414）
        foreach (array_chunk($ids, 60) as $chunk) {
            $seg = array_merge($seg, self::sectionAgg(['會議代碼' => $chunk, 'agg_size' => 300], '會議代碼,切分器'));
            // 會議代碼×標籤 一次拿到每場的表決、三讀段數
            foreach (self::sectionAgg(['會議代碼' => $chunk, 'agg_size' => 500], '會議代碼,標籤') as $b) {
                if (($b->標籤 ?? '') === '表決') {
                    $votes[] = $b;
                } elseif (($b->標籤 ?? '') === '三讀') {
                    $threads[] = $b;
                }
            }
        }
        self::applySectionAgg($meets, $seg, $votes, $threads);
    }

    protected static function applySectionAgg(array $meets, array $seg, array $votes, array $threads)
    {
        $segmenter = [];
        foreach ($seg as $b) {
            foreach ((array)$b->會議代碼 as $id) {
                $segmenter[$id] = $b->切分器 ?? '';
            }
        }
        $vote_n = [];
        foreach ($votes as $b) {
            foreach ((array)$b->會議代碼 as $id) {
                $vote_n[$id] = intval($b->count);
            }
        }
        $thread_n = [];
        foreach ($threads as $b) {
            foreach ((array)$b->會議代碼 as $id) {
                $thread_n[$id] = intval($b->count);
            }
        }
        foreach ($meets as $m) {
            $m->segmenter = $segmenter[$m->id] ?? null;
            $m->segmented = !is_null($m->segmenter);
            if ($m->type === '院會') {
                // 三讀 N 案：會議資料的三讀法案；表決 N 次：切分器標了「表決」的段
                $m->marks = [
                    '三讀' => count($m->third_reading) ?: ($thread_n[$m->id] ?? 0),
                    '表決' => $vote_n[$m->id] ?? 0,
                ];
            }
            $m->kinds = self::kindsOf($m);
        }
    }

    /** meet_section 的 agg；bucket 滿 100 時把日期區間對半再查，避免漏掉 */
    public static function sectionAgg(array $filter, $agg)
    {
        $qs = LYAPI::qs(array_merge($filter, ['limit' => 0, 'agg' => $agg]));
        try {
            $res = LYAPI::apiQuery("/meet_sections?{$qs}", '段落目錄的統計（' . $agg . '）', isset($filter['日期:']) ? 1800 : null);
        } catch (LYAPI_NotFound $e) {
            return [];
        }
        $buckets = (array)($res->aggs[0]->buckets ?? []);
        if (count($buckets) >= 100 and isset($filter['日期:'])) {
            [$from, $to] = explode(',', $filter['日期:']);
            if ($from < $to) {
                $mid = date('Y-m-d', intdiv(strtotime($from) + strtotime($to), 2));
                $mid2 = SiteHelper::addDays($mid, 1);
                if ($mid2 <= $to) {
                    $a = self::sectionAgg(array_merge($filter, ['日期:' => "{$from},{$mid}"]), $agg);
                    $b = self::sectionAgg(array_merge($filter, ['日期:' => "{$mid2},{$to}"]), $agg);
                    return array_merge($a, $b);
                }
            }
        }
        return $buckets;
    }

    /**
     * 這場「在做什麼」：委員會、聯席會議只有審預算（James 的預算審查名單＝切分器「委員會預算審查」）；
     * 全院委員會只有人事同意權（會議屬性）；院會、公聽會本身就是類別。黨團協商「誰主持」要 meet-json 的 host，資料還沒有。
     */
    public static function kindsOf($m)
    {
        if (in_array($m->type, ['委員會', '聯席會議'])) {
            return ($m->segmenter ?? '') === '委員會預算審查' ? ['審預算'] : [];
        }
        if ($m->type === '全院委員會') {
            return in_array('人事審查', $m->attrs) ? ['人事同意權'] : [];
        }
        if (in_array($m->type, ['院會', '公聽會'])) {
            return [$m->type];
        }
        return [];
    }

    /** 有沒有段落目錄 */
    public static function isSeg($m)
    {
        if (in_array($m->type, self::NO_SEG_TYPES)) {
            return false;
        }
        if ($m->type === '全院委員會' and !$m->kinds) {
            return false;
        }
        return !empty($m->segmented);
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
            'output_fields' => ['IVOD_ID', 'IVOD_URL', '會議資料', '支援功能', '日期', '開始時間', '結束時間', '影片長度', 'video_url'],
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
        $o->attrs = (array)($m->會議屬性 ?? []);
        $o->dates = array_values(array_unique((array)($m->日期 ?? [])));
        sort($o->dates);
        $o->date = $o->dates[0] ?? '';
        $o->term = $m->屆 ?? null;
        $o->session = $m->會期 ?? null;
        $md = (array)($m->會議資料 ?? []);
        $md0 = $md[0] ?? null;
        $o->title = self::title($m);
        $o->subject = trim(str_replace("\r", '', $md0->會議事由 ?? ''));
        $o->unit = trim($md0->會議單位 ?? '');
        $o->start_time = $md0->開始時間 ?? null;
        $o->end_time = $md0->結束時間 ?? null;
        $o->room = $md0->會議地點 ?? null;
        $o->chair = trim(preg_replace('#委員$#u', '', $md0->委員會召集委員 ?? ''));
        $o->ppg_url = $md0->ppg_url ?? null;
        $committees = self::committees();
        $o->committees = [];
        foreach ((array)($m->委員會代號 ?? []) as $i => $cid) {
            $o->committees[] = ((array)($m->{'委員會代號:str'} ?? []))[$i] ?? ($committees[intval($cid)] ?? "委員會 {$cid}");
        }
        if ($o->unit === '' and $o->committees) {
            $o->unit = implode('、', $o->committees);
        }
        $o->gazette_records = (array)($m->公報發言紀錄 ?? []);
        $o->third_reading = (array)($m->三讀法案 ?? []);
        $o->bills = [];
        $o->docs = [];
        $o->ivod_links = [];
        foreach ((array)($m->議事網資料 ?? []) as $pp) {
            foreach ((array)($pp->關係文書->議案 ?? []) as $bill) {
                $o->bills[$bill->議案編號 ?? ($bill->標題 ?? '')] = $bill;
            }
            foreach ((array)($pp->附件 ?? []) as $att) {
                // 本站沒有收錄開會通知單（2026-11-03 Claire）
                if (!empty($att->連結) and strpos($att->標題 ?? '', '開會通知單') === false) {
                    $o->docs[$att->連結] = ['title' => $att->標題 ?: basename($att->連結), 'url' => $att->連結, 'type' => strtoupper($att->格式 ?? '')];
                }
            }
            foreach ((array)($pp->連結 ?? []) as $link) {
                if (($link->類型 ?? '') === 'video' and !empty($link->連結)) {
                    $o->ivod_links[] = $link->連結;
                }
            }
        }
        $o->bills = array_values($o->bills);
        $o->docs = array_values($o->docs);
        usort($fulls, fn($a, $b) => strcmp($a->開始時間 ?? '', $b->開始時間 ?? ''));
        $o->fulls = $fulls;
        [$o->status, $o->status_label, $o->is_live] = self::status($o, $fulls);
        $o->segmenter = null;
        $o->segmented = false;
        $o->marks = [];
        $o->kinds = self::kindsOf($o);
        $o->raw = $m;
        return $o;
    }

    public static function title($m)
    {
        $title = trim($m->會議標題 ?? ($m->name ?? ''));
        $type = $m->會議種類 ?? '';
        $title = preg_replace('#\s+#u', ' ', $title);
        if ($type === '黨團協商' and (!$title or $title === '立法院朝野黨團協商')) {
            // 黨團協商的標題是通用的，改取事由
            $content = trim(((array)($m->會議資料 ?? []))[0]->會議事由 ?? '');
            $content = preg_replace('#^一、#u', '', preg_replace('#\s+#u', ' ', $content));
            if ($content !== '') {
                $title = '朝野黨團協商：' . mb_substr($content, 0, 40) . (mb_strlen($content) > 40 ? '…' : '');
            }
        }
        if (in_array($type, ['公聽會', '考察']) and mb_strlen($title) > 70) {
            $title = mb_substr($title, 0, 68) . '…';
        }
        return $title ?: $m->會議代碼;
    }

    /**
     * 狀態：一個會議只有一個狀態，系統依日期和資料自動判斷。
     * 公報定稿 > AI 轉錄稿 > AI 轉錄稿產生中（有影片沒逐字稿，或今天已開始） > 已排定 > 沒有影片。
     * @return [status, label, is_live]
     */
    public static function status($o, array $fulls)
    {
        if ($o->gazette_records) {
            return ['gazette', self::STATUS_LABEL['gazette'], false];
        }
        foreach ($fulls as $f) {
            if (in_array('ai-transcript', (array)($f->支援功能 ?? []))) {
                return ['ai', self::STATUS_LABEL['ai'], false];
            }
        }
        $now = date('Y-m-d\TH:i:s');
        $today = date('Y-m-d');
        if ($fulls) {
            return ['processing', self::STATUS_LABEL['processing'], false];
        }
        if (in_array($today, $o->dates)) {
            // 今天的會議：開始時間到了就算開會中（直播中要看 IVOD 的直播狀態，資料還沒有，先用時間近似）
            $start = $o->start_time ? substr($o->start_time, 0, 19) : "{$today}T09:00:00";
            $end = $o->end_time ? substr($o->end_time, 0, 19) : "{$today}T23:59:59";
            if ($now >= $start) {
                return ['processing', self::STATUS_LABEL['processing'], $now <= $end];
            }
            return ['scheduled', self::STATUS_LABEL['scheduled'], false];
        }
        $last = $o->dates ? max($o->dates) : null;
        if (!$last or $last > $today or $last >= date('Y-m-d', strtotime('-3 days'))) {
            return ['scheduled', self::STATUS_LABEL['scheduled'], false];
        }
        return ['novideo', self::STATUS_LABEL['novideo'], false];
    }

    /** 單場會議（找不到丟 LYAPI_NotFound） */
    public static function getMeet($id)
    {
        $m = null;
        // 聯席會議的代碼含逗號（聯席會議-11-6-26,19-2），v2 的單筆 endpoint 會把逗號當多個 id 而失敗，改用清單查
        if (strpos($id, ',') === false) {
            try {
                $res = LYAPI::apiQuery(LYAPI::path(['meet', $id]), "會議 {$id}");
                $m = $res->data ?? $res;
            } catch (LYAPI_NotFound $e) {
            }
        }
        if (empty($m->會議代碼)) {
            $qs = LYAPI::qs(['會議代碼' => $id, 'limit' => 1]);
            $res = LYAPI::apiQuery("/meets?{$qs}", "會議 {$id}");
            $m = $res->meets[0] ?? null;
        }
        if (empty($m->會議代碼)) {
            throw new LYAPI_NotFound("meet {$id}");
        }
        [$fulls] = self::getIvods($id);
        return self::decorate($m, $fulls);
    }

    /** 一次抓多場會議（搜尋結果、法律頁用），回 [id => decorated] */
    public static function getMeets(array $ids)
    {
        $ids = array_values(array_unique(array_filter($ids)));
        $meets = [];
        foreach (array_chunk($ids, 100) as $chunk) {
            $qs = LYAPI::qs(['會議代碼' => $chunk, 'limit' => count($chunk), 'output_fields' => self::LIST_FIELDS]);
            $res = LYAPI::apiQuery("/meets?{$qs}", '多場會議的資料');
            foreach ($res->meets ?? [] as $m) {
                $meets[$m->會議代碼] = self::decorate($m);
            }
        }
        if ($meets) {
            self::attachStatus($meets);
        }
        return $meets;
    }

    /** 補上影片、公報發言、段落統計算出的狀態（decorate() 之後、沒有日期區間時用） */
    public static function attachStatus(array $meets)
    {
        if (!$meets) {
            return;
        }
        self::attachVideoStatus($meets);
        self::attachSectionsByIds(array_values($meets));
        foreach ($meets as $m) {
            $m->status_attached = true;
        }
    }

    /** 沒有日期區間時，用會議代碼補 Full 影片與發言索引，算出狀態 */
    protected static function attachVideoStatus(array $meets)
    {
        $ids = array_keys($meets);
        $fulls = [];
        $with_speeches = [];
        foreach (array_chunk($ids, 60) as $chunk) {
            $qs = LYAPI::qs(['會議資料.會議代碼' => $chunk, '影片種類' => 'Full', 'limit' => 1000, 'output_fields' => ['IVOD_ID', 'IVOD_URL', '會議資料', '支援功能', '日期', '開始時間', '結束時間', '影片長度', 'video_url']]);
            $res = LYAPI::apiQuery("/ivods?{$qs}", '多場會議的完整影片');
            foreach ($res->ivods ?? [] as $v) {
                $mid = $v->會議資料->會議代碼 ?? null;
                if ($mid) {
                    $fulls[$mid][] = $v;
                }
            }
            $qs = LYAPI::qs(['會議代碼' => $chunk, 'limit' => 0, 'agg' => '會議代碼', 'agg_size' => 200]);
            $r = LYAPI::speeches($qs, '多場會議有沒有公報發言');
            foreach ($r->aggs[0]->buckets ?? [] as $b) {
                foreach ((array)$b->會議代碼 as $id) {
                    $with_speeches[$id] = true;
                }
            }
        }
        foreach ($meets as $id => $m) {
            $m->fulls = $fulls[$id] ?? [];
            usort($m->fulls, fn($a, $b) => strcmp($a->開始時間 ?? '', $b->開始時間 ?? ''));
            [$m->status, $m->status_label, $m->is_live] = self::status($m, $m->fulls);
            if ($m->status !== 'gazette' and isset($with_speeches[$id])) {
                [$m->status, $m->status_label] = ['gazette', self::STATUS_LABEL['gazette']];
            }
        }
    }

    /** @return [fulls, clips]，都依開始時間排序 */
    public static function getIvods($meet_id)
    {
        $qs = LYAPI::qs(['會議資料.會議代碼' => $meet_id, 'limit' => 500, 'output_fields' => ['IVOD_ID', 'IVOD_URL', '會議資料', '影片種類', '支援功能', '日期', '開始時間', '結束時間', '影片長度', '委員名稱', 'video_url']]);
        $res = LYAPI::apiQuery("/ivods?{$qs}", "會議 {$meet_id} 的影片");
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
        $res = LYAPI::apiQuery(LYAPI::path(['ivod', $ivod_id]), "影片 {$ivod_id}（含 AI 逐字稿）");
        return $res->data ?? $res;
    }

    /** 這場會議有哪些公報文件（文件編號 → 筆數），依文件編號排序（公報編號遞增大致就是時間順序） */
    public static function gazetteDocs($meet_id)
    {
        $qs = LYAPI::qs(['會議代碼' => $meet_id, 'limit' => 0, 'agg' => '文件編號']);
        $res = LYAPI::speeches($qs, "會議 {$meet_id} 的公報文件清單");
        $docs = [];
        foreach ($res->aggs[0]->buckets ?? [] as $b) {
            $docs[$b->文件編號] = intval($b->count);
        }
        ksort($docs);
        return $docs;
    }

    /** 一份公報文件的發言（院會一場常有二十幾份文件、上萬筆，所以會議頁一次只顯示一份） */
    public static function gazetteSpeeches($meet_id, $doc_id)
    {
        $qs = LYAPI::qs([
            '文件編號' => $doc_id,
            '會議代碼' => $meet_id,
            'limit' => 10000,
            'sort' => '順序<',
            'output_fields' => ['發言編號', '文件編號', '公報編號', '日期', '順序', '區塊種類', '段落', '發言者', '發言者身分', '發言者職稱', '質詢輪次', '質詢輪次委員', '內文', '發言時間', '時間來源', '影片', '公報起始頁', '公報結束頁', '來源網址'],
        ]);
        $r = LYAPI::speeches($qs, "公報文件 {$doc_id} 的發言");
        return (array)($r->speeches ?? []);
    }

    /** 這場會議的段落（單元 → 段），來自 LYAPI 的 meet_section（James 的切分器）。還沒切分回空陣列 */
    public static function meetSections($meet_id)
    {
        $qs = LYAPI::qs([
            '會議代碼' => $meet_id,
            'limit' => 1000,
            'sort' => ['單元序<', '段序<'],
            'output_fields' => ['段落編號', '切分器', '單元序', '單元名稱', '單元種類', '單元日期', '單元標籤', '段序', '段類型', '段名', '標籤', '段種類', '表決', '文件編號', '發言起', '發言迄', '發言數', '發言者', '摘句', '日期'],
        ]);
        try {
            $res = LYAPI::apiQuery("/meet_sections?{$qs}", "會議 {$meet_id} 的段落目錄");
        } catch (LYAPI_NotFound $e) {
            return [];
        }
        return (array)($res->meet_sections ?? []);
    }

    /** 段的錨點：段起始那則發言的發言編號（{文件編號}-{順序 4 位}），對不到發言的段回 null */
    public static function sectionAnchor($s)
    {
        if (!isset($s->發言起) or empty($s->文件編號)) {
            return null;
        }
        return sprintf('%s-%04d', $s->文件編號, intval($s->發言起));
    }

    /**
     * 把 meet_section 整理成單元 → 段的結構（畫段落目錄、議程時間軸、逐字稿裡的段標題）。
     * 單元依日期與時間排（James 的資料照單元種類排，跨好幾天的會議會倒序；2026-11-22 Claire：段落目錄要對照影片時間）。
     * 同一段跨好幾份文件時只列一次（目前文件裡有的那份優先）。
     */
    public static function sectionUnits(array $sections, $meet_id, $doc_id, array $times = [])
    {
        $units = [];
        foreach ($sections as $s) {
            $no = intval($s->單元序);
            $sec_no = intval($s->段序);
            if (!isset($units[$no])) {
                $units[$no] = [
                    'no' => $no,
                    'label' => trim((string)$s->單元名稱),
                    'kind' => $s->單元種類 ?? '',
                    'dates' => array_values(array_filter((array)($s->單元日期 ?? []))),
                    'tags' => (array)($s->單元標籤 ?? []),
                    'items' => [],
                ];
            }
            $anchor = self::sectionAnchor($s);
            $here = ($s->文件編號 ?? null) === $doc_id;
            $href = null;
            if ($anchor) {
                $href = $here ? "#{$anchor}" : '/meet/' . rawurlencode($meet_id) . '?doc=' . rawurlencode($s->文件編號) . "#{$anchor}";
            }
            if (isset($units[$no]['items'][$sec_no])) {
                if ($here and !$units[$no]['items'][$sec_no]['here'] and $href) {
                    $units[$no]['items'][$sec_no]['href'] = $href;
                    $units[$no]['items'][$sec_no]['anchor'] = $anchor;
                    $units[$no]['items'][$sec_no]['doc'] = $s->文件編號;
                    $units[$no]['items'][$sec_no]['here'] = true;
                }
                continue;
            }
            $kind = $s->段種類 ?? 'person';
            $t = $anchor ? ($times[$anchor] ?? null) : null;
            if (!$t and $here and isset($s->發言起)) {
                // 段起頭那則沒有影片時間（紀錄文字、宣讀）時，往後找這段裡第一則有時間的發言
                $end = isset($s->發言迄) ? intval($s->發言迄) : intval($s->發言起) + 50;
                for ($seq = intval($s->發言起); $seq <= $end; $seq ++) {
                    $k = sprintf('%s-%04d', $s->文件編號, $seq);
                    if (isset($times[$k])) {
                        $t = $times[$k];
                        break;
                    }
                }
            }
            $units[$no]['items'][$sec_no] = [
                'no' => $sec_no,
                'type' => $s->段類型 ?? '',
                'label' => trim((string)$s->段名),
                'kind' => $kind,
                'tags' => (array)($s->標籤 ?? []),
                'vote' => $s->表決 ?? null,
                'anchor' => $anchor,
                'doc' => $s->文件編號 ?? null,
                'here' => $here,
                'href' => $href,
                'start' => $t['start'] ?? null,
                'end' => $t['end'] ?? null,
                'estimated' => $t['estimated'] ?? true,
                'ivod' => $t['ivod'] ?? null,
                'speakers' => (array)($s->發言者 ?? []),
                'sample' => $s->摘句 ?? '',
                'attachment' => ($kind === 'attachment' or !isset($s->發言起)),
            ];
        }
        foreach ($units as &$u) {
            $u['items'] = array_values($u['items']);
        }
        unset($u);
        $units = array_values($units);
        // 單元照開會時間排：有日期的依第一天，沒有日期的排最後；同一天保留原順序
        usort($units, function($a, $b) {
            $da = $a['dates'] ? min($a['dates']) : '9999';
            $db = $b['dates'] ? min($b['dates']) : '9999';
            if ($da !== $db) {
                return strcmp($da, $db);
            }
            return $a['no'] <=> $b['no'];
        });
        return $units;
    }

    /**
     * 目前文件裡各則發言的影片時間：發言編號 → ['start' => 秒數(影片內), 'ivod' => id, 'estimated' => bool, 'iso' => 真實時間]
     */
    public static function speechTimes(array $blocks)
    {
        $times = [];
        foreach ($blocks as $b) {
            if (empty($b->影片->IVOD_ID)) {
                continue;
            }
            $times[$b->發言編號] = [
                'start' => intval($b->影片->秒數 ?? 0),
                'ivod' => intval($b->影片->IVOD_ID),
                'estimated' => ($b->時間來源 ?? '') !== 'clip',
                'iso' => $b->發言時間 ?? null,
            ];
        }
        return $times;
    }

    /** 逐字稿裡要插段標題的位置：順序 → 這則發言起頭的單元與段（只看目前文件） */
    public static function sectionStarts(array $units, $doc_id)
    {
        $starts = [];
        foreach ($units as $u) {
            $single = (count($u['items']) === 1 and $u['items'][0]['label'] === $u['label']);
            foreach ($u['items'] as $it) {
                if ($it['doc'] !== $doc_id or !$it['anchor']) {
                    continue;
                }
                $seq = intval(substr($it['anchor'], -4));
                $starts[$seq][] = ['unit' => $u, 'item' => $it, 'show_label' => !$single];
            }
        }
        return $starts;
    }

    /** 舊式目錄（沒有切分結果時用）：段落 → 單元，質詢輪次／表決 → 段 */
    public static function legacyUnits(array $blocks, array $times = [])
    {
        $units = [];
        $section = null;
        $turn = null;
        foreach ($blocks as $b) {
            $sec = trim((string)($b->段落 ?? ''));
            if ($sec !== '' and $sec !== $section) {
                $section = $sec;
                $turn = null;
                $units[] = ['no' => count($units) + 1, 'label' => $sec, 'kind' => '', 'dates' => [], 'tags' => [], 'items' => []];
            }
            if (!$units) {
                $units[] = ['no' => 1, 'label' => '', 'kind' => '', 'dates' => [], 'tags' => [], 'items' => []];
            }
            $item = null;
            if (!empty($b->質詢輪次委員) and ($b->質詢輪次 ?? null) !== $turn) {
                $turn = $b->質詢輪次;
                $item = ['label' => $b->質詢輪次委員, 'kind' => 'person', 'tags' => []];
            } elseif (($b->區塊種類 ?? '') === '宣讀表決') {
                $item = ['label' => '表決', 'kind' => 'case', 'tags' => ['表決']];
            }
            if ($item) {
                $t = $times[$b->發言編號] ?? null;
                $units[count($units) - 1]['items'][] = array_merge($item, [
                    'no' => count($units[count($units) - 1]['items']) + 1, 'type' => '', 'vote' => null, 'anchor' => $b->發言編號, 'doc' => $b->文件編號,
                    'here' => true, 'href' => '#' . $b->發言編號, 'start' => $t['start'] ?? null, 'end' => null, 'estimated' => $t['estimated'] ?? true, 'ivod' => $t['ivod'] ?? null,
                    'speakers' => [], 'sample' => '', 'attachment' => false,
                ]);
            }
        }
        return $units;
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
                'agenda_id' => $r->agenda_id ?? null,
                'page_start' => $r->page_start ?? null,
                'page_end' => $r->page_end ?? null,
                'content' => $r->content ?? '',
            ];
        }
        return array_values($pdfs);
    }

    /** 公報卷期（公報編號 1156001 → 115卷60期1冊） */
    public static function gazetteVol($gazette_id)
    {
        $g = (string)$gazette_id;
        if (strlen($g) !== 7) {
            return $g;
        }
        return sprintf('%d卷%d期%d冊', intval(substr($g, 0, 3)), intval(substr($g, 3, 2)), intval(substr($g, 5, 2)));
    }

    /**
     * 這場討論的議案（含議案流程），給「同一案的下一步」用。
     * 議案編號取自會議資料的關係文書；再向 /bills 補議案流程與法律。
     */
    public static function billsOfMeet($meet)
    {
        $ids = [];
        foreach ($meet->bills as $b) {
            if (!empty($b->議案編號)) {
                $ids[] = $b->議案編號;
            }
        }
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (array_chunk(array_slice(array_unique($ids), 0, 100), 50) as $chunk) {
            $qs = LYAPI::qs(['議案編號' => $chunk, 'limit' => count($chunk), 'output_fields' => ['議案編號', '議案名稱', '議案流程', '法律編號', '議案狀態', '提案來源']]);
            $res = LYAPI::apiQuery("/bills?{$qs}", '這場討論的議案');
            foreach ($res->bills ?? [] as $b) {
                $out[$b->議案編號] = $b;
            }
        }
        $bills = [];
        foreach ($meet->bills as $b) {
            $bills[] = $out[$b->議案編號 ?? ''] ?? $b;
        }
        return $bills;
    }

    /** 跑馬燈：下一場、新上線 AI 轉錄稿、最近三讀；只放排程與已完成的結果 */
    public static function ticker()
    {
        $today = SiteHelper::today();
        $out = ['today' => $today, 'live' => 0, 'next' => null, 'ai' => null, 'third' => null];
        try {
            $qs = LYAPI::qs(['日期:' => SiteHelper::addDays($today, 1) . ',' . SiteHelper::addDays($today, 30), 'limit' => 1, 'sort' => '日期<', 'output_fields' => ['會議代碼', '會議種類', '日期', '會議標題', '會議資料']]);
            $res = LYAPI::apiQuery("/meets?{$qs}", '跑馬燈：下一場', 600);
            if (!empty($res->meets[0])) {
                $m = $res->meets[0];
                $md = ((array)($m->會議資料 ?? []))[0] ?? null;
                $future = array_values(array_filter((array)$m->日期, fn($d) => $d > $today));
                $out['next'] = ['id' => $m->會議代碼, 'date' => $future ? min($future) : min((array)$m->日期), 'label' => trim($md->會議單位 ?? '') ?: $m->會議種類];
            }
        } catch (Exception $e) {
        }
        try {
            $qs = LYAPI::qs(['影片種類' => 'Full', '支援功能' => 'ai-transcript', 'limit' => 1, 'sort' => '日期>', 'output_fields' => ['IVOD_ID', '會議資料', '日期', '會議名稱']]);
            $res = LYAPI::apiQuery("/ivods?{$qs}", '跑馬燈：新上線 AI 轉錄稿', 600);
            if (!empty($res->ivods[0])) {
                $v = $res->ivods[0];
                $out['ai'] = ['id' => $v->會議資料->會議代碼 ?? null, 'title' => $v->會議資料->{'會議代碼:str'} ?? ($v->會議名稱 ?? ''), 'date' => $v->日期];
            }
        } catch (Exception $e) {
        }
        try {
            $qs = LYAPI::qs(['會議種類' => '院會', '會議屬性' => '三讀', '日期:' => SiteHelper::addDays($today, -180) . ",{$today}", 'limit' => 1, 'sort' => '日期>', 'output_fields' => ['會議代碼', '日期', '三讀法案']]);
            $res = LYAPI::apiQuery("/meets?{$qs}", '跑馬燈：最近三讀', 600);
            if (!empty($res->meets[0])) {
                $m = $res->meets[0];
                $out['third'] = ['id' => $m->會議代碼, 'date' => max((array)$m->日期), 'n' => count((array)($m->三讀法案 ?? []))];
            }
        } catch (Exception $e) {
        }
        return $out;
    }

    public static function fmtDate($d)
    {
        return SiteHelper::fmtFull($d);
    }

    public static function fmtDuration($seconds)
    {
        return SiteHelper::fmtSec($seconds);
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
