<?php
/**
 * 全站共用的呈現小工具：日期、黨籍標籤、狀態標籤、一筆會議的各層、跑馬燈資料。
 * 規則照資訊架構 v2 第 5 節「共用元件」。
 */
class SiteHelper
{
    const WD = ['日', '一', '二', '三', '四', '五', '六'];

    const PARTY_SHORT = ['中國國民黨' => '國民黨', '民主進步黨' => '民進黨', '台灣民眾黨' => '民眾黨', '台灣團結聯盟' => '台聯'];
    const PARTY_LOGO = ['民主進步黨', '中國國民黨', '台灣民眾黨', '親民黨', '台灣團結聯盟', '民國黨', '無黨團結聯盟', '時代力量'];
    const PARTY_COLOR = ['民進黨' => '#1b9431', '國民黨' => '#000095', '民眾黨' => '#28c8c8', '無黨籍' => '#8a8a8a'];

    public static function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    public static function today()
    {
        return date('Y-m-d');
    }

    public static function dow($d)
    {
        return self::WD[intval(date('w', strtotime($d)))];
    }

    /** 2026-07-17 → 07/17（五） */
    public static function fmtDate($d)
    {
        if (!$d) {
            return '';
        }
        return substr($d, 5, 2) . '/' . substr($d, 8, 2) . '（' . self::dow($d) . '）';
    }

    /** 2026-07-17 → 2026/07/17（五） */
    public static function fmtFull($d)
    {
        if (!$d) {
            return '';
        }
        return str_replace('-', '/', $d) . '（' . self::dow($d) . '）';
    }

    /** 2026-07-17 → 07/17 */
    public static function mmdd($d)
    {
        return $d ? substr($d, 5, 2) . '/' . substr($d, 8, 2) : '';
    }

    public static function addDays($d, $n)
    {
        return date('Y-m-d', strtotime("{$d} {$n} days"));
    }

    /** ISO 時間 → hh:mm:ss */
    public static function hms($iso)
    {
        return $iso ? substr($iso, 11, 8) : '';
    }

    /** 秒數 → h:mm:ss */
    public static function fmtSec($seconds)
    {
        $seconds = max(0, intval($seconds));
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public static function partyShort($full)
    {
        $full = trim((string)$full) ?: '無黨籍';
        return self::PARTY_SHORT[$full] ?? $full;
    }

    /** 黨籍標籤：政黨 logo＋黨名；沒有 logo 的用灰點 */
    public static function partyChip($full)
    {
        $full = trim((string)$full) ?: '無黨籍';
        $short = self::partyShort($full);
        if (in_array($full, self::PARTY_LOGO)) {
            $icon = sprintf('<img src="/static/img/party/%s.svg" alt="" width="16" height="16">', rawurlencode($full));
        } else {
            $icon = sprintf('<i style="background:%s"></i>', self::PARTY_COLOR[$short] ?? '#8a8a8a');
        }
        return sprintf('<span class="pchip" title="%s">%s%s</span>', self::e($full), $icon, self::e($short));
    }

    /** 資料狀態標籤（一筆會議只有這一個標籤） */
    public static function statusTag($m)
    {
        $cls = ['gazette' => 'gz', 'ai' => 'ai', 'processing' => 'live', 'scheduled' => '', 'novideo' => 'novideo'][$m->status] ?? '';
        return sprintf('<span class="status %s">%s</span>', $cls, self::e($m->status_label));
    }

    /** 會議種類・委員會（一般文字） */
    public static function kind($m)
    {
        $unit = $m->unit ?? '';
        $extra = ($unit !== '' and strpos($unit, $m->type) === false) ? '・' . self::e($unit) : '';
        return '<span class="kind">' . Glossary::term($m->type) . $extra . '</span>';
    }

    /** 列表左側的日期 */
    public static function dateBlock($d)
    {
        if (!$d) {
            return '';
        }
        return sprintf('<span class="dnum">%s</span><span class="dwk">星期%s</span>', self::mmdd($d), self::dow($d));
    }

    /** 院會：三讀 N 案、表決 N 次；已排定的院會：排入討論事項 */
    public static function marksOf($m)
    {
        if ($m->type !== '院會') {
            return [];
        }
        $out = [];
        if (!empty($m->marks['三讀'])) {
            $out[] = "三讀 {$m->marks['三讀']} 案";
        }
        if (!empty($m->marks['表決'])) {
            $out[] = "表決 {$m->marks['表決']} 次";
        }
        if (!$out and $m->status === 'scheduled' and strpos($m->subject ?? '', '討論事項') !== false) {
            $out[] = '排入討論事項';
        }
        return $out;
    }

    public static function markKeys($m)
    {
        $keys = [];
        foreach (self::marksOf($m) as $t) {
            if (strpos($t, '三讀') === 0) {
                $keys[] = '三讀';
            } elseif (strpos($t, '表決') === 0) {
                $keys[] = '表決';
            }
        }
        return $keys;
    }

    /** 這天的議程：委員會在做什麼（審預算）、院會三讀表決、黨團協商誰主持 */
    public static function agendaLine($m)
    {
        $t = '';
        if (in_array($m->type, ['委員會', '聯席會議', '全院委員會'])) {
            $t = implode('、', $m->kinds ?? []);
        } elseif ($m->type === '院會') {
            $t = implode('、', self::marksOf($m));
        }
        return $t !== '' ? '<div class="agenda">這天的議程：' . self::e($t) . '</div>' : '';
    }

    /** 關鍵字標亮 */
    public static function hl($text, $q)
    {
        $s = self::e($text);
        if ($q === '' or is_null($q)) {
            return $s;
        }
        return str_replace(self::e($q), '<mark>' . self::e($q) . '</mark>', $s);
    }

    /** 關鍵字前後各 n 字 */
    public static function snippet($text, $q = '', $n = 60)
    {
        $text = trim(preg_replace('#\s+#u', ' ', (string)$text));
        $i = ($q !== '' and !is_null($q)) ? mb_strpos($text, $q) : false;
        if ($i === false) {
            return mb_substr($text, 0, $n * 2) . (mb_strlen($text) > $n * 2 ? '…' : '');
        }
        $a = max(0, $i - $n);
        $b = min(mb_strlen($text), $i + mb_strlen($q) + $n);
        return ($a > 0 ? '…' : '') . mb_substr($text, $a, $b - $a) . ($b < mb_strlen($text) ? '…' : '');
    }

    /**
     * 公報版的發言者字串 → [顯示名, 角色(立委／官員／主席／紀錄／其他), 人名]
     * 例：「林委員德福」→ ['林德福 委員', '立委', '林德福']
     */
    public static function parseSpeaker($sp)
    {
        $sp = trim((string)$sp);
        if ($sp === '' or $sp === '〔結構〕') {
            return ['', '紀錄', null];
        }
        if (strpos($sp, '主席') === 0) {
            return ['主席', '主席', null];
        }
        if (preg_match('#^(.)委員(.{1,3})$#u', $sp, $m)) {
            return [$m[1] . $m[2] . ' 委員', '立委', $m[1] . $m[2]];
        }
        if (preg_match('#^(.{2,4})委員$#u', $sp, $m)) {
            return [$m[1] . ' 委員', '立委', $m[1]];
        }
        if (preg_match('#^(.)(' . TranscriptHelper::OFFICIAL_TITLES . ')(.{1,3})$#u', $sp, $m)) {
            return [$m[1] . $m[3] . ' ' . $m[2], '官員', $m[1] . $m[3]];
        }
        return [$sp, '其他', null];
    }
}
