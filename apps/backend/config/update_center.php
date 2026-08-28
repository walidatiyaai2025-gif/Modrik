<?php

$defaultWebRoot = dirname(base_path()).DIRECTORY_SEPARATOR.'demo.modrik.org';
$publicHtmlRoot = dirname(base_path());
$defaultHome = dirname($publicHtmlRoot);
$defaultNodeAppRoot = ltrim(str_replace($defaultHome, '', $defaultWebRoot), DIRECTORY_SEPARATOR);

return [
    'demo' => [
        // Selector mutation remains opt-in. The Update Center may always place
        // the standard Passenger restart marker after a verified live copy.
        'hosting_bridge_enabled' => (bool) env('MODRIK_DEMO_HOSTING_BRIDGE_ENABLED', false),
        'web_root' => (string) env('MODRIK_DEMO_WEB_ROOT', $defaultWebRoot),
        'node_app_root' => (string) env('MODRIK_DEMO_NODE_APP_ROOT', $defaultNodeAppRoot),
        'domain' => (string) env('MODRIK_DEMO_DOMAIN', 'demo.modrik.org'),
        'node_major' => (int) env('MODRIK_DEMO_NODE_MAJOR', 22),
        'origin_ip' => (string) env('MODRIK_DEMO_ORIGIN_IP', ''),
        'cloudlinux_selector_bin' => (string) env('MODRIK_CLOUDLINUX_SELECTOR_BIN', ''),
        'cagefs_enter_bin' => (string) env('MODRIK_CAGEFS_ENTER_BIN', '/bin/cagefs_enter.proxied'),
        'selector_timeout_seconds' => (int) env('MODRIK_DEMO_SELECTOR_TIMEOUT_SECONDS', 20),
        'health_timeout_seconds' => (int) env('MODRIK_DEMO_HEALTH_TIMEOUT_SECONDS', 8),
        'api_up_url' => (string) env('MODRIK_DEMO_API_UP_URL', 'https://api.demo.modrik.org/up'),
        'web_url' => (string) env('MODRIK_DEMO_WEB_URL', 'https://demo.modrik.org/'),
        'student_url' => (string) env('MODRIK_DEMO_STUDENT_URL', 'https://demo.modrik.org/student'),
        'admin_login_url' => (string) env('MODRIK_DEMO_ADMIN_LOGIN_URL', 'https://api.demo.modrik.org/admin/login'),
    ],
];
