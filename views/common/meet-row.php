<?php
/**
 * 一筆會議（共用元件 MeetingRow）：①標題 ②會議本身（種類・委員會＋資料狀態）③這天的議程 ④為什麼在這裡。
 * 參數：meet、q（標亮）、date（列表要顯示的日期，預設第一天）、show_date、snippet（第④層 HTML，可省）、agenda（第③層 HTML，省略就用預設）、focus（錨點）
 */
$m = $this->meet;
$q = $this->q ?? '';
$date = $this->date ?? $m->date;
$show_date = $this->show_date ?? true;
$href = '/meet/' . rawurlencode($m->id) . (($this->focus ?? '') ? '#' . $this->focus : '');
$same = preg_replace('#\s#u', '', $m->subject) === preg_replace('#\s#u', '', $m->title);
$snip = $this->snippet ?? null;
if (is_null($snip) and !$same and $m->subject !== '') {
    $snip = '<div class="snip">' . SiteHelper::hl(SiteHelper::snippet($m->subject, $q, 45), $q) . '</div>';
}
$marks = SiteHelper::markKeys($m);
?>
<div class="item" data-t="<?= $this->escape($m->type) ?>" data-k="|<?= $this->escape(implode('|', $m->kinds ?? [])) ?>|" data-m="|<?= $this->escape(implode('|', $marks)) ?>|" data-d="<?= $this->escape($date) ?>">
  <?php if ($show_date) { ?><div class="date"><?= SiteHelper::dateBlock($date) ?></div><?php } ?>
  <div class="body">
    <div class="title"><a class="linkish" href="<?= $this->escape($href) ?>"><?= SiteHelper::hl($m->title, $q) ?></a></div>
    <div class="row meta"><?= SiteHelper::kind($m) ?><?= SiteHelper::statusTag($m) ?></div>
    <?= isset($this->agenda) ? $this->agenda : SiteHelper::agendaLine($m) ?>
    <?= $snip ?? '' ?>
  </div>
</div>
