<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\DB;
use Throwable;

final class DemoActivationHealthVerifier
{
    /**
     * @return array{ok:bool,checks:array<string,bool>,reason:?string}
     */
    public function verify(string $releaseSha): array
    {
        if (preg_match('/^[0-9a-f]{40}$/i', $releaseSha) !== 1) {
            return $this->failure([], 'invalid_release_sha');
        }

        $releaseSha = strtolower($releaseSha);
        $shortSha = substr($releaseSha, 0, 12);
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = true;
        } catch (Throwable) {
            return $this->failure($checks, 'database_unavailable');
        }

        $apiUrl = (string) config('update_center.demo.api_up_url', 'https://api.demo.modrik.org/up');
        $webUrl = (string) config('update_center.demo.web_url', 'https://demo.modrik.org/');
        $studentUrl = (string) config('update_center.demo.student_url', 'https://demo.modrik.org/student');
        $adminUrl = (string) config('update_center.demo.admin_login_url', 'https://api.demo.modrik.org/admin/login');
        $originIp = trim((string) config('update_center.demo.origin_ip', ''));

        if ($originIp === '' || filter_var($originIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return $this->failure($checks, 'origin_ip_unavailable');
        }

        $api = $this->request($apiUrl, $shortSha.'-api');
        if ($api === null || ! $this->successfulHttp($api)) {
            return $this->failure($checks, 'api_unreachable');
        }
        $checks['api'] = true;

        $web = $this->request($webUrl, $shortSha.'-web');
        if ($web === null || ! $this->successfulHttp($web) || ! $this->hasReleaseIdentity($web['body'], $releaseSha, $shortSha)) {
            return $this->failure($checks, 'web_release_mismatch');
        }
        if (! str_contains($web['body'], 'data-testid="modrik-landing-page"')
            || ! str_contains($web['body'], 'data-testid="modrik-student-portal-entry"')
            || str_contains($web['body'], 'This screen could not be completed.')) {
            return $this->failure($checks, 'web_runtime_mismatch');
        }
        $checks['web'] = true;

        $student = $this->request($studentUrl, $shortSha.'-student');
        if ($student === null || ! $this->successfulHttp($student) || ! $this->hasReleaseIdentity($student['body'], $releaseSha, $shortSha)) {
            return $this->failure($checks, 'student_release_mismatch');
        }
        if (! str_contains($student['body'], 'data-testid="modrik-student-portal"')
            || ! str_contains($student['body'], 'class="auth-shell"')
            || str_contains($student['body'], 'data-testid="modrik-landing-page"')
            || str_contains($student['body'], 'This screen could not be completed.')) {
            return $this->failure($checks, 'student_runtime_mismatch');
        }
        $checks['student'] = true;

        $admin = $this->request($adminUrl, $shortSha.'-admin');
        if ($admin === null || ! $this->successfulHttp($admin)
            || ! str_contains($admin['body'], 'data-testid="modrik-release-badge"')
            || ! str_contains($admin['body'], "MODRIK deployed release: {$releaseSha}")
            || ! str_contains($admin['body'], "Build {$shortSha}")) {
            return $this->failure($checks, 'admin_release_mismatch');
        }
        $checks['admin'] = true;

        $cssUrl = $this->extractCssUrl($webUrl, $web['body']);
        if ($cssUrl === null) {
            return $this->failure($checks, 'css_reference_missing');
        }
        $css = $this->request($cssUrl, $shortSha.'-css');
        if ($css === null || ! $this->successfulHttp($css) || ! str_contains(strtolower($css['content_type']), 'text/css')) {
            return $this->failure($checks, 'css_unreachable');
        }
        $checks['css'] = true;

        $origin = $this->request($webUrl, $shortSha.'-origin', $originIp);
        if ($origin === null || ! $this->successfulHttp($origin) || ! $this->hasReleaseIdentity($origin['body'], $releaseSha, $shortSha)) {
            return $this->failure($checks, 'origin_release_mismatch');
        }
        if (! str_contains($origin['body'], 'data-testid="modrik-landing-page"')
            || str_contains($origin['body'], 'This screen could not be completed.')) {
            return $this->failure($checks, 'origin_runtime_mismatch');
        }
        $checks['origin'] = true;

        return ['ok' => true, 'checks' => $checks, 'reason' => null];
    }

    /**
     * @return array{status:int,body:string,content_type:string}|null
     */
    private function request(string $url, string $probe, ?string $resolveIp = null): ?array
    {
        if (! extension_loaded('curl') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            return null;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $requestUrl = $url.$separator.'modrik_release_probe='.rawurlencode($probe);
        $curl = curl_init();
        if ($curl === false) {
            return null;
        }

        $timeout = max(3, min(20, (int) config('update_center.demo.health_timeout_seconds', 8)));
        $options = [
            CURLOPT_URL => $requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Cache-Control: no-cache, no-store, max-age=0',
                'Pragma: no-cache',
                'Accept: text/html,application/json;q=0.9,*/*;q=0.8',
            ],
            CURLOPT_USERAGENT => 'MODRIK-Activation-Health/1.0',
            CURLOPT_ENCODING => '',
        ];

        if ($resolveIp !== null) {
            $port = isset($parts['port']) ? (int) $parts['port'] : 443;
            $options[CURLOPT_RESOLVE] = [$parts['host'].':'.$port.':'.$resolveIp];
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        if (! is_string($body)) {
            curl_close($curl);

            return null;
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string) (curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: '');
        curl_close($curl);

        return ['status' => $status, 'body' => $body, 'content_type' => $contentType];
    }

    /** @param array{status:int,body:string,content_type:string} $response */
    private function successfulHttp(array $response): bool
    {
        return $response['status'] >= 200 && $response['status'] < 300;
    }

    private function hasReleaseIdentity(string $body, string $releaseSha, string $shortSha): bool
    {
        return str_contains($body, 'data-testid="modrik-web-release-badge"')
            && str_contains($body, "MODRIK deployed release: {$releaseSha}")
            && str_contains($body, "Build {$shortSha}");
    }

    private function extractCssUrl(string $webUrl, string $body): ?string
    {
        if (preg_match('~href=["\']([^"\']*/_next/static/css/[^"\']+\.css(?:\?[^"\']*)?)["\']~i', $body, $match) !== 1) {
            return null;
        }

        $href = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        if (str_starts_with($href, 'https://')) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return 'https:'.$href;
        }

        $parts = parse_url($webUrl);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin.'/'.ltrim($href, '/');
    }

    /**
     * @param  array<string,bool>  $checks
     * @return array{ok:bool,checks:array<string,bool>,reason:string}
     */
    private function failure(array $checks, string $reason): array
    {
        return ['ok' => false, 'checks' => $checks, 'reason' => $reason];
    }
}
