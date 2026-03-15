<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExternalUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Rejects URLs that resolve to internal/private IP ranges:
     * - Loopback: 127.0.0.0/8, ::1
     * - Link-local IPv4: 169.254.0.0/16
     * - Link-local IPv6: fe80::/10
     * - RFC-1918: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
     * - Unique-Local IPv6: fc00::/7
     * - IPv4-mapped IPv6: ::ffff:0:0/96
     * - Carrier-Grade NAT: 100.64.0.0/10
     * - "This network": 0.0.0.0/8
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid external URL.');

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! $host) {
            $fail('The :attribute URL has an invalid host.');

            return;
        }

        // Strip IPv6 brackets
        $host = ltrim(rtrim($host, ']'), '[');

        // Resolve hostname to IP
        $ip = filter_var($host, FILTER_VALIDATE_IP)
            ? $host
            : gethostbyname($host);

        if ($ip === $host && ! filter_var($ip, FILTER_VALIDATE_IP)) {
            // gethostbyname returned the same string — could not resolve
            $fail('The :attribute URL hostname could not be resolved.');

            return;
        }

        if (! $this->isAllowed($ip)) {
            $fail('The :attribute URL must not point to an internal network address.');
        }
    }

    /**
     * Check whether a resolved IP address is allowed (not private/internal).
     *
     * This method is intentionally public so it can be reused by the delivery
     * job for SSRF re-validation at request time (DNS rebinding protection).
     */
    public function isAllowed(string $ip): bool
    {
        // IPv6 checks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ! $this->isBlockedIpv6($ip);
        }

        // IPv4 checks
        return ! $this->isBlockedIpv4($ip);
    }

    /**
     * Resolve a hostname to an IP and validate it as external.
     * Returns null if the hostname cannot be resolved.
     *
     * Used by the delivery job to re-validate at dispatch time (DNS rebinding guard).
     */
    public function resolveAndValidate(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        // Strip IPv6 brackets
        $host = ltrim(rtrim($host, ']'), '[');

        // Resolve hostname to IP (fresh DNS lookup)
        $ip = filter_var($host, FILTER_VALIDATE_IP)
            ? $host
            : gethostbyname($host);

        if ($ip === $host && ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $this->isAllowed($ip);
    }

    /**
     * Check whether an IPv6 address falls into a blocked range.
     */
    private function isBlockedIpv6(string $ip): bool
    {
        // Loopback: ::1
        if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
            return true;
        }

        $packed = inet_pton($ip);
        if ($packed === false) {
            return true; // Unparseable — block to be safe
        }

        $ipInt = gmp_import($packed, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);

        // fc00::/7 — Unique-Local (fc00:: through fdff:ffff:...)
        $fc00 = gmp_import(inet_pton('fc00::'), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        $fdff = gmp_import(inet_pton('fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        if (gmp_cmp($ipInt, $fc00) >= 0 && gmp_cmp($ipInt, $fdff) <= 0) {
            return true;
        }

        // fe80::/10 — Link-local
        $fe80 = gmp_import(inet_pton('fe80::'), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        $febf = gmp_import(inet_pton('febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff'), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        if (gmp_cmp($ipInt, $fe80) >= 0 && gmp_cmp($ipInt, $febf) <= 0) {
            return true;
        }

        // ::ffff:0:0/96 — IPv4-mapped
        $ffff0 = gmp_import(inet_pton('::ffff:0.0.0.0'), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        $ffffffff = gmp_import(inet_pton('::ffff:255.255.255.255'), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        if (gmp_cmp($ipInt, $ffff0) >= 0 && gmp_cmp($ipInt, $ffffffff) <= 0) {
            return true;
        }

        return false;
    }

    /**
     * Check whether an IPv4 address falls into a blocked range.
     */
    private function isBlockedIpv4(string $ip): bool
    {
        $long = ip2long($ip);

        $blockedRanges = [
            ['start' => ip2long('0.0.0.0'),     'end' => ip2long('0.255.255.255')],   // 0.0.0.0/8 "This network"
            ['start' => ip2long('10.0.0.0'),    'end' => ip2long('10.255.255.255')],  // RFC-1918
            ['start' => ip2long('100.64.0.0'),  'end' => ip2long('100.127.255.255')], // Carrier-Grade NAT
            ['start' => ip2long('127.0.0.0'),   'end' => ip2long('127.255.255.255')], // Loopback
            ['start' => ip2long('169.254.0.0'), 'end' => ip2long('169.254.255.255')], // Link-local
            ['start' => ip2long('172.16.0.0'),  'end' => ip2long('172.31.255.255')],  // RFC-1918
            ['start' => ip2long('192.168.0.0'), 'end' => ip2long('192.168.255.255')], // RFC-1918
        ];

        foreach ($blockedRanges as $range) {
            if ($long >= $range['start'] && $long <= $range['end']) {
                return true;
            }
        }

        return false;
    }
}
