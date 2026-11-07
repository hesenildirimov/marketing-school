/* Marketing School video player: hls.js + yüngül öz UI.
   Qoruma qatları (bölmə 2.3): qısa ömürlü imzalı token, yeniləmədə serverdə icazə yoxlaması,
   dinamik watermark (tam ekranda da qalır), download düyməsi yoxdur. 100% qoruma mümkün deyil (ekran yazısı). */
(() => {
  "use strict";
  const root = document.getElementById("player");
  if (!root) return;
  const video = root.querySelector("video");
  const poster = root.querySelector(".poster");
  const wm = root.querySelector(".wm");
  const msg = root.querySelector(".p-msg");
  const ctl = root.querySelector(".controls");
  const $ = (s) => ctl.querySelector(s);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const hbSec = parseInt(root.dataset.heartbeat || "15", 10);
  const conn = navigator.connection || {};
  const saveData = !!conn.saveData || ["slow-2g", "2g", "3g"].includes(conn.effectiveType) || conn.type === "cellular";
  const isMobile = matchMedia("(max-width: 760px), (pointer: coarse)").matches;
  let hls = null, tokenPrefix = null, refreshTimer = null, hbTimer = null, started = false, ended = false, lastSent = -1;
  const m = { clickAt: 0, ttff: null, rebuffers: 0, rebufferMs: 0, waitAt: 0, switches: 0, bitrateSum: 0, bitrateT: 0, curBitrate: 0, lastT: 0 };

  const post = (url, body) => fetch(url, { method: "POST", credentials: "same-origin",
    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf, Accept: "application/json" }, body: JSON.stringify(body || {}) });
  const fmt = (s) => { s = Math.max(0, Math.floor(s || 0)); const h = Math.floor(s / 3600), mm = Math.floor((s % 3600) / 60), ss = String(s % 60).padStart(2, "0");
    return h ? `${h}:${String(mm).padStart(2, "0")}:${ss}` : `${mm}:${ss}`; };
  const show = (text) => { msg.textContent = text; msg.hidden = !text; };
  const prefixOf = (src) => (src.match(/\/bcdn_token=[^/]*/) || [null])[0];

  function ended403() {
    ended = true; clearTimeout(refreshTimer); clearInterval(hbTimer);
    try { video.pause(); } catch (e) {}
    if (hls) { hls.destroy(); hls = null; }
    video.removeAttribute("src"); video.load();
    msg.innerHTML = "";
    const p = document.createElement("p"); p.textContent = root.dataset.msgEnded;
    const a = document.createElement("a"); a.className = "btn"; a.href = root.dataset.buy; a.textContent = root.dataset.msgBuy;
    msg.append(p, a); msg.hidden = false;
  }

  async function grant(kind) {
    const r = await post(root.dataset.playback, { kind });
    if (r.status === 403 || r.status === 401) { ended403(); return null; }
    if (!r.ok) throw new Error("playback " + r.status);
    const g = await r.json();
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(refresh, g.refresh_in * 1000);
    return g;
  }

  async function refresh() {
    try {
      const g = await grant("refresh");
      if (!g) return;
      if (hls) { tokenPrefix = prefixOf(g.src); }
      else { const t = video.currentTime, playing = !video.paused; video.src = g.src; video.currentTime = t; if (playing) video.play(); }
    } catch (e) { refreshTimer = setTimeout(refresh, 15000); }
  }

  function placeWatermark(w) {
    wm.textContent = `${w.email} · ${w.code}`;
    const move = () => { wm.style.left = 5 + Math.random() * 55 + "%"; wm.style.top = 5 + Math.random() * 75 + "%"; };
    move(); setInterval(move, 20000);
    // Watermark DOM-dan silinsə yenidən əlavə olunur (kosmetik qoruma)
    new MutationObserver(() => { if (!root.contains(wm)) root.appendChild(wm); }).observe(root, { childList: true });
  }

  function setupQuality(levels) {
    const sel = $('[data-act="quality"]');
    levels.forEach((l, i) => { const o = document.createElement("option"); o.value = i; o.textContent = (l.height || Math.round(l.bitrate / 1000)) + "p"; sel.append(o); });
    sel.addEventListener("change", () => {
      const v = parseInt(sel.value, 10);
      if (v === -1) { hls.autoLevelCapping = cap720(); hls.currentLevel = -1; } else { hls.autoLevelCapping = -1; hls.currentLevel = v; }
    });
  }
  const idxOf = (h) => hls.levels.findIndex((l) => l.height === h);
  const cap720 = () => (saveData ? Math.max(idxOf(720), -1) : -1);

  async function start() {
    if (started) return; started = true; m.clickAt = performance.now();
    poster.hidden = true; ctl.hidden = false;
    let g;
    try { g = await grant("issue"); } catch (e) { show("…"); started = false; poster.hidden = false; return; }
    if (!g) return;
    placeWatermark(g.watermark);
    if (window.Hls && Hls.isSupported()) {
      tokenPrefix = prefixOf(g.src);
      hls = new Hls({
        autoStartLoad: false, capLevelToPlayerSize: true, maxBufferLength: 30, maxMaxBufferLength: 60, backBufferLength: 30,
        enableWorker: true,
        // Token yeniləndikdə növbəti seqmentlər yeni imza ilə soruşulur
        xhrSetup: (xhr, url) => { if (tokenPrefix) url = url.replace(/\/bcdn_token=[^/]*/, tokenPrefix); xhr.open("GET", url, true); },
      });
      hls.on(Hls.Events.MANIFEST_PARSED, () => {
        const i720 = idxOf(720);
        if (i720 >= 0) hls.startLevel = i720;           // 720p ilə başla, şəbəkə yaxşıdırsa 1080p-yə qalx
        hls.autoLevelCapping = cap720();                 // Save-Data / mobil şəbəkə: avtomatik 1080p yoxdur
        setupQuality(hls.levels);
        hls.startLoad(g.resume_at > 5 ? g.resume_at : -1);
      });
      hls.on(Hls.Events.LEVEL_SWITCHED, (e, d) => { m.switches++; trackBitrate(); m.curBitrate = hls.levels[d.level]?.bitrate || 0; });
      let netRetry = 0;
      hls.on(Hls.Events.ERROR, (e, d) => {
        if (!d.fatal) return;
        if (d.type === Hls.ErrorTypes.NETWORK_ERROR) {
          if (d.response && (d.response.code === 403 || d.response.code === 401)) { refresh(); }
          show(root.dataset.msgRetry);
          setTimeout(() => { show(""); hls && hls.startLoad(); }, Math.min(30000, 2000 * 2 ** netRetry++));
        } else if (d.type === Hls.ErrorTypes.MEDIA_ERROR) { hls.recoverMediaError(); }
        else { show("Error"); }
      });
      hls.on(Hls.Events.FRAG_LOADED, () => { netRetry = 0; });
      hls.loadSource(g.src); hls.attachMedia(video);
    } else if (video.canPlayType("application/vnd.apple.mpegurl")) {
      video.src = g.src;                                  // Safari/iOS native HLS
      video.addEventListener("loadedmetadata", () => { if (g.resume_at > 5 && g.resume_at < video.duration - 5) video.currentTime = g.resume_at; }, { once: true });
    }
    video.play().catch(() => {});
    hbTimer = setInterval(() => { if (!video.paused) heartbeat(); }, hbSec * 1000);
  }

  function trackBitrate() { const now = performance.now(); if (m.lastT && m.curBitrate) { m.bitrateSum += m.curBitrate * (now - m.lastT); m.bitrateT += now - m.lastT; } m.lastT = now; }
  function heartbeat(beacon) {
    if (ended || !started || !root.dataset.progress) return;
    const pos = Math.floor(video.currentTime || 0);
    if (pos === lastSent && !beacon) return;
    lastSent = pos;
    const body = JSON.stringify({ position: pos, duration: Math.floor(video.duration || 0) || null });
    if (beacon && navigator.sendBeacon) navigator.sendBeacon(root.dataset.progress, new Blob([body], { type: "text/plain" }));
    else post(root.dataset.progress, JSON.parse(body)).catch(() => {});
  }
  function sendMetrics() {
    if (!started || m.sent || !root.dataset.metrics) return; m.sent = true; trackBitrate();
    const body = JSON.stringify({ ttff_ms: m.ttff, rebuffer_count: m.rebuffers, rebuffer_ms: Math.round(m.rebufferMs), quality_switches: m.switches,
      avg_bitrate_kbps: m.bitrateT ? Math.round(m.bitrateSum / m.bitrateT / 1000) : null, device: isMobile ? "mobile" : "desktop" });
    navigator.sendBeacon && navigator.sendBeacon(root.dataset.metrics, new Blob([body], { type: "text/plain" }));
  }

  // --- UI ---
  poster.addEventListener("click", start);
  const playBtn = $('[data-act="play"]'), seek = $('[data-act="seek"]');
  const toggle = () => { if (!started) return start(); video.paused ? video.play() : video.pause(); };
  playBtn.addEventListener("click", toggle);
  video.addEventListener("click", toggle);
  video.addEventListener("play", () => { playBtn.textContent = "❚❚"; });
  video.addEventListener("pause", () => { playBtn.textContent = "▶"; heartbeat(); });
  video.addEventListener("playing", () => {
    if (m.ttff === null) m.ttff = Math.round(performance.now() - m.clickAt);
    if (m.waitAt) { m.rebufferMs += performance.now() - m.waitAt; m.waitAt = 0; }
  });
  video.addEventListener("waiting", () => { if (m.ttff !== null) { m.rebuffers++; m.waitAt = performance.now(); } });
  video.addEventListener("timeupdate", () => {
    ctl.querySelector('[data-t="cur"]').textContent = fmt(video.currentTime);
    ctl.querySelector('[data-t="dur"]').textContent = fmt(video.duration);
    if (video.duration) seek.value = Math.round((video.currentTime / video.duration) * 1000);
    // Növbəti dərsin ilk hissəsini yalnız sonda əvvəlcədən yüklə
    if (video.duration && video.duration - video.currentTime < 30 && root.dataset.nextPlayback && !root.dataset.prefetched) {
      root.dataset.prefetched = "1";
      post(root.dataset.nextPlayback, { kind: "issue" }).then((r) => (r.ok ? r.json() : null)).then((g) => { if (g) fetch(g.src, { mode: "cors" }).catch(() => {}); });
    }
  });
  video.addEventListener("ended", () => { heartbeat(); if (root.dataset.next) { const a = document.querySelector(".lesson-head a.btn-ghost"); a && a.focus(); } });
  seek.addEventListener("input", () => { if (video.duration) video.currentTime = (seek.value / 1000) * video.duration; });
  $('[data-act="mute"]').addEventListener("click", (e) => { video.muted = !video.muted; e.target.textContent = video.muted ? "🔇" : "🔊"; });
  $('[data-act="speed"]').addEventListener("change", (e) => { video.playbackRate = parseFloat(e.target.value); });
  const ch = $('[data-act="chapter"]');
  if (ch) ch.addEventListener("change", () => { if (ch.value !== "") { if (!started) start(); video.currentTime = parseInt(ch.value, 10); ch.value = ""; } });
  const fs = () => (document.fullscreenElement ? document.exitFullscreen() : (root.requestFullscreen || root.webkitRequestFullscreen).call(root));
  $('[data-act="fs"]').addEventListener("click", fs);
  video.addEventListener("dblclick", (e) => { e.preventDefault(); fs(); });
  root.addEventListener("contextmenu", (e) => e.preventDefault());
  document.addEventListener("keydown", (e) => {
    if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable) return;
    if (e.key === " " || e.code === "Space") { e.preventDefault(); toggle(); }
    else if (e.key === "ArrowRight") { video.currentTime = Math.min((video.duration || 0), video.currentTime + 10); }
    else if (e.key === "ArrowLeft") { video.currentTime = Math.max(0, video.currentTime - 10); }
    else if (e.key === "f" || e.key === "F") { fs(); }
  });
  document.querySelectorAll("[data-seek]").forEach((b) => b.addEventListener("click", () => { const t = parseInt(b.dataset.seek, 10); if (!started) start().then(() => (video.currentTime = t)); else video.currentTime = t; }));
  document.addEventListener("visibilitychange", () => { if (document.visibilityState === "hidden") { heartbeat(true); sendMetrics(); } });
  addEventListener("pagehide", () => { heartbeat(true); sendMetrics(); });
  window.msPlayer = { time: () => Math.floor(video.currentTime || 0) };
})();
