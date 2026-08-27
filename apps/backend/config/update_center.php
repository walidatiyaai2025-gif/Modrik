<?php

return [
    'demo' => [
        // Fail closed by default. Demo hosting mutation must be explicitly enabled
        // in the deployed Backend after the locked cPanel topology is confirmed.
        'hosting_bridge_enabled' => (bool) env('MODRIK_DEMO_HOSTING_BRIDGE_ENABLED', false),
        'web_root' => (string) env('MODRIK_DEMO_WEB_ROOT', '/home/solscool/public_html/demo.modrik.org'),
        'node_app_root' => (string) env('MODRIK_DEMO_NODE_APP_ROOT', 'public_html/demo.modrik.org'),
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
