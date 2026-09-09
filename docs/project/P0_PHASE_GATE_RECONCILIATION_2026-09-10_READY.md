# Reconciliation candidate status

Canonical branch: `integration/p0-phase-gate-reconcile-20260909`

Scope: control/state/evidence reconciliation only.

Candidate requirements before integration:

- exact-head governed CI must be green;
- no unresolved reviews/threads;
- base `main` must remain compatible with the recorded baseline or the branch must be reconciled before merge;
- #260 and #318 remain OPEN and not PASS;
- no P1 activation or `VERIFIED_FINAL_COMPLETE` claim is permitted by this candidate.
