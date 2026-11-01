import { itemList } from "./_includes/schema.js";
export default {
  eleventyComputed: {
    ld: (d) => [
      { "@context": "https://schema.org", "@type": "WebSite", "@id": `${d.siteUrl}/#website`, url: `${d.siteUrl}/`, name: d.site.name, inLanguage: "az", publisher: { "@id": `${d.siteUrl}/#organization` } },
      itemList(d.courses),
    ],
  },
};
