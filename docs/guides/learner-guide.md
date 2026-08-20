# MODRIK learner guide

Status: P0-RELEASE-001 / Issue #32. Public presentation route: `/help` with AR/EN/FR localization.

This guide describes the implemented product workflow. It does not identify a real exam board/syllabus/version, promise grades or outcomes, or invent a support channel.

## 1. Academic context

The active academic context is Backend-owned. When onboarding data is available, the learner sees the active context. Changing track uses the defined full-reset workflow: prior attempts/mastery/history are archived rather than silently overwritten. The Web client does not invent an academic-track catalogue; the production selectable catalogue remains Backend-owned.

## 2. Study

Use the Student Web Study workspace to read a published lesson. AR/EN/FR interface direction follows the selected locale and lesson/question/option text handles mixed direction independently. Content shown in repository verification remains synthetic unless separately approved real-curriculum inputs and rights evidence exist.

## 3. Practice and reconnect

Starting practice asks the Backend to create an authoritative attempt. The browser never creates the assessment seed, question selection, score or authoritative order. Reconnecting to an in-progress attempt reloads the persisted Backend attempt and preserves its authoritative order.

Answer revisions and submission are Backend-owned/idempotent. A conflict causes the client to reload authoritative state rather than silently overwrite it.

## 4. Progress

Progress/mastery is read from the active Backend context. Student Web displays the projection; it does not independently calculate authoritative mastery.

## 5. Offline/stale behavior

Web surfaces expose explicit offline/stale/retry states and pause server actions while disconnected. Do not assume an unsent browser change is acknowledged until the server confirms it. Mobile offline synchronization has its own durable operation/acknowledgement contract.

## 6. Accessibility and languages

Applicable learner UI supports:

- Arabic, English and French;
- RTL for Arabic and LTR for English/French;
- keyboard-native controls and visible focus;
- semantic headings/landmarks/form groups and screen-reader status/error announcements;
- large-text/200% zoom layouts without fixed text-height assumptions;
- reduced-motion preference;
- loading, empty, error, offline, retry and permission states where applicable.

## 7. Support and account deletion

Public support/contact channels, service hours and escalation ownership are still owner-controlled inputs. Do not use repository author emails, GitHub identities or placeholders as production support contacts.

The Backend account lifecycle supports protected account deletion requiring recent production authentication and explicit `DELETE` confirmation; final user-facing entry point, legal retention/hard-purge periods and public support routing remain pending approved release inputs. See `/account-deletion`, `/support` and `/contact` in the release-candidate public surface.
