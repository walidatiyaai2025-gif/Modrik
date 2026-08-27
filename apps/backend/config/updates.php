<?php

$maxPackageKb = (int) env('MODRIK_UPDATE_MAX_PACKAGE_KB', 131072);
$maxPackageKb = max(12288, min(524288, $maxPackageKb));

return [
    'runtime_root' => env('MODRIK_UPDATE_RUNTIME_ROOT', dirname(base_path()).DIRECTORY_SEPARATOR.'.modrik-updates'),
    'shared_env_path' => env('MODRIK_UPDATE_SHARED_ENV', base_path('.env')),
    'shared_storage_path' => env('MODRIK_UPDATE_SHARED_STORAGE', storage_path()),
    'shared_uploads_path' => env('MODRIK_UPDATE_SHARED_UPLOADS', storage_path('app/public')),
    'upload_disk' => 'local',
    'upload_directory' => 'system-updates/uploads',
    'max_package_kb' => $maxPackageKb,
];
