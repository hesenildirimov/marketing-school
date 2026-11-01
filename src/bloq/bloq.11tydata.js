import { post } from "../_includes/schema.js";
const ymd = (d) => (d ? new Date(d).toISOString().slice(0, 10) : undefined);
export default {
  layout: "post.njk",
  ogType: "article",
  eleventyComputed: {
    slug: (d) => `bloq/${d.page.fileSlug}`,
    permalink: (d) => (d.draft ? false : process.env.DEMO === "1" ? `bloq-${d.page.fileSlug}.html` : `bloq/${d.page.fileSlug}/index.html`),
    seo_title: (d) => d.seo_title || d.title,
    seo_description: (d) => d.description,
    crumbs: (d) => [["Bloq", "bloq"], [d.title, `bloq/${d.page.fileSlug}`]],
    ld: (d) => [post({ ...d, ymd: ymd(d.page.date), updatedYmd: ymd(d.updated) }, `https://marketingschool.az/bloq/${d.page.fileSlug}/`)],
  },
};
