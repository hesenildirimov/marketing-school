// Əsas axınlar üçün Playwright e2e (bölmə 6, DoD): demo qeydiyyatı → təsdiq → demo məhdudiyyətləri → ödənişlə tam giriş;
// admin girişi + məcburi 2FA; başqa tələbənin resursuna giriş yoxdur.
import { test, before, after } from "node:test";
import assert from "node:assert/strict";
import crypto from "node:crypto";
import { execFileSync } from "node:child_process";
import { createRequire } from "node:module";

const require = createRequire(import.meta.url);
let chromium;
try { ({ chromium } = require("playwright")); } catch { ({ chromium } = require(execFileSync("npm", ["root", "-g"]).toString().trim() + "/playwright")); }

const BASE = "http://127.0.0.1:8001";
const tinker = (php) => execFileSync("php", ["artisan", "tinker", "--execute", php], { cwd: "platform", env: process.env }).toString().trim();
let browser;
before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

const totp = (secretB32) => {
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  let bits = ""; for (const c of secretB32.replace(/\s|=/g, "")) bits += alphabet.indexOf(c).toString(2).padStart(5, "0");
  const key = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
  const counter = Buffer.alloc(8); counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
  const h = crypto.createHmac("sha1", key).update(counter).digest(); const o = h[h.length - 1] & 15;
  return String(((h.readUInt32BE(o) & 0x7fffffff) % 1e6)).padStart(6, "0");
};

test("demo: qeydiyyat → e-mail linki → parol → yalnız ilk 2 modul, tapşırıq yoxdur → ödənişdən sonra tam giriş", async () => {
  const page = await browser.newPage({ locale: "az-AZ" });
  await page.goto(`${BASE}/platform/demo?kurs=marketinq-kursu`);
  await page.fill("#f-ad", "E2E Tələbə");
  await page.fill("#f-email", "e2e.student@mail.az");
  await page.fill("#f-tel", "050 999 88 77");
  await page.check('input[name="consent"]');
  await page.waitForTimeout(3200); // minimum doldurma vaxtı (bot qoruması)
  await page.click('main button[type="submit"]');
  await page.waitForURL(/check-email/);
  assert.match(await page.textContent("h1"), /E-mailinizi yoxlayın/);

  // E-maildəki link (MAIL_MAILER=log): test üçün yeni təsdiq linki buraxılır (DB-də yalnız hash saxlanılır)
  const url = tinker(`echo \\App\\Support\\System::run(fn () => route('verify.show', ['t' => app(\\App\\Domain\\Auth\\TokenService::class)->issue(\\App\\Models\\User::where('email','e2e.student@mail.az')->first(), 'email_verify', now()->addDay())]));`).split("\n").pop();
  await page.goto(url.replace(/^https?:\/\/[^/]+/, BASE));
  await page.fill("#f-password", "E2ePassword1");
  await page.fill("#f-password_confirmation", "E2ePassword1");
  await page.click('main button[type="submit"]');
  await page.waitForURL(/\/platform\/courses\/marketinq-kursu\/lessons\/\d+/);
  assert.ok(await page.isVisible("#player"), "demo: 1-ci modulun videosu açıqdır");
  assert.equal(await page.locator('[role="tab"]', { hasText: "Ev tapşırığı" }).count(), 0, "demo: tapşırıq tabı yoxdur");
  assert.ok((await page.locator(".is-locked").count()) > 0, "3+ modullar kilidlidir");
  // Kilidli dərsə birbaşa URL ilə keçid
  const lockedHref = await page.evaluate(async () => {
    const r = await fetch(location.pathname.replace(/\d+$/, "") + "0"); return r.status;
  });
  assert.ok([403, 404].includes(lockedHref));
  // Tapşırıq API-si demo üçün 403
  const apiStatus = await page.evaluate(async () => (await fetch("/platform/api/assignments/1", { headers: { Accept: "application/json" } })).status);
  assert.equal(apiStatus, 403);

  // Ödəniş (mərhələ 1: admin əl ilə aktivləşdirir) → eyni hesab, tam giriş
  tinker(`\\App\\Support\\System::run(fn () => app(\\App\\Domain\\Billing\\BillingService::class)->manualActivate(\\App\\Models\\User::where('email','admin@example.test')->first(), \\App\\Models\\User::where('email','e2e.student@mail.az')->first(), \\App\\Models\\Course::where('slug','marketinq-kursu')->first(), 1, 'e2e test'));`);
  await page.reload();
  assert.equal(await page.locator('[role="tab"]', { hasText: "Ev tapşırığı" }).count(), 1, "ödənişdən sonra tapşırıq tabı açılır");
  assert.equal(await page.locator(".is-locked").count(), 0, "bütün modullar açılır");
  await page.click("text=Dərsi tamamladım");
  await page.waitForSelector("text=Dərs tamamlandı");
  await page.close();
});

test("admin: parol + məcburi TOTP 2FA, recovery kodları, admin paneli", async () => {
  const page = await browser.newPage();
  await page.goto(`${BASE}/platform/login`);
  await page.fill("#f-email", "admin@example.test");
  await page.fill("#f-password", "Secret12345");
  await page.click('main button[type="submit"]');
  await page.waitForURL(/\/platform\/2fa/);
  await page.goto(`${BASE}/platform/admin`);
  assert.match(page.url(), /\/platform\/2fa/, "2FA olmadan admin panel açılmır");
  const secret = (await page.textContent("code")).trim();
  await page.fill("#f-code", totp(secret));
  await page.click('main button[type="submit"]');
  try { await page.waitForSelector("ul.codes li", { timeout: 8000 }); } catch (e) { console.error("URL", page.url(), (await page.textContent("main")).slice(0, 400)); throw e; }
  assert.equal(await page.locator("ul.codes li").count(), 10);
  await page.goto(`${BASE}/platform/admin/analytics`);
  assert.match(await page.textContent("h1"), /Analitika/);
  await page.close();
});

test("tələbə başqasının mentorluq/ödəniş resursuna və admin panelə girə bilmir; təhlükəsizlik başlıqları", async () => {
  const page = await browser.newPage();
  await page.goto(`${BASE}/platform/login`);
  await page.fill("#f-email", "student@example.test");
  await page.fill("#f-password", "Secret12345");
  await page.click('main button[type="submit"]');
  await page.waitForURL(/dashboard/);
  const r = await page.goto(`${BASE}/platform/admin/students`);
  assert.equal(r.status(), 403);
  const r2 = await page.goto(`${BASE}/platform/courses/digital-marketinq-kursu`);
  assert.equal(r2.status(), 403, "bir kursun ödənişi digərinə giriş vermir");
  const h = r2.headers();
  assert.match(h["content-security-policy"], /frame-ancestors 'none'/);
  assert.equal(h["x-content-type-options"], "nosniff");
  const cookies = await page.context().cookies();
  const sess = cookies.find((c) => c.name === "ms_session");
  assert.ok(sess.httpOnly && sess.sameSite === "Lax" && sess.path === "/platform");
  await page.close();
});
