</main>
<footer class="site-footer">
  <div class="container">
    <p>本網站由<a href="https://openfun.tw/" target="_blank" rel="noopener">歐噴有限公司</a>開發，<a href="https://www.wfd.org/" target="_blank" rel="noopener">西敏寺民主基金會（WFD）</a>支持。資料來自立法院議事暨公報資訊網、IVOD 與立法院開放資料，透過 <a href="https://v2.ly.govapi.tw/" target="_blank" rel="noopener">LYAPI</a> 取得；本站不是立法院官方網站。</p>
    <p class="footer-links">
      <a href="/about">關於資料</a>
      <a href="https://github.com/openfunltd/lymeet.openfun.app" target="_blank" rel="noopener">原始碼</a>
      <details class="api-log"><summary>本頁使用的 API</summary>
        <ul>
        <?php foreach (LYAPI::getLogs() as $log) { ?>
          <li><a href="<?= $this->escape($log[0]) ?>" target="_blank" rel="noopener"><?= $this->escape($log[1]) ?></a> <span class="muted"><?= $this->escape($log[2] ?? '') ?></span></li>
        <?php } ?>
        </ul>
      </details>
    </p>
  </div>
</footer>
<script src="/static/js/common.js?v=<?= filemtime(MINI_ENGINE_ROOT . '/static/js/common.js') ?>"></script>
</body>
</html>
