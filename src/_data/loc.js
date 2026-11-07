// Hər dil üçün birləşdirilmiş məzmun (məntiq: _includes/i18n.js)
import { load } from "../_includes/i18n.js";
export default () => Object.fromEntries(["az", "en", "ru"].map((l) => [l, load(l)]));
