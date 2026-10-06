<?php

/** Import one original image from IIIF Image 1/2/3 or Presentation 2/3 metadata. */
class WalkingTour_HistoricalMapIiif
{
    private $load;
    private $remote;

    public function __construct($load = null)
    {
        if ($load) { $this->load = $load; }
        else { $this->remote = new WalkingTour_HistoricalMapRemoteJson; $this->load = array($this->remote, 'fetch'); }
    }

    public static function url($url)
    {
        if (!is_string($url) || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Enter a valid HTTPS URL.');
        }
        $parts = parse_url($url);
        if (strtolower($parts['scheme']) !== 'https' || isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new InvalidArgumentException('Use an HTTPS URL without credentials, fragments, or a custom port.');
        }
        return $url;
    }

    private function label($value)
    {
        if (is_string($value)) { return $value; }
        if (!is_array($value)) { return ''; }
        if (isset($value['@value'])) { return $this->label($value['@value']); }
        foreach (array('en', 'none') as $language) {
            if (isset($value[$language])) { return $this->label($value[$language]); }
        }
        foreach ($value as $item) { $label = $this->label($item); if ($label !== '') { return $label; } }
        return '';
    }

    private function service($body)
    {
        if (!is_array($body)) { return null; }
        if (isset($body[0])) {
            foreach ($body as $item) { $id = $this->service($item); if ($id) { return $id; } }
            return null;
        }
        $services = $body['service'] ?? array();
        if (isset($services['id']) || isset($services['@id'])) { $services = array($services); }
        foreach ($services as $service) {
            if (!is_array($service)) { continue; }
            $type = $service['type'] ?? $service['@type'] ?? '';
            $profile = json_encode($service['profile'] ?? '', JSON_UNESCAPED_SLASHES);
            if (in_array($type, array('ImageService1', 'ImageService2', 'ImageService3'), true) ||
                strpos($profile, '/api/image/') !== false || strpos($profile, '/iiif/image-api/') !== false) {
                return $service['id'] ?? $service['@id'] ?? null;
            }
        }
        return null;
    }

