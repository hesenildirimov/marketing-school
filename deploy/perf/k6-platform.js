// Yük testi (bölmə 4.6): 100 eyni vaxtlı tələbə, video XARİC (video Bunny CDN-dən gəlir).
// Hər virtual istifadəçi: giriş → kabinet → dərs səhifəsi → video token → 15 saniyədən bir progress heartbeat (~10 dəq),
// token yeniləməsi, arada qeyd. Staging-də işə salın (prod məlumatı yox):
//   k6 run -e BASE=https://staging.marketingschool.az -e USERS=loadtest+{n}@example.test -e PASSWORD=... deploy/perf/k6-platform.js
// Netlify imzalı proxy-dən keçir (real yol). Hədəflər: p95 < 300 ms, xəta < 0.1%.
import http from "k6/http";
import { check, sleep } from "k6";

export const options = {
  scenarios: {
    students: { executor: "ramping-vus", startVUs: 0, stages: [{ duration: "2m", target: 100 }, { duration: "10m", target: 100 }, { duration: "1m", target: 0 }] },
  },
  thresholds: { http_req_failed: ["rate<0.001"], "http_req_duration{kind:api}": ["p(95)<300"], "http_req_duration{kind:page}": ["p(95)<800"] },
};

const BASE = __ENV.BASE || "http://127.0.0.1:8001";
const sameOrigin = { headers: { "Sec-Fetch-Site": "same-origin", Accept: "application/json", "Content-Type": "application/json" } };

export default function () {
  const email = (__ENV.USERS || "loadtest+{n}@example.test").replace("{n}", String(__VU));
  let r = http.post(`${BASE}/platform/login`, { email, password: __ENV.PASSWORD }, { headers: { "Sec-Fetch-Site": "same-origin" }, redirects: 5, tags: { kind: "page" } });
  check(r, { "login ok": (x) => x.status === 200 && x.url.includes("/platform/") });
  r = http.get(`${BASE}/platform/courses/${__ENV.COURSE || "marketinq-kursu"}`, { redirects: 5, tags: { kind: "page" } });
  const lesson = (r.url.match(/lessons\/(\d+)/) || [])[1];
  check(r, { "lesson page": (x) => x.status === 200 && !!lesson });
  if (!lesson) return;
  r = http.post(`${BASE}/platform/api/lessons/${lesson}/playback`, "{}", { ...sameOrigin, tags: { kind: "api" } });
  check(r, { "token": (x) => x.status === 200 });
  for (let pos = 15, i = 0; i < 40; i++, pos += 15) {            // ~10 dəqiqəlik izləmə
    sleep(15);
    r = http.post(`${BASE}/platform/api/lessons/${lesson}/progress`, JSON.stringify({ position: pos, duration: 1200 }), { ...sameOrigin, tags: { kind: "api" } });
    check(r, { "progress": (x) => x.status === 200 });
    if (i === 30) http.post(`${BASE}/platform/api/lessons/${lesson}/playback`, JSON.stringify({ kind: "refresh" }), { ...sameOrigin, tags: { kind: "api" } });
  }
}
