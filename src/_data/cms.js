// Decap CMS konfiqurasiyası. admin/config.yml bu obyektdən generasiya olunur.
import fs from "node:fs";

const s = (name, label, extra = {}) => ({ name, label, widget: "string", ...extra });
const t = (name, label, extra = {}) => ({ name, label, widget: "text", ...extra });
const opt = { required: false };
const strList = (name, label, extra = {}) => ({ name, label, widget: "list", ...extra });
const objList = (name, label, fields, extra = {}) => ({ name, label, widget: "list", fields, ...extra });
const seo = [
  s("seo_title", "SEO başlıq (≤60 simvol)", { pattern: ["^.{10,60}$", "10–60 simvol olmalıdır"] }),
  t("seo_description", "SEO təsvir (≤155 simvol)", { pattern: ["^[\\s\\S]{50,160}$", "50–160 simvol olmalıdır"] }),
];
const ICONS = ["bars", "target", "growth", "case", "book", "ruler", "filter", "people", "open"];
const COURSES = [{ label: "Marketinq", value: "marketinq-kursu" }, { label: "Rəqəmsal Marketinq", value: "digital-marketinq-kursu" }];

const courseFields = [
  s("name", "Kurs adı"), s("slug", "URL (dəyişməyin!)", { hint: "URL dəyişəndə köhnə ünvan itir — SEO zərər görür." }),
  strList("alt_names", "Alternativ adlar (axtarış variantları)", { hint: "Məs: Digital marketing kursu, SMM kursu" }),
  { name: "icon", label: "İkon", widget: "select", options: ICONS },
  ...seo, s("eyebrow", "Üst yazı"), s("h1", "H1 başlıq"), t("lede", "Giriş mətni"),
  t("summary", "Qısa cavab (AI/GEO üçün)", { hint: "2–3 cümlə: kurs nədir, kim keçirir, harada, neçə həftə, nə öyrədilir. Süni intellekt bu mətni sitat gətirir." }),
  strList("chips", "Etiketlər"), s("focus", "Müqayisə: nə öyrədir"), s("for_whom_short", "Müqayisə: kimə uyğundur"),
  t("card_text", "Ana səhifə kart mətni"), t("overview", "Kurs haqqında"),
  objList("facts", "Əsas faktlar cədvəli", [s("label", "Ad"), s("value", "Dəyər")]),
  strList("audience", "Kimlər üçündür"), strList("outcomes", "Kursdan sonra nə bacaracaqsınız"),
  strList("tools", "Alətlər", opt),
  objList("features", "Fərq kartları", [s("title", "Başlıq"), t("text", "Mətn")]),
  objList("modules", "Modullar", [s("title", "Modul adı"), t("text", "Təsvir")]),
  s("teacher_short", "Təlimçi (qısa, müqayisə cədvəli üçün)"),
  { name: "instructor", label: "Təlimçi", widget: "object", fields: [
    s("name", "Ad, soyad"), s("role", "Vəzifə (qısa)"), { name: "photo", label: "Şəkil (kvadrat, 400×400)", widget: "image", required: false }, s("linkedin", "LinkedIn linki", opt),
    s("job_title", "Vəzifə (ingiliscə, schema üçün)"), s("works_for", "İş yeri"),
    strList("knows_about", "Ekspertiza sahələri"),
    strList("highlights", "Önə çıxan faktlar (3–4 qısa maddə)", { hint: "Yalnız doğrulana bilən faktlar: vəzifə, şirkət, nəticə, sertifikat." }),
  ] },
  s("price", "Qiymət (boşdursa göstərilmir)", opt), s("currency", "Valyuta", { default: "AZN" }),
  { name: "updated", label: "Son yenilənmə", widget: "datetime", format: "YYYY-MM-DD", date_format: "YYYY-MM-DD", time_format: false },
];

const file = (name, label, path, fields) => ({ name, label, file: path, format: "json", fields });

