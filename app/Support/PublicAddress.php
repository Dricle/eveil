<?php

namespace App\Support;

/**
 * Whether the server may send a request a user chose the address of.
 *
 * On cloud, a URL pointing at a private or reserved address would let any
 * account make our server talk to the instance metadata endpoint or the
 * internal network. Self-hosted is the opposite case: the operator's own n8n
 * at `http://n8n:5678` is exactly what they want to reach, and the network is
 * theirs, so nothing is refused there.
 *
 * ponytail: checked at save and again before every send, but Guzzle resolves
 * the host itself afterwards, so a DNS answer that changes in between (DNS
 * rebinding) slips through. Pin the resolved IP with CURLOPT_RESOLVE if that
 * ever matters.
 */
class PublicAddress
{
    public static function allows(string $url): bool
    {
        if (config('eveil.edition') !== 'cloud') {
            return true;
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');

        if ($host === '') {
            return false;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