    public function import($url, $imageNumber = 1)
    {
        $url = self::url(trim($url));
        if (!is_int($imageNumber) || $imageNumber < 1 || $imageNumber > 1000) {
            throw new InvalidArgumentException('Choose an image number between 1 and 1000.');
        }
        $document = call_user_func($this->load, $url);
        $manifest = isset($document['sequences']) || ($document['type'] ?? null) === 'Manifest' || ($document['@type'] ?? null) === 'sc:Manifest';
        $label = $this->label($document['label'] ?? '');
        if ($manifest) {
            $canvases = $document['sequences'][0]['canvases'] ?? $document['items'] ?? array();
            $canvas = $canvases[$imageNumber - 1] ?? null;
            if (!is_array($canvas)) { throw new InvalidArgumentException('That image number does not exist in this manifest.'); }
            $service = null;
            if (isset($canvas['images'])) {
                foreach ($canvas['images'] as $annotation) {
                    $service = $this->service($annotation['resource'] ?? null); if ($service) { break; }
                }
            } else {
                foreach ($canvas['items'] ?? array() as $page) {
                    foreach ($page['items'] ?? array() as $annotation) {
                        if (isset($annotation['motivation']) && $annotation['motivation'] !== 'painting') { continue; }
                        $service = $this->service($annotation['body'] ?? null); if ($service) { break 2; }
                    }
                }
            }
            if (!$service) { throw new InvalidArgumentException('The selected image has no supported IIIF image service.'); }
            $canvasLabel = $this->label($canvas['label'] ?? '');
            if (count($canvases) > 1 && $canvasLabel !== '') { $label .= ($label !== '' ? ' — ' : '') . $canvasLabel; }
            $info = call_user_func($this->load, rtrim(self::url($service), '/') . '/info.json');
        } else {
            if ($imageNumber !== 1) { throw new InvalidArgumentException('Use image number 1 for a single image information URL.'); }
            $info = $document;
        }
        $context = json_encode($info['@context'] ?? '', JSON_UNESCAPED_SLASHES);
        if (!preg_match('~(?:/api/image/[123]/|/iiif/image-api/1\.1/)~', $context)) {
            throw new InvalidArgumentException('Use a IIIF Image API 1, 2, or 3 information URL, or a Presentation API 2 or 3 manifest.');
        }
        $profile = json_encode($info['profile'] ?? '', JSON_UNESCAPED_SLASHES);
        if (!preg_match('/level[12]/', $profile)) {
            throw new InvalidArgumentException('This viewer requires a level 1 or level 2 image service for interactive region requests.');
        }
        foreach (array('width', 'height') as $dimension) {
            if (!isset($info[$dimension]) || !is_int($info[$dimension]) || $info[$dimension] < 1 || $info[$dimension] > 1000000) {
                throw new InvalidArgumentException('The image information must supply valid pixel dimensions.');
            }
        }
        $service = rtrim(self::url($info['id'] ?? $info['@id'] ?? ''), '/');
        if ($this->remote) { $this->remote->addresses($service); }
        if (parse_url($service, PHP_URL_QUERY) !== null) { throw new InvalidArgumentException('The image service URL cannot contain a query string.'); }
        if ($label === '') { $label = 'Imported historical map'; }
        return array('title' => mb_substr(trim($label), 0, 255), 'image_service' => $service,
            'image_width' => $info['width'], 'image_height' => $info['height'],
            'manifest_url' => $url, 'source_url' => $url,
            'source_snapshot' => json_encode(array('document' => $document, 'image_info' => $info, 'image_number' => $imageNumber,
                'image_quality' => preg_match('~(?:/api/image/1/|/iiif/image-api/1\.1/)~', $context) ? 'native' : 'default'), JSON_UNESCAPED_SLASHES));
    }
}

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
        if (!$addresses) { throw new InvalidArgumentException('The IIIF source host could not be resolved.'); }
        foreach ($addresses as $address) {
            if (!self::publicAddress($address)) { throw new InvalidArgumentException('IIIF sources must use public internet addresses.'); }
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

    public function fetch($url)
    {
        if (!function_exists('curl_init')) { throw new RuntimeException('IIIF import requires the PHP cURL extension.'); }
        $deadline = microtime(true) + 25;
        for ($hop = 0; $hop < 4; $hop++) {
            $url = WalkingTour_HistoricalMapIiif::url($url);
            $host = trim(parse_url($url, PHP_URL_HOST), '[]');
            $addresses = $this->addresses($url);
            if (microtime(true) >= $deadline) { throw new InvalidArgumentException('The IIIF source timed out. Please try again.'); }
            $address = $addresses[0];
            $body = ''; $location = null; $tooLarge = false;
            $curl = curl_init($url);
            curl_setopt_array($curl, array(CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROXY => '', CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => min(15, max(1, (int) ($deadline - microtime(true)))),
                CURLOPT_RESOLVE => array($host . ':443:' . (strpos($address, ':') !== false ? '[' . $address . ']' : $address)),
                CURLOPT_HTTPHEADER => array('Accept: application/ld+json, application/json'),
                CURLOPT_USERAGENT => 'WalkingTour IIIF importer',
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
            if ($tooLarge) { throw new InvalidArgumentException('The IIIF metadata exceeds the 3 MB import limit.'); }
            if ($success === false) { throw new InvalidArgumentException('The IIIF source could not be reached securely. Please check the URL and try again.'); }
            if ($status >= 300 && $status < 400 && $location) { $url = $this->redirect($url, $location); continue; }
            if ($status !== 200) { throw new InvalidArgumentException('The IIIF source did not return a successful response.'); }
            $document = json_decode($body, true, 128);
            if (!is_array($document)) { throw new InvalidArgumentException('The IIIF source did not return valid JSON metadata.'); }
            return $document;
        }
        throw new InvalidArgumentException('The IIIF source redirected too many times.');
    }
}
