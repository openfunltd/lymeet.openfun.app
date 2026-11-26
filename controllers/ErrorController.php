<?php

class ErrorController extends MiniEngine_Controller
{
    public function errorAction($error)
    {
        if ($error instanceof MiniEngine_Controller_NotFound) {
            http_response_code(404);
            $this->view->what = '頁面';
            $this->view->title = '找不到這個頁面';
            $this->view->nav = '';
            echo $this->view->partial('error/notfound', $this->view);
            return $this->noview();
        }
        if ($error instanceof LYAPI_NotFound) {
            http_response_code(404);
            $this->view->what = '頁面';
            $this->view->title = '找不到這個頁面';
            $this->view->nav = '';
            echo $this->view->partial('error/notfound', $this->view);
            return $this->noview();
        }
        // 資料讀取失敗：每一頁共用，只說發生什麼、下一步怎麼做，不顯示錯誤代碼
        http_response_code(500);
        error_log($error);
        if (getenv('ENV') != 'production') {
            $this->view->detail = (string)$error;
        }
        $this->view->title = '資料暫時讀不到';
        $this->view->nav = '';
        echo $this->view->partial('error/fail', $this->view);
        return $this->noview();
    }
}
