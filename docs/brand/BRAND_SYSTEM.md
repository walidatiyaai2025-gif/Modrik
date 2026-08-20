# MODRIK | مُدرك — Brand System v1

Status: **LOCKED FOR PILOT**  
Date: 2026-08-20  
Authority: implementation refinement under D-051 (MODRIK brand), D-056 (single canonical design-token source), and D-060 (modrik.org).

## Brand direction

MODRIK is calm, modern, intelligent education. It must not feel overly childish, overly corporate, or visually noisy. The same visual identity is used by Student Web, Mobile, Admin, Landing, and Help, while layout density and navigation remain surface-specific.

## Canonical palette

| Token | Hex | Primary usage |
| --- | --- | --- |
| Deep Navy | `#0D1B2A` | Dark surfaces, hero backgrounds, primary ink on dark identity |
| Royal Blue | `#1E3A8A` | Secondary brand color, links, data/education accents |
| Teal | `#00BFA6` | Primary accent, highlights, focus and active states |
| Sky Blue | `#E6F5FF` | Soft information surfaces |
| Soft Gray | `#F5F7FA` | App/page background |
| Slate Gray | `#64748B` | Muted text and secondary labels |
| Amber | `#FBBF24` | Limited highlight/accent; not a primary CTA color |
| White | `#FFFFFF` | Light surfaces and dark-background text |

Machine-readable source: `packages/design-tokens/tokens.json`. Apps should consume generated/adapted tokens from that source rather than redefining brand colors locally.

## Logo

Concept: an open book / knowledge mark inside a circular growth arc, with a small spark/learner point. The mark is designed to work as app icon/favicon and next to the MODRIK wordmark.

Canonical source files:
- `deploy/coming-soon/assets/logo-mark.svg`
- `deploy/coming-soon/assets/logo-horizontal.svg`

Do not redraw or recolor the logo per surface. Monochrome/white variants may be generated from the canonical SVG while preserving geometry.

## Typography

- Latin: **Poppins** (fallback: Inter/system sans)
- Arabic: **Noto Kufi Arabic** (fallback: Noto Sans Arabic/system sans)

Fonts are referenced by family name only; do not commit font binaries to the repository.

## Working brand line

**Learn More. Achieve More.**  
Arabic working equivalent: **تعلّم أكثر. أنجز أكثر.**

This is a marketing line, not an educational-results guarantee.

## Public shell

Until the full Landing release is ready, `deploy/coming-soon/` is the canonical temporary public shell for `modrik.org`. It must stay truthful, fast, responsive, accessible, and dependency-free. Do not add fake counters, fake testimonials, fake student counts, or non-functional signup forms.
