# cPanel deployment — MODRIK

The first public release is the dependency-free static shell in `deploy/coming-soon/`.

## First publish

1. Add `modrik.org` in cPanel Domains and note its exact **Document Root**.
2. Ensure DNS resolves to the hosting account and AutoSSL/HTTPS is active.
3. Either upload/extract the contents of `deploy/coming-soon/` into that Document Root, or use cPanel Git Version Control.
4. For Git deployment, copy `.cpanel.yml.example` to repository root as `.cpanel.yml` and replace `CHANGE_ME_MODRIK_DOCROOT` with the real Document Root before deployment.
5. Verify `https://modrik.org/` on desktop and mobile, plus favicon, CSS, HTTPS redirect and no directory listing.

Do not point the deploy template at an existing unrelated site root.

Later, when the full Next.js public/student web is production-ready, replace the temporary shell through a controlled release; keep rollback to the static shell available.
