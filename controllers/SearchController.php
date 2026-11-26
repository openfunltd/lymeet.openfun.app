<?php

class SearchController extends MiniEngine_Controller
{
    /** /search?q=&tab=reason|speech|doc&page= */
    public function indexAction()
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $tab = (string)($_GET['tab'] ?? '');
        $page = max(1, intval($_GET['page'] ?? 1));
        $this->view->q = $q;
        $this->view->result = $q !== '' ? SearchHelper::search($q, $tab, $page) : null;
        $this->view->cover_from = '2012/02';
        $this->view->nav = '';
        $this->view->title = $q !== '' ? "「{$q}」的搜尋結果" : '搜尋';
    }
}
