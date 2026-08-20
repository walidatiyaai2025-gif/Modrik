# Requirement indexes

These files are the machine-readable implementation entry point for MODRIK P0 work.

## Completeness boundary

The formatted master-plan DOCX referenced by `AGENTS.md` is not present in the repository as of 2026-08-20. The current indexes therefore extract only facts stated in:

- `AGENTS.md`
- `docs/product/MASTER_PLAN_START_HERE.md`
- `docs/brand/BRAND_SYSTEM.md`
- GitHub Issue #1

Every YAML file declares `completeness: kickoff_mirror_only`. Importing the owner DOCX and reconciling its locked decisions, requirements, and acceptance criteria is a READY documentation task, but its absence does not block the explicitly listed bootstrap work.

Do not treat repository-local `LOCK-P0-*` identifiers as replacements for owner-document decision IDs. Known owner decision IDs are recorded in `source_decision_ids`; unknown IDs remain empty rather than being guessed.
