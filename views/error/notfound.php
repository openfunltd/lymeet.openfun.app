<?php $what = $this->what ?? '頁面'; ?>
<?= $this->partial('common/header') ?>
<div class="stack"><h1>找不到這個<?= $this->escape($what) ?></h1><div class="empty"><p class="muted">網址可能打錯，或這個<?= $this->escape($what) ?>還沒有收錄。</p><div class="row" style="margin-top:8px;flex-wrap:wrap;gap:6px"><a class="chip btn" href="/">回首頁搜尋</a><a class="chip btn" href="/meets">看會議</a><a class="chip btn" href="/legislators">瀏覽立委</a><a class="chip btn" href="/laws">瀏覽法律</a></div></div></div>
<?= $this->partial('common/footer') ?>
