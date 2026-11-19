# LyMeet 立法院會議逐字稿

lymeet.openfun.app：聚焦立法院會議逐字稿的網站。不用等公報，也不用看完整場直播，會議當天就查得到立委和官員說了什麼，每一句都能直接播放那段會議錄影。

## Quick start

```
cp config.sample.inc.php config.inc.php   # 填 LYAPI_HOST 等
php -S 0:8889 route.php
```

## Software stack

- PHP + [mini-engine](https://github.com/openfunltd/mini-engine)（OpenFun 自製的輕量 MVC framework）
- 資料全部即時來自 [LYAPI v2](https://v2.ly.govapi.tw/)，本站沒有資料庫，只做 `/tmp` 檔案快取
- 前端：伺服器端 render 的 PHP view，純 CSS／JS；影片用 hls.js（CDN）播 IVOD 的 m3u8

架構說明見 `PLAN.md`。

## License

BSD 3-Clause. Copyright (c) 2026 OpenFun Ltd., supported by WFD.
