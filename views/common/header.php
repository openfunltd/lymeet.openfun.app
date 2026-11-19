<?php
$site = 'LyMeet 立法院會議逐字稿';
$title = ($this->title ?? false) ? "{$this->title}｜{$site}" : $site;
$nav = $this->nav ?? '';
?>
<!DOCTYPE html>
<html lang="zh-Hant-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $this->escape($title) ?></title>
  <meta name="description" content="<?= $this->escape($this->description ?? '不用等公報，也不用看完整場直播。會議當天就查得到立委和官員說了什麼，每一句都能直接播放那段會議錄影。') ?>">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= $this->escape($title) ?>">
  <meta property="og:description" content="<?= $this->escape($this->description ?? '') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/static/css/common.css?v=<?= filemtime(MINI_ENGINE_ROOT . '/static/css/common.css') ?>">
</head>
<body class="<?= $this->escape($this->body_class ?? '') ?>">
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/">LyMeet<span class="brand-sub">立法院會議逐字稿</span></a>
    <nav class="site-nav">
      <a href="/meets" class="<?= $nav == 'meets' ? 'active' : '' ?>">會議</a>
      <a href="/legislators" class="<?= $nav == 'legislators' ? 'active' : '' ?>">立委</a>
      <a href="/about" class="<?= $nav == 'about' ? 'active' : '' ?>">說明</a>
    </nav>
    <?php if (($this->body_class ?? '') !== 'home') { ?>
    <form class="header-search" action="/search" method="get" role="search">
      <input type="search" name="q" value="<?= $this->escape($this->q ?? '') ?>" placeholder="搜尋發言內容" aria-label="搜尋發言內容">
      <button type="submit">搜尋</button>
    </form>
    <?php } ?>
  </div>
</header>
<main class="container">
