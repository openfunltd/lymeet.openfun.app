<?= $this->partial('common/header') ?>
<div class="stack"><div class="empty"><h3>資料暫時讀不到</h3><p class="muted">可能是網路不穩，或立法院的資料來源暫時沒有回應。請稍後再試一次。</p><div class="row" style="margin-top:8px"><button type="button" class="act" data-act="retry">重新載入</button><a class="linkish" href="/">回首頁</a></div></div>
<?php if (!empty($this->detail)) { ?><pre class="small muted" style="white-space:pre-wrap"><?= $this->escape($this->detail) ?></pre><?php } ?>
</div>
<?= $this->partial('common/footer') ?>
