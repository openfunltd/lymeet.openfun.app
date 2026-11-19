<?php $this->description = '不用等公報，也不用看完整場直播。會議當天就查得到立委和官員說了什麼，每一句都能直接播放那段會議錄影，聽本人怎麼說。'; ?>
<?= $this->partial('common/header') ?>
<section class="hero">
  <h1>找到立法院會議裡的那一句話</h1>
  <p class="lead">不用等公報，也不用看完整場直播。會議當天就查得到立委和官員說了什麼，每一句都能直接播放那段會議錄影，聽本人怎麼說。</p>
  <form class="hero-search" action="/search" method="get" role="search">
    <input type="search" name="q" placeholder="輸入法案、議題或某句話，例如：核能、租金補貼" aria-label="搜尋發言內容" autofocus>
    <button type="submit">搜尋發言</button>
  </form>
  <p class="hint">搜的是第 <?= MeetHelper::defaultTerm() ?> 屆會議紀錄裡的發言，依整句比對；也可以從<a href="/legislators">立委</a>或<a href="/meets">會議</a>進去找。</p>
</section>

<section class="schedule">
  <h2>會議節目表</h2>
  <h3 class="day-title">今天 <?= MeetHelper::fmtDate($this->today) ?></h3>
  <?php if ($this->today_meets) { ?>
    <?= $this->partial('common/meet-list', ['meets' => $this->today_meets]) ?>
  <?php } else { ?>
    <p class="empty">今天沒有排定會議。</p>
  <?php } ?>

  <?php foreach ($this->recent_days as $day => $meets) { ?>
  <h3 class="day-title"><?= MeetHelper::fmtDate($day) ?></h3>
  <?= $this->partial('common/meet-list', ['meets' => $meets]) ?>
  <?php } ?>

  <?php if ($this->upcoming_days) { ?>
  <h3 class="day-title">接下來</h3>
  <?php foreach ($this->upcoming_days as $day => $meets) { ?>
    <div class="upcoming-day"><span class="date"><?= MeetHelper::fmtDate($day) ?></span>
      <?php foreach ($meets as $m) { ?><a href="/meet/<?= rawurlencode($m->id) ?>" class="chip"><?= $this->escape($m->type) ?>・<?= $this->escape($m->committees ? implode('、', $m->committees) : $m->title) ?></a><?php } ?>
    </div>
  <?php } ?>
  <?php } ?>
  <p class="more"><a href="/meets">看更早的會議 →</a></p>
</section>
<?= $this->partial('common/footer') ?>
