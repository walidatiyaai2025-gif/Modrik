# MODRIK Mobile

Flutter student application shell for Android and iOS. Use Flutter 3.47.1 stable. Windows is deferred.

```bash
flutter pub get
flutter analyze
flutter test
```

The shell consumes the path package at `packages/design-tokens`. App icons are deterministically generated from the canonical MODRIK SVG with `npm run brand:icons` at repository root.

`org.modrik.placeholder` identifiers are intentionally non-production. Final bundle IDs, store IDs, signing, and provider configuration remain owner-controlled release inputs.
