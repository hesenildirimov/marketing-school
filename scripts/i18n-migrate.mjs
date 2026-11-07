#!/usr/bin/env node
// Landing məzmununu dillərə uyğunlaşdıran İDEMPOTENT miqrasiya (bölmə 2.13).
// - AZ faylları (src/_data/*.json, kurslar/*.json) toxunulmur → AZ URL-ləri dəyişmir;
// - src/_data/i18n/{en,ru}/ altında eyni struktur yaradılır/tamamlanır, mövcud tərcümələr heç vaxt əzilmir;
// - `--check`: dəyişiklik yazmır; çatışmayan/boş tərcümə varsa çıxış kodu 1 (CI).
import { migrate } from "../src/_includes/i18n-check.js";

const check = process.argv.includes("--check");
const { changed, empty } = migrate({ write: !check });
for (const p of changed) console.log(`${check ? "dəyişməli" : "yeniləndi"}: ${p}`);
for (const [p, fields] of Object.entries(empty)) console.log(`boş tərcümə: ${p}: ${fields.join(", ")}`);
if (check && (changed.length || Object.keys(empty).length)) process.exit(1);
console.log(changed.length ? `${changed.length} fayl` : "struktur aktualdır");
