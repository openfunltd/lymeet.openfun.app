<?php
/**
 * 部會：用 LYAPI meet 的「機關」欄位（匯入端依中央機關名稱清單從會議事由比對出來的）。
 * 先上一版：只算被找來開會（會議事由提到），被發言提到不算（2026-11-23 Claire）。
 */
class DeptHelper
{
    /** 各機關的會議場數（最近兩年），依場數排序：[['name', 'count']] */
    public static function counts()
    {
        $today = SiteHelper::today();
        $from = SiteHelper::addDays($today, -730);
        $qs = LYAPI::qs(['日期:' => "{$from},{$today}", 'limit' => 0, 'agg' => '機關', 'agg_size' => 200]);
        $res = LYAPI::apiQuery("/meets?{$qs}", '各機關的會議場數', 86400);
        $out = [];
        foreach ($res->aggs[0]->buckets ?? [] as $b) {
            $name = is_array($b->機關) ? ($b->機關[0] ?? '') : $b->機關;
            if ($name === '') {
                continue;
            }
            $out[] = ['name' => $name, 'count' => intval($b->count)];
        }
        usort($out, fn($a, $b) => $b['count'] <=> $a['count']);
        return $out;
    }

    /** 機關名稱清單（有會議的） */
    public static function names()
    {
        static $names = null;
        if (is_null($names)) {
            $names = array_map(fn($d) => $d['name'], self::counts());
        }
        return $names;
    }

    public static function isDept($name)
    {
        return in_array($name, self::names());
    }

    /** 事由提到這個機關的會議，新的在前 */
    public static function meets($name)
    {
        $qs = LYAPI::qs(['機關' => $name, 'limit' => 200, 'sort' => '日期>', 'output_fields' => MeetHelper::LIST_FIELDS]);
        $res = LYAPI::apiQuery("/meets?{$qs}", "事由提到{$name}的會議");
        $meets = [];
        foreach ($res->meets ?? [] as $m) {
            $o = MeetHelper::decorate($m);
            $o->topic = self::topic($o, $name);
            $meets[] = $o;
        }
        return $meets;
    }

    /** 題目取自事由中機關名稱後面的「」 */
    public static function topic($m, $name)
    {
        $c = $m->subject;
        $i = mb_strpos($c, $name);
        if ($i === false) {
            return null;
        }
        if (preg_match('#「([^」]{4,60})」#u', mb_substr($c, $i), $t)) {
            return $t[1];
        }
        return null;
    }

    /** 搜尋用：名稱符合的機關 */
    public static function search($q)
    {
        $out = [];
        foreach (self::names() as $name) {
            if (mb_strpos($name, $q) !== false or (mb_strlen($q) >= 3 and mb_strpos($q, $name) !== false)) {
                $out[] = $name;
            }
        }
        return $out;
    }
}
