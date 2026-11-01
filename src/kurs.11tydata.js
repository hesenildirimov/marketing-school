import { course, faqPage } from "./_includes/schema.js";
export default {
  eleventyComputed: {
    crumbs: (d) => [[d.c.name, d.c.slug]],
    ld: (d) => {
      const items = d.faq.items.filter((f) => (f.show_on || []).includes(d.c.slug));
      return [course(d.c, d.site), ...(items.length ? [faqPage(items, d.c.slug)] : [])];
    },
  },
};
