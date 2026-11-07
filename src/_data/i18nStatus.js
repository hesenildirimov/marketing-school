// CMS redaktoru üçün tərcümə göstəricisi (/admin/tercume/): hansı dildə hansı sahə boşdur.
import { migrate } from "../_includes/i18n-check.js";
export default () => migrate({ write: false });
