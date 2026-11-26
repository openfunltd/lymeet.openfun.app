<?php
/**
 * 法律：法律列表、法律頁（修法進度、討論過這部法的會議）。
 * 修法進度取自立法院議案資料的議案流程；院會與委員會的步驟都帶會議代碼，可以點到那場會議。
 */
class LawHelper
{
    const STEP_ORDER = ['院會一讀', '委員會審查', '院會決定逕付二讀', '排入院會討論', '院會二讀', '院會三讀'];

    /** 法律基本資料；找不到丟 LYAPI_NotFound */
    public static function get($code)
    {
        try {
            $res = LYAPI::apiQuery(LYAPI::path(['law', $code]), "法律 {$code}");
        } catch (LYAPI_NotFound $e) {
            throw $e;
        }
        $l = $res->data ?? $res;
        if (empty($l->法律編號)) {
            throw new LYAPI_NotFound("law {$code}");
        }
        return $l;
    }

    /** 本站有會議討論過的法律：依議案數排序（第 8–11 屆議案的法律編號統計） */
    public static function listLaws()
    {
        $qs = LYAPI::qs(['屆:' => '8,11', 'limit' => 0, 'agg' => '法律編號', 'agg_size' => 500]);
        $res = LYAPI::apiQuery("/bills?{$qs}", '有議案的法律（依議案數）');
        $out = [];
        foreach ($res->aggs[0]->buckets ?? [] as $b) {
            $code = is_array($b->法律編號) ? ($b->法律編號[0] ?? '') : $b->法律編號;
            $name = $b->{'法律編號:str'} ?? $code;
            $name = is_array($name) ? ($name[0] ?? $code) : $name;
            if ($code === '' or $name === '') {
                continue;
            }
            $out[] = ['code' => $code, 'name' => $name, 'count' => intval($b->count)];
        }
        return $out;
    }

    /** 搜尋用：名稱符合的法律 */
    public static function search($q, $limit = 10)
    {
        $qs = LYAPI::qs(['q' => '"' . trim($q, '"') . '"', 'limit' => $limit, 'output_fields' => ['法律編號', '名稱', '其他名稱', '別名']]);
        try {
            $res = LYAPI::apiQuery("/laws?{$qs}", "名稱符合「{$q}」的法律");
        } catch (Exception $e) {
            return [];
        }
        $out = [];
        foreach ($res->laws ?? [] as $l) {
            $out[] = ['code' => $l->法律編號, 'name' => $l->名稱];
        }
        return $out;
    }

    /** 這部法的議案（第 8–11 屆），新的在前 */
    public static function bills($code)
    {
        $qs = LYAPI::qs([
            '法律編號' => $code,
            '屆:' => '8,11',
            'limit' => 200,
            'sort' => '最新進度日期>',
            'output_fields' => ['議案編號', '議案名稱', '議案流程', '法律編號', '提案來源', '議案狀態', '提案單位/提案委員', '最新進度日期', '屆'],
        ]);
        $res = LYAPI::apiQuery("/bills?{$qs}", "法律 {$code} 的議案");
        return (array)($res->bills ?? []);
    }

    /** 議案流程的一步 → 步驟名稱（只用六種；對不上的回 null） */
    public static function stepLabel($f)
    {
        $s = trim((string)($f->狀態 ?? ''));
        $where = trim((string)($f->{'院會/委員會'} ?? ''));
        if ($where !== '' and $where !== '院會') {
            if (preg_match('#協商#u', $where)) {
                return '黨團協商';
            }
            return preg_match('#發文#u', $s) ? null : '委員會審查';
        }
        if ($where === '') {
            return null;
        }
        if (preg_match('#交付審查|排入院會 ?\(交#u', $s)) {
            return '院會一讀';
        }
        if (preg_match('#逕付二讀#u', $s)) {
            return '院會決定逕付二讀';
        }
        if (preg_match('#三讀#u', $s)) {
            return '院會三讀';
        }
        if (preg_match('#二讀#u', $s)) {
            return '院會二讀';
        }
        if (preg_match('#討論事項#u', $s)) {
            return '排入院會討論';
        }
        return null;
    }

