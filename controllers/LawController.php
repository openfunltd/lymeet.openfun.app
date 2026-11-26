<?php

class LawController extends MiniEngine_Controller
{
    /** /laws：本站有會議討論過的法律，依議案數排序 */
    public function indexAction()
    {
        $this->view->laws = LawHelper::listLaws();
        $this->view->nav = 'laws';
        $this->view->title = '法律';
    }

    /** /law/{法律編號}?r1=1 */
    public function showAction($code)
    {
        try {
            $law = LawHelper::get($code);
        } catch (LYAPI_NotFound $e) {
            $this->view->what = '法律';
            $this->view->title = '找不到這個法律';
            http_response_code(404);
            $this->view->nav = 'laws';
            echo $this->view->partial('error/notfound', $this->view);
            return $this->noview();
        }
        $name = $law->名稱;
        $bills = LawHelper::bills($code);
        $this->view->law = $law;
        $this->view->name = $name;
        $this->view->code = $code;
        $this->view->bills = $bills;
        $this->view->branches = LawHelper::branches($bills);
        $this->view->rows = LawHelper::meetsOf($code, $name, $bills);
        $this->view->r1 = !empty($_GET['r1']);
        $this->view->nav = 'laws';
        $this->view->title = $name;
        $this->view->description = "{$name} 在立法院每場會議的討論與修法進度";
    }
}
