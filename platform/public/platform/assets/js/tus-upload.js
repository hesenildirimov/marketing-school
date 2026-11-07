/* Minimal TUS 1.0 klienti: admin brauzeri videonu birbaşa Bunny Stream-ə yükləyir (Netlify proxy-dən keçmir).
   5 MB-lıq hissələr, kəsilsə Upload-Offset ilə davam edir. */
(() => {
  "use strict";
  const box = document.querySelector(".tus");
  if (!box) return;
  const input = box.querySelector("input[type=file]"), bar = box.querySelector(".progress span"), status = box.querySelector(".tus-status");
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const CHUNK = 5 * 1024 * 1024;
  const setPct = (p) => { bar.className = "w-" + Math.max(0, Math.min(100, Math.round(p))); };
  const b64 = (s) => btoa(unescape(encodeURIComponent(s)));
  input.addEventListener("change", async () => {
    const file = input.files[0]; if (!file) return;
    input.disabled = true; status.textContent = box.dataset.uploading;
    try {
      const t = await fetch(box.dataset.ticket, { method: "POST", headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" }, credentials: "same-origin" }).then((r) => { if (!r.ok) throw new Error(r.status); return r.json(); });
      const create = await fetch(t.endpoint, { method: "POST", headers: Object.assign({ "Tus-Resumable": "1.0.0", "Upload-Length": String(file.size),
        "Upload-Metadata": `filetype ${b64(file.type)},title ${b64(file.name)}` }, t.headers) });
      if (!create.ok) throw new Error("create " + create.status);
      const loc = new URL(create.headers.get("Location"), t.endpoint).toString();
      let offset = 0, tries = 0;
      while (offset < file.size) {
        try {
          const r = await fetch(loc, { method: "PATCH", headers: Object.assign({ "Tus-Resumable": "1.0.0", "Upload-Offset": String(offset), "Content-Type": "application/offset+octet-stream" }, t.headers),
            body: file.slice(offset, offset + CHUNK) });
          if (!r.ok) throw new Error("patch " + r.status);
          offset = parseInt(r.headers.get("Upload-Offset"), 10); tries = 0; setPct((offset / file.size) * 100);
        } catch (e) {
          if (++tries > 5) throw e;
          await new Promise((res) => setTimeout(res, 2000 * tries));
          const h = await fetch(loc, { method: "HEAD", headers: Object.assign({ "Tus-Resumable": "1.0.0" }, t.headers) });
          offset = parseInt(h.headers.get("Upload-Offset") || String(offset), 10);
        }
      }
      status.textContent = box.dataset.done;
    } catch (e) { status.textContent = "Error: " + e.message; input.disabled = false; }
  });
})();
