import { safe } from "../_includes/safe.js";
import { post } from "../_includes/schema.js";
const ymd = (d) => (d ? new Date(d).toISOString().slice(0, 10) : undefined);
// AZ bloq yazıları: /bloq/<slug>/ (URL-lər dəyişmir). translation_key digər dillərdəki versiyalarla hreflang əlaqəsi qurur.
export default {
  layout: "post.njk",
  ogType: "article",
  lang: "az",
  eleventyComputed: {
    slug: (d) => `bloq/${d.page.fileSlug}`,
    permalink: (d) => (d.draft ? false : process.env.DEMO === "1" ? `bloq-${d.page.fileSlug}.html` : `bloq/${d.page.fileSlug}/index.html`),
    seo_title: (d) => d.seo_title || d.title,
    seo_description: (d) => d.description,
    crumbs: (d) => [["Bloq", "bloq"], [d.title, `bloq/${d.page.fileSlug}`]],
    alternates: (d) => {
      const out = {};
      for (const [l, base] of Object.entries(d.routes?.postBase || {})) {
        const s = d.postIndex?.[l]?.[d.translation_key || d.page.fileSlug];
        if (s) out[l] = `${base}/${s}`;
      }
      return out;
    },
    ld: safe((d) => [post({ ...d, ymd: ymd(d.page.date), updatedYmd: ymd(d.updated) }, `https://marketingschool.az/bloq/${d.page.fileSlug}/`, "az")], []),
  },
};
