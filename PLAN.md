# PLAN.md — 立法院會議逐字稿（lymeet.openfun.app）專案導覽

> 給 AI Agent 與新加入的人快速掌握架構。若本文件與程式碼有出入，以程式碼為準，並請更新本文件。
> 產品設計定案在另一個 repo：`research-ly-meet/prototype-claire/`（HANDOFF-RD.md 最先讀、README 決策紀錄、hifi/v2/template.html Prototype、hifi/v2/sitemap.html 資訊架構、design-system/tokens.json）。
> 做法是「照 Prototype 做、文案一字不改；資料對不上就列出來等人決定」，對不上的清單見本文件最後一節。

## 這個網站是什麼

聚焦立法院「會議逐字稿」：會議當天（影片上架、整場轉錄完成後）就有 AI 轉錄稿，分段對照影片；公報出版後自動換成公報定稿，可以引用。
不寫摘要（含 AI 摘要）。不做深色模式。沒有「回報段落有誤」。

## 技術棧與原則

- PHP，框架 **mini-engine**（`mini-engine.php`，單檔 MVC）。
- **沒有資料庫**：所有資料即時向 LYAPI v2（`https://v2.ly.govapi.tw`）查，`libraries/LYAPI.php` 做 `/tmp/lymeet-cache-*.json` 檔案快取。頁尾「本頁使用的 API」列出這一頁打過的 API。
- 前端：伺服器端 render 的 PHP view，`static/css/common.css`（直播台風格；手機 600px 以下照資訊架構第 6 節）、`static/js/common.js`，無建置流程。影片用 hls.js（cdnjs）播 IVOD 的 m3u8；下載片段用 ffmpeg.wasm（`static/js/clip.js`，沿用 dataly-v2）。
- 影片一律播 **Full（完整會議）**；Clip（委員片段）只拿「委員名稱＋起訖時間」當時間軸的標籤。
- 本機啟動：`php -S 0:8889 route.php`；`config.inc.php`（不進 git）可把 `LYAPI_HOST` 指到本機跑的 v2。
- Logo（`static/img/logo-*.svg`、`favicon.svg`）與政黨 logo（`static/img/party/`，授權見同目錄 README）取自 research-ly-meet 的 design-system。

## 目錄

```
index.php            路由：/、/meets、/meet/{id}、/search、/legislators、/legislator/{name}、/laws、/law/{code}、/depts、/dept/{name}、/learn、/about、/about-us
controllers/
  IndexController    首頁：搜尋、今天的會議（監看牆）、會議節目表、依法律／部會／立委找
  MeetController     /meets（今天、最近 3 天、已排定、更早；?before= 翻 30 天）；/meet/{id} 會議頁
  SearchController   /search?q=&tab=reason|speech|doc 三個分頁
  LegislatorController /legislators?term=8..11 依該屆黨籍分組；/legislator/{name}
  LawController      /laws 依議案數排序；/law/{code} 修法進度＋討論過這部法的會議
  DeptController     /depts、/dept/{name}（只算事由邀請列席）
  LearnController    /learn 立法院怎麼運作、/about 關於資料、/about-us 關於我們
  ErrorController    404 → views/error/notfound；其他錯誤 → views/error/fail（重新載入）
libraries/
  LYAPI              apiQuery()、qs()（篩選 key=value、區間 key:from,to、排序 sort=欄位< / >）
  MeetHelper         會議清單＋狀態＋在做什麼＋三讀表決標記、單場、影片、公報發言、段落（meet_section → 單元 → 段）、跑馬燈
  SectionHelper      段落目錄的一項與段標題的呈現規則（段的種類看 unit_kind；過場段去括號；案段案號＋標籤）
  TranscriptHelper   AI 轉錄稿：whisperx 句子＋pyannote 語者 → 一段一位發言者；Clip 疊名字；逐字稿涵蓋範圍（缺口）
  SearchHelper       三個分頁：會議事由／會議文件（/meets q，依 highlight 欄位分流）、發言（/speeches q）；找不到時自動改找較短的詞
  LegislatorHelper   第 8–11 屆名單（該屆黨籍、委員會取最後一個會期）、他的那一輪（meet_sections q 段名）、發言
  LawHelper          法律列表、議案流程 → 修法進度的步驟與分支、討論過這部法的會議（主角／只是提到）
  DeptHelper         部會候選清單、事由提到的場數
  Glossary           名詞表（文案同 Prototype）、段標籤白名單、修法進度文案
  SiteHelper         日期、黨籍標籤、狀態標籤、一筆會議的各層
views/common/        header（頁首＋跑馬燈）、footer、meet-row（共用元件 MeetingRow）、filter-bar（FilterBar）
```

