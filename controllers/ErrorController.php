<?php

class ErrorController extends MiniEngine_Controller
{
    public function errorAction($error)
    {
        if ($error instanceof MiniEngine_Controller_NotFound) {
            http_response_code(404);
            $this->view->message = $error->getMessage();
            $this->view->title = '找不到頁面';
            return;
        }
        if ($error instanceof LYAPI_NotFound) {
            http_response_code(404);
            $this->view->message = '資料來源沒有這一筆';
            $this->view->title = '找不到頁面';
            return;
        }
        if (getenv('ENV') == 'production') {
            http_response_code(500);
            error_log($error);
            $this->view->message = '網站暫時出了問題，請稍後再試。';
            $this->view->title = '出了問題';
            return;
        }
        MiniEngine::defaultErrorHandler($error);
    }
}
