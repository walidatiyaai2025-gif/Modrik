<?php

declare(strict_types=1);

/**
 * GOV-DEPLOY-001 CloudLinux Node Selector desired-state validator.
 *
 * Usage:
 *   php validate-cloudlinux-node-state.php <app-root> <domain> <node-major> [expected-startup]
 *
 * Reads the raw `cloudlinux-selector get --json --interpreter nodejs` response
 * from STDIN. Emits one sanitized machine-readable line on success and no
 * credentials/environment values.
 */

$appRoot = trim((string) ($argv[1] ?? ''), '/');
$expectedDomain = strtolower(trim((string) ($argv[2] ?? '')));
$expectedNodeMajor = trim((string) ($argv[3] ?? ''));
$expectedStartup = isset($argv[4]) ? trim((string) $argv[4]) : null;

$fail = static function (string $message): never {
    fwrite(STDERR, "MODRIK_SELECTOR_STATE_ERROR: {$message}\n");
    exit(1);
};

if ($appRoot === '' || preg_match('/[^A-Za-z0-9._\/-]/', $appRoot)) {
    $fail('target application root is invalid');
}
if ($expectedDomain === '' || preg_match('/[^a-z0-9.-]/', $expectedDomain)) {
    $fail('expected domain is invalid');
}
if (!preg_match('/^\d+$/', $expectedNodeMajor)) {
    $fail('expected Node major is invalid');
}
if ($expectedStartup !== null && ($expectedStartup === '' || str_starts_with($expectedStartup, '/') || str_contains($expectedStartup, '..') || preg_match('/[^A-Za-z0-9._\/-]/', $expectedStartup))) {
    $fail('expected startup file is invalid');
}

$raw = stream_get_contents(STDIN);
$decoded = json_decode($raw === false ? '' : $raw, true);
if (!is_array($decoded)) {
    $fail('Selector output is not valid JSON');
}
if (($decoded['result'] ?? null) !== 'success') {
    $fail('Selector did not report success');
}

$matches = [];

$walk = static function (mixed $value, array $path = []) use (&$walk, &$matches, $appRoot): void {
    if (!is_array($value)) {
        return;
    }

    $pathTail = $path === [] ? null : (string) end($path);
    $fieldRoot = $value['app_root'] ?? $value['app-root'] ?? null;
    $isTargetKey = $pathTail !== null && trim($pathTail, '/') === $appRoot;
    $isTargetField = is_string($fieldRoot) && trim($fieldRoot, '/') === $appRoot;

    if ($isTargetKey || $isTargetField) {
        $version = null;
        foreach (array_reverse($path) as $segment) {
            if (preg_match('/^(\d+)\.\d+\.\d+$/', (string) $segment, $versionMatch)) {
                $version = (string) $segment;
                break;
            }
        }
        if ($version === null && isset($value['version']) && is_scalar($value['version'])) {
            $version = (string) $value['version'];
        }

        $matches[] = [
            'app_root' => $appRoot,
            'domain' => is_scalar($value['domain'] ?? null) ? strtolower(trim((string) $value['domain'])) : '',
            'version' => $version ?? '',
            'startup_file' => is_scalar($value['startup_file'] ?? $value['startup-file'] ?? null)
                ? trim((string) ($value['startup_file'] ?? $value['startup-file']))
                : '',
            'mode' => is_scalar($value['app_mode'] ?? $value['mode'] ?? null)
                ? strtolower(trim((string) ($value['app_mode'] ?? $value['mode'])))
                : '',
            'status' => is_scalar($value['app_status'] ?? $value['status'] ?? null)
                ? strtolower(trim((string) ($value['app_status'] ?? $value['status'])))
                : '',
        ];
    }

    foreach ($value as $key => $child) {
        if (is_array($child)) {
            $walk($child, [...$path, (string) $key]);
        }
    }
};

$walk($decoded);

// Some Selector versions expose the same application through more than one
// wrapper object. De-duplicate byte-identical safe state before enforcing the
// single-authoritative-application invariant.
$unique = [];
foreach ($matches as $match) {
    $key = json_encode($match, JSON_UNESCAPED_SLASHES);
    if (is_string($key)) {
        $unique[$key] = $match;
    }
}
$matches = array_values($unique);

if (count($matches) !== 1) {
    $fail('expected exactly one registered target application; found '.count($matches));
}

$state = $matches[0];
if ($state['domain'] !== $expectedDomain) {
    $fail('registered domain does not match locked Demo domain');
}
if (!preg_match('/^(\d+)\./', $state['version'], $majorMatch) || $majorMatch[1] !== $expectedNodeMajor) {
    $fail('registered Node version does not match locked Node major');
}
if ($state['mode'] !== 'production') {
    $fail('registered application mode is not production');
}
if ($state['status'] !== 'started') {
    $fail('registered application is not started');
}
if ($state['startup_file'] === '' || str_starts_with($state['startup_file'], '/') || str_contains($state['startup_file'], '..') || preg_match('/[^A-Za-z0-9._\/-]/', $state['startup_file'])) {
    $fail('registered startup file is missing or unsafe');
}
if ($expectedStartup !== null && $state['startup_file'] !== $expectedStartup) {
    $fail('registered startup file did not converge to the artifact-derived desired state');
}

printf(
    "MODRIK_SELECTOR_STATE_OK app_root=%s domain=%s version=%s mode=%s status=%s startup_file=%s\n",
    $state['app_root'],
    $state['domain'],
    $state['version'],
    $state['mode'],
    $state['status'],
    $state['startup_file'],
);
