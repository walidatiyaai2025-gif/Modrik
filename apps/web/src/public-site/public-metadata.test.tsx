import assert from "node:assert/strict";
import test from "node:test";
import { publicPageKeys, publicPages } from "./content";
import { publicMetadata } from "./metadata";

test("every public route has canonical and AR EN FR alternate metadata", () => {
  for (const key of publicPageKeys) {
    const metadata = publicMetadata(key);
    const canonical = `https://modrik.org/${publicPages[key].slug}`;

    assert.equal(metadata.alternates?.canonical, canonical);
    assert.deepEqual(metadata.alternates?.languages, {
      en: canonical,
      ar: `${canonical}?lang=ar`,
      fr: `${canonical}?lang=fr`,
    });
    assert.ok(String(metadata.title).includes("MODRIK"));
    assert.ok(metadata.description);
  }
});

test("unapproved legal and support templates stay noindex while truthful public information can index", () => {
  for (const key of publicPageKeys) {
    const metadata = publicMetadata(key);
    const robots = metadata.robots as { index?: boolean; follow?: boolean };
    assert.equal(robots.index, publicPages[key].indexable, `${key} index policy drifted`);
    assert.equal(robots.follow, true);
  }

  assert.equal((publicMetadata("privacy").robots as { index?: boolean }).index, false);
  assert.equal((publicMetadata("terms").robots as { index?: boolean }).index, false);
  assert.equal((publicMetadata("landing").robots as { index?: boolean }).index, true);
  assert.equal((publicMetadata("help").robots as { index?: boolean }).index, true);
});
