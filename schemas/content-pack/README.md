# Content Pack v1

A returned ZIP is treated as hostile input. Validation order is:

1. Reject absolute paths, traversal, symlinks, duplicate normalized paths, unsupported media types, excessive compression ratio, and declared or actual size/count limit breaches.
2. Parse `manifest.json` with `manifest.schema.json`.
3. Match `preparation_request_id`, canonical `settings_hash`, and `schema_version` to the immutable backend request.
4. Recompute every file size and SHA-256 before parsing content.
5. Validate `content-pack.json` and all referenced records.
6. Apply semantic checks: unique IDs/references/positions, references exist, answer option IDs exist, requested scope/locales are respected, and rights status permits review/import.
7. Stage and review. Publication is a separate authorized action.

The ZIP must contain `manifest.json` and the files declared by that manifest at archive root. Unknown or undeclared files are rejected. Golden fixtures use synthetic, non-copyrighted educational examples and placeholder academic references only.
