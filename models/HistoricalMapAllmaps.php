<?php

require_once __DIR__ . '/HistoricalMapIiif.php';
require_once __DIR__ . '/HistoricalMapGeometry.php';

/** Review georeference annotations; image checks are deferred until explicit selection. */
class WalkingTour_HistoricalMapAllmaps
{
    private $load;
    private $image;

    public function __construct($load = null, $image = null)
    {
        $remote = new WalkingTour_HistoricalMapRemoteJson;
        $this->load = $load ?: array($remote, 'fetch');
        $this->image = $image ?: array($remote, 'image');
    }

    private function label($value)
    {
        if (is_string($value)) { return trim($value); }
        if (!is_array($value)) { return ''; }
        foreach (array('en', 'none') as $language) {
            if (isset($value[$language])) { return $this->label($value[$language]); }
        }
        foreach ($value as $item) { $label = $this->label($item); if ($label !== '') { return $label; } }
        return '';
    }

    private function ancestors($source)
    {
        $title = ''; $manifest = '';
        $parents = $source['partOf'] ?? array();
        if (!is_array($parents)) { throw new InvalidArgumentException('Image ancestry must be an array.'); }
        foreach ($parents as $parent) {
            if (!is_array($parent)) { continue; }
            $nested = $this->ancestors($parent);
            if ($nested[0] !== '') { $title = $nested[0]; }
            if ($nested[1] !== '') { $manifest = $nested[1]; }
            if (($parent['type'] ?? '') === 'Manifest') {
                $manifest = WalkingTour_HistoricalMapIiif::url($parent['id'] ?? '');
                $title = $this->label($parent['label'] ?? '');
            }
        }
        return array($title, $manifest);
    }

