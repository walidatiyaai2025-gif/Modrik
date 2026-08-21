import Image from "next/image";
import {
  legalBlockers,
  publicHref,
  publicPages,
  publicUi,
  publicDirection,
  type LegalBlockerId,
  type PublicLocale,
  type PublicPageKey,
} from "./content";

export type PublicStyleMap = Record<string, string>;

type RenderedSection = {
  id: string;
  title: string;
  paragraphs: string[];
  bullets?: string[];
  blockers?: LegalBlockerId[];
};

type LandingPresentation = {
  eyebrow: string;
  summary: string;
  highlights: string[];
  sections: RenderedSection[];
};

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

const landingPresentation: Record<PublicLocale, LandingPresentation> = {
  en: {
    eyebrow: "A calmer way to learn",
    summary:
      "Study lessons, practice at your pace, and keep progress easy to understand in one multilingual learning workspace.",
    highlights: ["Focused study", "Practice that resumes", "Progress in view"],
    sections: [
      {
        id: "workspace",
        title: "Study without the clutter",
        paragraphs: [
          "Open a lesson in a focused reading space designed to keep the learning material, language and next step easy to find.",
        ],
      },
      {
        id: "practice",
        title: "Practice and return with confidence",
        paragraphs: [
          "Start practice when you are ready. If you return to the same in-progress attempt, MODRIK keeps that attempt consistent instead of changing it underneath you.",
        ],
      },
      {
        id: "progress",
        title: "Keep progress easy to read",
        paragraphs: [
          "Use the progress workspace to review your learning state and decide what to revisit next without turning the experience into a wall of scores.",
        ],
      },
      {
        id: "languages",
        title: "Built for Arabic, English and French",
        paragraphs: [
          "Language direction, mixed-content reading, keyboard access and larger text are treated as part of the learning experience from the start.",
        ],
      },
      {
        id: "trust",
        title: "Clear rules behind the experience",
        paragraphs: [
          "Question order, scoring, academic context and official content follow authoritative system rules. Draft legal pages stay visibly unapproved until the required owner and legal facts are supplied.",
        ],
      },
    ],
  },
  ar: {
    eyebrow: "تعلّم بهدوء ووضوح",
    summary:
      "ذاكر دروسك، وتدرّب بالوتيرة المناسبة لك، وتابع تقدّمك بوضوح داخل مساحة تعلّم واحدة متعددة اللغات.",
    highlights: ["مذاكرة مركّزة", "تدريب يُستأنف كما هو", "تقدّم واضح"],
    sections: [
      {
        id: "workspace",
        title: "ذاكر دون تشتيت",
        paragraphs: [
          "افتح الدرس في مساحة قراءة مركّزة تساعدك على العثور بسهولة على مادة التعلّم واللغة والخطوة التالية.",
        ],
      },
      {
        id: "practice",
        title: "تدرّب وارجع بثقة",
        paragraphs: [
          "ابدأ التدريب عندما تكون مستعدًا. وإذا عدت إلى نفس المحاولة الجارية، تحافظ مُدرك على اتساقها بدل تغييرها أثناء تقدّمك.",
        ],
      },
      {
        id: "progress",
        title: "تابع تقدّمك بوضوح",
        paragraphs: [
          "استخدم مساحة التقدّم لمراجعة حالة تعلّمك وتحديد ما تريد مراجعته لاحقًا دون تحويل التجربة إلى جدار من الدرجات.",
        ],
      },
      {
        id: "languages",
        title: "مصمم للعربية والإنجليزية والفرنسية",
        paragraphs: [
          "اتجاه اللغة وقراءة المحتوى المختلط والوصول بلوحة المفاتيح والنص الأكبر أجزاء أساسية من تجربة التعلّم منذ البداية.",
        ],
      },
      {
        id: "trust",
        title: "قواعد واضحة خلف التجربة",
        paragraphs: [
          "يتبع ترتيب الأسئلة والدرجات والسياق الأكاديمي والمحتوى الرسمي قواعد النظام المعتمدة. وتظل الصفحات القانونية المسودة ظاهرة بوضوح كغير معتمدة حتى توفير الحقائق المطلوبة من المالك والجهة القانونية.",
        ],
      },
    ],
  },
  fr: {
    eyebrow: "Apprendre avec calme et clarté",
    summary:
      "Étudiez vos leçons, exercez-vous à votre rythme et suivez votre progression clairement dans un seul espace d’apprentissage multilingue.",
    highlights: ["Étude ciblée", "Exercices qui reprennent", "Progression visible"],
    sections: [
      {
        id: "workspace",
        title: "Étudier sans distraction",
        paragraphs: [
          "Ouvrez une leçon dans un espace de lecture ciblé qui garde le contenu, la langue et la prochaine étape faciles à repérer.",
        ],
      },
      {
        id: "practice",
        title: "S’exercer et revenir en confiance",
        paragraphs: [
          "Commencez un exercice lorsque vous êtes prêt. Si vous revenez à la même tentative en cours, MODRIK la garde cohérente au lieu de la modifier en cours de route.",
        ],
      },
      {
        id: "progress",
        title: "Lire sa progression simplement",
        paragraphs: [
          "Utilisez l’espace de progression pour revoir votre état d’apprentissage et choisir ce que vous souhaitez reprendre ensuite, sans transformer l’expérience en mur de scores.",
        ],
      },
      {
        id: "languages",
        title: "Conçu pour l’arabe, l’anglais et le français",
        paragraphs: [
          "La direction du texte, la lecture de contenu mixte, l’accès clavier et le texte agrandi font partie de l’expérience d’apprentissage dès le départ.",
        ],
      },
      {
        id: "trust",
        title: "Des règles claires derrière l’expérience",
        paragraphs: [
          "L’ordre des questions, la notation, le contexte académique et le contenu officiel suivent les règles autoritaires du système. Les pages juridiques provisoires restent clairement non approuvées tant que les informations propriétaire et juridiques requises ne sont pas fournies.",
        ],
      },
    ],
  },
};

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
  styles,
}: {
  title: string;
  keys: PublicPageKey[];
  pageKey: PublicPageKey;
  locale: PublicLocale;
  styles: PublicStyleMap;
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

export function PublicSiteView({
  pageKey,
  locale,
  styles,
}: {
  pageKey: PublicPageKey;
  locale: PublicLocale;
  styles: PublicStyleMap;
}) {
  const page = publicPages[pageKey];
  const labels = publicUi[locale];
  const direction = publicDirection(locale);
  const landingCopy = pageKey === "landing" ? landingPresentation[locale] : null;
  const renderedSections: RenderedSection[] = landingCopy
    ? landingCopy.sections
    : page.sections.map((section) => ({
        id: section.id,
        title: section.title[locale],
        paragraphs: section.paragraphs.map((paragraph) => paragraph[locale]),
        bullets: section.bullets?.map((bullet) => bullet[locale]),
        blockers: section.blockers ? [...section.blockers] : undefined,
      }));
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
            <p className={styles.eyebrow}>{landingCopy?.eyebrow ?? page.eyebrow[locale]}</p>
            <h1 id="public-page-title">{page.title[locale]}</h1>
            <p className={styles.lead}>{landingCopy?.summary ?? page.summary[locale]}</p>
            {landingCopy && (
              <>
                <div className={styles.heroActions}>
                  <PageLink pageKey="help" currentPage={pageKey} locale={locale} className={styles.primaryAction} />
                  <PageLink pageKey="about" currentPage={pageKey} locale={locale} className={styles.secondaryAction} />
                </div>
                <ul className={styles.heroHighlights} aria-label={labels.explore}>
                  {landingCopy.highlights.map((highlight) => (
                    <li key={highlight}>{highlight}</li>
                  ))}
                </ul>
              </>
            )}
          </div>
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
            {renderedSections.map((section, index) => (
              <section className={styles.contentSection} key={section.id} aria-labelledby={`${section.id}-heading`}>
                <div className={styles.sectionNumber} aria-hidden="true">
                  {String(index + 1).padStart(2, "0")}
                </div>
                <div>
                  <h2 id={`${section.id}-heading`}>{section.title}</h2>
                  {section.paragraphs.map((paragraph, paragraphIndex) => (
                    <p key={`${section.id}-paragraph-${paragraphIndex}`}>{paragraph}</p>
                  ))}
                  {section.bullets && (
                    <ul className={styles.bulletList}>
                      {section.bullets.map((bullet, bulletIndex) => (
                        <li key={`${section.id}-bullet-${bulletIndex}`}>{bullet}</li>
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
            <div className={styles.releaseBoundary}>
              <h2>{labels.releaseBoundary}</h2>
              <p>{labels.releaseBoundaryBody}</p>
            </div>
          </div>
          <LinkGroup title={labels.guides} keys={guideKeys} pageKey={pageKey} locale={locale} styles={styles} />
          <LinkGroup title={labels.purpose} keys={purposeKeys} pageKey={pageKey} locale={locale} styles={styles} />
          <LinkGroup title={labels.trustLegal} keys={trustKeys} pageKey={pageKey} locale={locale} styles={styles} />
        </div>
      </footer>
    </div>
  );
}
