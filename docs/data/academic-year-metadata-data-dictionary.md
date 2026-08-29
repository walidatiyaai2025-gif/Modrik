# Academic catalogue metadata data dictionary — Issue #310

This document extends the canonical P0 data dictionary for the engineering-owned academic display metadata introduced by Issue #310. It does not authorize or invent real board, syllabus, version, curriculum, or owner-controlled school-year facts.

| Entity / field | Purpose | Invariants |
| --- | --- | --- |
| `academic_year_metadata.year_level` | Stable join key to the existing Backend-owned `academic_tracks.year_level` reference. | Unique. Metadata may be absent; absence preserves the existing readable year-label fallback. It is not a learner-entered identifier. |
| `academic_year_metadata.labels` | Operator-authored display labels for one school-year key. | JSON object containing non-empty safe `ar`, `en`, and `fr` strings. No markup/control characters. Labels are display metadata only and never redefine curriculum authority. |
| `academic_year_metadata.display_order` | Curated order of school years in the learner catalogue. | Integer operator metadata. Configured rows sort first; missing metadata falls back deterministically to the existing year key. |
| `academic_tracks.display_order` | Curated track order inside a school year. | Integer operator metadata. Ordering does not bypass `availability_state`; only published display-safe tracks remain learner-selectable. Stable track identity remains the final deterministic tie-breaker. |
| `academic_catalogue_metadata_audits` | Immutable evidence for year-label/year-order and track-order Admin mutations. | ULID identity; target type/key, actor, action, before/after JSON, mandatory safe operator reason, occurrence timestamp. Mutations are Admin-only, transactionally revalidated, and do not contain credentials or fabricated academic facts. |

## Learner-facing contract

`GET /v1/academic-tracks` preserves its established wire shape: each item exposes only the opaque track `id`, `year: {key, label}`, and safe AR/EN/FR track `labels`. `year.label` is selected by the Backend from configured metadata for the current locale, with the existing readable fallback when metadata is absent. The Backend returns the catalogue in curated year/track order; Web and Mobile consume that order rather than re-sorting it.

Internal year metadata rows, display-order numbers, availability state, board, syllabus, track code, fixture markers, audit rows, and operator reasons are not exposed to learners.

## Migration / owner-input rule

The migration creates metadata storage and ordering columns only. It does not seed real localized school-year names or infer owner academic facts. Existing year references continue to work through deterministic fallback until an authorized operator supplies labels/order through the discoverable audited Admin surface.