## 資料怎麼來（對應 LYAPI）

| 畫面 | API |
|---|---|
| 會議清單 | `/meets?日期:from,to`（含 `公報發言紀錄`、`三讀法案`、`會議屬性`、`資料狀態`）。`資料狀態` 是 LYAPI 匯入端（ly.govapi.tw `imports/meet/enrich-signals.php`）算好的訊號：完整影片、有AI逐字稿、有公報發言、切分器、表決段數、三讀段數；有它就不必再查 ivod／speech／meet_section。沒有 `資料狀態` 的會議才退回老方法：`/ivods?影片種類=Full`、`/speeches agg=會議代碼`、`/meet_sections agg=會議代碼,切分器` 與 `會議代碼,標籤` |
| 會議頁 | `/meet/{id}`、`/ivods?會議資料.會議代碼=`；公報定稿 `/speeches?會議代碼=…&agg=文件編號` 再逐份 `/speeches?文件編號=…&limit=10000`（一次只顯示一份文件，目錄跨文件連結）；段落 `/meet_sections?會議代碼=`（單元依日期排）；AI 轉錄稿 `/ivod/{id}`；議案 `/bills?議案編號=`（同一案的下一步） |
| 搜尋 | `/meets?q="…"`（會議標題、事由、附件標題；依 `:highlight` 欄位分到事由／文件分頁）、`/speeches?q="…"&區塊種類=發言`、`/laws?q=`、立委名單比對 |
| 立委 | `/legislators?屆=N`（8–11）、`/speeches?發言者=`、`/meet_sections?q="X委員Y"&段種類=person`（他的那一輪）、`/speeches agg=發言者&agg_size=300`（排序用的發言段數） |
| 法律 | `/law/{code}`、`/bills?法律編號=&屆:8,11`（議案流程每一步都帶會議代碼，院會與委員會都能點）、`/bills agg=法律編號&agg_size=500`（列表） |

### 會議狀態（`MeetHelper::status()`，不靠 API 的狀態欄位）

公報定稿（meet 有 `公報發言紀錄`，或 `資料狀態.有公報發言`）＞ AI 轉錄稿（`資料狀態.有AI逐字稿`，或 Full 的 `支援功能` 含 ai-transcript）＞ AI 轉錄稿產生中（有 Full 沒逐字稿，或今天已到開始時間）＞ 已排定（還沒開會或結束 3 天內）＞ 沒有影片。
「直播中」目前用「今天、已到開始時間、還沒到結束時間、還沒有 Full」近似，IVOD 沒有直播狀態欄位。

## 設計和資料對不上的地方（等人決定）

- **黨團協商「誰主持」篩選**：要 meet-json 的 `host`，LYAPI 的 meet_section 還沒有（黨團協商還沒切分），篩選列不出現這一行。
- **直播中**：IVOD 沒有直播狀態與每場直播網址；用時間近似，外連按鈕連到會議資料的 IVOD 會議影片頁（沒有就連 ivod.ly.gov.tw）。
- **審預算、三讀、表決標記只有公報出版後才有**（來自切分結果），AI 轉錄稿階段的會議列表看不到。
- **AI 轉錄稿版沒有單元**（James 的 ASR 切分器還沒進 LYAPI），段落目錄用委員影片段落代替。
- **公報版的影片時間**：匯入端的 interpolated 是「沿用上一個錨點」，同一區間每一段的「約」時間相同；要改 ly.govapi.tw 的 GazetteSpeechParser 做真的內插再重匯。
- **掃描圖頁數**（`empty_note`）、**人事審查名單**（James 16 場；目前用 API 會議屬性「人事審查」10 場）、**全院委員會切分** 還沒有。
- **立委列表的「N 場」**：一次查不到每位立委的會議數（agg 上限），改顯示發言段數「N 段」。
- **法律列表的「N 場」**：用議案數「N 件議案」。
- **附件段**：只在目錄列出「附件：…」並在內文標「本文為圖片，請看公報 PDF」，對不到公報文件的位置。
- **院會一場 20 幾份公報文件、上萬則發言**：會議頁一次只顯示一份文件，段落目錄跨文件連到那份文件（Prototype 是整場一頁）。
