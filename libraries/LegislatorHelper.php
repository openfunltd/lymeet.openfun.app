<?php
/**
 * 立委清單與個人頁。
 */
class LegislatorHelper
{
    const PARTY_ORDER = ['民主進步黨', '中國國民黨', '台灣民眾黨', '無黨籍'];

    /** 某屆全部立委，依黨籍分組（在任的在前） */
    public static function byParty($term)
    {
        $qs = LYAPI::qs([
            '屆' => $term,
            'limit' => 300,
            'output_fields' => ['委員姓名', '黨籍', '黨團', '選區名稱', '照片位址', '是否離職', '離職日期', '委員會'],
        ]);
        $res = LYAPI::apiQuery("/legislators?{$qs}", "第 {$term} 屆立委清單", 86400);
        $groups = [];
        foreach ($res->legislators ?? [] as $l) {
            $party = $l->黨籍 ?: '無黨籍';
            $groups[$party][] = $l;
        }
        uksort($groups, function($a, $b) use ($groups) {
            $ia = array_search($a, self::PARTY_ORDER);
            $ib = array_search($b, self::PARTY_ORDER);
            if ($ia !== false and $ib !== false) {
                return $ia <=> $ib;
            }
            if ($ia !== false) return -1;
            if ($ib !== false) return 1;
            return count($groups[$b]) <=> count($groups[$a]);
        });
        foreach ($groups as &$list) {
            usort($list, function($a, $b) {
                $la = ($a->是否離職 ?? '') === '是' ? 1 : 0;
                $lb = ($b->是否離職 ?? '') === '是' ? 1 : 0;
                if ($la !== $lb) return $la <=> $lb;
                return strcmp($a->委員姓名, $b->委員姓名);
            });
        }
        return $groups;
    }

    public static function get($term, $name)
    {
        $res = LYAPI::apiQuery(LYAPI::path(['legislator', $term, $name]), "立委 {$name}", 86400);
        $l = $res->data ?? $res;
        if (empty($l->委員姓名)) {
            throw new LYAPI_NotFound("legislator {$name}");
        }
        return $l;
    }

    /** 最近的發言（可加關鍵字），依會議分組 */
    public static function speeches($term, $name, $q = '', $page = 1)
    {
        $qs = LYAPI::qs([
            '屆' => $term,
            '發言者' => $name,
            '區塊種類' => '發言',
            'q' => $q !== '' ? '"' . trim($q, '"') . '"' : null,
            'limit' => 50,
            'page' => max(1, intval($page)),
            'sort' => '日期>',
            'output_fields' => ['發言編號', '文件編號', '會議代碼', '會議種類', '日期', '發言者', '發言者身分', '內文', '發言時間', '影片', '順序'],
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
    public static function meetCount($term, $name)
    {
        $qs = LYAPI::qs(['屆' => $term, '發言者' => $name, '區塊種類' => '發言', 'limit' => 0, 'agg' => '會議代碼']);
        $res = LYAPI::speeches($qs, "{$name} 發言過的會議數", 3600);
        if (is_null($res)) {
            return [null, null];
        }
        $n = count($res->aggs[0]->buckets ?? []);
        return [$n >= 100 ? '100+' : $n, intval($res->total ?? 0)];
    }
}
