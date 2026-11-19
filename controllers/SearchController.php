<?php

class SearchController extends MiniEngine_Controller
{
    public function indexAction()
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $page = max(1, intval($_GET['page'] ?? 1));
        $speaker = trim((string)($_GET['speaker'] ?? ''));
        $type = trim((string)($_GET['type'] ?? ''));
        $this->view->q = $q;
        $this->view->speaker = $speaker;
        $this->view->type = in_array($type, MeetHelper::TYPE_ORDER) ? $type : '';
        $this->view->result = null;
        if ($q !== '') {
            $this->view->result = SearchHelper::speeches($q, [
                'page' => $page,
                'speaker' => $speaker ?: null,
                'type' => $this->view->type ?: null,
            ]);
        }
        $this->view->title = $q !== '' ? "搜尋「{$q}」" : '搜尋';
    }
}
