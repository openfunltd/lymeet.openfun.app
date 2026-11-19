# PLAN.md — LyMeet（lymeet.openfun.app）專案導覽

> 給 AI Agent 與新加入的人快速掌握架構。若本文件與程式碼有出入，以程式碼為準，並請更新本文件。
> 產品規劃（JTBD、prototype、決策紀錄）在另一個 repo：`research-ly-meet/prototype-claire/`（README 的決策表、HANDOFF、hifi/sitemap）。

## 這個網站是什麼

聚焦立法院「會議逐字稿」的查詢網站：找原話、追立委、核對出處（播放原音、公報頁碼）、會議當天就有 AI 轉錄稿。
不寫摘要（含 AI 摘要）。第一階段不做新的 AI 判斷：IVOD 的 AI 逐字稿維持 LYAPI 給的樣子，不另外分段。

## 技術棧與原則

- PHP，框架 **mini-engine**（`mini-engine.php`，單檔 MVC；用法見 `~/work/mini-engine/MINI_ENGINE_GUIDE.md`）。
- **沒有資料庫**：所有資料即時向 LYAPI v2（`https://v2.ly.govapi.tw`）查，`libraries/LYAPI.php` 做 `/tmp/lymeet-cache-*.json` 檔案快取。
  架構與 `lawtrace` 相同。頁尾「本頁使用的 API」會列出這一頁打過的 API。
- 前端：伺服器端 render 的 PHP view，`static/css/common.css`、`static/js/common.js`，無建置流程。影片用 hls.js（cdnjs）播 IVOD 的 m3u8。
- 影片一律播 **Full（完整會議）**；Clip（委員片段）只拿「委員名稱＋起訖時間」當 Full 時間軸的標籤。
- 本機啟動：`php -S 0:8889 route.php`；`config.inc.php`（不進 git）可把 `LYAPI_HOST` 指到本機跑的 v2（`LYAPI_SCHEME=http`）。

## 目錄

```
index.php            自訂路由（/meet/{id}、/legislator/{name} 這類含中文 id 的網址）
init.inc.php         環境變數預設（LYAPI_HOST、LYAPI_SCHEME、DEFAULT_TERM）
controllers/
  IndexController    首頁：搜尋＋會議節目表（今天、最近 3 天、接下來 7 天）
  MeetController     /meets 清單（日期區間、會議種類）；/meet/{會議代碼} 會議頁
  SearchController   /search?q= 發言搜尋
  LegislatorController /legislators 立委清單（依黨籍）；/legislator/{姓名} 個人頁
  AboutController    /about 說明
libraries/
  LYAPI              apiQuery()、qs()（v2 的 query string：篩選 key=value、區間 key:from,to、排序 sort=欄位< / >）
  MeetHelper         會議清單＋狀態、單場、影片、公報發言（逐份文件抓）、段落目錄
  TranscriptHelper   AI 轉錄稿：whisperx 句子＋pyannote 語者 → 一段一位發言者；Clip 疊名字
  SearchHelper       發言搜尋（片語）、依會議分組、highlight
  LegislatorHelper   立委清單、個人頁的發言
views/{controller}/{action}.php；common/header、footer、meet-list；meet/video（播放器面板）
```

## 資料怎麼來（對應 LYAPI）

| 頁面 | API |
|---|---|
| 會議清單 | `/meets?日期:from,to&output_fields=…`（含 `公報發言紀錄` 判斷公報有沒有出）＋ `/ivods?日期:from,to&影片種類=Full`（`支援功能` 含 `ai-transcript` 表示有 AI 逐字稿） |
| 會議頁 | `/meet/{id}`、`/meet/{id}/ivods`；公報定稿 `/speeches?會議代碼=…&agg=文件編號` 再逐份 `/speeches?文件編號=…&limit=10000&sort=順序<`（一場可能上萬筆，ES 單次上限 1 萬，所以分文件抓）；AI 轉錄稿 `/ivod/{id}`（transcript.whisperx／pyannote） |
| 搜尋 | `/speeches?q="片語"&屆=11&區塊種類=發言&sort=日期>`（q 一定加引號，否則被拆成單字）；會議標題用 `/meets?會議代碼=a&會議代碼=b` 一次補 |
| 立委 | `/legislators?屆=11`、`/legislator/{屆}/{姓名}`、`/speeches?發言者=…` |

### 會議狀態（`MeetHelper::status()`，不靠 API 的狀態欄位）

公報定稿（meet 有 `公報發言紀錄`）＞ AI 轉錄稿（有 Full 且 `支援功能` 含 ai-transcript）＞ AI 轉錄稿產生中（有 Full 沒逐字稿）＞ 已排定（還沒開會或結束 3 天內）＞ 沒有影片。

### AI 轉錄稿的語者（`TranscriptHelper::build()`）

1. pyannote 區段自帶的 `speaker_name`（LYAPI 的語者辨識，Full 影片只有約四分之一有）
2. 同場 Clip 的起訖時間內講最多的語者群，還沒名字就標成那位委員（委員一定有，官員靠辨識）
3. 都沒有 → 「發言者待確認」，顯示語者代號

### 已知限制（2026-11-19）

- `/speeches` 在正式 v2 還沒部署前會 404，本機開發要自己跑 v2。
- 公報頁碼只到文件層級（一份公報文件一個頁碼區間），引文寫「第 N 頁起」。
- 公報版的影片時間大多是內插（`時間來源=interpolated`），畫面標「約」。
- 黨團協商、公聽會的公報多半沒有時鐘戳記，公報版沒有影片時間；用 AI 轉錄稿那邊看。
- 回報段落有誤還沒做（本站沒有資料庫，要等 LYAPI 提供寫入端或改走 GitHub issue）。
- 下載影片片段還沒做；可沿用 dataly-v2 的純前端 ffmpeg.wasm 做法（`dataly-v2/static/js/ivod/clip.js`）。
- 法律頁、部會頁還沒做。
