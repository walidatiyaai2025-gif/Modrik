# Real Pilot Materials Registry

Issue: #362  
Campaign: `MODRIK-CHILDREN-READY-20260917-A`

## Purpose

Record the real learning sources supplied by the owner for the Year 6/7 pilot without treating receipt of a file as permission to publish it or as proof of an academic mapping.

The executable registry is:

`apps/backend/resources/pilot/real-pilot-materials.json`

The Admin surface is:

`Content -> Real Pilot Materials`

## Guardrails

- Raw books, worksheets and scans are **not committed to GitHub**.
- Each received source is bound by SHA-256 so a future controlled-storage copy can be verified byte-for-byte.
- A source is not student-deliverable merely because it is registered.
- Rights remain `pending_review` unless authoritative evidence explicitly approves them.
- Unknown Year/track/board/syllabus values remain `mapping_required`; they are never guessed.
- Student-identifying marks must be redacted before controlled storage or content preparation.
- Registration does not create questions, lessons, quizzes, publication approval, or a readiness-ledger PASS.

## Owner-supplied source set — 2026-09-18

| Source | Type | Source-backed academic fact | Registry state |
| --- | --- | --- | --- |
| Arabic Language — Grade 6 — Second Term — Part 1 | PDF/book | Grade 6, second term, part 1, 2025/2026 are visible in the source | Source-verified partial mapping; rights pending |
| FANBOYS coordinating-conjunction worksheet — completed | Worksheet scan | English / FANBOYS visible; pilot year not visible | Mapping required; PII redaction required; rights pending |
| FANBOYS answer key | Answer key image | English / FANBOYS visible; pilot year not visible | Mapping required; rights pending |
| Negative Number Guide | Reference image | Mathematics / negative-number sign rules visible; pilot year not visible | Mapping required; rights pending |
| Integer addition/subtraction practice | Worksheet image | Mathematics / integer operations visible; pilot year not visible | Mapping required; rights pending |
| Subtracting integers worksheet | Worksheet image | Source itself labels the sheet Grade 5 | Mapping required; retained as source-labelled Grade 5 and never silently relabelled Year 6/7 |

## Grade 6 Arabic segmentation

The book is indexed as a source, not as one opaque PDF. The manifest records printed-page and PDF-page ranges for its table-of-contents structure.

### Unit 1

- Learning outcomes.
- Topic: **آيات من سورة القصص**
  - reading, vocabulary and comprehension;
  - complete simile;
  - `إن وأخواتها`;
  - tanween / spelling / handwriting;
  - two-linked-paragraph writing;
  - mixed training.
- Topic: **الحديث الشريف — أعظم الصدقة**
  - reading, vocabulary and comprehension;
  - complete simile;
  - `إن وأخواتها` and predicate types;
  - tanween / spelling / handwriting;
  - writing;
  - mixed training.
- Listening: **هاجر ثبات ويقين**.
- Comprehensive application.

### Unit 2

- Learning outcomes.
- Topic: **هذي بلادي**
  - reading, vocabulary and comprehension;
  - incomplete simile;
  - adjective (`النعت`);
  - `التاء المربوطة والتاء المفتوحة`;
  - letter writing;
  - mixed training.
- Topic: **من عبق التاريخ**
  - reading, vocabulary and comprehension;
  - incomplete simile;
  - adjective (`النعت`);
  - `التاء المربوطة والهاء`;
  - letter writing;
  - mixed training.
- Listening: **النوخذة العادل**.
- Comprehensive application.
- Free reading.

The source learning-outcome families are normalized only for operational grouping: reading, vocabulary, comprehension, literary appreciation, grammar, spelling, handwriting, writing and listening. These labels do not replace the canonical `curriculum_nodes` authority.

## Next governed transition

A registered source may move toward real pilot content only through the existing content workflow:

`verified controlled-storage source -> approved academic mapping -> preparation request -> manual governed Question Bank preparation -> validation -> review -> rights gate -> publication`

Only published, rights-cleared, correctly mapped canonical Question Bank content can become student-deliverable.