const config = {
  backend: { name: "github", repo: "hesenildirimov/marketing-school", branch: "main" },
  local_backend: true,
  locale: "en",
  publish_mode: "editorial_workflow",
  site_url: "https://marketingschool.az",
  media_folder: "src/assets/uploads",
  public_folder: "/uploads",
  slug: { encoding: "ascii", clean_accents: true },
  collections: [
    {
      name: "bloq", label: "Bloq", label_singular: "Məqalə", folder: "src/bloq", create: true,
      extension: "md", format: "frontmatter", slug: "{{slug}}", summary: "{{title}} — {{date}}",
      sortable_fields: ["date", "title"], preview_path: "bloq/{{slug}}/",
      fields: [
        s("title", "Başlıq (H1)", { hint: "Oxucunun axtardığı sual/ifadə ilə yazın. Məs: \"SMM nədir və necə öyrənmək olar\"" }),
        s("seo_title", "SEO başlıq (≤60, boş qala bilər)", { required: false, pattern: ["^.{0,60}$", "≤60 simvol"] }),
        t("description", "Qısa təsvir (≤155)", { pattern: ["^[\\s\\S]{50,160}$", "50–160 simvol"] }),
        { name: "date", label: "Tarix", widget: "datetime", format: "YYYY-MM-DD", date_format: "YYYY-MM-DD", time_format: false },
        { name: "updated", label: "Yenilənmə tarixi", widget: "datetime", format: "YYYY-MM-DD", date_format: "YYYY-MM-DD", time_format: false, required: false },
        strList("tags", "Mövzular", { required: false }),
        { name: "course", label: "Əlaqəli kurs", widget: "select", options: COURSES, required: false },
        strList("key_points", "Qısaca (2–4 əsas fikir)", { required: false, hint: "Məqalənin başında xülasə kimi görünür — AI cavablarında sitat şansını artırır." }),
        objList("sources", "Mənbələr", [s("title", "Ad"), s("url", "Link", opt)], { required: false }),
        s("author", "Müəllif (boşdursa Marketing School)", opt),
        { name: "image", label: "Paylaşım şəkli (1200×630)", widget: "image", required: false },
        { name: "draft", label: "Qaralama (saytda görünmür)", widget: "boolean", default: false, required: false },
        { name: "body", label: "Mətn", widget: "markdown", hint: "Kursa link: [Rəqəmsal Marketinq Kursu](kurs:digital-marketinq-kursu)" },
      ],
    },
    {
      name: "sehifeler", label: "Səhifələr", editor: { preview: false },
      files: [
        file("home", "Ana səhifə", "src/_data/home.json", [
          ...seo, s("eyebrow", "Üst yazı"), s("h1", "H1 (<em> vurğu üçün)"), t("lede", "Giriş mətni"),
          objList("stats", "Statistika", [s("value", "Rəqəm"), s("label", "Ad")]),
          s("why_title", "Niyə biz — başlıq"),
          objList("why", "Niyə biz — kartlar", [{ name: "icon", label: "İkon", widget: "select", options: ICONS }, s("title", "Başlıq"), t("text", "Mətn")]),
          s("courses_title", "Kurslar başlığı"), t("courses_lede", "Kurslar mətni"), s("compare_title", "Müqayisə başlığı"),
          s("team_title", "Təlimçilər başlığı"), t("team_lede", "Təlimçilər mətni"), s("faq_title", "FAQ başlığı"), s("cta_title", "Müraciət başlığı"),
        ]),
        file("brend", "Kurs: Marketinq", "src/_data/kurslar/1-marketinq.json", courseFields),
        file("reqemsal", "Kurs: Rəqəmsal Marketinq", "src/_data/kurslar/2-reqemsal.json", courseFields),
        file("faq", "FAQ", "src/_data/faq.json", [
          ...seo, s("h1", "H1"), t("lede", "Giriş"),
          { name: "updated", label: "Son yenilənmə", widget: "datetime", format: "YYYY-MM-DD", date_format: "YYYY-MM-DD", time_format: false },
          objList("groups", "Qruplar", [s("id", "ID (latın, boşluqsuz)"), s("name", "Ad")]),
          objList("items", "Suallar", [
            s("q", "Sual", { hint: "İnsanların Google/ChatGPT-yə yazdığı kimi: \"Bakıda digital marketinq kursu harada keçirilir?\"" }),
            t("a", "Cavab", { hint: "İlk cümlə birbaşa cavab olsun və 'Marketing School' adını, rəqəmləri daxil etsin. 40–80 söz." }),
            { name: "group", label: "Qrup", widget: "select", options: ["umumi", "format", "reqemsal", "marketinq", "karyera", "qeydiyyat"] },
            { name: "show_on", label: "Bu səhifələrdə də göstər", widget: "select", multiple: true, required: false, options: [{ label: "Ana səhifə", value: "home" }, ...COURSES] },
          ], { summary: "{{fields.q}}" }),
        ]),
        file("blog", "Bloq səhifəsi", "src/_data/blog.json", [...seo, s("h1", "H1"), t("lede", "Giriş")]),
        file("site", "Sayt ayarları", "src/_data/site.json", [
          s("name", "Ad"), strList("alternate_names", "Alternativ adlar"), s("tagline", "Footer mətni"), s("slogan", "Slogan", opt),
          t("description", "Təşkilat təsviri (schema + llms.txt)"),
          s("city", "Şəhər"), s("country", "Ölkə kodu"), s("address", "Ünvan", opt), s("phone", "Telefon", opt), s("email", "E-mail", opt),
          strList("knows_about", "Ekspertiza sahələri"),
          objList("social", "Sosial şəbəkələr", [s("name", "Ad"), s("url", "Link")]),
          { name: "updated", label: "Sayt son yenilənmə", widget: "datetime", format: "YYYY-MM-DD", date_format: "YYYY-MM-DD", time_format: false },
          { name: "form", label: "Müraciət forması", widget: "object", fields: [s("title", "Başlıq"), t("lede", "Mətn"), strList("benefits", "Nə alacaqsınız"), s("button", "Düymə"), s("consent", "Razılıq mətni")] },
          { name: "thanks", label: "Təşəkkür səhifəsi", widget: "object", fields: [s("title", "Başlıq"), t("text", "Mətn")] },
        ]),
      ],
    },
  ],
};

// Demo üçün: test-repo backend-ə real məzmun faylları ötürülür.
function tree(paths) {
  const root = {};
  for (const p of paths) {
    const parts = p.split("/"); let node = root;
    parts.slice(0, -1).forEach((k) => (node = node[k] ??= {}));
    node[parts.at(-1)] = { content: fs.readFileSync(p, "utf8") };
  }
  return root;
}
const ls = (dir, ext) => fs.readdirSync(dir).filter((f) => f.endsWith(ext)).map((f) => dir + f);

export default () => {
  const demoFiles = tree([...ls("src/_data/", ".json"), ...ls("src/_data/kurslar/", ".json"), ...ls("src/bloq/", ".md")]);
  // test-repo folder kolleksiyasını tam yol açarı ilə axtarır
  demoFiles["src/bloq"] = demoFiles.src.bloq;
  return { config, demoFiles };
};
