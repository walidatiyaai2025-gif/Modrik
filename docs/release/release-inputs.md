# Production release inputs

Missing inputs block only their affected release/workflow. Safe fixtures and placeholders remain allowed for architecture and tests.

| Input | Status | Blocks | Safe repository behavior |
| --- | --- | --- | --- |
| Exact board, syllabus, and version | BLOCKED — owner | Real curriculum configuration/import | Use `FIXTURE:*` synthetic scope only. |
| Initial real subject IDs and content-rights evidence | BLOCKED — owner/content | Real content publication | Keep synthetic golden fixtures; require provenance review. |
| Legal entity/controller/contact and approved Privacy/Terms | BLOCKED — owner/legal | Final public legal publication | Build clearly marked templates/matrix only. |
| Google/Apple production IDs and secrets | BLOCKED — owner/provider | Production social sign-in | Keep provider configuration absent/off; never invent IDs. |
| Firebase production project/credentials | BLOCKED — owner/provider | Optional FCM/Remote Config/Crashlytics/Analytics | Core remains functional without Firebase. |
| Android/iOS bundle IDs, store IDs, and signing | BLOCKED — owner/provider | Store builds/submission | Use `org.modrik.placeholder` only in the non-production scaffold. |
| Age/ad/community activation policy | BLOCKED — owner/legal/safety | Production ads or community activation | Default safe/off; community remains P1 disabled. |
| RPO, RTO, backup retention, and data retention | BLOCKED — owner/ops/legal | Production disaster-recovery and retention sign-off | Do not claim values; preserve auditable archival/deletion capability. |
| cPanel/hosting access and confirmed deploy paths | BLOCKED — owner/ops | WEB-PRE-002 and public service deployment | Preserve deployable artifacts and recorded smoke commands. |
| Full formatted master-plan DOCX | PENDING — owner/repository | Completeness reconciliation of REQ/AC/decision indexes | Mark indexes `kickoff_mirror_only`; continue explicit Issue #1 bootstrap. |
