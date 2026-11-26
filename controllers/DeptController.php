<?php

class DeptController extends MiniEngine_Controller
{
    /** /depts：依會議事由提到的次數排序 */
    public function indexAction()
    {
        $this->view->depts = DeptHelper::counts();
        $this->view->nav = '';
        $this->view->title = '部會';
    }

    /** /dept/{名稱} */
    public function showAction($name)
    {
        if (!DeptHelper::isDept($name)) {
            $this->view->what = '部會';
            $this->view->title = '找不到這個部會';
            http_response_code(404);
            $this->view->nav = '';
            echo $this->view->partial('error/notfound', $this->view);
            return $this->noview();
        }
        $meets = DeptHelper::meets($name);
        MeetHelper::attachSectionsByIds($meets);
        $this->view->name = $name;
        $this->view->meets = $meets;
        $this->view->nav = '';
        $this->view->title = $name;
        $this->view->description = "邀請{$name}列席的立法院會議";
    }
}
