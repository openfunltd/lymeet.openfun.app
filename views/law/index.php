<?= $this->partial('common/header') ?>
<div class="stack idxpage">
  <div class="stack" style="gap:6px"><h1>法律</h1><p class="muted">依討論過的會議數排序。每部法一頁，列出審查它的議案和會議。</p></div>
  <div class="idxtools"><input type="search" class="idxq" placeholder="輸入法律名稱" aria-label="輸入法律名稱"></div>
  <ul class="idxlist">
  <?php foreach ($this->laws as $l) { ?>
    <li data-tx="<?= $this->escape($l['name']) ?>" data-g=""><a class="linkish" href="/law/<?= rawurlencode($l['code']) ?>"><?= $this->escape($l['name']) ?></a><span class="meta"><span class="mono"><?= $this->escape($l['code']) ?></span></span><span class="n"><?= intval($l['count']) ?> 件議案</span></li>
  <?php } ?>
  </ul>
  <p class="muted small idxempty" hidden>沒有符合的項目。</p>
</div>
<?= $this->partial('common/footer') ?>
