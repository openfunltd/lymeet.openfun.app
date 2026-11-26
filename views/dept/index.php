<?= $this->partial('common/header') ?>
<div class="stack idxpage">
  <div class="stack" style="gap:6px"><h1>部會</h1><p class="muted">依會議事由提到的次數排序。每個部會一頁，列出邀請它列席的會議。</p></div>
  <div class="idxtools"><input type="search" class="idxq" placeholder="輸入部會名稱" aria-label="輸入部會名稱"></div>
  <ul class="idxlist">
  <?php foreach ($this->depts as $d) { ?>
    <li data-tx="<?= $this->escape($d['name']) ?>" data-g=""><a class="linkish" href="/dept/<?= rawurlencode($d['name']) ?>"><?= $this->escape($d['name']) ?></a><span class="meta"></span><span class="n"><?= intval($d['count']) ?> 場</span></li>
  <?php } ?>
  </ul>
  <p class="muted small idxempty" hidden>沒有符合的項目。</p>
</div>
<?= $this->partial('common/footer') ?>
