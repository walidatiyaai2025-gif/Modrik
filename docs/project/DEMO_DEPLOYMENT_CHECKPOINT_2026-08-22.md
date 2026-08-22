# Demo Deployment Checkpoint — 2026-08-22

## Result

The authorized MODRIK evaluation deployment to `demo.modrik.org` completed successfully on GitHub Actions workflow run `32563427725`, attempt 2.

The deployment checked out canonical `main` and resolved immutable release SHA:

`c82604443c5d6b3100e8df03f8fb37f089fc2853`

This remains the repository-recorded deployed Demo source checkpoint until a newer authorized deployment succeeds.

## Successful gates

The successful attempt passed:
- deployment secret-presence validation;
- canonical `main` checkout and immutable release resolution;
- Student Web production build;
- Backend production Composer install;
- verified cPanel package assembly and audit retention;
- FTPS client installation;
- protected one-shot deployment bridge preparation and upload;
- FTPS package transfer;
- protected deployment bridge execution;
- one-shot deployment file cleanup;
- external smoke for `https://api.demo.modrik.org/up` and `https://demo.modrik.org/`.

## Previous blocker closed

Attempt 1 failed before FTPS upload because Backend Admin Vite assets were absent from the package boundary. PR #196 fixed that defect by making `scripts/package-demo-cpanel.sh` build and verify Backend Admin assets before packaging. Attempt 2 then passed the complete deployment path.

## Source integration after this deployment

Subsequent source integrations are newer than the deployed Demo SHA and must not be represented as already deployed. In particular, PR #234 made capability-surface governance executable in CI and PR #232 strengthened the next authorized Demo deployment so API health plus exact Web and unauthenticated Admin Build SHA identity are mandatory.

A future Demo deployment advances this checkpoint only after package, FTPS, protected bridge, cleanup and the integrated exact Build SHA smoke all succeed.

## Boundaries

This checkpoint records Demo/evaluation deployment evidence only. It does not authorize or imply:
- production `modrik.org` cutover;
- production credentials/signing completion;
- final legal approval;
- real curriculum/content-rights approval;
- production age/ad/community policy approval;
- Production Ready status.

The Demo remains limited to fixture/synthetic or separately owner-approved evaluation content under existing repository gates.
