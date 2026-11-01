import { faqPage } from "./_includes/schema.js";
export default { eleventyComputed: { ld: (d) => [faqPage(d.faq.items, "faq", { dateModified: d.faq.updated })] } };
