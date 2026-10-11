<?php

require_once __DIR__ . "/HistoricalMapRemoteJson.php";

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
