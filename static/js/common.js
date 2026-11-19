/* lymeet: 影片播放、逐字稿點句跳轉、複製連結與引文。純前端，無框架。 */
(function () {
  'use strict';
  var videos = window.LYMEET_VIDEOS || [];
  var player = document.getElementById('player');
  var select = document.getElementById('video-select');
  var nowEl = document.getElementById('video-now');
  var ivodLink = document.getElementById('ivod-link');
  var current = null;
  var hls = null;
  var pendingSeek = null;

  function videoById(id) {
    for (var i = 0; i < videos.length; i++) {
      if (videos[i].id === Number(id)) return videos[i];
    }
    return null;
  }

  function loadHls(cb) {
    if (window.Hls) return cb();
    var s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.15/hls.min.js';
    s.onload = cb;
    s.onerror = cb;
    document.head.appendChild(s);
  }

  function attach(video, done) {
    if (!player || !video || !video.url) return;
    if (current && current.id === video.id) return done && done();
    current = video;
    if (select) select.value = String(video.id);
    if (ivodLink && video.ivod_url) ivodLink.href = video.ivod_url;
    if (hls) { hls.destroy(); hls = null; }
    var url = video.url;
    if (player.canPlayType('application/vnd.apple.mpegurl')) {
      player.src = url;
      done && done();
      return;
    }
    loadHls(function () {
      if (window.Hls && window.Hls.isSupported()) {
        hls = new window.Hls();
        hls.loadSource(url);
        hls.attachMedia(player);
        hls.on(window.Hls.Events.MANIFEST_PARSED, function () { done && done(); });
      } else {
        player.src = url;
        done && done();
      }
    });
  }

  function seek(ivodId, offset, play) {
    var v = videoById(ivodId);
    if (!v || !player) return;
    var go = function () {
      var doSeek = function () {
        player.currentTime = Math.max(0, Number(offset));
        if (play !== false) player.play().catch(function () {});
      };
      if (player.readyState >= 1) doSeek();
      else player.addEventListener('loadedmetadata', doSeek, { once: true });
    };
    attach(v, go);
    if (current && current.id === v.id) go();
    var panel = document.getElementById('video-panel');
    if (panel && window.innerWidth > 1200) panel.scrollIntoView({ block: 'nearest' });
  }

  function fmt(sec) {
    sec = Math.floor(sec);
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
  }

  function copy(text, btn) {
    var done = function () {
      if (!btn) return;
      var old = btn.textContent;
      btn.textContent = '已複製';
      setTimeout(function () { btn.textContent = old; }, 1500);
    };
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, done);
    else { window.prompt('複製下面的文字', text); }
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-ivod][data-offset]');
    if (t) {
      e.preventDefault();
      seek(t.getAttribute('data-ivod'), t.getAttribute('data-offset'));
      var block = t.closest('.block');
      if (block && block.id) history.replaceState(null, '', '#' + block.id);
      return;
    }
    var link = e.target.closest('.copy-link');
    if (link) {
      var anchor = link.getAttribute('data-anchor');
      copy(location.origin + location.pathname + location.search + '#' + anchor, link);
      return;
    }
    var quote = e.target.closest('.copy-quote');
    if (quote) {
      var block = quote.closest('.block');
      var text = block ? block.querySelector('.text').textContent.trim() : '';
      if (text.length > 220) text = text.slice(0, 218) + '…';
      var parts = [
        '「' + text + '」',
        '— ' + quote.getAttribute('data-speaker') + '，' + quote.getAttribute('data-meet') + '（' + quote.getAttribute('data-date') + '）'
      ];
      if (quote.getAttribute('data-gazette')) parts.push('立法院公報 ' + quote.getAttribute('data-gazette') + (Number(quote.getAttribute('data-page')) ? '，第 ' + quote.getAttribute('data-page') + ' 頁起' : ''));
      var playBtn = block && block.querySelector('.play[data-offset]');
      if (playBtn) parts.push('影片時間 ' + fmt(playBtn.getAttribute('data-offset')));
      parts.push(location.origin + location.pathname + '?view=gazette#' + quote.getAttribute('data-anchor'));
      copy(parts.join('\n'), quote);
      return;
    }
    var tl = e.target.closest('.copy-time-link');
    if (tl && player && current) {
      var u = location.origin + location.pathname + '?view=' + (location.search.indexOf('view=ai') >= 0 ? 'ai' : 'gazette') + '&ivod=' + current.id + '&t=' + Math.floor(player.currentTime);
      copy(u, tl);
    }
  });

  if (select) {
    select.addEventListener('change', function () { attach(videoById(select.value)); });
  }

  if (player) {
    attach(videos[0]);
    var sentences = document.querySelectorAll('.sentence[data-offset]');
    var lastHighlight = null;
    player.addEventListener('timeupdate', function () {
      if (nowEl) nowEl.textContent = fmt(player.currentTime);
      if (!sentences.length || !current) return;
      var t = player.currentTime;
      var best = null;
      for (var i = 0; i < sentences.length; i++) {
        var s = sentences[i];
        if (Number(s.getAttribute('data-ivod')) !== current.id) continue;
        if (Number(s.getAttribute('data-offset')) <= t) best = s; else break;
      }
      if (best !== lastHighlight) {
        if (lastHighlight) lastHighlight.classList.remove('playing');
        if (best) best.classList.add('playing');
        lastHighlight = best;
      }
    });
    var params = new URLSearchParams(location.search);
    if (params.get('t')) {
      seek(params.get('ivod') || (videos[0] && videos[0].id), params.get('t'), false);
    }
  }

  // 從分享連結進來：把那一段捲到畫面上（瀏覽器自己會做 :target，這裡補 sticky 標頭的位移）
  if (location.hash) {
    var target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (target) setTimeout(function () { target.scrollIntoView({ block: 'start' }); }, 50);
  }
})();