    private function mask($selector, $width, $height)
    {
        if (!is_array($selector)) { throw new InvalidArgumentException('The resource mask must be an SVG selector.'); }
        $svg = $selector['value'] ?? null;
        if (($selector['type'] ?? '') !== 'SvgSelector' || !is_string($svg) || strlen($svg) > 65536 || strpos($svg, '<!') !== false) {
            throw new InvalidArgumentException('The annotation must include a polygonal SVG resource mask without declarations.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (!$document->loadXML($svg, LIBXML_NONET) || $document->documentElement->localName !== 'svg') {
                throw new InvalidArgumentException('The annotation resource mask is invalid.');
            }
            $polygons = $document->getElementsByTagName('polygon');
            if ($polygons->length !== 1) { throw new InvalidArgumentException('This importer supports one polygon resource mask per map.'); }
            $ring = array();
            foreach (preg_split('/\s+/', trim($polygons->item(0)->getAttribute('points'))) as $pair) {
                $parts = explode(',', $pair);
                if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) { throw new InvalidArgumentException('The resource mask must use numeric pixel coordinates.'); }
                // Allmaps SVG masks may extend past the image edges. Preserve their
                // pixel vertices; image bounds are enforced separately for place pins.
                $ring[] = WalkingTour_HistoricalMapGeometry::coordinate(array((float) $parts[0], (float) $parts[1]), 1000000, 1000000);
            }
            if ($ring && $ring[0] !== end($ring)) { $ring[] = $ring[0]; }
            return WalkingTour_HistoricalMapGeometry::ring($ring);
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    /** Check constraints without evaluating the retired PHP calibration model. */
    private function rank($rows, $required)
    {
        $required = (int) $required; $pivot = 0;
        for ($column = 0; $column < $required && $pivot < count($rows); $column++) {
            $best = $pivot;
            for ($i = $pivot + 1; $i < count($rows); $i++) { if (abs($rows[$i][$column]) > abs($rows[$best][$column])) { $best = $i; } }
            if (abs($rows[$best][$column]) < 1e-10) { continue; }
            $swap = $rows[$pivot]; $rows[$pivot] = $rows[$best]; $rows[$best] = $swap;
            $divisor = $rows[$pivot][$column];
            for ($j = $column; $j < $required; $j++) { $rows[$pivot][$j] /= $divisor; }
            for ($i = $pivot + 1; $i < count($rows); $i++) {
                $factor = $rows[$i][$column];
                for ($j = $column; $j < $required; $j++) { $rows[$i][$j] -= $factor * $rows[$pivot][$j]; }
            }
            $pivot++;
        }
        if ($pivot !== $required) { throw new InvalidArgumentException('The control points cannot support the declared transformation. Complete the calibration in Allmaps first.'); }
    }

    private function calibration($gcps, $transformation)
    {
        if (!is_array($transformation)) { throw new InvalidArgumentException('The annotation transformation must be an object.'); }
        $type = $transformation['type'] ?? '';
        if (!in_array($type, array('thinPlateSpline', 'polynomial', 'helmert', 'projective'), true)) {
            throw new InvalidArgumentException('Unsupported Allmaps transformation. Choose thinPlateSpline, polynomial, helmert, or projective in Allmaps.');
        }
        $options = $transformation['options'] ?? array();
        if (!is_array($options) || array_diff(array_keys($options), $type === 'polynomial' ? array('order') : array())) {
            throw new InvalidArgumentException('Unsupported Allmaps transformation options.');
        }
        $order = $type === 'polynomial' ? ($options['order'] ?? 1) : 1;
        if (!is_int($order) || $order < 1 || $order > 3) { throw new InvalidArgumentException('Polynomial transformations require order 1, 2, or 3.'); }
        foreach (array('resource', 'geo') as $space) {
            $points = array_column($gcps, $space);
            $xs = array_column($points, 0); $ys = array_column($points, 1);
            $minX = min($xs); $minY = min($ys); $scale = max(max($xs) - $minX, max($ys) - $minY);
            if ($scale <= 0) { throw new InvalidArgumentException('Control points must refer to distinct positions.'); }
            $normalized[$space] = array_map(function ($p) use ($minX, $minY, $scale) { return array(($p[0] - $minX) / $scale, ($p[1] - $minY) / $scale); }, $points);
            if ($type === 'helmert') { continue; }
            $rows = array();
            foreach ($normalized[$space] as $p) {
                $row = array();
                for ($degree = 0; $degree <= $order; $degree++) {
                    for ($power = 0; $power <= $degree; $power++) { $row[] = pow($p[0], $degree - $power) * pow($p[1], $power); }
                }
                $rows[] = $row;
            }
            $this->rank($rows, ($order + 1) * ($order + 2) / 2);
        }
        if ($type === 'projective') {
            $rows = array();
            foreach ($normalized['resource'] as $i => $p) {
                list($x, $y) = $p; list($u, $v) = $normalized['geo'][$i];
                $rows[] = array($x, $y, 1, 0, 0, 0, -$u*$x, -$u*$y);
                $rows[] = array(0, 0, 0, $x, $y, 1, -$v*$x, -$v*$y);
            }
            $this->rank($rows, 8);
        }
        return $type;
    }

    public function review($url)
    {
        $url = WalkingTour_HistoricalMapIiif::url(trim($url));
        $document = call_user_func($this->load, $url);
        if (($document['type'] ?? '') === 'Annotation') { $annotations = array($document); }
        elseif (($document['type'] ?? '') === 'AnnotationPage' && is_array($document['items'] ?? null)) { $annotations = $document['items']; }
        else { throw new InvalidArgumentException('Use an Allmaps georeference Annotation or AnnotationPage URL, not an IIIF manifest or Editor URL.'); }
        if (!$annotations || count($annotations) > 50 || array_keys($annotations) !== range(0, count($annotations) - 1)) { throw new InvalidArgumentException('The annotation page must contain between 1 and 50 maps.'); }
        $resolvedId = isset($document['id']) ? WalkingTour_HistoricalMapIiif::url($document['id']) : $url;
        $images = array(); $seen = array(); $previewBytes = 0;
        foreach ($annotations as $annotation) {
            if (!is_array($annotation) || !is_array($annotation['target'] ?? null)) { throw new InvalidArgumentException('Each map must contain an annotation target.'); }
            $source = $annotation['target']['source'] ?? array(); $body = $annotation['body'] ?? array();
            if (!is_array($annotation) || !is_array($source) || !is_array($body) || ($annotation['type'] ?? '') !== 'Annotation' || ($annotation['motivation'] ?? '') !== 'georeferencing' ||
                ($annotation['target']['type'] ?? '') !== 'SpecificResource' || !in_array($source['type'] ?? '', array('ImageService1','ImageService2','ImageService3'), true) ||
                ($body['type'] ?? '') !== 'FeatureCollection' || !is_array($body['features'] ?? null) || array_keys($body['features']) !== range(0, count($body['features']) - 1) || count($body['features']) < 2 || count($body['features']) > 200) {
                throw new InvalidArgumentException('Each selected map must be a georeference annotation with a supported IIIF image service and 2 to 200 control points.');
            }
            $resourceCrs = $body['resourceCrs'] ?? null;
            if ($resourceCrs !== null && (!is_array($resourceCrs) || !in_array($resourceCrs['definition'] ?? '', array('EPSG:4326', 'EPSG:3857'), true))) {
                throw new InvalidArgumentException('Supported Allmaps resource projections are EPSG:4326 and EPSG:3857.');
            }
            $annotationId = WalkingTour_HistoricalMapIiif::url($annotation['id'] ?? '');
            $service = rtrim(WalkingTour_HistoricalMapIiif::url($source['id'] ?? ''), '/');
            if (parse_url($service, PHP_URL_QUERY) !== null) { throw new InvalidArgumentException('The image service cannot contain a query string.'); }
            foreach (array('width','height') as $key) {
                if (!is_int($source[$key] ?? null) || $source[$key] < 1 || $source[$key] > 1000000) { throw new InvalidArgumentException('The annotation must provide valid original image dimensions.'); }
            }
            $gcps = array(); $positions = array('resource'=>array(), 'geo'=>array());
            foreach ($body['features'] as $feature) {
                if (!is_array($feature) || !is_array($feature['geometry'] ?? null) || !is_array($feature['properties'] ?? null)) { throw new InvalidArgumentException('Ground control points must contain geometry and pixel properties.'); }
                if (($feature['type'] ?? '') !== 'Feature' || ($feature['geometry']['type'] ?? '') !== 'Point') { throw new InvalidArgumentException('Ground control points must be GeoJSON Point features.'); }
                $pixel = WalkingTour_HistoricalMapGeometry::coordinate($feature['properties']['resourceCoords'] ?? null, $source['width'], $source['height']);
                if (min($pixel) < 0) { throw new InvalidArgumentException('Control point pixels must be inside the original image.'); }
                $geo = WalkingTour_HistoricalMapGeometry::coordinate($feature['geometry']['coordinates'] ?? null, 180, 90);
                foreach (array('resource'=>$pixel,'geo'=>$geo) as $space=>$point) {
                    $key = json_encode($point);
                    if (isset($positions[$space][$key])) { throw new InvalidArgumentException('Control point positions must be distinct in both coordinate spaces.'); }
                    $positions[$space][$key] = true;
                }
                $gcps[] = array('resource'=>$pixel,'geo'=>$geo);
            }
            $type = $this->calibration($gcps, $body['transformation'] ?? array());
            $mask = $this->mask($annotation['target']['selector'] ?? array(), $source['width'], $source['height']);
            list($title, $manifest) = $this->ancestors($source);
            $title = $this->label($annotation['label'] ?? '') ?: ($title ?: ($this->label($source['label'] ?? '') ?: 'Imported historical map'));
            // Distinct map regions on the same image remain distinct catalog records.
            $identity = $annotationId . "\n" . $service;
            if (isset($seen[$identity])) { throw new InvalidArgumentException('The annotation page contains a duplicate map identity.'); }
            $seen[$identity] = true;
            $map = array('id'=>$annotationId, 'resource'=>$source, 'gcps'=>$gcps, 'resourceMask'=>$mask, 'resourceCrs'=>$resourceCrs, 'transformation'=>$body['transformation'],
                'geographic_bounds'=>array(array(min(array_column(array_column($gcps,'geo'),0)),min(array_column(array_column($gcps,'geo'),1))),
                    array(max(array_column(array_column($gcps,'geo'),0)),max(array_column(array_column($gcps,'geo'),1)))));
            $snapshot = array('source_kind'=>'allmaps','annotation_url'=>$url,'resolved_id'=>$resolvedId,'source_identity'=>$identity,
                'document'=>$document,'annotation'=>$annotation,'map'=>$map,'content_hash'=>hash('sha256',json_encode($annotation,JSON_UNESCAPED_SLASHES)), 'imported_at'=>gmdate('Y-m-d H:i:s'));
            $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $previewBytes += strlen($encoded);
            if (strlen($encoded) > 6500000 || $previewBytes > 8388608) { throw new InvalidArgumentException('This annotation page is too large for one import preview. Use individual map annotation URLs.'); }
            $images[] = array('title'=>mb_substr($title,0,255),'source_url'=>$url,'image_service'=>$service,'image_width'=>$source['width'],
                'image_height'=>$source['height'],'manifest_url'=>$manifest,'transformation'=>$type,'source_snapshot'=>$encoded);
        }
        return $images;
    }

    public function checkImage(array $image)
    {
        $infoUrl = $image['image_service'] . '/info.json';
        $checked = (new WalkingTour_HistoricalMapIiif($this->load))->import($infoUrl);
        if ($checked['image_service'] !== $image['image_service'] || $checked['image_width'] !== $image['image_width'] || $checked['image_height'] !== $image['image_height']) {
            throw new InvalidArgumentException('The IIIF image identity or dimensions differ from the reviewed annotation. Complete the calibration for this image in Allmaps before importing.');
        }
        $quality = json_decode($checked['source_snapshot'], true)['image_quality'];
        call_user_func($this->image, $image['image_service'] . '/full/' . min(128, $image['image_width']) . ',/0/' . $quality . '.jpg');
        $snapshot = json_decode($image['source_snapshot'],true); $snapshot['image_quality'] = $quality;
        $image['source_snapshot'] = json_encode($snapshot,JSON_UNESCAPED_SLASHES);
        return $image;
    }
}
