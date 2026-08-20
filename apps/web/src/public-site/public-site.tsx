import Image from "next/image";
import {
  legalBlockers,
  publicHref,
  publicPages,
  publicPath,
  publicUi,
  publicDirection,
  type PublicLocale,
  type PublicPageKey,
} from "./content";
import styles from "./public-site.module.css";

const primaryKeys: PublicPageKey[] = ["landing", "help", "about", "safety"];
const guideKeys: PublicPageKey[] = ["help", "adminGuide", "accountDeletion", "support"];
const purposeKeys: PublicPageKey[] = ["about", "goal", "vision", "mission"];
const trustKeys: PublicPageKey[] = [
  "disclaimer",
  "privacy",
  "terms",
  "safety",
  "cookies",
  "contentPolicy",
  "contact",
];

function labelFor(key: PublicPageKey, locale: PublicLocale): string {
  const labels = publicUi[locale];
  const mapped: Partial<Record<PublicPageKey, string>> = {
    landing: labels.landing,
    help: labels.learnerGuide,
    adminGuide: labels.adminGuide,
    about: labels.about,
    goal: labels.goal,
    vision: labels.vision,
    mission: labels.mission,
    disclaimer: labels.disclaimer,
    privacy: labels.privacy,
    terms: labels.terms,
    safety: labels.safety,
    cookies: labels.cookies,
    contentPolicy: labels.contentPolicy,
    accountDeletion: labels.accountDeletion,
    support: labels.support,
    contact: labels.contact,
  };
  return mapped[key] ?? publicPages[key].title[locale];
}

function PageLink({
  pageKey,
  currentPage,
  locale,
  className,
}: {
  pageKey: PublicPageKey;
  currentPage: PublicPageKey;
  locale: PublicLocale;
  className?: string;
}) {
  return (
    <a
      className={className}
      href={publicHref(pageKey, locale)}
      aria-current={currentPage === pageKey ? "page" : undefined}
    >
      {labelFor(pageKey, locale)}
    </a>
  );
}

function LinkGroup({
  title,
  keys,
  pageKey,
  locale,
}: {
  title: string;
  keys: PublicPageKey[];
  pageKey: PublicPageKey;
  locale: PublicLocale;
}) {
  return (
    <section className={styles.footerGroup} aria-label={title}>
      <h2>{title}</h2>
      <ul>
        {keys.map((key) => (
          <li key={key}>
            <PageLink pageKey={key} currentPage={pageKey} locale={locale} />
          </li>
        ))}
      </ul>
    </section>
  );
}

