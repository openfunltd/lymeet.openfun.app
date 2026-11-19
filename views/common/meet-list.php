<ul class="meet-list">
<?php foreach ($this->meets as $m) { ?>
  <li class="meet-row status-<?= $this->escape($m->status) ?>">
    <a class="meet-link" href="/meet/<?= rawurlencode($m->id) ?>">
      <span class="meet-time"><?= $m->start_time ? $this->escape(substr($m->start_time, 11, 5)) : '' ?></span>
      <span class="meet-main">
        <span class="meet-kind"><?= $this->escape($m->type) ?><?= $m->committees ? '・' . $this->escape(implode('、', $m->committees)) : '' ?></span>
        <span class="meet-title"><?= $this->escape($m->subject ?: $m->title) ?></span>
      </span>
      <span class="badge badge-<?= $this->escape($m->status) ?>"><?= $this->escape($m->status_label) ?></span>
    </a>
  </li>
<?php } ?>
</ul>
