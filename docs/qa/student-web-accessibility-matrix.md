# Student Web accessibility and responsive matrix — P0-WEB-001

Scope: Issue #17, REQ-P0-007, REQ-P0-012, AC-P0-014 and clean-checkout AC-P0-020. This matrix supplements automated Web gates; it does not move Backend business rules into the client.

## Automated evidence

| Contract | Automated evidence |
| --- | --- |
| REQ-P0-007 desktop/laptop-first Web | `page.test.tsx` asserts the dedicated desktop navigation/stage shell and semantic navigation. Next production build is a required CI gate. |
| REQ-P0-012 AR/EN/FR + RTL/LTR | `student-copy.test.tsx` requires identical complete copy keys for all three locales, verifies Arabic RTL and EN/FR LTR, and checks deterministic mixed-content fallback. |
| AC-P0-014 keyboard/screen-reader/state foundations | Server-render smoke asserts skip navigation, landmarks/navigation labels, current navigation state, live status region and language controls. Interactive controls use native button/input/fieldset/summary semantics and global visible focus. |
| Assessment authority consumed, not recreated | `learning-api.test.tsx` proves `POST /attempts` sends only `quiz_id` + idempotency metadata, with no client seed/question/order fields; resume uses `GET /attempts/{id}`. |
| Reduced motion + large text | CSS removes nonessential motion under `prefers-reduced-motion: reduce`; layout uses fluid/minmax grids without fixed content heights. Production build/lint/typecheck guard regressions. |

## Manual desktop/laptop matrix

Run against the fixture Backend with browser DevTools and at least one screen reader available on the target OS.

| Case | Required result |
| --- | --- |
| 1440×900 / 1920×1080 | Persistent desktop sidebar, spacious content stage, dashboard metric row, two-column academic/action region, two-column study/practice workspaces. No mobile-card stretching. |
| 1280×800 / 1024×768 | Sidebar and content remain distinct; metrics wrap to two columns where needed; long AR/FR copy does not clip or overlap. |
| 900px transition | Navigation becomes a compact horizontal application bar; study/practice rails stack without hiding content or controls. |
| 200% browser zoom / enlarged default font | No essential text, buttons, question legends, options or result controls are clipped; content can reflow vertically; horizontal scrolling is not required for primary learning tasks. |
| English | `lang=en`, LTR shell, all primary navigation/state/action copy in English. |
| French | `lang=fr`, LTR shell, all primary navigation/state/action copy in French; long labels reflow. |
| Arabic | `lang=ar`, RTL shell, Arabic font stack, logical spacing/borders mirror, navigation and academic/study/practice/progress remain usable. |
| Mixed Arabic/Latin lesson/question/option text | Content blocks, prompts, options and free-text inputs use content-aware direction (`dir=auto`) so formulas, IDs and Latin terms do not reverse incorrectly. |
| Keyboard only | Tab begins with the skip link, reaches locale controls and navigation/action controls in DOM order, radio groups operate normally, `summary` toggles reset consequences, Enter/Space activate buttons. No keyboard trap. |
| Visible focus | Every interactive link/button/input/summary has a high-visibility token-based focus outline against both dark and light surfaces. |
| Screen reader landmarks | Navigation is named, main content is labelled by the current H1, loading/offline/result/error updates use status/alert regions, question groups expose fieldset/legend semantics, progress elements have accessible names. |
| Reduced motion | With OS/browser reduced motion enabled, transitions/animations collapse to effectively zero duration and no essential state depends on animation. |

## Data and failure-state matrix

| State | Expected Web behavior |
| --- | --- |
| Loading | Branded loading panel is a polite status region; navigation shell remains stable. |
| Empty published lesson | Dashboard/study show an explicit empty lesson state; study/practice launch controls are disabled rather than fabricating content. |
| Empty progress | Progress workspace states that a completed practice is needed and offers a practice navigation action when a lesson exists. |
| Backend unavailable | Error panel explains service unavailability and exposes Retry. |
| 401/403 | Permission panel is shown; no privileged workaround or fixture credential is exposed to the browser bundle. |
| Offline after content loaded | Previously loaded lesson/workspace stays visible as stale content; a prominent offline banner explains that server writes are paused; start/submit inputs are disabled. |
| Offline before first load | Offline state panel explains the condition and exposes Retry for reconnection. |
| Retry / reconnect | Reloads session/context/lesson/progress from the Backend; an in-progress locally remembered attempt ID is resolved through `GET /attempts/{id}`. |
| Answer revision conflict | Client reloads the persisted authoritative attempt and asks the learner to review before continuing; it does not resolve the conflict by overwriting Backend state. |
| Same-attempt resume | Question and option arrays render in exactly the order returned by the Backend. No client sorting, shuffling, seed generation or question selection occurs. |
| Submitted attempt | Result is announced in a status region, the active-attempt resume pointer is cleared, and a learner may start a separate new Backend attempt. |
| Academic context active | Year level and Backend-configured track state are presented; reset consequences explain archival rather than deletion. |
| Academic onboarding / track change | Web does not invent real board/syllabus/track values. Until a Backend track-catalogue/selection contract exists, the professional change-track control remains intentionally absent and the dependency is documented. |

## Contract dependency owned outside Issue #17

A production track-change selector requires a Backend-owned, authorized contract that returns the tracks available to the current learner with display-safe labels and stable IDs. Issue #17 does not add migrations/OpenAPI or hardcode synthetic board/syllabus choices because Academic/Backend contract ownership is outside this Web branch.
