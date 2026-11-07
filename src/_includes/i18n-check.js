// Tərcümə strukturunun yoxlanması (miqrasiya skripti, CMS göstəricisi və testlər eyni məntiqi istifadə edir).
import fs from "node:fs";

// Tərcümə olunmayan sahələr: həmişə AZ-dan gəlir (iki yerdə redaktə olunmur)
export const NON_TRANSLATABLE = new Set(["slug", "icon", "price", "currency", "updated", "photo", "linkedin", "job_title", "works_for", "tools",
  "name@site", "country", "address", "phone", "email", "social", "slogan", "value@stats", "id@groups", "group", "show_on", "_file", "_lang"]);

const isObj = (v) => v && typeof v === "object" && !Array.isArray(v);
const skip = (key, parent) => NON_TRANSLATABLE.has(key) || NON_TRANSLATABLE.has(`${key}@${parent}`);

/** AZ strukturuna görə tərcümə skeleti: mövcud dəyərlər saxlanılır, çatışmayanlar "" ilə əlavə olunur. */
export function skeleton(az, tr, parent = "") {
  if (Array.isArray(az)) {
    if (Array.isArray(tr) && tr.length) return tr.map((x, i) => skeleton(az[i] ?? az[0], x, parent));
    return az.map((x) => skeleton(x, undefined, parent));
  }
  if (isObj(az)) {
    const out = isObj(tr) ? { ...tr } : {};
    for (const [k, v] of Object.entries(az)) {
      if (skip(k, parent)) continue;
      out[k] = skeleton(v, isObj(tr) ? tr[k] : undefined, k);
    }
    return out;
  }
  if (typeof az === "string") return typeof tr === "string" ? tr : "";
  return tr !== undefined ? tr : az;
}

export function emptyFields(obj, path = "") {
  if (Array.isArray(obj)) return obj.flatMap((x, i) => emptyFields(x, `${path}[${i}]`));
  if (isObj(obj)) return Object.entries(obj).flatMap(([k, v]) => emptyFields(v, path ? `${path}.${k}` : k));
  return typeof obj === "string" && obj.trim() === "" ? [path] : [];
}

export const FILES = () => [
  ...["site", "home", "blog", "faq"].map((n) => ({ az: `src/_data/${n}.json`, rel: `${n}.json` })),
  ...fs.readdirSync("src/_data/kurslar").filter((f) => f.endsWith(".json")).sort().map((f) => ({ az: `src/_data/kurslar/${f}`, rel: `kurslar/${f}` })),
];

/** @return {{changed: string[], empty: Record<string, string[]>}} */
export function migrate({ write = true, langs = ["en", "ru"] } = {}) {
  const changed = [];
  const empty = {};
  for (const lang of langs) {
    for (const f of FILES()) {
      const az = JSON.parse(fs.readFileSync(f.az, "utf8"));
      const path = `src/_data/i18n/${lang}/${f.rel}`;
      const cur = fs.existsSync(path) ? JSON.parse(fs.readFileSync(path, "utf8")) : undefined;
      // FAQ hər dildə müstəqil siyahıdır (İngilis FAQ-ı öz suallarına malikdir): yalnız mövcudluq və boşluq yoxlanılır
      const next = f.rel === "faq.json" && cur ? cur : skeleton(az, cur, f.rel.replace(/\.json$/, ""));
      const text = JSON.stringify(next, null, 2) + "\n";
      if (!cur || JSON.stringify(cur, null, 2) + "\n" !== text) {
        changed.push(path);
        if (write) {
          fs.mkdirSync(path.replace(/\/[^/]+$/, ""), { recursive: true });
          fs.writeFileSync(path, text);
        }
      }
      const e = emptyFields(next);
      if (e.length) empty[path] = e;
    }
  }
  return { changed, empty };
}
