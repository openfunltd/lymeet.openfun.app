<?php
/**
 * 段落目錄的一項（共用元件 TocItem）與段標題的呈現規則：
 * 段的種類看資料的 unit_kind（段種類），不從標題猜；過場段拿掉括號前綴；案段第一行案號＋標籤、第二行案名；人段人名＋黨籍。
 */
class SectionHelper
{
    const CASE_RE = '#第[（(]?[〇○零一二三四五六七八九十百\d０-９]+(?:[-－之][\d一二三四五六七八九十]+)?[）)]?案#u';

    /** 段名去掉編號前綴與〔標籤〕 */
    public static function name($label)
    {
        $b = preg_replace('#^[\d\-、]+｜#u', '', trim((string)$label));
        if (preg_match('#^〔[^〕]*〕$#u', $b)) {
            return $b;
        }
        return trim(preg_replace('#〔[^〕]*〕#u', '', $b));
    }

    /** 過場段：拿掉「（程序／表決）」等括號前綴，只顯示主席說的第一句 */
    public static function procName($n)
    {
        $t = trim(preg_replace('#^（[^）]*）#u', '', $n));
        $t = preg_replace('#^開場／未宣告案次的部分$#u', '開場', $t);
        return $t !== '' ? $t : $n;
    }

    /** 畫面上的段標籤（只顯示 Glossary::FLAGS 裡的；過場段不顯示） */
    public static function flags(array $item)
    {
        if ($item['kind'] === 'procedure') {
            return '';
        }
        $tags = $item['tags'];
        $n = self::name($item['label']);
        if (!empty($item['vote']) or preg_match('#^表決#u', $n)) {
            $tags[] = '表決';
        }
        $out = '';
        foreach (Glossary::FLAGS as $f) {
            if (in_array($f, $tags)) {
                $out .= '<span class="flag">' . SiteHelper::e($f) . '</span>';
            }
        }
        return $out;
    }

    /** 段標題顯示用的文字（h3） */
    public static function show(array $item)
    {
        $n = self::name($item['label']);
        if ($item['kind'] === 'procedure') {
            return self::procName($n);
        }
        if ($item['kind'] === 'person') {
            [$disp] = SiteHelper::parseSpeaker(preg_split('#[ 　]#u', $n)[0]);
            if (preg_match('#^(.+?)聯合#u', $n, $j)) {
                return implode('、', array_map(fn($x) => preg_replace('#委員#u', '', $x), explode('及', $j[1]))) . '（聯合質詢）';
            }
            $rest = trim(mb_substr($n, mb_strlen(preg_split('#[ 　]#u', $n)[0])));
            return ($disp ?: $n) . ($rest !== '' ? '　' . $rest : '');
        }
        if ($item['kind'] === 'attachment') {
            return '附件：' . $n;
        }
        if ($item['kind'] === 'case') {
            // 表決段：標籤已經標了「表決」，標題不重複
            return preg_replace('#^表決：#u', '', $n);
        }
        return $n;
    }

    /** 段落目錄的一項（不含時間與色條） */
    public static function tocItem(array $item, array $parties = [])
    {
        $k = $item['kind'];
        $n = self::name($item['label']);
        $cont = in_array('續', $item['tags']) ? '<span class="tcont">（續）</span>' : '';
        if ($k === 'procedure') {
            return '<span class="tproc">' . SiteHelper::e(self::procName($n)) . $cont . '</span>';
        }
        if ($k === 'attachment') {
            return '<span class="tproc">附件：' . SiteHelper::e($n) . '</span>';
        }
        if ($k === 'group' or $k === 'stage') {
            return '<span class="tgroup">' . SiteHelper::e($n) . '</span><span class="tflags">' . self::flags($item) . '</span>';
        }
        if ($k === 'case') {
            $fl = self::flags($item);
            $head = function($lab) use ($fl) {
                return ($lab !== '' or $fl !== '') ? '<span class="thead">' . ($lab !== '' ? '<b>' . SiteHelper::e($lab) . '</b>' : '') . $fl . '</span>' : '';
            };
            if (preg_match('#^(併案|彙整)：#u', $n, $pre)) {
                return '<span class="tcase">' . $head($pre[1]) . '<span>' . SiteHelper::e(mb_substr($n, mb_strlen($pre[0]))) . '</span></span>';
            }
            if (preg_match('#^第[^、\s]+案(、第[^、\s]+案)+$#u', $n)) {
                return '<span class="tcase">' . $head($n) . '</span>';
            }
            if (preg_match('#^表決：(.*)$#u', $n, $v)) {
                // 沒有案號的表決段：第一行只有標籤
                return '<span class="tcase">' . $head('') . '<span>' . SiteHelper::e($v[1]) . '</span></span>';
            }
            $no = preg_match(self::CASE_RE, $n, $mm) ? $mm[0] : '';
            $rest = $no !== '' ? trim(str_replace($no, '', $n)) : $n;
            return '<span class="tcase">' . $head($no) . '<span>' . SiteHelper::e($rest) . '</span></span>';
        }
        // 人段：人名＋黨籍；聯合質詢列出全部委員
        if (preg_match('#^(.+?)聯合#u', $n, $j)) {
            $names = array_map(fn($x) => preg_replace('#委員#u', '', $x), explode('及', $j[1]));
            return '<span class="tperson">' . SiteHelper::e(implode('、', $names)) . '<span class="tparty">聯合質詢</span></span>';
        }
        $first = preg_split('#[ 　]#u', $n)[0];
        [$disp, $role, $person] = SiteHelper::parseSpeaker($first);
        $party = ($person and isset($parties[$person])) ? '<span class="tparty">' . SiteHelper::e(SiteHelper::partyShort($parties[$person])) . '</span>' : '';
        return '<span class="tperson">' . SiteHelper::e($disp ?: $n) . $party . $cont . '</span>';
    }

    /** 這一段的主角人名（人段），給「只看一位發言者」用 */
    public static function personOf(array $item)
    {
        if ($item['kind'] !== 'person') {
            return null;
        }
        $first = preg_split('#[ 　]#u', self::name($item['label']))[0];
        return SiteHelper::parseSpeaker($first)[2];
    }
}
