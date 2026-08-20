# P0 public release / trust-surface QA matrix

Scope: Issue #32 / P0-RELEASE-001, REQ-P0-011/012, AC-P0-014/018/019.

`/landing` and the routes below are release-candidate application surfaces only. `deploy/coming-soon/` remains canonical until a separately approved cutover.

## Route inventory

`/landing`, `/help`, `/admin-guide`, `/about`, `/goal`, `/vision`, `/mission`, `/disclaimer`, `/privacy`, `/terms`, `/safety`, `/cookies`, `/content-policy`, `/account-deletion`, `/support`, `/contact`.

## Automated coverage

| Risk | Automated evidence |
| --- | --- |
| Missing/extra route or broken locale link | Exact 16-slug set, uniqueness and AR/EN/FR href assertions in `public-content.test.tsx`. |
| Localization drift | Every title, summary, SEO description, section title and paragraph must be non-empty in AR/EN/FR; direction helper asserts AR=RTL, EN/FR=LTR. |
| Basic accessibility semantics | Static render tests require skip-to-main, header/nav/main/footer landmarks, page heading, locale/direction, and blocker semantics. CSS uses visible `:focus-visible`, logical properties, fluid dimensions and reduced-motion override. |
| SEO drift | Every route has a canonical URL and language alternates; unapproved template/support/legal routes are `noindex`; release-safe informational routes follow their explicit index policy. |
| Placeholder/legal integrity | Stable blocker-ID inventory is exact; blocker messages are visibly BLOCKED/محجوب/BLOQUÉ; every template page exposes at least one blocker. |
| Unsupported marketing claims | Copy scan rejects numeric student/school scale, "trusted by", named school/exam-board partnership/approval and guaranteed outcome patterns. |
| Brand drift | Web horizontal logo must equal the canonical Coming Soon SVG byte-for-byte; public CSS uses canonical token variables rather than a new color system. |
| Coming Soon regression | `Coming Soon Smoke` runs for public-release application paths and verifies required dependency-free shell files, canonical domain and legacy-name exclusion. |
| Web build integrity | npm audit, ESLint, TypeScript, Node tests and Next production build in Bootstrap CI. |
| Repository integrity | Full seven-job Bootstrap CI: contracts, Backend, MariaDB 10.11, Web, Mobile, Gitleaks, dependency review. |

## Manual accessibility/responsive matrix before production cutover

These checks supplement automation and must be repeated against the actual release artifact/browser matrix before any domain cutover.

| Case | Desktop 1440px | Laptop 1024px | Mobile ~390px | Expected |
| --- | --- | --- | --- | --- |
| EN LTR | Required | Required | Required | Header/nav/content/footer retain reading order; no horizontal text clipping. |
| AR RTL | Required | Required | Required | Shell mirrors via logical layout; mixed technical tokens remain isolated/readable. |
| FR LTR | Required | Required | Required | Accents/apostrophes wrap without clipping. |
| Keyboard only | Required | Required | Required | Skip link becomes visible; every link/language control receives visible focus; focus order matches DOM order. |
| Screen reader | Required | Required | Mobile reader recommended | One page H1; meaningful landmarks/navigation labels; template warning and blocker lists announced as normal content. |
| 200% zoom / large text | Required | Required | N/A browser-specific | No fixed-height copy containers; nav wraps; content/side rail collapse without overlap. |
| Reduced motion | Required | Required | Required where supported | No essential animation; reduced-motion media query suppresses transitions/animation duration. |
| Legal template status | Required | Required | Required | "not approved" warning remains visible before policy body; blockers remain human-readable and no invented contacts appear. |
| Link traversal | Required | Required | Required | Primary nav, guide links, footer legal links and language links remain on the defined route set. |

## State applicability review

AC-P0-014 calls for Loading/Empty/Error/Offline/Retry/Permission where applicable. These public surfaces are static, repository-bundled informational pages and perform no data fetch, mutation, permission check or browser persistence. Therefore runtime loading/empty/offline/retry/permission states are **not applicable to Issue #32 static content**. The guides explicitly document those states for the data-driven Student/Admin products. If a future public route gains network data, forms, consent state or authenticated content, the full state matrix becomes applicable and must be added before release.

## Production legal/cutover gate

Do not mark Privacy, Terms, disclaimer, child-safety, Cookie/tracking, content/takedown, support/contact or account-deletion wording as final until their blocker IDs in `docs/release/release-inputs.md` are resolved by the accountable owner/legal/safety/operations roles. A final legal review must cover all three language versions.

Do not replace `deploy/coming-soon/` from this issue. Public cutover requires the ADR-005 release boundary: explicit approval, rollback artifact, HTTPS/redirect/assets/mobile/desktop/directory-listing smoke on the live domain.
