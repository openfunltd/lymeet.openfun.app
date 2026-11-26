<?php
/**
 * AI 轉錄稿：把 LYAPI ivod item 的 transcript（whisperx 句子＋pyannote 語者區段）合併成
 * 「一段一位發言者、底下一句一句有時間」的結構。
 *
 * 語者名字來源：
 *   1. pyannote 區段本身的 speaker_name（lyapi 的語者辨識，Full 影片只有一部分有）
 *   2. 同場會議的 Clip（委員質詢片段）：Clip 的起訖時間內講最多話的那個語者群，如果還沒有名字就標成該委員
 * 都沒有的標「發言者待確認」。
 */
class TranscriptHelper
{
    const OFFICIAL_TITLES = '部長|次長|署長|局長|司長|處長|院長|秘書長|主任委員|主委|主計長|總裁|總長|司令|執行長|董事長|總經理|主任|組長|科長|參事|顧問|委員長|人事長|審計長|參謀長|代表|教授|理事長|律師|醫師|檢察長|廳長|館長|校長';

    /**
     * @param object $ivod  /ivod/{id} 的 item（要有 transcript、開始時間、影片長度）
     * @param array  $clips 同場會議的 Clip 影片（list item，要有 委員名稱、開始時間、結束時間）
     * @return array ['blocks' => [...], 'toc' => [...], 'sentences' => int, 'named' => int]
     */
    public static function build($ivod, array $clips)
    {
        $wx = (array)($ivod->transcript->whisperx ?? []);
        $py = (array)($ivod->transcript->pyannote ?? []);
        $full_start = strtotime($ivod->開始時間 ?? '') ?: null;
        $duration = MeetHelper::toSeconds($ivod->影片長度 ?? 0);
        usort($wx, fn($a, $b) => $a->start <=> $b->start);
        usort($py, fn($a, $b) => $a->start <=> $b->start);

        // 1. 語者群 → 名字
        $names = [];
        foreach ($py as $seg) {
            if (!empty($seg->speaker_name) and empty($names[$seg->speaker])) {
                $names[$seg->speaker] = [self::cleanName($seg->speaker_name), self::roleOf($seg->speaker_name, !empty($seg->is_chair))];
            }
        }

        // 2. Clip 疊上去：Clip 時間內講最多的語者群 = 那位委員
        $toc = [];
        if ($full_start) {
            foreach ($clips as $clip) {
                $cs = strtotime($clip->開始時間 ?? '') - $full_start;
                $ce = strtotime($clip->結束時間 ?? '') - $full_start;
                if ($ce <= 0 or $cs >= $duration or $ce <= $cs) {
                    continue;
                }
                $name = str_replace(' ', '', $clip->委員名稱 ?? '');
                if ($name === '' or $name === '完整會議') {
                    continue;
                }
                $toc[] = ['label' => $name . ' 質詢', 'offset' => max(0, $cs), 'anchor' => 'ai-' . intval($ivod->IVOD_ID) . '-' . max(0, intval($cs))];
                $tally = [];
                foreach ($py as $seg) {
                    if ($seg->start >= $ce) {
                        break;
                    }
                    $o = min($seg->end, $ce) - max($seg->start, $cs);
                    if ($o > 0) {
                        $tally[$seg->speaker] = ($tally[$seg->speaker] ?? 0) + $o;
                    }
                }
                if (!$tally) {
                    continue;
                }
                arsort($tally);
                $top = array_key_first($tally);
                if ($tally[$top] < 0.3 * ($ce - $cs)) {
                    continue;
                }
                if (empty($names[$top])) {
                    $names[$top] = [$name, '委員'];
                }
            }
        }
        usort($toc, fn($a, $b) => $a['offset'] <=> $b['offset']);

        // 3. 每一句配語者（重疊最多的 pyannote 區段）
        $sentences = [];
        $j = 0;
        $n = count($py);
        foreach ($wx as $s) {
            while ($j < $n and $py[$j]->end < $s->start) {
                $j ++;
            }
            $best = null;
            $best_overlap = 0;
            for ($k = $j; $k < $n and $py[$k]->start < $s->end; $k ++) {
                $o = min($py[$k]->end, $s->end) - max($py[$k]->start, $s->start);
                if ($o > $best_overlap) {
                    $best_overlap = $o;
                    $best = $py[$k]->speaker;
                }
            }
            $text = trim((string)$s->text);
            if ($text === '') {
                continue;
            }
            $sentences[] = ['start' => floatval($s->start), 'end' => floatval($s->end), 'text' => $text, 'speaker' => $best];
        }

        // 4. 連續同一語者併成一段
        $blocks = [];
        $cur = null;
        $named = 0;
        foreach ($sentences as $s) {
            if (!$cur or $cur['speaker'] !== $s['speaker']) {
                if ($cur) {
                    $blocks[] = $cur;
                }
                $cur = [
                    'speaker' => $s['speaker'],
                    'name' => $names[$s['speaker']][0] ?? null,
                    'role' => $names[$s['speaker']][1] ?? '待確認',
                    'start' => $s['start'],
                    'end' => $s['end'],
                    'anchor' => 'ai-' . intval($ivod->IVOD_ID) . '-' . intval($s['start']),
                    'sentences' => [],
                ];
            }
            $cur['sentences'][] = $s;
            $cur['end'] = $s['end'];
            if (!empty($names[$s['speaker']])) {
                $named ++;
            }
        }
        if ($cur) {
            $blocks[] = $cur;
        }
        // 逐字稿涵蓋範圍：影片有一段沒有轉錄（例：逐字稿從 2 小時 30 分才開始、影片 9:43 結束但逐字稿到 8:46）
        $coverage = null;
        if ($sentences) {
            $first = $sentences[0]['start'];
            $last = end($sentences)['end'];
            $gap_head = $first > 300;
            $gap_tail = $duration > 0 and ($duration - $last) > 300;
            $coverage = ['from' => $first, 'to' => $last, 'duration' => $duration, 'gap' => ($gap_head or $gap_tail)];
        }
        return ['blocks' => $blocks, 'toc' => $toc, 'sentences' => count($sentences), 'named' => $named, 'names' => $names, 'coverage' => $coverage];
    }

    /** 「林委員德福」→「林德福」、「卓院長榮泰」→「卓榮泰」；其他原樣 */
    public static function cleanName($raw)
    {
        $raw = trim(preg_replace('#[（(].*$#u', '', (string)$raw));
        if (preg_match('#^(.{1,2})委員(.+)$#u', $raw, $m)) {
            return $m[1] . $m[2];
        }
        if (preg_match('#^(.{1,3}?)副?(?:' . self::OFFICIAL_TITLES . ')(.{1,8})$#u', $raw, $m)) {
            return $m[1] . $m[2];
        }
        return $raw;
    }

    public static function roleOf($raw, $is_chair = false)
    {
        if ($is_chair) {
            return '主席';
        }
        if (preg_match('#委員#u', $raw)) {
            return '委員';
        }
        if (preg_match('#' . self::OFFICIAL_TITLES . '#u', $raw)) {
            return '官員';
        }
        return '其他';
    }

    public static function fmtOffset($seconds)
    {
        $seconds = intval($seconds);
        if ($seconds >= 3600) {
            return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
