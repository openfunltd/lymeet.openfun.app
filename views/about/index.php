<?= $this->partial('common/header') ?>
<article class="prose">
<h1>說明</h1>

<h2>這個網站能做什麼</h2>
<ul>
  <li><strong>查原話</strong>：搜尋法案、議題或某句話，找到那一段發言，看得到前後文。</li>
  <li><strong>追立委</strong>：從立委頁看這位委員在各場會議說過什麼，可以再用關鍵字篩。</li>
  <li><strong>核對出處</strong>：有影片的段落都能直接播放原音；公報出版後也標出公報卷期與頁碼。</li>
  <li><strong>當天就看</strong>：會議影片上傳後由電腦自動轉成 AI 轉錄稿，不用等公報。</li>
</ul>

<h2>兩種版本的逐字稿</h2>
<table class="info-table">
  <tr><th>公報定稿</th><td>立法院公報出版的正式會議紀錄，通常在會議後兩到四週出刊。可以引用，引文會附上公報卷期頁碼與影片時間。</td></tr>
  <tr><th>AI 轉錄稿</th><td>由會議影片自動語音辨識產生，整場轉錄完成後一次放上。文字和發言者都可能有錯，引用前請聽原音核對；立委的名字來自 IVOD 的委員片段，官員等其他發言者靠辨識，認不出時標「發言者待確認」。公報出版後會自動換成正式紀錄。</td></tr>
</table>

<h2>會議的狀態</h2>
<ul>
  <li><span class="badge badge-scheduled">已排定</span> 還沒開會，或影片還沒上傳。</li>
  <li><span class="badge badge-processing">AI 轉錄稿產生中</span> 影片已上傳，整場轉錄完成後一次放上。</li>
  <li><span class="badge badge-ai">AI 轉錄稿</span> 可以讀、可以播放每一句，公報出版前只能分享連結。</li>
  <li><span class="badge badge-gazette">公報定稿</span> 文字換成正式紀錄，可以引用。</li>
  <li><span class="badge badge-novideo">沒有影片</span> 立法院沒有這場會議的影片，所以無法產生 AI 轉錄稿；本站不判斷會議有沒有召開。</li>
</ul>

<h2>收錄範圍</h2>
<ul>
  <li>會議：院會、全院委員會、委員會、聯席會議、公聽會、黨團協商、考察的排程都有；逐字稿來自立法院公報與 IVOD 影片，秘密會議沒有影片也沒有紀錄。</li>
  <li>公報發言索引涵蓋第 8 屆至今的院會、委員會、聯席會議、全院委員會，以及第 11 屆的公聽會與黨團協商；搜尋預設只搜第 11 屆。</li>
  <li>資料每 3 小時向立法院更新一次。</li>
  <li>本站不寫摘要，也不提供 AI 摘要。</li>
</ul>

<h2>資料來源與授權</h2>
<p>資料來自<a href="https://ppg.ly.gov.tw/" target="_blank" rel="noopener">立法院議事暨公報資訊網</a>、<a href="https://ivod.ly.gov.tw/" target="_blank" rel="noopener">立法院議事轉播系統（IVOD）</a>與<a href="https://data.ly.gov.tw/" target="_blank" rel="noopener">立法院開放資料</a>，由歐噴有限公司整理成 <a href="https://v2.ly.govapi.tw/" target="_blank" rel="noopener">LYAPI</a> 供本站與所有人取用。網站程式以 BSD 3-Clause 授權開放。</p>

<h2>發現問題</h2>
<p>段落切錯、發言者認錯或其他問題，請到 <a href="https://github.com/openfunltd/lymeet.openfun.app/issues" target="_blank" rel="noopener">GitHub</a> 回報，或到 g0v Slack 的國會松頻道找我們。</p>
</article>
<?= $this->partial('common/footer') ?>
