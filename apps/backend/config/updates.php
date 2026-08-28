<?php

$maxPackageKb = (int) env('MODRIK_UPDATE_MAX_PACKAGE_KB', 131072);
$maxPackageKb = max(12288, min(524288, $maxPackageKb));
$defaultLiveBackendRoot = base_path();
$defaultLiveWebRoot = dirname(base_path()).DIRECTORY_SEPARATOR.'demo.modrik.org';

return [
    'runtime_root' => env('MODRIK_UPDATE_RUNTIME_ROOT', dirname(base_path()).DIRECTORY_SEPARATOR.'.modrik-updates'),
    'shared_env_path' => env('MODRIK_UPDATE_SHARED_ENV', base_path('.env')),
    'shared_storage_path' => env('MODRIK_UPDATE_SHARED_STORAGE', storage_path()),
    'shared_uploads_path' => env('MODRIK_UPDATE_SHARED_UPLOADS', storage_path('app/public')),
    'live_backend_root' => env('MODRIK_UPDATE_LIVE_BACKEND_ROOT', $defaultLiveBackendRoot),
    'live_web_root' => env('MODRIK_UPDATE_LIVE_WEB_ROOT', $defaultLiveWebRoot),
    'upload_disk' => 'local',
    'upload_directory' => 'system-updates/uploads',
    'max_package_kb' => $maxPackageKb,
    'php_binary' => env('MODRIK_UPDATE_PHP_BINARY'),
    'minimum_php_version' => '8.4.1',
];
