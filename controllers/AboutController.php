<?php

class AboutController extends MiniEngine_Controller
{
    public function indexAction()
    {
        $this->view->nav = 'about';
        $this->view->title = '說明';
    }
}
