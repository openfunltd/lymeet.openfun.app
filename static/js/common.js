/* 立法院會議逐字稿：手機版開關、名詞小視窗、篩選列、逐字稿字級、影片播放與播放列、引用與分享面板。純前端，無框架。 */
(function () {
  'use strict';
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var fmt = function (sec) { sec = Math.max(0, Math.floor(Number(sec) || 0)); var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60; return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0'); };
  var todayStr = function () { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
  var addDays = function (d, n) { var t = new Date(d + 'T00:00:00'); t.setDate(t.getDate() + n); return t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0'); };
  var GL = window.LYMEET_GLOSS || { terms: {}, more: {} };
  var MEET = window.LYMEET_MEET || null;
  var layer = $('#layer'), barhost = $('#barhost');

  function toast(t) { var el = document.createElement('div'); el.className = 'toast'; el.setAttribute('role', 'status'); el.textContent = t; document.body.appendChild(el); setTimeout(function () { el.remove(); }, 1800); }
  function copy(t) {
    if (navigator.clipboard) { navigator.clipboard.writeText(t).then(function () { toast('已複製'); }, function () { toast('無法自動複製，請手動選取文字複製'); }); }
    else { window.prompt('複製下面的文字', t); }
  }

  /* ---------- 逐字稿字級：小 15／中 16／大 20，記在瀏覽器 ---------- */
  function getTx() { try { return localStorage.getItem('ivod-tx') || 'm'; } catch (e) { return 'm'; } }
  function setTx(v) { document.body.dataset.tx = v; try { localStorage.setItem('ivod-tx', v); } catch (e) {} $$('[data-txs]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.txs === v)); }); }
  setTx(getTx());

  /* ---------- 名詞小視窗：點名詞打開，不用滑過 ---------- */
  function closePop() { var p = $('#pop'); if (p) p.remove(); }
  function showTerm(btn) {
    closePop();
    var t = btn.dataset.term, g = GL.terms[t]; if (!g) return;
    var r = btn.getBoundingClientRect();
    var mo = GL.more[t];
    var src = g.s && !(g.s.indexOf('本站說明') === 0 && mo) ? '<span class="src">出處：' + esc(g.s) + '</span>' : '';
    var more = mo ? '<a class="linkish more" href="' + (mo.indexOf('learn') === 0 ? '/learn' : '/about') + '#' + mo + '">' + (mo.indexOf('learn') === 0 ? '看更多：立法院怎麼運作' : '看更多：關於資料') + ' →</a>' : '';
    var p = document.createElement('div'); p.className = 'pop'; p.id = 'pop'; p.setAttribute('role', 'tooltip');
    p.innerHTML = '<b>' + esc(t) + '</b>' + esc(g.d) + src + more;
    document.body.appendChild(p);
    var left = Math.min(window.scrollX + r.left, window.scrollX + document.documentElement.clientWidth - p.offsetWidth - 16);
    p.style.left = Math.max(16, left) + 'px'; p.style.top = (window.scrollY + r.bottom + 6) + 'px';
  }

  /* ---------- 篩選列：會議種類 → 在做什麼 → 院會三讀表決＋時間（前端篩已載入的列表） ---------- */
  function markDates(list) {
    var prev = null;
    $$('.item', list).forEach(function (el) { if (el.hidden) return; var d = el.dataset.d; el.classList.toggle('same', !!d && d === prev); prev = d; });
  }
  function kindsOfType(bar, t) {
    var out = [];
    $$('[data-fk][data-kt]', bar).forEach(function (b) { if (b.dataset.kt === t && out.indexOf(b.dataset.fk) < 0) out.push(b.dataset.fk); });
    return out;
  }
  function applyFilter(id) {
    var bar = $('[data-filter="' + id + '"]'); if (!bar) return;
    var lists = $$('[data-list="' + id + '"],[data-group="' + id + '"]'); if (!lists.length) return;
    var ft = $('[data-ft][aria-pressed="true"]', bar), t = ft ? ft.dataset.ft : '';
    var fd = $('[data-fd]', bar), days = fd ? +fd.value || 0 : 0;
    var kb = $('[data-fk][aria-pressed="true"]', bar), fk = kb ? kb.dataset.fk : '';
    var fms = $$('[data-fm][aria-pressed="true"]', bar).map(function (b) { return b.dataset.fm; });
    $$('[data-fk][data-kt]', bar).forEach(function (b) { b.hidden = !!t && kindsOfType(bar, t).indexOf(b.dataset.fk) < 0; });
    var kr = $('.kindrow', bar);
    if (kr) { kr.hidden = !t || kindsOfType(bar, t).length < 1; var kl = $('.kl', kr); if (kl) kl.textContent = t === '黨團協商' ? '誰主持' : '在做什麼'; }
    var mr = $('.mrow', bar);
    if (mr) { mr.hidden = !!t && t !== '院會'; if (mr.hidden) { $$('[data-fm]', mr).forEach(function (b) { b.setAttribute('aria-pressed', 'false'); }); fms = []; } }
    var today = todayStr(), cut = days ? addDays(today, -days) : '';
    lists.forEach(function (list) {
      var lim = +list.dataset.limit || 9999, all = list.dataset.all === '1' || !!(t || fk || fms.length || cut), shown = 0;
      $$('.item', list).forEach(function (el) {
        var ok = (!t || el.dataset.t === t) && (!fk || (el.dataset.k || '').indexOf('|' + fk + '|') >= 0) &&
          fms.every(function (x) { return (el.dataset.m || '').indexOf('|' + x + '|') >= 0; }) &&
          (!cut || el.dataset.d > today || (el.dataset.d >= cut && el.dataset.d <= today));
        el.hidden = !ok || (!all && shown >= lim); if (ok) shown++;
      });
      var em = list.nextElementSibling && list.nextElementSibling.classList.contains('fempty') ? list.nextElementSibling : null;
      markDates(list);
      var more = list.parentElement && $('[data-more="' + list.dataset.list + '"]', list.parentElement); if (more) more.hidden = !!(t || fk || fms.length || cut);
      if (!shown && list.children.length) { if (!em) { em = document.createElement('p'); em.className = 'muted fempty'; em.textContent = list.dataset.group ? '這一區沒有符合條件的會議。' : '沒有符合條件的會議。請放寬篩選條件。'; list.after(em); } }
      else if (em) em.remove();
    });
  }
  $$('.list').forEach(markDates);

  /* ---------- 列表頁（立委、法律、部會）的輸入篩選與黨籍分組 ---------- */
  (function () {
    var pg = $('.idxpage'); if (!pg) return;
    var q = $('.idxq', pg); var g = '';
    var run = function () {
      var t = q ? q.value.trim() : ''; var shown = 0;
      $$('.idxlist li', pg).forEach(function (li) { var ok = (!t || (li.dataset.tx || '').indexOf(t) >= 0) && (!g || li.dataset.g === g); li.hidden = !ok; if (ok) shown++; });
      $$('.idxgrp', pg).forEach(function (sec) { sec.hidden = !$$('li', sec).some(function (li) { return !li.hidden; }); });
      var em = $('.idxempty', pg); if (em) em.hidden = shown > 0;
    };
    if (q) q.addEventListener('input', run);
    $$('[data-ig]', pg).forEach(function (b) { b.addEventListener('click', function () { g = b.dataset.ig; $$('[data-ig]', pg).forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); }); run(); }); });
  })();

  /* ---------- 載入中：換頁時先畫灰色版面輪廓 ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]'); if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
    var href = a.getAttribute('href'); if (!href || href[0] === '#' || /^(https?:|mailto:|javascript:)/.test(href)) return;
    if (a.closest('.toc') || a.dataset.jump) return;
    setTimeout(function () { document.body.classList.add('loading'); window.scrollTo(0, 0); }, 250);
  });
  window.addEventListener('pageshow', function () { document.body.classList.remove('loading'); });

  /* ---------- 影片播放：全部用 Full，Clip 只當時間軸標籤 ---------- */
  var player = $('#player'), hls = null, current = null, playing = null, barDay = null, spot = null;
  var vshut = true, vmin = window.innerWidth <= 860;
  var videos = MEET ? MEET.videos : [];
  var clips = MEET ? MEET.clips : [];
  function videoById(id) { for (var i = 0; i < videos.length; i++) if (videos[i].id === Number(id)) return videos[i]; return null; }
  function loadHls(cb) { if (window.Hls) return cb(); var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.15/hls.min.js'; s.onload = cb; s.onerror = cb; document.head.appendChild(s); }
  function attach(video, done) {
    if (!player || !video || !video.url) return;
    if (current && current.id === video.id) return done && done();
    current = video;
    if (hls) { hls.destroy(); hls = null; }
    if (player.canPlayType('application/vnd.apple.mpegurl')) { player.src = video.url; return done && done(); }
    loadHls(function () {
      if (window.Hls && window.Hls.isSupported()) { hls = new window.Hls(); hls.loadSource(video.url); hls.attachMedia(player); hls.on(window.Hls.Events.MANIFEST_PARSED, function () { done && done(); }); }
      else { player.src = video.url; done && done(); }
    });
  }
  function seek(ivodId, offset, autoplay) {
    var v = videoById(ivodId); if (!v || !player) return;
    var go = function () {
      var doSeek = function () { player.currentTime = Math.max(0, Number(offset)); if (autoplay !== false) player.play().catch(function () {}); };
      if (player.readyState >= 1) doSeek(); else player.addEventListener('loadedmetadata', doSeek, { once: true });
    };
    var same = current && current.id === v.id;
    attach(v, go);
    if (same) go();
  }
  function play(offset, ivod, anchor, label) {
    vshut = false; document.body.classList.remove('vshut');
    var v = videoById(ivod) || videos[0]; if (!v) return;
    playing = { ivod: v.id, offset: Number(offset), anchor: anchor, label: label || '' };
    barDay = v.date;
    $$('.speech.playing,.secblock.playing,.snt.playing').forEach(function (e) { e.classList.remove('playing'); });
    var el = anchor && document.getElementById(anchor); if (el) el.classList.add('playing');
    seek(v.id, offset);
    renderBar();
    var vd = $('#vdock'); if (vd) vd.classList.remove('min');
  }
  function clipIn(v) { return clips.filter(function (c) { return c.ivod === v.id && c.offset != null && c.offsetEnd != null; }).sort(function (a, b) { return a.offset - b.offset; }); }
  function renderBar() {
    if (!MEET || !videos.length || !player) return;
    var v = (playing && videoById(playing.ivod)) || videos[0];
    var segs = clipIn(v);
    var t0 = 0, t1 = Math.max(v.duration || 0, segs.length ? segs[segs.length - 1].offsetEnd : 1, 1);
    var pct = function (t) { return (t - t0) / Math.max(t1 - t0, 1) * 100; };
    var names = []; clips.forEach(function (s) { if (names.indexOf(s.name) < 0) names.push(s.name); });
    if (spot && names.indexOf(spot) < 0) spot = null;
    var cnt = function (n) { return clips.filter(function (s) { return s.name === n; }).length; };
    var cur = playing;
    var curP = cur ? pct(player.currentTime || cur.offset) : null;
    var tl = segs.length ? segs.map(function (s, i) {
      return '<button type="button" class="seg ' + (i % 2 ? 'alt' : '') + ' ' + (spot ? (s.name === spot ? 'hit' : 'dim') : '') + '" style="left:' + pct(s.offset) + '%;width:' + Math.max(pct(s.offsetEnd) - pct(s.offset), .6) + '%" title="' + esc(s.name) + ' ' + fmt(s.offset) + '–' + fmt(s.offsetEnd) + '" aria-label="' + esc(s.name) + ' 委員，' + fmt(s.offset) + '" data-seg="' + i + '"></button>';
    }).join('') + (curP != null ? '<span class="cursor" style="left:' + curP + '%"></span>' : '') : '<span class="none">這場還沒有依發言者分段的時間</span>';
    var days = videos.map(function (x) { return x; });
    var inner = '<button type="button" class="act mbarx" data-act="mbar"><span class="mbx-o">播放控制 ▴</span><span class="mbx-c">收起 ▾</span></button>' +
      '<div class="now">' + (cur ? '<b>' + fmt(player.currentTime || cur.offset) + '</b>' + esc(cur.label) : '<b>' + (segs.length ? fmt(segs[0].offset) : '--:--:--') + '</b><span class="muted">按段落標題旁的「播放」開始</span>') + '</div>' +
      '<div class="tlwrap">' + (videos.length > 1 ? '<div class="row small"><span class="muted">這場會議分 ' + videos.length + ' 支影片，選一支看：</span>' + videos.map(function (x) { return '<button type="button" class="chip btn" aria-pressed="' + (x.id === v.id) + '" data-vid="' + x.id + '">' + esc((x.start || '').slice(5, 16).replace('T', ' ')) + '</button>'; }).join('') + '</div>' : '') +
      (names.length > 1 ? '<div class="row small spotrow"><label class="muted">只看一位發言者 <select data-spot><option value="">全部</option>' + names.map(function (n) { return '<option value="' + esc(n) + '" ' + (n === spot ? 'selected' : '') + '>' + esc(n) + '（' + cnt(n) + ' 段）</option>'; }).join('') + '</select></label></div>' : '') +
      '<div class="tl" id="tl">' + tl + '</div>' + (segs.length ? '<div class="lg"><span class="mono">' + fmt(0).slice(0, 5) + '</span><span>' + (spot ? '亮起來的是 ' + esc(spot) + ' 的段落' : '滑過色塊看是誰') + '</span><span class="mono">' + fmt(t1).slice(0, 5) + '</span></div>' : '') + '</div>' +
      '<div class="btns"><button type="button" class="act" data-act="tocur" ' + (cur ? '' : 'disabled') + '>回到正在播放的段落</button><button type="button" class="act" data-act="clip" ' + (cur ? '' : 'disabled') + ' title="' + (cur ? '' : '先播放一段，才能擷取') + '">分享或下載這段影片</button>' + (v.ivod_url ? '<a class="act" href="' + esc(v.ivod_url) + '" target="_blank" rel="noopener">在 IVOD 開啟 ↗</a>' : '') + '</div>';
    var vc = $('#vctl'), wide = window.matchMedia('(min-width:1200px)').matches;
    document.body.classList.toggle('vshut', vshut);
    if (vc && wide) { barhost.innerHTML = ''; document.body.classList.remove('has-bar'); vc.innerHTML = vshut ? '' : '<div class="vbar" role="region" aria-label="影片播放控制">' + inner + '</div>'; }
    else { if (vc) vc.innerHTML = ''; document.body.classList.add('has-bar'); barhost.innerHTML = '<div class="bar gutter" role="region" aria-label="影片播放列"><div class="wrap">' + inner + '</div></div>'; }
    barhost._segs = segs; barhost._video = v;
    var vd = $('#vdock');
    if (vd) {
      var old = $('.vpill,.vclose,.vtoggle', vd); $$('.vpill,.vclose,.vtoggle', vd).forEach(function (x) { x.remove(); });
      if (wide) {
        if (vshut) { var b = document.createElement('button'); b.type = 'button'; b.className = 'vpill'; if (cur) { b.dataset.act = 'vopen'; } else if (segs.length) { b.dataset.play = segs[0].offset; b.dataset.ivod = v.id; b.dataset.label = segs[0].name + ' 委員'; } else { b.dataset.play = 0; b.dataset.ivod = v.id; b.dataset.label = ''; } b.innerHTML = '<span class="vplay">▶</span>' + (cur ? '展開影片 <span class="mono small">' + fmt(cur.offset) + '</span>' : '看影片'); vd.appendChild(b); }
        else { var c = document.createElement('button'); c.type = 'button'; c.className = 'linkish small vclose'; c.dataset.act = 'vshut'; c.textContent = '收起影片，讓逐字稿變寬 ✕'; vd.insertBefore(c, vd.firstChild); }
      } else {
        vd.classList.toggle('min', vmin);
        var tg = document.createElement('button'); tg.type = 'button'; tg.className = 'linkish small vtoggle'; tg.dataset.act = 'vmin'; tg.textContent = vmin ? '顯示影片 ▾' : '收起影片 ▴'; vd.insertBefore(tg, vd.firstChild);
      }
    }
    $$('.toc li[data-person]').forEach(function (li) { li.classList.toggle('dimmed', !!spot && li.dataset.person !== spot); li.classList.toggle('hit', !!spot && li.dataset.person === spot); });
    $$('.secblock[data-person]').forEach(function (s) { s.classList.toggle('hit', !!spot && s.dataset.person === spot); });
  }
  if (player && MEET) {
    attach(videos[0]);
    document.body.classList.add('vshut');
    renderBar();
    var sentences = $$('.snt[data-play]'), lastHl = null, lastTick = 0;
    player.addEventListener('timeupdate', function () {
      var t = player.currentTime;
      if (sentences.length && current) {
        var best = null;
        for (var i = 0; i < sentences.length; i++) { var s = sentences[i]; if (Number(s.dataset.ivod) !== current.id) continue; if (Number(s.dataset.play) <= t) best = s; else break; }
        if (best !== lastHl) { if (lastHl) lastHl.classList.remove('playing'); if (best) best.classList.add('playing'); lastHl = best; }
      }
      if (Date.now() - lastTick > 1000) { lastTick = Date.now(); var nb = $('.now b'); if (nb && playing) nb.textContent = fmt(t); var cu = $('#tl .cursor'); if (cu && barhost._video) { var tt1 = Math.max(barhost._video.duration || 1, 1); cu.style.left = (t / tt1 * 100) + '%'; } }
    });
    var params = new URLSearchParams(location.search);
    if (params.get('t')) { vshut = false; document.body.classList.remove('vshut'); seek(params.get('ivod') || (videos[0] && videos[0].id), params.get('t'), false); playing = { ivod: Number(params.get('ivod') || videos[0].id), offset: Number(params.get('t')), anchor: '', label: '' }; renderBar(); }
    window.addEventListener('resize', renderBar);
  }

  /* ---------- 引用與分享面板 ---------- */
  function speechInfo(key) {
    var el = document.getElementById(key); if (!el) return null;
    var txEl = $('.tx', el); var text = txEl ? txEl.textContent.trim() : '';
    return { el: el, key: key, who: el.dataset.who || '', text: text, t: el.dataset.t != null ? Number(el.dataset.t) : null, tend: el.dataset.tend != null ? Number(el.dataset.tend) : null, ivod: el.dataset.ivod ? Number(el.dataset.ivod) : null, est: el.dataset.est === '1' };
  }
  function openQuote(key, from) {
    if (!MEET) return;
    var b = speechInfo(key); if (!b) return;
    var selObj = window.getSelection && window.getSelection(); var sel = selObj ? String(selObj).trim() : '';
    var useSel = sel && selObj.anchorNode && b.el.contains(selObj.anchorNode);
    var text = useSel ? sel : b.text;
    if (text.length > 220) text = text.slice(0, 220) + '……';
    var isAI = MEET.version === 'ai';
    var gz = MEET.gazette;
    var ver = isAI ? 'AI 轉錄稿（電腦自動產生，可能有誤，請以原音核對）' : '公報定稿' + (gz && gz.vol ? '（' + gz.vol + (gz.pages ? '，這段所在文件的第 ' + gz.pages + ' 頁' : '') + '）' : '');
    var url = location.origin + '/meet/' + encodeURIComponent(MEET.id) + (isAI ? '?from=ai' : (gz && gz.doc ? '?doc=' + encodeURIComponent(gz.doc) : '')) + '#' + key;
    var vt = b.t; var qs = from != null ? from : vt;
    var vday = (MEET.dates && MEET.dates[0]) || MEET.date;
    var cite = '「' + text + '」\n—— ' + b.who + '，' + MEET.title + '，' + String(vday).replace(/-/g, '/') + '\n版本：' + ver + '\n' + (vt != null ? '影片：' + (isAI ? '' : '約 ') + '這段發言從 ' + fmt(vt) + ' 開始' + (isAI ? '' : '（公報版的時間是估計值）') + '\n' : '') + '連結：' + url;
    var cut = text.slice(-2) === '……' && !useSel;
    var end = b.tend != null ? b.tend : (vt != null ? vt + 180 : null);
    var dl = (vt != null && end != null && b.ivod) ? '<div class="grp"><h3>下載這段影片</h3><div class="row small"><label>開始 <input type="text" id="qs" value="' + fmt(qs) + '" placeholder="hh:mm:ss"></label><label>結束 <input type="text" id="qe" value="' + fmt(end) + '" placeholder="hh:mm:ss"></label><button type="button" class="act" data-act="dl" data-ivod="' + b.ivod + '">擷取並下載</button></div><p class="muted small" id="dlstatus">' + (from != null ? '已帶入正在播放的時間，到這段發言結束' : '已帶入這段發言的開始與結束時間') + '，可以再調整。</p></div>' : '';
    layer.innerHTML = isAI ?
      '<div class="sheet-bg" data-close><div class="sheet" role="dialog" aria-modal="true" aria-label="分享這段"><div class="sechead"><h2>分享這段</h2><button type="button" class="linkish" data-close>關閉</button></div><div class="banner ai small"><span>AI 轉錄稿可能有錯字或認錯人，所以不提供引文。公報出版後，這段才能引用。</span></div><div class="row"><button type="button" class="act primary" data-copytext="' + esc(url) + '">複製這段的連結</button></div><p class="muted small">對方打開連結，影片會停在這段' + (vt != null ? '（' + fmt(vt) + '）' : '') + '，可以自己聽原音。</p>' + dl + '</div></div>' :
      '<div class="sheet-bg" data-close><div class="sheet" role="dialog" aria-modal="true" aria-label="引用這段話"><div class="sechead"><h2>引用這段話</h2><button type="button" class="linkish" data-close>關閉</button></div><p class="muted small">' + (useSel ? '已帶入選取的句子。' : cut ? '這段超過 220 字，引文只帶入開頭。想引用特定幾句，請先選取句子再按「引用這段」。' : '帶入了整段。想只引用其中幾句，請先選取句子再按「引用這段」。') + '</p><pre id="cite">' + esc(cite) + '</pre><div class="row"><button type="button" class="act primary" data-copy="cite">複製引文</button><button type="button" class="act" data-copytext="' + esc(url) + '">複製這段的連結</button></div>' + (b.who === '發言者待確認' ? '<div class="banner ai small"><span>這段的發言者待確認。引用前，請播放這個段落確認是誰說的。</span></div>' : '') + dl + '</div></div>';
  }
  function parseTc(s) { var p = String(s || '').trim().split(':').map(Number); if (p.some(isNaN) || !p.length) return NaN; if (p.length === 3) return p[0] * 3600 + p[1] * 60 + p[2]; if (p.length === 2) return p[0] * 60 + p[1]; return p[0]; }
  function curKey() {
    if (!playing || !playing.anchor) return null;
    var m = playing.anchor.match(/^s-(.+)-\d+$/); if (m) return m[1];
    var el = document.getElementById(playing.anchor); if (!el) return null;
    if (el.classList.contains('speech')) return el.dataset.key;
    var sp = $('.speech:not(.rec):not(.chair)', el) || $('.speech', el); return sp ? sp.dataset.key : null;
  }

  /* ---------- 段落目錄：正在讀的那一項標亮 ---------- */
  if (MEET && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (!en.isIntersecting) return; var a = en.target.dataset.anchor; $$('.toc li').forEach(function (li) { li.classList.toggle('on', li.dataset.anchor === a); }); });
    }, { rootMargin: '-80px 0px -70% 0px' });
    $$('.secblock[data-anchor]').forEach(function (s) { io.observe(s); });
  }

  /* ---------- 事件 ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('button,a,[data-close],.snt'); if (!t) { closePop(); return; }
    var ds = t.dataset;
    if (t.classList.contains('term')) { e.preventDefault(); showTerm(t); return; }
    closePop();
    if (ds.close !== undefined && (e.target === t || t.tagName === 'BUTTON')) { layer.innerHTML = ''; return; }
    if (ds.act === 'minfo') { var on = document.body.classList.toggle('minfo'); $$('.minfobtn').forEach(function (b) { b.setAttribute('aria-expanded', String(on)); }); return; }
    if (['msearch', 'mnav', 'mticker', 'mbar', 'mtoc'].indexOf(ds.act) >= 0) { var c = ds.act, on2 = !document.body.classList.contains(c); if (c === 'msearch' || c === 'mnav') document.body.classList.remove('msearch', 'mnav'); document.body.classList.toggle(c, on2); if (c === 'msearch' && on2) { var q = $('#hq'); if (q) q.focus(); } return; }
    if (ds.act === 'retry') { location.reload(); return; }
    if (ds.act === 'vmin') { vmin = !vmin; renderBar(); return; }
    if (ds.act === 'vshut') { vshut = true; renderBar(); return; }
    if (ds.act === 'vopen') { vshut = false; renderBar(); return; }
    if (ds.act === 'tocur') { if (playing) { var el = document.getElementById(playing.anchor); if (el) el.scrollIntoView({ block: 'center' }); } return; }
    if (ds.act === 'clip') { var k = curKey(); if (k) openQuote(k, player ? player.currentTime : null); return; }
    if (ds.act === 'dl') {
      var st = parseTc($('#qs').value), en = parseTc($('#qe').value), stat = $('#dlstatus'), v = videoById(ds.ivod);
      if (isNaN(st) || isNaN(en) || !v) { stat.textContent = '請輸入正確的時間格式，例如 00:01:23'; return; }
      t.disabled = true;
      import('/static/js/clip.js').then(function (mod) {
        return mod.clipAndDownload({ masterUrl: v.url, start: st, end: en, filename: 'ivod_' + v.id + '_' + Math.floor(st) + '-' + Math.floor(en) + '.mp4', onStatus: function (m) { stat.textContent = m; } });
      }).catch(function (err) { stat.textContent = '失敗：' + err.message; }).then(function () { t.disabled = false; });
      return;
    }
    if (ds.txs) { setTx(ds.txs); return; }
    if (ds.gl) { var g = ds.glg; $$('.glchip[data-glg="' + g + '"]').forEach(function (b) { b.setAttribute('aria-expanded', String(b === t)); }); var d = document.getElementById('gldef-' + g), gg = GL.terms[ds.gl]; if (d && gg) d.innerHTML = '<b>' + esc(ds.gl) + '</b><p>' + esc(gg.d) + '</p><span class="src">出處：' + esc(gg.s) + '</span>'; return; }
    if (ds.jump !== undefined && ds.play === undefined) { document.body.classList.remove('mtoc'); var el2 = document.getElementById(ds.jump) || document.getElementById('sec-' + ds.jump); if (el2 && t.getAttribute('href').charAt(0) === '#') { e.preventDefault(); el2.scrollIntoView({ block: 'start' }); history.replaceState(null, '', '#' + ds.jump); } return; }
    if (ds.vid) { var vv = videoById(ds.vid); if (vv) { attach(vv); playing = null; barDay = vv.date; renderBar(); } return; }
    if (ds.seg !== undefined) { var s = barhost._segs[+ds.seg]; if (s) { var anchor = null; $$('.secblock[data-person="' + s.name + '"]').some(function (sb) { anchor = sb.id; return true; }); play(s.offset, s.ivod, anchor, s.name + ' 委員'); var el3 = anchor && document.getElementById(anchor); if (el3) el3.scrollIntoView({ block: 'center' }); } return; }
    if (ds.play !== undefined) { e.preventDefault(); document.body.classList.remove('mtoc'); play(ds.play, ds.ivod, ds.anchor || (t.closest('.speech') || {}).id, ds.label); var sp = t.closest('.speech'); if (sp && sp.id) history.replaceState(null, '', '#' + sp.id); return; }
    if (ds.quote) { openQuote(ds.quote); return; }
    if (ds.copy) { copy($('#' + ds.copy).textContent); return; }
    if (ds.copytext) { copy(ds.copytext); return; }
    if (ds.more) { var l = $('[data-list="' + ds.more + '"]'); if (l) { l.dataset.all = '1'; $$('.item', l).forEach(function (x) { x.hidden = false; }); applyFilter(ds.more); markDates(l); } t.remove(); return; }
    if (ds.ft !== undefined) { var bar = t.closest('[data-filter]'); $$('[data-ft]', bar).forEach(function (b) { b.setAttribute('aria-pressed', String(b === t)); }); $$('[data-fk]', bar).forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.fk === '')); }); applyFilter(bar.dataset.filter); return; }
    if (ds.fm !== undefined) { var bar2 = t.closest('[data-filter]'); t.setAttribute('aria-pressed', String(t.getAttribute('aria-pressed') !== 'true')); applyFilter(bar2.dataset.filter); return; }
    if (ds.fk !== undefined) { var bar3 = t.closest('[data-filter]'); $$('[data-fk]', bar3).forEach(function (b) { b.setAttribute('aria-pressed', String(b === t)); }); applyFilter(bar3.dataset.filter); return; }
  });
  document.addEventListener('change', function (e) {
    var bar = e.target.closest('[data-filter]'); if (bar) applyFilter(bar.dataset.filter);
    if (e.target.matches('[data-spot]')) { spot = e.target.value || null; renderBar(); }
    if (e.target.matches('[data-tocorg]')) { var o = e.target.value; $$('.toc li[data-org]').forEach(function (li) { li.hidden = !!o && li.dataset.org !== o && !(li.dataset.org === '' && false); }); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { layer.innerHTML = ''; closePop(); document.body.classList.remove('mtoc', 'mnav', 'msearch'); }
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('snt')) { e.preventDefault(); e.target.click(); }
  });

  /* 從分享連結進來：把那一段捲到畫面上、標亮，影片停在那一段 */
  if (location.hash) {
    var target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (target) {
      setTimeout(function () { target.scrollIntoView({ block: 'start' }); }, 50);
      if (target.classList.contains('speech')) {
        target.classList.add('focus');
        if (MEET && target.dataset.ivod && target.dataset.t != null) { seek(target.dataset.ivod, target.dataset.t, false); }
      }
    }
  }
})();
