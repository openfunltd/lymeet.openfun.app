<?php

class LegislatorController extends MiniEngine_Controller
{
    public function indexAction()
    {
        $term = MeetHelper::defaultTerm();
        $this->view->term = $term;
        $this->view->groups = LegislatorHelper::byParty($term);
        $this->view->nav = 'legislators';
        $this->view->title = "第 {$term} 屆立法委員";
    }

    public function showAction($name)
    {
        $term = intval($_GET['term'] ?? 0) ?: MeetHelper::defaultTerm();
        try {
            $legislator = LegislatorHelper::get($term, $name);
        } catch (LYAPI_NotFound $e) {
            return $this->notfound("找不到這位立委：{$name}");
        }
        $q = trim((string)($_GET['q'] ?? ''));
        $page = max(1, intval($_GET['page'] ?? 1));
        [$meet_count, $speech_count] = LegislatorHelper::meetCount($term, $name);
        $this->view->term = $term;
        $this->view->legislator = $legislator;
        $this->view->q = $q;
        $this->view->meet_count = $meet_count;
        $this->view->speech_count = $speech_count;
        $this->view->result = LegislatorHelper::speeches($term, $name, $q, $page);
        $this->view->nav = 'legislators';
        $this->view->title = $name;
        $this->view->description = "{$name} 在第 {$term} 屆立法院會議的發言紀錄";
    }
}
