/* Landing JS (CSP: script-src 'self'). 1) yüngül scroll reveal (prefers-reduced-motion-a hörmət),
   2) demo forması üçün minimum doldurma vaxtı, 3) dil TƏKLİFİ — avtomatik yönləndirmə YOXDUR (bölmə 2.13). */
(() => {
  document.querySelectorAll('[data-demo-form] input[name="ts"]').forEach((i) => { i.value = String(Date.now()); });

  const store = { get: (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} } };
  document.querySelectorAll("[data-lang-link]").forEach((a) => a.addEventListener("click", () => store.set("ms-lang", a.hreflang)));
  const box = document.querySelector("[data-lang-suggest]");
  const cur = document.documentElement.lang;
  if (box && !store.get("ms-lang")) {
    const prefs = (navigator.languages || [navigator.language || ""]).map((x) => String(x).slice(0, 2).toLowerCase());
    const want = prefs.find((p) => ["az", "en", "ru"].includes(p));
    const alt = want && want !== cur ? document.querySelector(`link[rel="alternate"][hreflang="${want}"]`) : null;
    if (alt) {
      box.querySelector("[data-msg]").textContent = box.dataset["msg" + want[0].toUpperCase() + want[1]];
      const a = box.querySelector("[data-target]"); a.href = new URL(alt.href).pathname; a.textContent = want.toUpperCase(); a.hreflang = want;
      a.addEventListener("click", () => store.set("ms-lang", want));
      box.querySelector("[data-close]").addEventListener("click", () => { store.set("ms-lang", cur); box.hidden = true; });
      box.hidden = false;
    }
  }

  if (matchMedia("(prefers-reduced-motion:reduce)").matches || !("IntersectionObserver" in window)) return;
  const q = "main section:not(.hero) .wrap>h2,main section:not(.hero) .wrap>.lede,.card,.posts li,.faq details,.cta-info,#muraciet form,.modules li,.src-card,.table-scroll,.summary,.keypoints,.stats div";
  const io = new IntersectionObserver((es) => es.forEach((e) => { if (!e.isIntersecting) return; const el = e.target; el.classList.add("in"); io.unobserve(el);
    setTimeout(() => { el.classList.remove("rv", "in"); el.style.removeProperty("--d"); }, 1200); }), { threshold: 0.1, rootMargin: "0px 0px -6% 0px" });
  document.querySelectorAll(q).forEach((el) => { if (el.getBoundingClientRect().top < innerHeight * 0.92) return; el.classList.add("rv");
    el.style.setProperty("--d", Math.min([...el.parentNode.children].indexOf(el), 4) * 90 + "ms"); io.observe(el); });
})();
