# Public/legal pages matrix

Status: P0-RELEASE-001 / Issue #32 implementation matrix.

Final legal text is owner/legal controlled. Engineering may build routes, localization, accessibility, metadata, version/effective-date plumbing and release-safe placeholders only. No placeholder page may be presented as approved legal advice, final policy, or a verified statement of production legal facts.

The release-candidate Next.js surfaces live in the application build only. `deploy/coming-soon/` remains the canonical dependency-free public shell under ADR-005 until an explicit approved Landing cutover, rollback artifact and domain smoke review occur.

## Implemented route matrix

| Surface | Route | Engineering-safe implementation | Owner/legal inputs still required before final publication |
| --- | --- | --- | --- |
| Landing | `/landing` | Professional Brand v1 landing; truthful product boundaries; AR/EN/FR; responsive/accessible; no unsupported proof/claims. | Explicit cutover approval; any final marketing/legal claims. |
| Help / learner guide | `/help` | Study/practice/progress/reconnect/accessibility guidance; no invented curriculum or support promise. | Final support channels/hours/escalation. |
| Admin / Content guide | `/admin-guide` | Synthetic-only preparation/validation/review/publication guidance; `noindex`. | Named operational owners/escalation only if approved for publication. |
| About / Goal / Vision / Mission | `/about`, `/goal`, `/vision`, `/mission` | Product-purpose copy framed as direction/objective, not outcome guarantees. | Any future externally verifiable claims must be separately evidenced/approved. |
| Educational/AI disclaimer | `/disclaimer` | Draft educational-use and optional-AI boundary; visibly non-final; `noindex`. | Legal entity/jurisdiction/final disclaimer/version/effective date. |
| Privacy | `/privacy` | Responsive AR/EN/FR Privacy template; explicit blocker IDs; version/effective-date slots; `noindex`. | Entity/controller, public contact, lawful bases, vendors/processors, transfers, retention, minor wording, final approval. |
| Terms | `/terms` | Versioned structural Terms template; no acceptance claim; `noindex`. | Legal entity, jurisdiction, eligibility/guardian rules, service/cancellation/responsibility wording, final approval. |
| Child/minor safety | `/safety` | Implemented safe-default architecture described without inventing final age policy; `noindex` while policy/escalation are pending. | Age/eligibility/guardian policy, safeguarding escalation contacts/process, final approved claims. |
| Cookies/tracking | `/cookies` | Inventory/consent architecture template; explicitly not an active consent notice or tracker activation; `noindex`. | Applicable-law determination, production vendor/storage inventory, consent categories, retention, final copy. |
| Content/copyright/reporting | `/content-policy` | Official-content governance boundary plus reporting/takedown shell; `noindex`. | Copyright/takedown contact, ownership-verification/report workflow, final legal wording, real rights evidence. |
| Account deletion | `/account-deletion` | Explains implemented protected Backend deletion lifecycle without inventing purge deadlines or support channel; `noindex`. | Retention/hard-purge schedule, final user-facing entry point/support contact, approved wording. |
| Support | `/support` | Self-service routing to learner/account/content guidance; `noindex` until real support facts exist. | Approved public channels, hours, response expectations and escalation ownership. |
| Contact | `/contact` | Explicitly explains why no placeholder email/contact is published; `noindex`. | Approved support/privacy/legal/safety/copyright contacts. |

## Canonical blocker IDs

Issue #32 uses stable visible blocker identifiers in code, tests and this release contract. These are not values to substitute into legal text; they are proof that unknown facts remain unknown.

- `LEGAL_ENTITY_CONTROLLER` — legal entity / data controller.
- `PUBLIC_CONTACT` — approved public support/privacy/legal contact details.
- `JURISDICTION` — governing law / jurisdiction.
- `PROCESSING_BASES` — approved processing purposes and lawful bases.
- `VENDOR_INVENTORY` — production vendors/processors/storage/tracking inventory.
- `INTERNATIONAL_TRANSFERS` — transfer facts and safeguards.
- `RETENTION_SCHEDULE` — retention and hard-purge periods.
- `AGE_GUARDIAN_POLICY` — age/eligibility/guardian policy.
- `SAFETY_ESCALATION_CONTACT` — safeguarding escalation owner/contact/process.
- `COPYRIGHT_TAKEDOWN_CONTACT` — rights/report/takedown intake contact/process.
- `SUPPORT_CHANNEL_HOURS` — support channels, service hours and escalation ownership.
- `POLICY_EFFECTIVE_DATE` — approved effective date.
- `POLICY_VERSION` — approved final version.

## Publication-integrity rules

1. Every template surface states that it is not final/approved legal text.
2. Unknown legal facts are rendered as explicit blockers and never inferred from GitHub ownership, developer emails, domain registration, code configuration or fixture data.
3. Unapproved legal/support/contact templates set `robots.index=false`; they may be built/tested without being presented as final indexed policy.
4. No route invents testimonials, school partnerships, learner counts, exam-board approvals, guaranteed results or real curriculum/rights claims.
5. AR/EN/FR and RTL/LTR are implemented in the same content contract; missing locale text fails automated tests.
6. The canonical Web logo asset is a byte-for-byte copy of `deploy/coming-soon/assets/logo-horizontal.svg`; no alternate palette or logo geometry is introduced.
7. `deploy/coming-soon/` is not modified by Issue #32. Coming Soon Smoke is triggered by public-release application changes so AC-P0-019 remains continuously checked.
