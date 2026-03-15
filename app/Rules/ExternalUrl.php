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
     * - Link-local: 169.254.0.0/16
     * - RFC-1918: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
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

        // Check IPv6 loopback
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
                $fail('The :attribute URL must not point to an internal network address.');
            }

            return;
        }

        // IPv4 range checks
        $long = ip2long($ip);

        $blockedRanges = [
            ['start' => ip2long('127.0.0.0'),   'end' => ip2long('127.255.255.255')], // Loopback
            ['start' => ip2long('169.254.0.0'), 'end' => ip2long('169.254.255.255')], // Link-local
            ['start' => ip2long('10.0.0.0'),    'end' => ip2long('10.255.255.255')],  // RFC-1918
            ['start' => ip2long('172.16.0.0'),  'end' => ip2long('172.31.255.255')],  // RFC-1918
            ['start' => ip2long('192.168.0.0'), 'end' => ip2long('192.168.255.255')], // RFC-1918
        ];

        foreach ($blockedRanges as $range) {
            if ($long >= $range['start'] && $long <= $range['end']) {
                $fail('The :attribute URL must not point to an internal network address.');

                return;
            }
        }
    }
}