export function PublicSite({ pageKey, locale }: { pageKey: PublicPageKey; locale: PublicLocale }) {
  const page = publicPages[pageKey];
  const labels = publicUi[locale];
  const direction = publicDirection(locale);
  const blockerIds = Array.from(new Set(page.sections.flatMap((section) => section.blockers ?? [])));

  return (
    <div className={styles.shell} lang={locale} dir={direction} data-public-page={pageKey}>
      <a className={styles.skipLink} href="#public-main">
        {labels.skip}
      </a>

      <header className={styles.siteHeader}>
        <div className={styles.headerInner}>
          <a className={styles.brandLink} href={publicHref("landing", locale)} aria-label="MODRIK | مُدرك">
            <Image
              src="/brand/logo-horizontal.svg"
              alt="MODRIK | مُدرك"
              width={510}
              height={126}
              priority
            />
          </a>

          <nav className={styles.primaryNav} aria-label={labels.primaryNavigation}>
            {primaryKeys.map((key) => (
              <PageLink key={key} pageKey={key} currentPage={pageKey} locale={locale} />
            ))}
          </nav>

          <nav className={styles.languageNav} aria-label={labels.language}>
            {(["ar", "en", "fr"] as const).map((language) => (
              <a
                key={language}
                href={publicHref(pageKey, language)}
                lang={language}
                aria-current={locale === language ? "page" : undefined}
                aria-label={`${labels.language}: ${language.toUpperCase()}`}
              >
                {language.toUpperCase()}
              </a>
            ))}
          </nav>
        </div>
      </header>

      <main id="public-main" className={styles.main}>
        <section className={`${styles.hero} ${pageKey === "landing" ? styles.landingHero : ""}`} aria-labelledby="public-page-title">
          <div className={styles.heroCopy}>
            <p className={styles.eyebrow}>{page.eyebrow[locale]}</p>
            <h1 id="public-page-title">{page.title[locale]}</h1>
            <p className={styles.lead}>{page.summary[locale]}</p>
            {pageKey === "landing" && (
              <div className={styles.heroActions}>
                <PageLink pageKey="help" currentPage={pageKey} locale={locale} className={styles.primaryAction} />
                <PageLink pageKey="about" currentPage={pageKey} locale={locale} className={styles.secondaryAction} />
              </div>
            )}
          </div>

          <aside className={styles.releaseCard} aria-labelledby="release-boundary-heading">
            <span className={styles.releaseMarker} aria-hidden="true">01</span>
            <h2 id="release-boundary-heading">{labels.releaseBoundary}</h2>
            <p>{labels.releaseBoundaryBody}</p>
            <code>{publicPath(pageKey)}</code>
          </aside>
        </section>

        {page.template && (
          <aside className={styles.templateNotice} aria-labelledby="template-notice-title">
            <div className={styles.noticeIcon} aria-hidden="true">!</div>
            <div>
              <h2 id="template-notice-title">{labels.templateTitle}</h2>
              <p>{labels.templateBody}</p>
            </div>
          </aside>
        )}

        <div className={styles.contentLayout}>
          <article className={styles.article}>
            {page.sections.map((section, index) => (
              <section className={styles.contentSection} key={section.id} aria-labelledby={`${section.id}-heading`}>
                <div className={styles.sectionNumber} aria-hidden="true">
                  {String(index + 1).padStart(2, "0")}
                </div>
                <div>
                  <h2 id={`${section.id}-heading`}>{section.title[locale]}</h2>
                  {section.paragraphs.map((paragraph, paragraphIndex) => (
                    <p key={`${section.id}-paragraph-${paragraphIndex}`}>{paragraph[locale]}</p>
                  ))}
                  {section.bullets && (
                    <ul className={styles.bulletList}>
                      {section.bullets.map((bullet, bulletIndex) => (
                        <li key={`${section.id}-bullet-${bulletIndex}`}>{bullet[locale]}</li>
                      ))}
                    </ul>
                  )}
                  {section.blockers && section.blockers.length > 0 && (
                    <ul className={styles.inlineBlockers} aria-label={labels.blockedInputs}>
                      {section.blockers.map((blockerId) => (
                        <li key={blockerId}>
                          <code>{`[${blockerId}]`}</code>
                          <span>{legalBlockers[blockerId][locale]}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              </section>
            ))}
          </article>

          <aside className={styles.sideRail} aria-label={labels.explore}>
            <section>
              <p className={styles.eyebrow}>{labels.explore}</p>
              <h2>{labels.guides}</h2>
              <PageLink pageKey="help" currentPage={pageKey} locale={locale} />
              <PageLink pageKey="adminGuide" currentPage={pageKey} locale={locale} />
              <PageLink pageKey="support" currentPage={pageKey} locale={locale} />
            </section>
            <section>
              <h2>{labels.trustLegal}</h2>
              <PageLink pageKey="disclaimer" currentPage={pageKey} locale={locale} />
              <PageLink pageKey="privacy" currentPage={pageKey} locale={locale} />
              <PageLink pageKey="terms" currentPage={pageKey} locale={locale} />
              <PageLink pageKey="contentPolicy" currentPage={pageKey} locale={locale} />
            </section>
          </aside>
        </div>

        {blockerIds.length > 0 && (
          <section className={styles.blockerPanel} aria-labelledby="blocker-panel-heading">
            <p className={styles.eyebrow}>{labels.blockedInputs}</p>
            <h2 id="blocker-panel-heading">{labels.templateTitle}</h2>
            <ul>
              {blockerIds.map((blockerId) => (
                <li key={blockerId}>
                  <code>{blockerId}</code>
                  <span>{legalBlockers[blockerId][locale]}</span>
                </li>
              ))}
            </ul>
          </section>
        )}
      </main>

      <footer className={styles.siteFooter}>
        <div className={styles.footerInner}>
          <div className={styles.footerBrand}>
            <strong>MODRIK</strong>
            <span lang="ar" dir="rtl">مُدرك</span>
            <p>{labels.releaseBoundaryBody}</p>
          </div>
          <LinkGroup title={labels.guides} keys={guideKeys} pageKey={pageKey} locale={locale} />
          <LinkGroup title={labels.purpose} keys={purposeKeys} pageKey={pageKey} locale={locale} />
          <LinkGroup title={labels.trustLegal} keys={trustKeys} pageKey={pageKey} locale={locale} />
        </div>
      </footer>
    </div>
  );
}
