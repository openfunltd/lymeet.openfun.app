<?php

class LearnController extends MiniEngine_Controller
{
    /** /learn 立法院怎麼運作 */
    public function indexAction()
    {
        $this->view->nav = 'learn';
        $this->view->title = '立法院怎麼運作';
        $this->view->description = '法案要經過院會、委員會，有時還有公聽會、黨團協商，最後在院會三讀通過，才成為法律。';
    }

    /** /about 關於資料 */
    public function aboutAction()
    {
        $this->view->nav = 'learn';
        $this->view->title = '關於資料';
        $this->view->cover_from = '2012/02';
        $this->view->description = '本站逐字稿的來源、版本與標示。';
    }

    /** /about-us 關於我們 */
    public function teamAction()
    {
        $this->view->nav = '';
        $this->view->title = '關於我們';
    }
}