    /**
     * 一件議案走過的步驟：[['l' => 步驟, 'id' => 會議代碼, 'date' => 日期, 'committee' => 委員會, 'more' => [再排進的院會]]]
     */
    public static function billSteps($bill)
    {
        $out = [];
        foreach ((array)($bill->議案流程 ?? []) as $f) {
            $l = self::stepLabel($f);
            if (!$l) {
                continue;
            }
            $id = $f->會議代碼 ?? '';
            $dates = (array)($f->日期 ?? []);
            $date = $dates ? min($dates) : '';
            $prev = $out ? $out[count($out) - 1] : null;
            if ($prev and $prev['l'] === $l and ($prev['id'] === $id or $l === '院會一讀' or ($l === '委員會審查' and !$id))) {
                continue;
            }
            if ($prev and $prev['l'] === $l and $l === '排入院會討論' and $id) {
                $out[count($out) - 1]['more'][] = ['id' => $id, 'date' => $date];
                continue;
            }
            $out[] = ['l' => $l, 'id' => $id, 'date' => $date, 'committee' => in_array($l, ['委員會審查', '黨團協商']) ? trim((string)$f->{'院會/委員會'}) : '', 'more' => []];
        }
        return $out;
    }

    /**
     * 修法進度：同一天走到同一場院會、同一步的議案視為一起處理，收成一條。
     * @return array of ['bills', 'steps', 'pending', 'range' => [步驟 => 日期...]]
     */
    public static function branches(array $bills)
    {
        $groups = [];
        $seen = [];
        foreach ($bills as $b) {
            if (isset($seen[$b->議案編號])) {
                continue;
            }
            $seen[$b->議案編號] = true;
            $st = self::billSteps($b);
            $far = null;
            foreach (array_reverse($st) as $s) {
                if ($s['id'] and $s['l'] !== '院會一讀') {
                    $far = $s;
                    break;
                }
            }
            if ($far) {
                $key = $far['id'] . '|' . $far['l'];
            } else {
                $committee = '';
                foreach ($st as $s) {
                    if ($s['committee']) {
                        $committee = $s['committee'];
                        break;
                    }
                }
                $key = 'pending|' . $committee;
            }
            $groups[$key][] = ['b' => $b, 'st' => $st];
        }
        $out = [];
        foreach ($groups as $arr) {
            $first_date = function($x) {
                foreach ($x['st'] as $s) {
                    if ($s['date']) {
                        return $s['date'];
                    }
                }
                return '9';
            };
            $rep = $arr[0];
            foreach ($arr as $x) {
                if ($first_date($x) < $first_date($rep)) {
                    $rep = $x;
                }
            }
            $dates = function($l) use ($arr) {
                $d = [];
                foreach ($arr as $x) {
                    foreach ($x['st'] as $s) {
                        if ($s['l'] === $l and $s['date']) {
                            $d[$s['date']] = true;
                        }
                    }
                }
                $d = array_keys($d);
                sort($d);
                return $d;
            };
            $pending = true;
            foreach ($arr[0]['st'] as $s) {
                if ($s['id'] and $s['l'] !== '院會一讀') {
                    $pending = false;
                }
            }
            $out[] = [
                'bills' => array_map(fn($x) => $x['b'], $arr),
                'steps' => $rep['st'],
                'pending' => $pending,
                'range' => ['院會一讀' => $dates('院會一讀'), '院會決定逕付二讀' => $dates('院會決定逕付二讀')],
                'latest' => max(array_map(function($x) { $d = ''; foreach ($x['st'] as $s) { if ($s['date'] > $d) $d = $s['date']; } return $d; }, $arr)),
            ];
        }
        usort($out, fn($a, $b) => strcmp($b['latest'], $a['latest']));
        return $out;
    }

    public static function proposer($title, $bill = null)
    {
        $unit = trim((string)($bill->{'提案單位/提案委員'} ?? ''));
        if ($unit !== '') {
            return preg_replace('#^本院(委員)?#u', '', $unit);
        }
        $t = (string)$title;
        $t2 = preg_replace('#^.*?併案審查#u', '本院', $t);
        if (preg_match('#本院(.+?)擬具#u', $t2, $m)) {
            return preg_replace('#^委員#u', '', $m[1]);
        }
        if (preg_match('#(行政院|司法院|考試院|監察院)#u', $t, $m)) {
            return $m[1];
        }
        return '';
    }

    public static function draftName($title)
    {
        return preg_match('#「(.+?)」#u', (string)$title, $m) ? $m[1] : '';
    }

    /** 這一場院會對這部法的議案做了什麼（議案流程裡這一場的狀態 → 階段標籤） */
    public static function stageTags($meet_id, array $bills)
    {
        $tags = [];
        foreach ($bills as $b) {
            foreach ((array)($b->議案流程 ?? []) as $f) {
                if (($f->會議代碼 ?? '') !== $meet_id) {
                    continue;
                }
                $l = self::stepLabel($f);
                if ($l and !in_array($l, ['委員會審查', '黨團協商'])) {
                    $tags[$l] = true;
                }
            }
        }
        $out = array_keys($tags);
        usort($out, fn($a, $b) => array_search($a, self::STEP_ORDER) <=> array_search($b, self::STEP_ORDER));
        return $out;
    }

