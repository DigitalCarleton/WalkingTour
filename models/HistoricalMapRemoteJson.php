<?php

/** Bounded public-HTTPS requests with DNS pinning and separately validated redirects. */
class WalkingTour_HistoricalMapRemoteJson
{
    public static function publicAddress($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) &&
            !preg_match('/^(?:0\.|127\.|169\.254\.|192\.0\.0\.|198\.(?:18|19)\.|100\.(?:6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.)/', $ip) &&
            (strpos($ip, ':') === false ? (int) explode('.', $ip)[0] < 224 :
                (preg_match('/^[23][0-9a-f]{3}:/i', $ip) && !preg_match('/^(?:2002:|2001:(?:db8:|0*:))/i', $ip)));
    }

    public function addresses($url)
    {
        WalkingTour_HistoricalMapIiif::url($url);
        $host = trim(parse_url($url, PHP_URL_HOST), '[]');
        $addresses = array();
        if (filter_var($host, FILTER_VALIDATE_IP)) { $addresses[] = $host; }
        else {
            foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: array() as $record) {
                if (isset($record['ip'])) { $addresses[] = $record['ip']; }
                if (isset($record['ipv6'])) { $addresses[] = $record['ipv6']; }
            }
        }
        if (!$addresses) { throw new InvalidArgumentException('The remote source host could not be resolved.'); }
        foreach ($addresses as $address) {
            if (!self::publicAddress($address)) { throw new InvalidArgumentException('Remote sources must use public internet addresses.'); }
        }
        return $addresses;
    }

    private function redirect($url, $location)
    {
        if (preg_match('~^https?://~i', $location)) { return $location; }
        if (strpos($location, '//') === 0) { return 'https:' . $location; }
        $parts = parse_url($url);
        $origin = 'https://' . $parts['host'];
        if (strpos($location, '/') === 0) { return $origin . $location; }
        if (strpos($location, '?') === 0) { return $origin . ($parts['path'] ?? '/') . $location; }
        return $origin . rtrim(dirname($parts['path'] ?? '/'), '/.') . '/' . $location;
    }

    private function request($url, $accept)
    {
        if (!function_exists('curl_init')) { throw new RuntimeException('Remote import requires the PHP cURL extension.'); }
        $deadline = microtime(true) + 25;
        for ($hop = 0; $hop < 4; $hop++) {
            $url = WalkingTour_HistoricalMapIiif::url($url);
            $host = trim(parse_url($url, PHP_URL_HOST), '[]');
            $addresses = $this->addresses($url);
            if (microtime(true) >= $deadline) { throw new InvalidArgumentException('The remote source timed out. Please try again.'); }
            $address = $addresses[0];
            $body = ''; $location = null; $tooLarge = false;
            $curl = curl_init($url);
            curl_setopt_array($curl, array(CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROXY => '', CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => min(15, max(1, (int) ($deadline - microtime(true)))),
                CURLOPT_RESOLVE => array($host . ':443:' . (strpos($address, ':') !== false ? '[' . $address . ']' : $address)),
                CURLOPT_HTTPHEADER => array('Accept: ' . $accept),
                CURLOPT_USERAGENT => 'WalkingTour Allmaps importer',
                CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$location) {
                    if (stripos($line, 'Location:') === 0) { $location = trim(substr($line, 9)); }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$body, &$tooLarge) {
                    if (strlen($body) + strlen($chunk) > 3145728) { $tooLarge = true; return 0; }
                    $body .= $chunk; return strlen($chunk);
                }));
            $success = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if ($tooLarge) { throw new InvalidArgumentException('The remote response exceeds the 3 MB import limit.'); }
            if ($success === false) { throw new InvalidArgumentException('The remote source could not be reached securely. Please check the URL and try again.'); }
            if ($status >= 300 && $status < 400 && $location) { $url = $this->redirect($url, $location); continue; }
            if ($status !== 200) { throw new InvalidArgumentException('The remote source returned HTTP ' . $status . '. Check that it is publicly accessible and try again.'); }
            return $body;
        }
        throw new InvalidArgumentException('The remote source redirected too many times.');
    }

    public function fetch($url)
    {
        $document = json_decode($this->request($url, 'application/ld+json, application/json'), true, 128);
        if (!is_array($document)) { throw new InvalidArgumentException('The remote source did not return valid JSON metadata.'); }
        return $document;
    }

    public function image($url)
    {
        $bytes = $this->request($url, 'image/jpeg, image/png');
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG), true)) {
            throw new InvalidArgumentException('The IIIF image service did not return a readable JPEG or PNG preview.');
        }
        return $info;
    }

}
