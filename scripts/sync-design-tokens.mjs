// Landing dizayn sisteminin (src/assets/style.css) :root CSS dəyişənlərini platformaya kopyalayır.
// Tək mənbə landing-dir. CI-da `node scripts/sync-design-tokens.mjs --check` uyğunsuzluğu tutur.
import fs from "node:fs";

const src = fs.readFileSync("src/assets/style.css", "utf8");
const blocks = [...src.matchAll(/:root\s*\{[^}]*\}/g)].map((m) => m[0]);
const out = `/* AVTOMATİK GENERASİYA: scripts/sync-design-tokens.mjs — əl ilə dəyişməyin. Mənbə: src/assets/style.css */\n${blocks.join("\n")}\n`;
const dest = "platform/public/platform/assets/css/tokens.css";
if (process.argv.includes("--check")) {
  const cur = fs.existsSync(dest) ? fs.readFileSync(dest, "utf8") : "";
  if (cur !== out) { console.error("tokens.css köhnədir: node scripts/sync-design-tokens.mjs"); process.exit(1); }
  console.log("tokens.css aktualdır");
} else {
  fs.writeFileSync(dest, out);
  console.log(`tokens.css yazıldı (${blocks.length} blok)`);
}
