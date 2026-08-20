# MODRIK Backend and Admin

Laravel 13 API/domain application and Filament 5/Livewire 4 administration shell. MariaDB 10.11 is the Pilot persistence authority; SQLite memory is for fast tests only.

From this directory with PHP 8.4.24 and Composer 2:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

Business rules, authorization, attempt seeds/order, idempotency, content validation, and canonical state belong here rather than in Web or Mobile clients.

BOOT-008 fixture mode is explicitly non-production and disabled unless `MODRIK_FIXTURE_MODE=true`. With the example local environment, `migrate --seed` imports the canonical synthetic Content Pack, and `MODRIK_FIXTURE_BEARER_TOKEN` authenticates only its fixture learner. Never enable this mode or token in a production environment.

Issue #4 adds idempotent `POST /v1/academic-context/activate` and `/reset` lifecycle commands. Reset is the only path for changing an active track: it archives the prior context, its attempts, and its progress while preserving every historical row.
