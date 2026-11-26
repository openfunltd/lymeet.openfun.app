<?php
/**
 * 搜尋：以會議為單位，依關鍵字出現在會議事由、發言、會議文件分三個分頁；上方另列相關法律、部會、立委。
 * 關鍵字一律當片語搜（加引號），否則 ES 會拆成單字。
 */
class SearchHelper
{
    const PAGE_SIZE = 50;
    const TABS = ['reason' => '會議事由', 'speech' => '發言', 'doc' => '會議文件'];

    /**
     * @return object { q, orig, relaxed, laws, depts, people, counts[tab], tab, groups(當前分頁的會議列), page, total_page, similar }
     */
    public static function search($q, $tab = '', $page = 1)
    {
        $q = trim(preg_replace('#^["「『]|["」』]$#u', '', trim((string)$q)));
        $r = self::raw($q, $tab, $page);
        $r->orig = $q;
        $r->relaxed = null;
        if ($q !== '' and !self::total($r) and mb_strlen($q) > 2) {
            // 找不到不是死路：自動改找較短的詞
            $cands = [];
            $gen = preg_replace('#(專法|修法|草案|條例|修正|法案|法)$#u', '', $q);
            if ($gen !== '' and $gen !== $q and mb_strlen($gen) >= 2) {
                $cands[] = $gen;
            }
            for ($len = mb_strlen($q) - 1; $len >= max(3, intdiv(mb_strlen($q) + 1, 2)); $len --) {
                $cands[] = mb_substr($q, 0, $len);
            }
            foreach (array_slice(array_unique($cands), 0, 3) as $sub) {
                $t = self::raw($sub, $tab, 1);
                if (self::total($t)) {
                    $t->relaxed = $sub;
                    $t->orig = $q;
                    $r = $t;
                    break;
                }
            }
        }
        if (!self::total($r) and $q !== '') {
            // 相近的關鍵字：名稱有一半以上字相同的法律
            $r->similar = [];
            foreach (LawHelper::search(mb_substr($q, 0, 2), 20) as $l) {
                $a = preg_split('//u', $q, -1, PREG_SPLIT_NO_EMPTY);
                $b = preg_split('//u', $l['name'], -1, PREG_SPLIT_NO_EMPTY);
                $score = count(array_intersect(array_unique($a), array_unique($b))) / max(count(array_unique($a)), 1);
                if ($score >= 0.5) {
                    $r->similar[] = $l;
                }
            }
            $r->similar = array_slice($r->similar, 0, 4);
        }
        return $r;
    }

    public static function total($r)
    {
        return count($r->laws) + count($r->depts) + count($r->people) + array_sum($r->counts);
    }

    protected static function raw($q, $want, $page)
    {
        $r = new StdClass;
        $r->q = $q;
        $r->laws = [];
        $r->depts = [];
        $r->people = [];
        $r->counts = ['reason' => 0, 'speech' => 0, 'doc' => 0];
        $r->groups = [];
        $r->tab = 'reason';
        $r->page = 1;
        $r->total_page = 1;
        $r->similar = [];
        $r->unavailable = false;
        if ($q === '') {
            return $r;
        }
        $r->laws = LawHelper::search($q, 8);
        foreach (DeptHelper::search($q) as $name) {
            $r->depts[] = $name;
        }
        $r->people = LegislatorHelper::search($q);

        // 會議事由、會議文件：同一次 /meets 查詢，依 highlight 落在哪個欄位分流
        $reason = [];
        $docs = [];
        $qs = LYAPI::qs(['q' => '"' . $q . '"', 'limit' => 200, 'sort' => '日期>', 'output_fields' => array_merge(MeetHelper::MEET_FIELDS, ['議事網資料'])]);
        $res = LYAPI::apiQuery("/meets?{$qs}", "搜尋會議事由與會議文件「{$q}」");
        foreach ($res->meets ?? [] as $m) {
            $hit_reason = false;
            $hit_doc = false;
            $doc_titles = [];
            foreach ((array)$m as $k => $v) {
                if (substr($k, -10) !== ':highlight') {
                    continue;
                }
                if (strpos($k, '附件') !== false or strpos($k, 'attach') !== false) {
                    $hit_doc = true;
                    foreach ((array)$v as $frag) {
                        $doc_titles[] = $frag;
                    }
                } else {
                    $hit_reason = true;
                }
            }
            $o = MeetHelper::decorate($m);
            if (!$hit_reason and !$hit_doc) {
                // 沒有 highlight 欄位時自己比對
                $hit_reason = (mb_strpos($o->title . $o->subject, $q) !== false);
                foreach ($o->docs as $d) {
                    if (mb_strpos($d['title'], $q) !== false) {
                        $hit_doc = true;
                    }
                }
            }
            if ($hit_reason) {
                $reason[$o->id] = $o;
            }
            if ($hit_doc) {
                $docs[$o->id] = $o;
            }
        }
        $r->counts['reason'] = count($reason);
        $r->counts['doc'] = count($docs);
        $r->more_meets = intval($res->total ?? 0) > 200;

        // 發言：場數用 agg（最多 100 個 bucket）
        $qs = LYAPI::qs(['q' => '"' . $q . '"', '區塊種類' => '發言', 'limit' => 0, 'agg' => '會議代碼']);
        $agg = LYAPI::speeches($qs, "發言提到「{$q}」的會議數");
        $r->unavailable = is_null($agg);
        $speech_meets = count($agg->aggs[0]->buckets ?? []);
        $r->counts['speech'] = $speech_meets;
        $r->speech_more = $speech_meets >= 100;

        // 預設停在會議事由；沒有結果時停在第一個有結果的分頁
        $tab = (isset(self::TABS[$want]) and $r->counts[$want]) ? $want : 'reason';
        if (!$r->counts[$tab]) {
            foreach (array_keys(self::TABS) as $k) {
                if ($r->counts[$k]) {
                    $tab = $k;
                    break;
                }
            }
        }
        $r->tab = $tab;
        $r->page = max(1, intval($page));
        if ($tab === 'speech' and $speech_meets) {
            $qs = LYAPI::qs([
                'q' => '"' . $q . '"',
                '區塊種類' => '發言',
                'limit' => self::PAGE_SIZE,
                'page' => $r->page,
                'sort' => '日期>',
                'output_fields' => ['發言編號', '文件編號', '會議代碼', '會議種類', '日期', '發言者', '發言者身分', '發言者職稱', '內文', '發言時間', '影片', '段落', '質詢輪次委員'],
            ]);
            $res = LYAPI::speeches($qs, "搜尋發言「{$q}」第 {$r->page} 頁");
            $r->total_page = intval($res->total_page ?? 1);
            $r->groups = self::groupByMeet((array)($res->speeches ?? []));
        } elseif ($tab === 'reason') {
            $r->groups = self::meetGroups($reason, $q, 'reason');
        } elseif ($tab === 'doc') {
            $r->groups = self::meetGroups($docs, $q, 'doc');
        }
        return $r;
    }

