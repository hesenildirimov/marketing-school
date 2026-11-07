import { load } from "../_includes/i18n.js";
// Kurs səhifələri: hər kurs × hər dil
export default () => ["az", "en", "ru"].flatMap((lang) => load(lang).courses.map((c) => ({ lang, key: c.slug, c })));
