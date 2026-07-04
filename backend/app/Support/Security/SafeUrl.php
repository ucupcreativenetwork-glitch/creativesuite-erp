<?php

namespace App\Support\Security;

use App\Support\Exceptions\ApiException;

class SafeUrl
{
    /**
     * Reject webhook/callback URLs that target private networks (SSRF mitigation).
     */
    public static function assertPublicHttpUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new ApiException('Invalid URL format.', 422, 'INVALID_URL');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new ApiException('URL must use http or https.', 422, 'INVALID_URL_SCHEME');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            throw new ApiException('URL host is required.', 422, 'INVALID_URL_HOST');
        }

        if (in_array($host, ['localhost', '0.0.0.0', '127.0.0.1', '::1', '[::1]'], true)) {
            throw new ApiException('URL must not target localhost.', 422, 'URL_NOT_ALLOWED');
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new ApiException('URL must not target internal hostnames.', 422, 'URL_NOT_ALLOWED');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new ApiException('URL must not target private or reserved IP addresses.', 422, 'URL_NOT_ALLOWED');
            }

            return;
        }

        $resolved = @gethostbyname($host);
        if ($resolved === $host) {
            return;
        }

        if (! filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new ApiException('URL resolves to a private or reserved IP address.', 422, 'URL_NOT_ALLOWED');
        }
    }
}