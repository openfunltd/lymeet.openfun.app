<?php

class IndexController extends MiniEngine_Controller
{
    public function indexAction()
    {
        $today = date('Y-m-d');
        $from = date('Y-m-d', strtotime('-3 days'));
        $to = date('Y-m-d', strtotime('+7 days'));
        $meets = MeetHelper::listMeets($from, $to);
        $days = MeetHelper::groupByDay($meets, $from, $to);
        $this->view->today = $today;
        $this->view->today_meets = $days[$today] ?? [];
        $recent = array_filter($days, fn($d) => $d < $today, ARRAY_FILTER_USE_KEY);
        krsort($recent);
        $this->view->recent_days = $recent;
        $this->view->upcoming_days = array_filter($days, fn($d) => $d > $today, ARRAY_FILTER_USE_KEY);
        $this->view->body_class = 'home';
    }

    public function robotsAction()
    {
        header('Content-Type: text/plain');
        echo "User-agent: *\nCrawl-delay: 10\n";
        return $this->noview();
    }
}
