<?php

return [
    'env_path' => env('MODRIK_INSTALLER_ENV_PATH', base_path('.env')),
    'lock_path' => env('MODRIK_INSTALLER_LOCK_PATH', storage_path('app/private/installation.lock')),
];