    /** 會議事由／會議文件分頁：一場會議一筆，附找到的那句 */
    protected static function meetGroups(array $meets, $q, $tab)
    {
        $list = array_values($meets);
        usort($list, fn($a, $b) => strcmp($b->date, $a->date));
        MeetHelper::attachSectionsByIds(array_slice($list, 0, 60));
        $groups = [];
        foreach ($list as $m) {
            $g = ['meet_id' => $m->id, 'meet' => $m, 'hits' => [], 'docs' => [], 'reason' => ''];
            if ($tab === 'reason') {
                $g['reason'] = SiteHelper::snippet($m->subject ?: $m->title, $q, 40);
            } else {
                foreach ($m->docs as $d) {
                    if (mb_strpos($d['title'], $q) !== false) {
                        $g['docs'][] = $d;
                    }
                }
            }
            $groups[] = $g;
        }
        return $groups;
    }

    /** 依會議分組（保持原本順序），並補上會議資料 */
    public static function groupByMeet(array $speeches)
    {
        $groups = [];
        foreach ($speeches as $s) {
            // 聯席會議的發言 會議代碼 是陣列
            $mid = is_array($s->會議代碼) ? ($s->會議代碼[0] ?? '') : $s->會議代碼;
            if (!isset($groups[$mid])) {
                $groups[$mid] = ['meet_id' => $mid, 'meet' => null, 'hits' => [], 'docs' => [], 'reason' => ''];
            }
            $groups[$mid]['hits'][] = $s;
        }
        $meets = MeetHelper::getMeets(array_keys($groups));
        foreach ($groups as $mid => &$g) {
            $g['meet'] = $meets[$mid] ?? null;
        }
        unset($g);
        return array_values($groups);
    }

    /** highlight 片段：先 escape 再把 <em> 還原成 <mark> */
    public static function highlightHtml($speech, $max = 2)
    {
        $frags = (array)($speech->{'內文:highlight'} ?? []);
        if (!$frags) {
            $text = (string)($speech->內文 ?? '');
            return SiteHelper::e(mb_strlen($text) > 120 ? mb_substr($text, 0, 118) . '…' : $text);
        }
        $out = [];
        foreach (array_slice($frags, 0, $max) as $f) {
            $f = SiteHelper::e($f);
            $out[] = '…' . str_replace(['&lt;em&gt;', '&lt;/em&gt;'], ['<mark>', '</mark>'], $f) . '…';
        }
        return implode(' ', $out);
    }

    /** 發言者顯示名（公報寫法 林委員德福 → 林德福 委員） */
    public static function whoLabel($speech)
    {
        $sp = trim((string)($speech->發言者 ?? ''));
        $role = $speech->發言者身分 ?? '';
        $title = $speech->發言者職稱 ?? '';
        if ($sp === '') {
            return '紀錄文字';
        }
        if ($role === '委員') {
            return $sp . ' 委員';
        }
        if ($title) {
            return $sp . ' ' . $title;
        }
        return $sp;
    }
}
