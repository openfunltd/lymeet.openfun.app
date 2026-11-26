</div></main>
<footer class="site gutter"><div class="wrap">
  <p>本網站由歐噴有限公司開發，西敏寺民主基金會（WFD）支持。資料來自立法院議事暨公報資訊網、IVOD 與立法院開放資料；本站不是立法院官方網站。</p>
  <p class="row" style="gap:6px 20px"><a class="linkish" href="/about-us">關於我們</a><a class="linkish" href="/about">關於資料</a><a class="linkish" href="/depts">部會列表</a><span><?= date('Y') ?> © OpenFun, supported by WFD</span></p>
  <details class="api-log"><summary>本頁使用的 API</summary>
    <ul>
    <?php foreach (LYAPI::getLogs() as $log) { ?>
      <li><a href="<?= $this->escape($log[0]) ?>" target="_blank" rel="noopener"><?= $this->escape($log[1]) ?></a> <span class="muted"><?= $this->escape($log[2] ?? '') ?></span></li>
    <?php } ?>
    </ul>
  </details>
</div></footer>
<div id="barhost"></div>
<div id="layer"></div>
<script>window.LYMEET_GLOSS = <?= Glossary::json() ?>;</script>
<script src="/static/js/common.js?v=<?= filemtime(MINI_ENGINE_ROOT . '/static/js/common.js') ?>"></script>
</body>
</html>
