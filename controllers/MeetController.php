<?php

class MeetController extends MiniEngine_Controller
{
    /** /meets?from=&to=&type[]= 會議清單（預設最近 7 天） */
    public function indexAction()
    {
        $to = preg_match('#^\d{4}-\d{2}-\d{2}$#', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
        $from = preg_match('#^\d{4}-\d{2}-\d{2}$#', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime($to . ' -6 days'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        // 一次最多看 31 天，避免 API 抓太多
        if (strtotime($to) - strtotime($from) > 31 * 86400) {
            $from = date('Y-m-d', strtotime($to . ' -30 days'));
        }
        $types = array_values(array_intersect((array)($_GET['type'] ?? []), MeetHelper::TYPE_ORDER));
        $meets = MeetHelper::listMeets($from, $to, $types);
        $days = MeetHelper::groupByDay($meets, $from, $to);
        krsort($days);
        $this->view->from = $from;
        $this->view->to = $to;
        $this->view->types = $types;
        $this->view->days = $days;
        $this->view->prev = ['from' => date('Y-m-d', strtotime($from . ' -7 days')), 'to' => date('Y-m-d', strtotime($from . ' -1 day'))];
        $this->view->next = ['from' => date('Y-m-d', strtotime($to . ' +1 day')), 'to' => date('Y-m-d', strtotime($to . ' +7 days'))];
        $this->view->nav = 'meets';
        $this->view->title = '會議';
    }

    /** /meet/{會議代碼}?view=gazette|ai&ivod= */
    public function showAction($id)
    {
        try {
            $meet = MeetHelper::getMeet($id);
        } catch (LYAPI_NotFound $e) {
            return $this->notfound("找不到這場會議：{$id}");
        }
        [$fulls, $clips] = MeetHelper::getIvods($id);
        // 發言索引有這場會議就算公報定稿（黨團協商的紀錄不經公報目錄，meet 沒有 公報發言紀錄 欄位）
        $docs = MeetHelper::gazetteDocs($id);
        if ($docs and $meet->status !== 'gazette') {
            [$meet->status, $meet->status_label] = ['gazette', MeetHelper::STATUS_LABEL['gazette']];
        }
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
                // 段落目錄以 LYAPI 的 meet_section（James 的切分器）為準；還沒切分的會議退回用 段落／質詢輪次 組
                $sections = MeetHelper::meetSections($id);
                $gazette = [
                    'doc_id' => $doc_id,
                    'docs' => $docs,
                    'blocks' => $blocks,
                    'toc' => $sections ? MeetHelper::sectionToc($sections, $id, $doc_id) : MeetHelper::legacyToc($blocks),
                    'starts' => $sections ? MeetHelper::sectionStarts($sections, $doc_id) : [],
                    'segmenter' => $sections ? ($sections[0]->切分器 ?? null) : null,
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
        $this->view->nav = 'meets';
        $this->view->title = $meet->title;
        $this->view->description = mb_substr($meet->subject ?: $meet->title, 0, 150);
    }
}
