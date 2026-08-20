import { type PublicLocale, type PublicPageKey } from "./content";
import styles from "./public-site.module.css";
import { PublicSiteView } from "./public-site-view";

export function PublicSite({ pageKey, locale }: { pageKey: PublicPageKey; locale: PublicLocale }) {
  return <PublicSiteView pageKey={pageKey} locale={locale} styles={styles} />;
}