    /**
     * 討論過這部法的會議：議案流程裡的會議（主角）＋事由提到＋發言提到（只是提到）。
     * @return array of ['meet', 'via' => 議案|事由|發言, 'tags', 'main', 'only1', 'snippet']
     */
    public static function meetsOf($code, $name, array $bills)
    {
        // 主角：議事網關係文書裡有這部法議案的會議（院會、委員會、聯席會議都有），一次查詢直接拿會議資料，
        // 不必先抓議案流程再反查上百個會議代碼
        $meets = [];
        $via = [];
        $qs = LYAPI::qs(['議事網資料.關係文書.議案.法律編號' => $code, 'limit' => 100, 'sort' => '日期>', 'output_fields' => MeetHelper::LIST_FIELDS]);
        $res = LYAPI::apiQuery("/meets?{$qs}", "議案關聯「{$name}」的會議");
        foreach ($res->meets ?? [] as $m) {
            $meets[$m->會議代碼] = MeetHelper::decorate($m);
            $via[$m->會議代碼] = '議案';
        }
        // 議案流程裡有、但關係文書沒列的會議（例：三讀那一場）也算主角
        foreach ($bills as $b) {
            foreach ((array)($b->議案流程 ?? []) as $f) {
                $id = $f->會議代碼 ?? '';
                if ($id !== '' and !isset($via[$id])) {
                    $via[$id] = '議案';
                }
            }
        }
        // 事由提到
        $qs = LYAPI::qs(['q' => '"' . $name . '"', 'limit' => 100, 'sort' => '日期>', 'output_fields' => MeetHelper::LIST_FIELDS]);
        $subjects = [];
        try {
            $res = LYAPI::apiQuery("/meets?{$qs}", "事由提到「{$name}」的會議");
            foreach ($res->meets ?? [] as $m) {
                $subjects[$m->會議代碼] = ((array)($m->會議資料 ?? []))[0]->會議事由 ?? '';
                if (!isset($via[$m->會議代碼])) {
                    $via[$m->會議代碼] = '事由';
                    $meets[$m->會議代碼] = MeetHelper::decorate($m);
                }
            }
        } catch (Exception $e) {
        }
        // 發言提到（只是提到）
        $qs = LYAPI::qs(['q' => '"' . $name . '"', '區塊種類' => '發言', 'limit' => 0, 'agg' => '會議代碼']);
        $res = LYAPI::speeches($qs, "發言提到「{$name}」的會議");
        foreach ($res->aggs[0]->buckets ?? [] as $b) {
            foreach ((array)$b->會議代碼 as $id) {
                if (!isset($via[$id])) {
                    $via[$id] = '發言';
                }
            }
        }
        // 還沒有會議資料的（流程裡的會議、發言提到的）才另外抓；全部補上狀態與段落統計
        $missing = array_values(array_filter(array_keys($via), fn($id) => !isset($meets[$id])));
        $meets = $meets + MeetHelper::getMeets(array_slice($missing, 0, 100));
        MeetHelper::attachStatus(array_filter($meets, fn($m) => empty($m->status_attached)));
        $rows = [];
        foreach ($meets as $id => $m) {
            $row = ['meet' => $m, 'via' => $via[$id], 'tags' => [], 'main' => false, 'only1' => false, 'snippet' => ''];
            if ($via[$id] === '議案') {
                $row['tags'] = LawHelper::stageTags($id, $bills);
                $row['main'] = true;
                $row['only1'] = ($row['tags'] === ['院會一讀']);
                $titles = [];
                foreach ($bills as $b) {
                    foreach ((array)($b->議案流程 ?? []) as $f) {
                        if (($f->會議代碼 ?? '') === $id) {
                            $titles[$b->議案名稱] = true;
                        }
                    }
                }
                $row['snippet'] = implode('；', array_slice(array_keys($titles), 0, 2));
            } elseif ($via[$id] === '事由') {
                $row['main'] = (bool)preg_match('#審查|研商|審議|公聽會#u', $subjects[$id] ?? '');
                $row['snippet'] = SiteHelper::snippet($subjects[$id] ?? '', $name, 40);
            }
            $rows[] = $row;
        }
        usort($rows, fn($a, $b) => strcmp($b['meet']->date, $a['meet']->date));
        return $rows;
    }
}
