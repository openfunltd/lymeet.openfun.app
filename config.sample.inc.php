<?php

putenv('APP_NAME=LyMeet 立法院會議逐字稿');
putenv('LYAPI_HOST=v2.ly.govapi.tw');   // 資料來源 LYAPI；本機開發可指到 127.0.0.1:18091
putenv('LYAPI_SCHEME=https');            // 本機開發用 http
putenv('LYAPI_TOKEN=');                  // 選用，LYAPI 的 Bearer token
putenv('DEFAULT_TERM=11');               // 立委清單、搜尋預設的屆
putenv('SESSION_SECRET=___SECRET___');
putenv('SESSION_DOMAIN='); // optional
