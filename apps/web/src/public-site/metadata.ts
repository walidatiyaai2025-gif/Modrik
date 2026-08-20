import type { Metadata } from "next";
import { publicPages, publicPath, type PublicPageKey } from "./content";

const publicOrigin = "https://modrik.org";

export function publicMetadata(pageKey: PublicPageKey): Metadata {
  const page = publicPages[pageKey];
  const canonicalPath = publicPath(pageKey);
  const canonical = `${publicOrigin}${canonicalPath}`;

  return {
    title: `${page.title.en} | MODRIK`,
    description: page.seoDescription.en,
    applicationName: "MODRIK",
    alternates: {
      canonical,
      languages: {
        en: canonical,
        ar: `${canonical}?lang=ar`,
        fr: `${canonical}?lang=fr`,
      },
    },
    robots: {
      index: page.indexable,
      follow: true,
    },
    openGraph: {
      type: "website",
      siteName: "MODRIK | مُدرك",
      title: page.title.en,
      description: page.seoDescription.en,
      url: canonical,
      locale: "en",
      alternateLocale: ["ar", "fr"],
    },
  };
}
