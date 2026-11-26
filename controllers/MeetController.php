<?php

class MeetController extends MiniEngine_Controller
{
    /** /meets：今天、最近 3 天、已排定（未來 7 天）、更早的會議；篩選列在前端做 */
    public function indexAction()
    {
        $today = SiteHelper::today();
        $before = preg_match('#^\d{4}-\d{2}-\d{2}$#', $_GET['before'] ?? '') ? $_GET['before'] : null;
        if ($before) {
            // 更早的會議：翻頁，一次 30 天
            $to = $before;
            $from = SiteHelper::addDays($to, -29);
            $meets = MeetHelper::listMeets($from, $to);
            $days = MeetHelper::groupByDay($meets, $from, $to);
            krsort($days);
            $this->view->older_only = true;
            $this->view->older_days = $days;
            $this->view->older_from = $from;
            $this->view->older_to = $to;
            $this->view->prev_before = SiteHelper::addDays($from, -1);
            $this->view->nav = 'meets';
            $this->view->title = '更早的會議';
            return;
        }
        $from = SiteHelper::addDays($today, -30);
        $to = SiteHelper::addDays($today, 7);
        $meets = MeetHelper::listMeets($from, $to);
        $days = MeetHelper::groupByDay($meets, $from, $to);
        $this->view->today = $today;
        $this->view->today_meets = $days[$today] ?? [];
        $past = array_filter($days, fn($d) => $d < $today, ARRAY_FILTER_USE_KEY);
        krsort($past);
        $recent_days = array_slice($past, 0, 3, true);
        $this->view->recent_days = $recent_days;
        $this->view->older_days = array_slice($past, 3, null, true);
        $next = array_filter($days, fn($d) => $d > $today, ARRAY_FILTER_USE_KEY);
        ksort($next);
        $this->view->next_days = $next;
        $this->view->older_only = false;
        $this->view->prev_before = SiteHelper::addDays($from, -1);
        $this->view->all_meets = $meets;
        $this->view->nav = 'meets';
        $this->view->title = '會議';
    }

    /** /meet/{會議代碼}?doc=&view=&from=ai */
    public function showAction($id)
    {
        try {
            $meet = MeetHelper::getMeet($id);
        } catch (LYAPI_NotFound $e) {
            $this->view->what = '會議';
            $this->view->title = '找不到這個會議';
            http_response_code(404);
            return $this->renderNotFound();
        }
        [$fulls, $clips] = MeetHelper::getIvods($id);
        // 發言索引有這場會議就算公報定稿（黨團協商的紀錄不經公報目錄，meet 沒有 公報發言紀錄 欄位）
        $docs = MeetHelper::gazetteDocs($id);
        if ($docs and $meet->status !== 'gazette') {
            [$meet->status, $meet->status_label] = ['gazette', MeetHelper::STATUS_LABEL['gazette']];
        }
        $sections = MeetHelper::meetSections($id);
        MeetHelper::attachSectionsByIds([$meet]);
        $meet->segmented = count($sections) > 0;
        $meet->segmenter = $sections ? ($sections[0]->切分器 ?? null) : null;
        $meet->kinds = MeetHelper::kindsOf($meet);

        $view = $_GET['view'] ?? null;
        if (!in_array($view, ['gazette', 'ai'])) {
            $view = $docs ? 'gazette' : 'ai';
        }

        $gazette = null;
        if ($view === 'gazette') {
            if ($docs) {
                $doc_id = $_GET['doc'] ?? '';
                if (!isset($docs[$doc_id])) {
                    $doc_id = array_key_first($docs);
                }
                $blocks = MeetHelper::gazetteSpeeches($id, $doc_id);
                $times = MeetHelper::speechTimes($blocks);
                $units = $sections ? MeetHelper::sectionUnits($sections, $id, $doc_id, $times) : MeetHelper::legacyUnits($blocks, $times);
                $gazette = [
                    'doc_id' => $doc_id,
                    'docs' => $docs,
                    'blocks' => $blocks,
                    'times' => $times,
                    'units' => $units,
                    'starts' => MeetHelper::sectionStarts($units, $doc_id),
                    'segmented' => count($sections) > 0,
                    'pages' => self::docPages($blocks),
                ];
            } else {
                // 公報發言紀錄有了但索引還沒匯入（cron 每 3 小時）：退回 AI 轉錄稿
                $view = 'ai';
            }
        }

        $ai = null;
        $ai_ivod = null;
        $ai_fulls = array_values(array_filter($fulls, fn($f) => in_array('ai-transcript', (array)($f->支援功能 ?? []))));
        if ($view === 'ai' and $ai_fulls) {
            $want = intval($_GET['ivod'] ?? 0);
            foreach ($ai_fulls as $f) {
                if (!$ai_ivod or intval($f->IVOD_ID) === $want) {
                    $ai_ivod = $f;
                }
            }
            // 少數影片標了 ai-transcript 但逐字稿是空的，換下一支
            $candidates = array_merge([$ai_ivod], array_filter($ai_fulls, fn($f) => $f !== $ai_ivod));
            foreach ($candidates as $f) {
                $item = MeetHelper::getIvod($f->IVOD_ID);
                $built = TranscriptHelper::build($item, $clips);
                if ($built['sentences'] > 0) {
                    $ai_ivod = $f;
                    $ai = $built;
                    break;
                }
            }
        }

        $this->view->meet = $meet;
        $this->view->fulls = $fulls;
        $this->view->clips = $clips;
        $this->view->ai_fulls = $ai_fulls;
        $this->view->view_mode = $view;
        $this->view->gazette = $gazette;
        $this->view->ai = $ai;
        $this->view->ai_ivod = $ai_ivod;
        $this->view->pdfs = MeetHelper::gazettePdfs($meet);
        $this->view->bills = ($gazette or $ai or $meet->status !== 'novideo') ? MeetHelper::billsOfMeet($meet) : [];
        $this->view->from_ai = ($_GET['from'] ?? '') === 'ai';
        $this->view->nav = 'meets';
        $this->view->title = $meet->title;
        $this->view->description = mb_substr($meet->subject ?: $meet->title, 0, 150);
    }

    /** 這份文件的公報頁碼範圍（取自發言的公報起始頁／結束頁） */
    public static function docPages(array $blocks)
    {
        $min = null;
        $max = null;
        $gazette_id = null;
        foreach ($blocks as $b) {
            $gazette_id = $gazette_id ?: ($b->公報編號 ?? null);
            if (!empty($b->公報起始頁)) {
                $min = is_null($min) ? intval($b->公報起始頁) : min($min, intval($b->公報起始頁));
            }
            if (!empty($b->公報結束頁)) {
                $max = is_null($max) ? intval($b->公報結束頁) : max($max, intval($b->公報結束頁));
            }
        }
        return ['from' => $min, 'to' => $max, 'gazette_id' => $gazette_id];
    }

    protected function renderNotFound()
    {
        $this->view->nav = 'meets';
        echo $this->view->partial('error/notfound', $this->view);
        return $this->noview();
    }
}
