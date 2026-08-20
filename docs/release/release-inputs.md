# Production release inputs

Missing inputs block only their affected release/workflow. Safe fixtures and explicit placeholders remain allowed for architecture and tests. Unknown values must never be inferred from repository authorship, account metadata, developer email addresses, DNS ownership or fixture configuration.

| Input | Status | Blocks | Safe repository behavior |
| --- | --- | --- | --- |
| Exact board, syllabus, and version | BLOCKED — owner | Real curriculum configuration/import and real-curriculum public claims | Use `FIXTURE:*` synthetic scope only; never imply exam-board approval. |
| Initial real subject IDs and content-rights evidence | BLOCKED — owner/content | Real content publication and rights claims | Keep synthetic golden fixtures; require provenance/rights review. |
| Legal entity / data controller | BLOCKED — owner/legal (`LEGAL_ENTITY_CONTROLLER`) | Final Privacy/Terms/disclaimer publication | Render the explicit blocker; never infer the entity/controller. |
| Approved public support/privacy/legal contact set | BLOCKED — owner/legal/ops (`PUBLIC_CONTACT`) | Final legal/contact/support publication | Do not invent email, postal address, phone, support portal or service promise. |
| Governing law / jurisdiction | BLOCKED — owner/legal (`JURISDICTION`) | Final Terms and jurisdiction-specific legal wording | Keep template `noindex`; publish no jurisdiction claim. |
| Processing purposes / lawful bases | BLOCKED — owner/legal (`PROCESSING_BASES`) | Final Privacy/Cookie notices | Base final wording on approved production inventory/legal analysis, not code assumptions. |
| Production vendor/processor/tracking inventory | BLOCKED — owner/legal/ops (`VENDOR_INVENTORY`) | Final Privacy/Cookie notices | Do not turn optional/absent provider configuration into a production-processing claim. |
| International transfer facts/safeguards | BLOCKED — owner/legal (`INTERNATIONAL_TRANSFERS`) | Final Privacy Notice | Keep explicit blocker until approved facts exist. |
| Data retention and hard-purge schedule | BLOCKED — owner/ops/legal (`RETENTION_SCHEDULE`) | Final Privacy/account deletion/DR wording | Do not claim periods; preserve auditable archival/deletion capability. |
| Age / eligibility / guardian policy | BLOCKED — owner/legal/safety (`AGE_GUARDIAN_POLICY`) | Final Terms/Privacy/child-safety policy and production age activation | Preserve safe Backend defaults; do not convert engineering thresholds into final legal policy. |
| Safeguarding escalation contacts/process | BLOCKED — owner/legal/safety (`SAFETY_ESCALATION_CONTACT`) | Final child-safety reporting/escalation guidance | Render explicit blocker; do not fabricate emergency/escalation contact. |
| Copyright/takedown reporting contact/process | BLOCKED — owner/legal/content (`COPYRIGHT_TAKEDOWN_CONTACT`) | Final content/copyright policy | Keep reporting shell non-actionable for sensitive submissions until approved intake exists. |
| Support channels, hours and escalation ownership | BLOCKED — owner/ops (`SUPPORT_CHANNEL_HOURS`) | Final Help/Support/Contact promises | Provide self-service guides only; no SLA/hours/contact invention. |
| Legal-policy effective dates / versions | BLOCKED — owner/legal (`POLICY_EFFECTIVE_DATE`, `POLICY_VERSION`) | Final policy publication and acceptance references | Keep templates visibly draft and `noindex` until approved. |
| Google/Apple production IDs and secrets | BLOCKED — owner/provider | Production social sign-in | Keep provider configuration absent/off; never invent IDs. |
| Firebase production project/credentials | BLOCKED — owner/provider | Optional FCM/Remote Config/Crashlytics/Analytics | Core remains functional without Firebase. |
| Android/iOS bundle IDs, store IDs, and signing | BLOCKED — owner/provider | Store builds/submission | Use `org.modrik.placeholder` only in the non-production scaffold. |
| Age/ad/community activation policy | BLOCKED — owner/legal/safety | Production policy row, ad SDK/network, or community activation | No policy row is seeded; Backend advertising eligibility fails closed for missing/stale/invalid/non-eligible state and immutable no-ad zones. Community remains P1 disabled. |
| RPO, RTO and backup retention | BLOCKED — owner/ops/legal | Production disaster-recovery sign-off | Do not claim values; keep runbook placeholders explicit. |
| cPanel/hosting access, confirmed deploy paths and Landing cutover approval | BLOCKED — owner/ops | WEB-PRE-002 and production replacement of Coming Soon | Preserve `deploy/coming-soon/` unchanged. `/landing` is a release-candidate application route only; no cutover from Issue #32. |
| Full formatted master-plan DOCX | PENDING — owner/repository | Completeness reconciliation of REQ/AC/decision indexes | Keep indexes `kickoff_mirror_only`; continue explicit blocker. |

## P0-RELEASE-001 safe completion boundary

Issue #32 may be code-complete and merge-ready while the legal/contact rows above remain blocked. Completion means the professional public surfaces, localization, accessibility, metadata, guides and visible blocker plumbing are tested and release-ready—not that missing owner/legal facts have been approved.

Final legal publication requires replacing each applicable blocker with approved owner/legal input, reviewing all three language versions, assigning real policy versions/effective dates, and performing a separate explicit public Landing cutover under ADR-005. Until then `deploy/coming-soon/` remains canonical.
