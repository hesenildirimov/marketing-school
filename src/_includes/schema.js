// JSON-LD qurucuları (bölmə 2.13): hər dildə tərcümə olunmuş name/description, səhifənin inLanguage dəyəri;
// kursun TƏDRİS dili isə bütün dillərdə "az"-dır. sameAs, address, areaServed hər dildə eynidir.
const SITE = "https://marketingschool.az";
const ORG = { "@id": `${SITE}/#organization` };
const abs = (slug) => `${SITE}/${slug ? slug + "/" : ""}`;
const plain = (s) => String(s ?? "").replace(/\[([^\]]+)\]\([^)]+\)/g, "$1").replace(/[*_`#>]/g, "").trim();
const ctx = (o) => ({ "@context": "https://schema.org", ...o });

export const faqPage = (items, slug, lang = "az", extra = {}) => ctx({
  "@type": "FAQPage", "@id": abs(slug) + "#faq", inLanguage: lang, ...extra,
  mainEntity: items.map((f) => ({ "@type": "Question", name: f.q, acceptedAnswer: { "@type": "Answer", text: plain(f.a) } })),
});

export const person = (p) => ({
  "@type": "Person", name: p.name, jobTitle: p.job_title, description: p.role,
  worksFor: p.works_for ? { "@type": "Organization", name: p.works_for } : undefined,
  knowsAbout: p.knows_about, image: p.photo ? `${SITE}/${p.photo}` : undefined, sameAs: p.linkedin ? [p.linkedin] : undefined,
});

const SCHEDULE_TEXT = {
  az: "Qeydə alınmış videodərslər (istənilən vaxt), hər ayın 2-ci və 4-cü şənbəsi canlı onlayn sual-cavab, real keyslərə əsaslanan ev tapşırıqları, bütün dərslərdən sonra fərdi mentorluq sessiyası. Tədris dili: Azərbaycan dili.",
  en: "Recorded video lessons (any time), live online Q&A on the 2nd and 4th Saturday of every month, homework based on real cases, and a one-to-one mentoring session after all lessons. Language of instruction: Azerbaijani.",
  ru: "Видеоуроки в записи (в любое время), живые онлайн-встречи во 2-ю и 4-ю субботу каждого месяца, домашние задания на реальных кейсах и индивидуальная менторская сессия после всех уроков. Язык обучения: азербайджанский.",
};
const CREDENTIAL = { az: "Diplom (sertifikat)", en: "Diploma (certificate)", ru: "Диплом (сертификат)" };

export const course = (c, url, lang = "az") => ctx({
  "@type": "Course", "@id": url + "#course", url,
  name: c.name, alternateName: c.alt_names, description: c.summary,
  inLanguage: "az",                           // kursun tədris dili
  provider: ORG, educationalLevel: "Beginner", dateModified: c.updated,
  educationalCredentialAwarded: CREDENTIAL[lang], timeRequired: "P3M", isAccessibleForFree: false,
  instructor: person(c.instructor),
  teaches: c.modules.map((m) => m.title),
  syllabusSections: c.modules.map((m, i) => ({ "@type": "Syllabus", position: i + 1, name: m.title, description: m.text })),
  audience: { "@type": "EducationalAudience", audienceType: c.audience.join("; ") },
  offers: {
    "@type": "Offer", category: "Paid", price: c.price || "99", priceCurrency: c.currency || "AZN", url,
    availability: "https://schema.org/InStock",
    priceSpecification: { "@type": "UnitPriceSpecification", price: c.price || "99", priceCurrency: c.currency || "AZN",
      unitCode: "MON", referenceQuantity: { "@type": "QuantitativeValue", value: 1, unitCode: "MON" } },
  },
  hasCourseInstance: [{
    "@type": "CourseInstance", courseMode: "Online", inLanguage: "az", instructor: person(c.instructor),
    description: SCHEDULE_TEXT[lang],
    courseSchedule: { "@type": "Schedule", repeatFrequency: "P1M", byDay: "https://schema.org/Saturday", byMonthWeek: [2, 4],
      startTime: "11:00", duration: "PT1H", scheduleTimezone: "Asia/Baku" },
  }],
});

export const post = (d, url, lang = "az") => ctx({
  "@type": "BlogPosting", "@id": url + "#post", url, mainEntityOfPage: url,
  headline: d.title, description: d.description, inLanguage: lang,
  datePublished: d.ymd, dateModified: d.updatedYmd || d.ymd,
  keywords: (d.tags || []).join(", "), articleSection: d.tags?.[0],
  isPartOf: { "@id": `${url.replace(/[^/]+\/$/, "")}#blog` }, author: d.author ? { "@type": "Person", name: d.author } : ORG, publisher: ORG,
  image: `${SITE}/${d.image || "og.png"}`,
  about: d.key_points?.length ? d.key_points.map((k) => ({ "@type": "Thing", name: k })) : undefined,
  citation: d.sources?.length ? d.sources.map((s) => (s.url ? { "@type": "CreativeWork", name: s.title, url: s.url } : s.title)) : undefined,
});

export const blog = (posts, slug, name, lang = "az") => ctx({
  "@type": "Blog", "@id": abs(slug) + "#blog", url: abs(slug), name, inLanguage: lang, publisher: ORG,
  blogPost: posts.map((p) => ({ "@type": "BlogPosting", headline: p.data.title, url: abs(p.data.slug), datePublished: p.date.toISOString().slice(0, 10) })),
});

export const itemList = (courses, slugs, name) => ctx({
  "@type": "ItemList", name,
  itemListElement: courses.map((c, i) => ({ "@type": "ListItem", position: i + 1, name: c.name, url: abs(slugs[c.slug]) })),
});
