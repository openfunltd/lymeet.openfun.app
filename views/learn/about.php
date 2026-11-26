<?php $t = fn($x, $c = '') => Glossary::term($x, $c); ?>
<?= $this->partial('common/header') ?>
<div class="stack docpage" style="max-width:760px">
  <div class="doctabs" role="tablist"><a href="/learn" role="tab" aria-selected="false">立法院怎麼運作</a><a class="on" href="/about" role="tab" aria-selected="true">關於資料</a></div>
  <div class="stack" style="gap:8px"><h1>關於資料</h1><p class="muted">本站逐字稿的來源、版本與標示。</p></div>
  <section class="panel stack" style="gap:8px" id="about-version"><h2>AI 轉錄稿與公報定稿</h2>
   <p><span class="status ai">AI 轉錄稿</span> 會議影片上架、整場轉錄完成後產生。由電腦辨識，可能有錯字、認錯人或漏掉一段；不提供引文，可以分享連結和影片片段。</p>
   <p><span class="status gz">公報定稿</span> 立法院出版的正式紀錄，約在會後 30 天出版。出版後，同一個連結自動換成公報，頁面會標示原本是 AI 轉錄稿；可以引用，引文附公報卷期與頁碼。</p></section>
  <section class="panel stack" style="gap:10px"><h2>會議狀態</h2>
   <div class="stline">
    <div class="stp"><span class="status">已排定</span><p>還沒開會，只有議程</p></div><span class="arr" aria-hidden="true">→</span>
    <div class="stp"><span class="status live">AI 轉錄稿產生中</span><p>開會中或剛結束，整場轉錄完成才放上</p></div><span class="arr" aria-hidden="true">→</span>
    <div class="stp"><span class="status ai">AI 轉錄稿</span><p>可以讀、可以分享</p></div><span class="arr" aria-hidden="true">→</span>
    <div class="stp"><span class="status gz">公報定稿</span><p>換成正式紀錄，可以引用</p></div>
   </div>
   <p class="small"><span class="status novideo">沒有影片</span>　立法院沒有這場會議的影片，所以沒有 AI 轉錄稿，只保留議程。</p></section>
  <section class="panel stack" style="gap:8px" id="about-scope"><h2>收錄範圍</h2>
   <ul class="tips"><li>收錄 <?= $this->escape($this->cover_from) ?> 起的會議。</li>
    <li>有影片的會議，才有 AI 轉錄稿。</li>
    <li>黨團協商約七成、委員會以外主辦的公聽會幾乎都不刊登公報，會一直是 AI 轉錄稿。</li>
    <li>這幾種會議沒有段落目錄，只能從頭讀：全院委員會審查覆議案與不信任案、程序委員會、全院委員談話會，以及委員會審法案、業務報告的日子（公報版）。考察通常沒有紀錄。</li></ul></section>
  <section class="panel stack" style="gap:10px" id="about-marks"><h2>會議頁上的標示</h2>
   <h3 class="mkh">段落目錄</h3>
   <p class="muted small" style="margin:0">會議頁左邊的段落目錄。左邊是它在會議頁上的樣子。</p>
   <div class="mklist"><div class="mkrow"><div class="mksamp"><b style="color:var(--brand-ink)">討論事項</b></div><div class="mktxt"><b>單元</b><p>一場會議分成好幾個單元，例如國是論壇、討論事項，照開會時間排。</p></div></div><div class="mkrow"><div class="mksamp"><span><b>第六案</b> <span class="flag">逐條討論</span> <span class="flag">三讀</span><br><span class="small">電信管理法第三十六條條文修正草案</span></span></div><div class="mktxt"><b>段與標籤</b><p>單元底下的每一項是一段：一位委員的發言，或一個案子。旁邊的小框標出這段發生了什麼，例如三讀、表決。</p></div></div><div class="mkrow"><div class="mksamp"><span style="color:var(--muted);font-size:.9em">現在請秘書長報告出席人數。</span></div><div class="mktxt"><b>過場</b><p>灰字的段落不是誰的發言，是清點人數、休息、散會這類過場。</p></div></div></div>
   <h3 class="mkh">逐字稿裡的標示</h3>
   <p class="muted small" style="margin:0">左邊是它在會議頁上的樣子。</p>
   <div class="mklist"><div class="mkrow"><div class="mksamp"><?= $t('發言者待確認', 'unk') ?></div><div class="mktxt"><b>發言者待確認</b><p>電腦認不出是誰在說話，也可能認錯人；抽測大約七、八成標得對。請播放那個段落聽原音。</p></div></div><div class="mkrow"><div class="mksamp"><?= $t('紀錄文字', 'rec') ?></div><div class="mktxt"><b>紀錄文字</b><p>公報上的紀錄，不是有人發言，例如會議的時間地點、議程、提案人名單。</p></div></div><div class="mkrow"><div class="mksamp"><span class="mkgap">這裡有一段影片沒有轉錄出文字…</span></div><div class="mktxt"><b>沒有轉錄的片段</b><p>這段影片沒有聲音，可能關了麥克風，或直播訊號中斷。</p></div></div><div class="mkrow"><div class="mksamp"><span class="mkgap">本段有 3 頁是掃描圖…</span></div><div class="mktxt"><b>公報的掃描圖</b><p>公報那幾頁是圖片，沒有文字可以顯示，請看公報 PDF。</p></div></div><div class="mkrow"><div class="mksamp"><span class="muted small">這個段落還沒有對到影片時間</span></div><div class="mktxt"><b>還沒對到影片時間</b><p>這個段落還不能直接播放，請到 IVOD 看整場影片。</p></div></div><div class="mkrow"><div class="mksamp"><span class="gzlink">所在公報文件 第 21–50 頁 ↗</span></div><div class="mktxt"><b>公報頁碼</b><p>這一段所在的公報文件頁碼範圍；段落沒有自己的頁碼。</p></div></div></div></section>
  <section class="panel stack" style="gap:8px"><details><summary style="cursor:pointer"><b>看更多：搜尋、引用、資料來源</b></summary><div class="stack" style="gap:12px;margin-top:10px">
   <section class="panel stack" style="gap:8px"><h2>搜尋找得到什麼</h2>
   <ul class="tips"><li>會議事由：議程上寫的要討論什麼</li><li>發言內容：逐字稿裡的每一段話</li><li>會議文件的標題：例如部會的書面報告。文件的內文還搜不到。</li></ul></section>
   <section class="panel stack" style="gap:8px"><h2>引用與下載</h2>
   <p>公報定稿的每段發言都可以「引用這段」，複製的引文會附上發言者、會議、公報卷期頁碼和影片時間，讀者可以自己回來核對。AI 轉錄稿可能有錯字或認錯人，所以不提供引文，只能分享連結、下載那段影片；公報出版後才能引用。</p></section>
   <section class="panel stack" style="gap:8px" id="about-source"><h2>資料從哪裡來</h2><p class="muted">立法院議事暨公報資訊網、立法院議事轉播系統（<?= $t('IVOD') ?>）、立法院開放資料。本站不是立法院官方網站。</p></section>
  </div></details></section>
</div>
<?= $this->partial('common/footer') ?>
