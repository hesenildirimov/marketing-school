// Bloq yazılarının dil üzrə indeksi (translation_key → slug). hreflang və "tərcüməsi olmayan yazı görünmür" qaydası üçün.
import fs from "node:fs";

const DIRS = { az: "src/bloq", en: "src/en/blog", ru: "src/ru/blog" };
export default () => {
  const idx = { az: {}, en: {}, ru: {} };
  for (const [lang, dir] of Object.entries(DIRS)) {
    if (!fs.existsSync(dir)) continue;
    for (const f of fs.readdirSync(dir).filter((x) => x.endsWith(".md"))) {
      const src = fs.readFileSync(`${dir}/${f}`, "utf8");
      const fm = src.match(/^---\n([\s\S]*?)\n---/);
      if (!fm || /^draft:\s*true/m.test(fm[1])) continue;
      const key = (fm[1].match(/^translation_key:\s*["']?([\w-]+)/m) || [])[1] || f.replace(/\.md$/, "");
      idx[lang][key] = f.replace(/\.md$/, "");
    }
  }
  return idx;
};
