<?php
/**
 * 部會：和會議沒有現成的對應欄位，靠會議事由的文字比對。
 * 先上一版：只算被找來開會（會議事由邀請列席），被提到不算（2026-11-23 Claire）。
 */
class DeptHelper
{
    /** 中央部會候選清單；只保留在會議資料中真的出現過的 */
    const CANDIDATES = [
        '內政部', '外交部', '國防部', '財政部', '教育部', '法務部', '經濟部', '交通部', '勞動部',
        '農業部', '衛生福利部', '環境部', '文化部', '數位發展部', '運動部', '國家發展委員會',
        '國家科學及技術委員會', '大陸委員會', '金融監督管理委員會', '海洋委員會', '僑務委員會',
        '國軍退除役官兵輔導委員會', '原住民族委員會', '客家委員會', '公共工程委員會',
        '行政院主計總處', '行政院人事行政總處', '中央銀行', '國立故宮博物院', '中央選舉委員會',
        '公平交易委員會', '國家通訊傳播委員會', '核能安全委員會', '行政院', '考試院', '監察院',
        '司法院', '內政部警政署', '內政部移民署', '內政部消防署', '海洋委員會海巡署', '財政部關務署',
    ];

    /** 每個部會的會議事由提到的場數（只看最近兩年），依次數排序 */
    public static function counts()
    {
        $today = SiteHelper::today();
        $from = SiteHelper::addDays($today, -730);
        $out = [];
        foreach (self::CANDIDATES as $name) {
            $qs = LYAPI::qs(['q' => '"' . $name . '"', '日期:' => "{$from},{$today}", 'limit' => 0]);
            try {
                $res = LYAPI::apiQuery("/meets?{$qs}", "會議事由提到{$name}的場數", 86400);
            } catch (Exception $e) {
                continue;
            }
            $n = intval($res->total ?? 0);
            if ($n > 0) {
                $out[] = ['name' => $name, 'count' => $n];
            }
        }
        usort($out, fn($a, $b) => $b['count'] <=> $a['count']);
        return $out;
    }

    public static function isDept($name)
    {
        return in_array($name, self::CANDIDATES);
    }

    /** 事由邀請這個部會列席的會議，新的在前 */
    public static function meets($name)
    {
        $qs = LYAPI::qs(['q' => '"' . $name . '"', 'limit' => 200, 'sort' => '日期>', 'output_fields' => MeetHelper::MEET_FIELDS]);
        $res = LYAPI::apiQuery("/meets?{$qs}", "事由提到{$name}的會議", 3600);
        $meets = [];
        foreach ($res->meets ?? [] as $m) {
            $o = MeetHelper::decorate($m);
            $text = $o->subject . ' ' . $o->title;
            if (mb_strpos($text, $name) === false) {
                continue;
            }
            // 只算被找來開會：事由寫「邀請…列席」或「請…列席」
            if (!preg_match('#(邀請|列席|報告)#u', $text)) {
                continue;
            }
            $o->topic = self::topic($o, $name);
            $meets[] = $o;
        }
        return $meets;
    }

    /** 題目取自事由中部會名稱後面的「」 */
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

    /** 搜尋用：名稱符合的部會 */
    public static function search($q)
    {
        $out = [];
        foreach (self::CANDIDATES as $name) {
            if (mb_strpos($name, $q) !== false or (mb_strlen($q) >= 3 and mb_strpos($q, $name) !== false)) {
                $out[] = $name;
            }
        }
        return $out;
    }
}
