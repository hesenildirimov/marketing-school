import { blog } from "./_includes/schema.js";
export default { eleventyComputed: { ld: (d) => [blog(d.collections.posts || [])] } };
