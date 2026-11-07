/* Böyük faylları 1 MB-lıq hissələrlə yükləyir (Netlify proxy 26 san limiti), sonra formu "staged" ID-ləri ilə göndərir. */
(() => {
  "use strict";
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const json = (r) => r.json().then((b) => { if (!r.ok) throw new Error(b.message || r.status); return b; });
  document.querySelectorAll("form.submit-form").forEach((form) => {
    form.addEventListener("submit", async (e) => {
      const input = form.querySelector('input[type="file"]');
      if (!input || !input.files.length || !window.fetch) return; // JS olmadan adi multipart (≤ 6 MB)
      e.preventDefault();
      const status = form.querySelector(".up-status"), btn = form.querySelector("button[type=submit]");
      btn.disabled = true;
      try {
        for (const file of input.files) {
          const init = await fetch(form.dataset.uploadInit, { method: "POST", credentials: "same-origin",
            headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf, Accept: "application/json" },
            body: JSON.stringify({ name: file.name, size: file.size }) }).then(json);
          for (let off = 0; off < file.size; off += init.chunk_size) {
            status.textContent = `${file.name}: ${Math.round((off / file.size) * 100)}%`;
            await fetch(`${form.action.replace(/assignments\/\d+\/submit$/, "api/uploads/")}${init.upload_id}?offset=${off}`, {
              method: "PUT", credentials: "same-origin", headers: { "X-CSRF-TOKEN": csrf, "Content-Type": "application/octet-stream", Accept: "application/json" },
              body: file.slice(off, off + init.chunk_size) }).then(json);
          }
          const done = await fetch(`${form.action.replace(/assignments\/\d+\/submit$/, "api/uploads/")}${init.upload_id}/complete`, {
            method: "POST", credentials: "same-origin", headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" } }).then(json);
          const h = document.createElement("input"); h.type = "hidden"; h.name = "staged[]"; h.value = done.file_id; form.append(h);
        }
        input.value = ""; input.disabled = true; form.submit();
      } catch (err) { status.textContent = err.message; btn.disabled = false; }
    });
  });
})();
