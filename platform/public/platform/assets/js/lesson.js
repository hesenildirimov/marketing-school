/* Dərs səhifəsi: tablar (klaviatura ilə), qeydin video anına bağlanması, mobil modul paneli. */
(() => {
  "use strict";
  const tabs = [...document.querySelectorAll('[role="tab"]')];
  const select = (t) => {
    tabs.forEach((x) => { const on = x === t; x.setAttribute("aria-selected", on); x.tabIndex = on ? 0 : -1;
      document.getElementById(x.getAttribute("aria-controls")).hidden = !on; });
    t.focus();
    try { localStorage.setItem("ms-tab", t.id); } catch (e) {}
  };
  tabs.forEach((t, i) => {
    t.addEventListener("click", () => select(t));
    t.addEventListener("keydown", (e) => {
      if (e.key === "ArrowRight") select(tabs[(i + 1) % tabs.length]);
      if (e.key === "ArrowLeft") select(tabs[(i - 1 + tabs.length) % tabs.length]);
    });
  });
  try { const saved = document.getElementById(localStorage.getItem("ms-tab") || ""); if (saved && tabs.includes(saved) && location.hash !== "#nofocus") { saved.click(); saved.blur(); } } catch (e) {}

  const form = document.querySelector(".note-form");
  if (form) {
    const at = form.querySelector('[name="at_second"]'), label = form.querySelector(".note-at");
    form.querySelector("textarea").addEventListener("focus", () => {
      if (!window.msPlayer) return;
      const t = window.msPlayer.time(); if (!t) return;
      at.value = t; const mm = Math.floor(t / 60), ss = String(t % 60).padStart(2, "0");
      label.textContent = label.dataset.tpl.replace("__T__", `${mm}:${ss}`);
    });
  }
  const nav = document.querySelector(".nav-panel");
  if (nav && matchMedia("(max-width: 900px)").matches) nav.open = false;
})();
