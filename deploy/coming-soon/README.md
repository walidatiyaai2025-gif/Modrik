# MODRIK Coming Soon — temporary public shell

Dependency-free static site for the first public publish of `modrik.org` while the main product is under development.

## Local preview

```bash
python3 -m http.server 8080 --directory deploy/coming-soon
```

Open `http://localhost:8080`.

## cPanel

Publish the contents of this directory to the Document Root for `modrik.org`. The target root must contain `index.html` and `assets/` directly.

Do not add a fake countdown, fake testimonials, or a notification form unless a real backend + privacy flow is implemented.
