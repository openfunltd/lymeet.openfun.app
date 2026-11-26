<?php

class LegislatorController extends MiniEngine_Controller
{
    /** /legislators?term=11：先選屆別，再依該屆黨籍分組 */
    public function indexAction()
    {
        $term = intval($_GET['term'] ?? 0);
        if (!in_array($term, LegislatorHelper::TERMS)) {
            $term = MeetHelper::defaultTerm();
        }
        $this->view->term = $term;
        $this->view->groups = LegislatorHelper::byParty($term);
        $this->view->nav = 'people';
        $this->view->title = '立委';
    }

    /** /legislator/{姓名}?q=&days=&page= */
    public function showAction($name)
    {
        try {
            $info = LegislatorHelper::get($name);
        } catch (LYAPI_NotFound $e) {
            $this->view->what = '人';
            $this->view->title = '找不到這個人';
            http_response_code(404);
            $this->view->nav = 'people';
            echo $this->view->partial('error/notfound', $this->view);
            return $this->noview();
        }
        $q = trim((string)($_GET['q'] ?? ''));
        $days = intval($_GET['days'] ?? 0);
        $page = max(1, intval($_GET['page'] ?? 1));
        $from = in_array($days, [3, 7, 30, 90]) ? SiteHelper::addDays(SiteHelper::today(), -$days) : null;
        $result = LegislatorHelper::speeches($name, $q, $page, $from);
        [$meet_count, $speech_count] = LegislatorHelper::meetCount($name);
        $rounds = LegislatorHelper::rounds($name);
        $this->view->info = $info;
        $this->view->name = $name;
        $this->view->q = $q;
        $this->view->days = $days;
        $this->view->result = $result;
        $this->view->rounds = $rounds;
        $this->view->meet_count = $meet_count;
        $this->view->speech_count = $speech_count;
        $this->view->nav = 'people';
        $this->view->title = $name;
        $this->view->description = "{$name} 在立法院會議的發言紀錄";
    }
}
