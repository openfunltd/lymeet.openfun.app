<?= $this->partial('common/header') ?>
<section class="prose">
  <h1><?= $this->escape($this->title) ?></h1>
  <p><?= $this->escape($this->message) ?></p>
  <p>網址可能打錯，或這一筆還沒有收錄。可以：</p>
  <ul>
    <li><a href="/">回首頁搜尋</a></li>
    <li><a href="/meets">看會議</a></li>
    <li><a href="/legislators">瀏覽立委</a></li>
  </ul>
</section>
<?= $this->partial('common/footer') ?>
