<?php
/**
 * 篩選列（共用元件 FilterBar）：會議種類 → 在做什麼 → 院會的有三讀／有表決＋時間。只列資料裡真的有的選項。
 * 參數：id、meets（要篩的會議，用來決定有哪些選項）、time（要不要時間篩選，預設 true）
 */
$meets = $this->meets ?? [];
$id = $this->id ?? 'mt';
$types = [];
$kinds = [];
$has_marks = false;
foreach ($meets as $m) {
    $types[$m->type] = true;
    foreach ($m->kinds ?? [] as $k) {
        $kinds[$k] = $m->type;
    }
    if (SiteHelper::markKeys($m)) {
        $has_marks = true;
    }
}
$types = array_values(array_filter(MeetHelper::TYPE_ORDER, fn($t) => isset($types[$t])));
$kind_rows = [];
foreach (MeetHelper::KINDS as $k) {
    if (isset($kinds[$k])) {
        $kind_rows[] = [$kinds[$k], $k];
    }
}
?>
<div class="filters" data-filter="<?= $this->escape($id) ?>"><span class="muted">會議種類</span>
  <span class="chiprow"><button type="button" class="chip btn" aria-pressed="true" data-ft="">全部</button><?php if (count($types) > 1) { foreach ($types as $t) { ?><button type="button" class="chip btn" aria-pressed="false" data-ft="<?= $this->escape($t) ?>"><?= $this->escape($t) ?></button><?php } } ?></span>
  <?php if (count($kind_rows) > 1) { ?>
  <div class="filters kindrow" hidden style="width:100%;margin:0"><span class="muted kl">在做什麼</span><button type="button" class="chip btn" aria-pressed="true" data-fk="">全部</button><?php foreach ($kind_rows as [$t, $k]) { ?><button type="button" class="chip btn" aria-pressed="false" data-fk="<?= $this->escape($k) ?>" data-kt="<?= $this->escape($t) ?>"><?= $this->escape($k) ?></button><?php } ?></div>
  <?php } ?>
  <div class="filters frow" style="width:100%;margin:0">
    <?php if ($has_marks) { ?><span class="mrow"><span class="muted">院會</span><button type="button" class="chip btn" aria-pressed="false" data-fm="三讀">有三讀</button><button type="button" class="chip btn" aria-pressed="false" data-fm="表決">有表決</button></span><?php } ?>
    <?php if ($this->time ?? true) { ?><label class="muted">時間 <select data-fd><option value="">不限</option><option value="3">最近 3 天</option><option value="7">最近 7 天</option><option value="30">最近 30 天</option><option value="90">最近 90 天</option></select></label><?php } ?>
  </div>
</div>
