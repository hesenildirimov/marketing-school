// Hər dil üçün birləşdirilmiş məzmun: AZ əsasdır, EN/RU tərcümələri (src/_data/i18n/<dil>/) onun üzərinə yazılır.
// Tərcümə olunmayan sahələr (slug, qiymət, şəkil, linklər, tarixlər) həmişə AZ-dan gəlir — iki yerdə redaktə olunmur.
import fs from "node:fs";

const read = (p) => (fs.existsSync(p) ? JSON.parse(fs.readFileSync(p, "utf8")) : null);
const isObj = (v) => v && typeof v === "object" && !Array.isArray(v);
// Massivlər bütöv əvəz olunur (faktlar, modullar, FAQ siyahısı); obyektlər açar üzrə birləşir.
export function merge(base, over) {
  if (over === null || over === undefined) return base;
  if (!isObj(base) || !isObj(over)) return over;
  const out = { ...base };
  for (const [k, v] of Object.entries(over)) out[k] = merge(base[k], v);
  return out;
}

export function load(lang) {
  const dir = "src/_data/";
  const tr = (name) => (lang === "az" ? null : read(`${dir}i18n/${lang}/${name}.json`));
  const files = fs.readdirSync(`${dir}kurslar/`).filter((f) => f.endsWith(".json")).sort();
  const courses = files.map((f) => {
    const az = read(`${dir}kurslar/${f}`);
    return { ...merge(az, lang === "az" ? null : read(`${dir}i18n/${lang}/kurslar/${f}`)), _file: f, _lang: lang };
  });
  const faqAz = read(`${dir}faq.json`);
  const faqTr = tr("faq");
  return {
    lang,
    site: merge(read(`${dir}site.json`), tr("site")),
    home: merge(read(`${dir}home.json`), tr("home")),
    blog: merge(read(`${dir}blog.json`), tr("blog")),
    faq: faqTr ? { ...faqTr, updated: faqTr.updated || faqAz.updated } : faqAz,
    courses,
    ui: read(`${dir}ui.json`)[lang],
  };
}

