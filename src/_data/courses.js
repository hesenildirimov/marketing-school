import fs from "node:fs";
const dir = "src/_data/kurslar/";
export default () => fs.readdirSync(dir).filter((f) => f.endsWith(".json")).sort()
  .map((f) => JSON.parse(fs.readFileSync(dir + f, "utf8")));
