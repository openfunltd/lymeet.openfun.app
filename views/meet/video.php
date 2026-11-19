<?php
$videos = $this->videos ?? [];
$current = $this->current ?? ($videos[0]['id'] ?? null);
?>
<aside class="video-panel" id="video-panel">
  <?php if ($videos) { ?>
  <video id="player" controls playsinline preload="none"></video>
  <div class="video-meta">
    <?php if (count($videos) > 1) { ?>
    <label>影片
      <select id="video-select">
      <?php foreach ($videos as $v) { ?>
        <option value="<?= $v['id'] ?>" <?= $v['id'] == $current ? 'selected' : '' ?>><?= $this->escape($v['label']) ?></option>
      <?php } ?>
      </select>
    </label>
    <?php } else { ?>
    <span class="muted"><?= $this->escape($videos[0]['label']) ?></span>
    <?php } ?>
    <span class="video-now muted" id="video-now"></span>
    <a id="ivod-link" class="muted" href="<?= $this->escape($videos[0]['ivod_url'] ?? '#') ?>" target="_blank" rel="noopener">在 IVOD 開啟 ↗</a>
  </div>
  <details class="clip-tool">
    <summary>分享或下載這段影片</summary>
    <p class="muted">複製帶時間的連結，打開時影片會停在那一段。</p>
    <button type="button" class="copy-time-link">複製目前播放時間的連結</button>
  </details>
  <?php } else { ?>
  <p class="muted">這場會議沒有對應的影片。</p>
  <?php } ?>
  <script>window.LYMEET_VIDEOS = <?= json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
</aside>
