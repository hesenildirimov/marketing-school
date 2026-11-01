// JSON-LD qurucuları — səhifələrin .11tydata.js fayllarından çağırılır.
const SITE = "https://marketingschool.az";
const ORG = { "@id": `${SITE}/#organization` };
const abs = (slug) => `${SITE}/${slug ? slug + "/" : ""}`;
const plain = (s) => String(s ?? "").replace(/\[([^\]]+)\]\([^)]+\)/g, "$1").replace(/[*_`#>]/g, "").trim();
const ctx = (o) => ({ "@context": "https://schema.org", ...o });

export const faqPage = (items, slug, extra = {}) => ctx({
  "@type": "FAQPage", "@id": abs(slug) + "#faq", inLanguage: "az", ...extra,
  mainEntity: items.map((f) => ({ "@type": "Question", name: f.q, acceptedAnswer: { "@type": "Answer", text: plain(f.a) } })),
});

export const person = (p) => ({
  "@type": "Person", name: p.name, jobTitle: p.job_title, description: p.role,
  worksFor: p.works_for ? { "@type": "Organization", name: p.works_for } : undefined,
  knowsAbout: p.knows_about, image: p.photo ? `${SITE}/${p.photo}` : undefined, sameAs: p.linkedin ? [p.linkedin] : undefined,
});

export const course = (c, site) => ctx({
  "@type": "Course", "@id": abs(c.slug) + "#course", url: abs(c.slug),
  name: c.name, alternateName: c.alt_names, description: c.summary, inLanguage: "az",
  provider: ORG, educationalLevel: "Beginner", dateModified: c.updated,
  educationalCredentialAwarded: "Diplom (sertifikat)", timeRequired: "P3M", isAccessibleForFree: false,
  instructor: person(c.instructor),
  teaches: c.modules.map((m) => m.title),
  syllabusSections: c.modules.map((m, i) => ({ "@type": "Syllabus", position: i + 1, name: m.title, description: m.text })),
  audience: { "@type": "EducationalAudience", audienceType: c.audience.join("; ") },
  offers: c.price
    ? { "@type": "Offer", category: "Paid", price: c.price, priceCurrency: c.currency, url: abs(c.slug), availability: "https://schema.org/InStock" }
    : { "@type": "Offer", category: "Paid" },
  hasCourseInstance: [{
    "@type": "CourseInstance", courseMode: "Online", inLanguage: "az", instructor: person(c.instructor),
    description: "Qeydə alınmış videodərslər (istənilən vaxt), 2 həftədən bir canlı onlayn sual-cavab, real keyslərə əsaslanan ev tapşırıqları, kurs sonunda fərdi mentorluq sessiyası.",
    courseSchedule: { "@type": "Schedule", repeatFrequency: "P2W", repeatCount: 6, scheduleTimezone: "Asia/Baku" },
  }],
});

export const post = (d, url) => ctx({
  "@type": "BlogPosting", "@id": url + "#post", url, mainEntityOfPage: url,
  headline: d.title, description: d.description, inLanguage: "az",
  datePublished: d.ymd, dateModified: d.updatedYmd || d.ymd,
  keywords: (d.tags || []).join(", "), articleSection: d.tags?.[0],
  isPartOf: { "@id": `${SITE}/bloq/#blog` }, author: d.author ? { "@type": "Person", name: d.author } : ORG, publisher: ORG,
  image: `${SITE}/${d.image || "og.png"}`,
  about: d.key_points?.length ? d.key_points.map((k) => ({ "@type": "Thing", name: k })) : undefined,
  citation: d.sources?.length ? d.sources.map((s) => (s.url ? { "@type": "CreativeWork", name: s.title, url: s.url } : s.title)) : undefined,
});

export const blog = (posts) => ctx({
  "@type": "Blog", "@id": `${SITE}/bloq/#blog`, url: abs("bloq"), name: "Marketing School Bloqu", inLanguage: "az", publisher: ORG,
  blogPost: posts.map((p) => ({ "@type": "BlogPosting", headline: p.data.title, url: abs(p.data.slug), datePublished: p.date.toISOString().slice(0, 10) })),
});

export const itemList = (courses) => ctx({
  "@type": "ItemList", name: "Marketinq kursları — Bakı",
  itemListElement: courses.map((c, i) => ({ "@type": "ListItem", position: i + 1, name: c.name, url: abs(c.slug) })),
});
