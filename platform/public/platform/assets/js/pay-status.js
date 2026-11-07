/* Ödəniş təsdiqini gözləyir (server-to-server status sorğusu serverdə aparılır), sonra səhifəni yeniləyir. */
(() => {
  const box = document.getElementById("pay-status");
  if (!box || !["created", "pending"].includes(box.dataset.status)) return;
  let n = 0;
  const tick = () => fetch(box.dataset.statusUrl, { headers: { Accept: "application/json" }, credentials: "same-origin" })
    .then((r) => r.json()).then((d) => { if (d.status !== box.dataset.status) location.reload(); else if (++n < 40) setTimeout(tick, 3000); })
    .catch(() => setTimeout(tick, 5000));
  setTimeout(tick, 2000);
})();
