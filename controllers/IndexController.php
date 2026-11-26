<?php

class IndexController extends MiniEngine_Controller
{
    /** 首頁：搜尋、今天的會議（監看牆）、會議節目表、依法律／部會／立委找 */
    public function indexAction()
    {
        $today = SiteHelper::today();
        $from = SiteHelper::addDays($today, -14);
        $to = SiteHelper::addDays($today, 7);
        $meets = MeetHelper::listMeets($from, $to);
        $days = MeetHelper::groupByDay($meets, $from, $to);

        $this->view->today = $today;
        $this->view->today_meets = $days[$today] ?? [];
        // 最近開過的 3 天（不含今天）
        $recent = array_filter($days, fn($d) => $d < $today, ARRAY_FILTER_USE_KEY);
        krsort($recent);
        $this->view->recent_days = array_slice($recent, 0, 3, true);
        $next = array_filter($days, fn($d) => $d > $today, ARRAY_FILTER_USE_KEY);
        ksort($next);
        $this->view->next_days = $next;

        // 首頁的搜尋範例：從最近 7 天的會議事由自動取最常出現的法律與部會名稱，不用人維護
        $text = '';
        foreach ($meets as $m) {
            if ($m->date >= SiteHelper::addDays($today, -7) and $m->date <= $today) {
                $text .= $m->title . "\n" . $m->subject . "\n";
            }
        }
        $examples = [];
        $laws = [];
        foreach (self::lawsInText($text) as $name => $n) {
            $laws[] = $name;
        }
        $examples = array_slice($laws, 0, 2);
        // 最近 7 天的會議提到的機關：直接數 meet 的「機關」欄位
        $depts = [];
        foreach ($meets as $m) {
            if ($m->date >= SiteHelper::addDays($today, -7) and $m->date <= $today) {
                foreach ($m->agencies as $d) {
                    if ($d !== '行政院') {
                        $depts[$d] = ($depts[$d] ?? 0) + 1;
                    }
                }
            }
        }
        arsort($depts);
        foreach (array_keys($depts) as $d) {
            if (count($examples) >= 4) {
                break;
            }
            $examples[] = $d;
        }
        $this->view->examples = $examples;

        $top_laws = [];
        try {
            $top_laws = array_slice(LawHelper::listLaws(), 0, 4);
        } catch (Exception $e) {
        }
        $this->view->top_laws = $top_laws;
        $top_depts = [];
        try {
            $top_depts = array_slice(array_values(array_filter(DeptHelper::counts(), fn($d) => $d['name'] !== '行政院')), 0, 4);
        } catch (Exception $e) {
        }
        $this->view->top_depts = $top_depts;
        $top_people = [];
        try {
            $term = MeetHelper::defaultTerm();
            $groups = LegislatorHelper::byParty($term);
            $all = [];
            foreach ($groups as $g) {
                foreach ($g['people'] as $p) {
                    if (!$p['left']) {
                        $all[] = $p;
                    }
                }
            }
            usort($all, fn($a, $b) => $b['count'] <=> $a['count']);
            $top_people = array_slice($all, 0, 4);
        } catch (Exception $e) {
        }
        $this->view->top_people = $top_people;
        $this->view->cover_from = '2012/02';
        $this->view->body_class = 'home';
        $this->view->nav = 'home';
    }

    /** 事由裡出現的法律名稱（「…法」「…條例」）出現次數 */
    public static function lawsInText($text)
    {
        $count = [];
        if (preg_match_all('#「([^」]{2,30}?(?:法|條例|通則|法規))(?:第[^」]*?條|部分條文)?(?:條文)?(?:修正|增訂|刪除)?草案?」#u', $text, $mm)) {
            foreach ($mm[1] as $name) {
                $count[$name] = ($count[$name] ?? 0) + 1;
            }
        }
        arsort($count);
        return $count;
    }

    public function robotsAction()
    {
        header('Content-Type: text/plain');
        // 搜尋、公報文件分頁、影片切換這些帶參數的頁面不給爬，一場院會 26 份文件乘上全站會把 LYAPI 打爆
        echo "User-agent: *\nCrawl-delay: 10\nDisallow: /search\nDisallow: /*?doc=\nDisallow: /*?view=\nDisallow: /*?ivod=\nDisallow: /*?nocache=\nDisallow: /*?page=\nDisallow: /*?before=\n";
        return $this->noview();
    }
}
