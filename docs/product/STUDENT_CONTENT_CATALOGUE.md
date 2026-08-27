# Student Content Catalogue

Issue: #322

## Authority

The Backend owns published-content visibility. The Student Web never guesses lesson, quiz, track, year, or subject identifiers and never promotes draft/staged/rights-pending content into learner-visible state.

## Student flow

1. The learner signs in.
2. The existing school-year-scoped academic track selector activates one Backend-approved academic context.
3. `GET /v1/content-catalogue` reads the learner's active academic context and returns published subject roots plus their published unit/topic descendants, published lessons, and published assessments.
4. The Student Web renders Subject → Unit → Topic → Lesson navigation from that response.
5. A lesson is opened through the existing `GET /v1/lessons/{lessonId}` authority.
6. Practice, quiz, and mock-exam entries are started through the existing authoritative attempt API using the published quiz ID returned by the catalogue.

## Filtering

`GET /v1/content-catalogue?subject_reference=<REFERENCE>` optionally restricts the result to one published subject reference inside the learner's active track. The track and school year are never client-selected query parameters; they come from the active academic context.

## Fail-closed publication rules

The catalogue returns only:

- an active academic context whose track is learner-available (`availability_state=published`),
- `curriculum_nodes.status=published`,
- `lessons.status=published`, and
- `quizzes.status=published` with kind `practice`, `quiz`, or `mock_exam`.

When fixture mode is disabled, an active fixture track is not surfaced by the catalogue. Production Student UI contains no hard-coded lesson ID and does not render fixture provenance as curriculum.

## Empty/onboarding states

- No active academic context → `state=onboarding_required`, empty subjects.
- Active context with no published content → `state=active`, empty subjects.
- A valid subject filter with no published match → `state=active`, empty subjects.

These states are not replaced with demo/sample content.

## Lesson/practice relationship

A published lesson is readable even when it has no practice quiz on the same curriculum node. `practice_quiz_id` on the lesson response is therefore nullable. Assessments are discovered through the catalogue hierarchy rather than being required as a condition for lesson readability.
