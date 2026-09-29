<?php

require_once __DIR__ . "/HistoricalMapTransform.php";
require_once __DIR__ . "/HistoricalMapGeometry.php";

/** Persistent catalog and independently editable copies of imported control points. */
class WalkingTour_HistoricalMapRepository
{
    private $db;
    private $maps;
    private $points;
    private $masks;
    private $annotations;

    public function __construct($db)
    {
        $this->db = $db;
        $this->maps = $db->prefix . 'walking_tour_maps';
        $this->points = $db->prefix . 'walking_tour_control_points';
        $this->masks = $db->prefix . 'walking_tour_map_masks';
        $this->annotations = $db->prefix . 'walking_tour_annotations';
    }

    public function install()
    {
        //create two maps if there are not any
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->maps}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            title VARCHAR(255) NOT NULL,
            image_service TEXT NOT NULL,
            image_width INT UNSIGNED NOT NULL,
            image_height INT UNSIGNED NOT NULL,
            manifest_url TEXT NOT NULL,
            source_url TEXT NOT NULL,
            source_snapshot MEDIUMTEXT NOT NULL,
            transformation VARCHAR(64) NOT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id), UNIQUE KEY slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->points}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            map_id INT UNSIGNED NOT NULL,
            ordinal INT UNSIGNED NOT NULL,
            image_x DOUBLE NOT NULL,
            image_y DOUBLE NOT NULL,
            longitude DOUBLE NOT NULL,
            latitude DOUBLE NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY map_ordinal (map_id, ordinal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        if (!$this->db->fetchOne("SHOW COLUMNS FROM `{$this->maps}` LIKE 'calibration_revision'")) {
            $this->db->query("ALTER TABLE `{$this->maps}` ADD calibration_revision INT UNSIGNED NOT NULL DEFAULT 1");
        }
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->masks}` (
            map_id INT UNSIGNED NOT NULL PRIMARY KEY,
            image_ring MEDIUMTEXT NULL,
            geographic_ring MEDIUMTEXT NULL,
            source VARCHAR(16) NOT NULL,
            calibration_revision INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->annotations}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            map_id INT UNSIGNED NOT NULL,
            source_side VARCHAR(16) NOT NULL,
            image_x DOUBLE NULL, image_y DOUBLE NULL,
            longitude DOUBLE NULL, latitude DOUBLE NULL,
            status VARCHAR(24) NOT NULL,
            calibration_revision INT UNSIGNED NOT NULL,
            request_id VARCHAR(64) NOT NULL,
            UNIQUE KEY request_per_map (map_id, request_id),
            KEY map_annotations (map_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        $this->seed();
        $this->importMasks();
    }

    private function seed()
    {
        $slug = 'bnf-rome-btv1b53119597n';
        // Never reseed an existing map: its points may already have been edited or deleted.
        if ($this->db->fetchOne("SELECT id FROM `{$this->maps}` WHERE slug = ?", array($slug))) {
            return;
        }

        $snapshot = file_get_contents(dirname(__FILE__) . '/../data/rome-allmaps-annotation.json');
        $page = json_decode($snapshot, true);
        if (!$page || count($page['items']) !== 1) {
            throw new RuntimeException('The historical map seed is invalid.');
        }
        $annotation = $page['items'][0];
        $source = $annotation['target']['source'];
        $body = $annotation['body'];

        // MySQL cannot prepare START TRANSACTION; use the driver's transaction API.
        $adapter = $this->db->getAdapter();
        $adapter->beginTransaction();
        try {
            $this->db->query("INSERT INTO `{$this->maps}`
                (slug, title, image_service, image_width, image_height, manifest_url,
                 source_url, source_snapshot, transformation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", array(
                $slug, 'Historic Rome — BnF GE C-11245', $source['id'],
                $source['width'], $source['height'],
                $source['partOf'][0]['partOf'][0]['id'], $page['id'], $snapshot,
                $body['transformation']['type']
            ));
            $mapId = $this->db->fetchOne('SELECT LAST_INSERT_ID()');
            foreach ($body['features'] as $index => $feature) {
                $image = $feature['properties']['resourceCoords'];
                $geo = $feature['geometry']['coordinates'];
                $this->db->query("INSERT INTO `{$this->points}`
                    (map_id, ordinal, image_x, image_y, longitude, latitude)
                    VALUES (?, ?, ?, ?, ?, ?)", array(
                    $mapId, $index + 1, $image[0], $image[1], $geo[0], $geo[1]
                ));
            }
            $adapter->commit();
        } catch (Exception $exception) {
            $adapter->rollBack();
            throw $exception;
        }
    }

    public function all()
    {
        $maps = $this->db->fetchAll("SELECT id, slug, title, image_service, image_width,
            image_height, manifest_url, source_url, transformation, revision, calibration_revision
            FROM `{$this->maps}` ORDER BY id");
        foreach ($maps as &$map) {
            foreach (array('id', 'image_width', 'image_height', 'revision', 'calibration_revision') as $key) {
                $map[$key] = (int) $map[$key];
            }
            $map['control_points'] = $this->db->fetchAll("SELECT id, ordinal, image_x, image_y,
                longitude, latitude FROM `{$this->points}` WHERE map_id = ? ORDER BY ordinal",
                array($map['id']));
            foreach ($map['control_points'] as &$point) {
                $point['id'] = (int) $point['id'];
                $point['ordinal'] = (int) $point['ordinal'];
                foreach (array('image_x', 'image_y', 'longitude', 'latitude') as $key) {
                    $point[$key] = (float) $point[$key];
                }
            }
            unset($point);
            $mask = $this->db->fetchRow("SELECT image_ring, geographic_ring, source, calibration_revision FROM `{$this->masks}` WHERE map_id = ?", array($map['id']));
            $map['mask'] = $mask ? array(
                'image_ring' => json_decode($mask['image_ring'] ?: 'null', true),
                'geographic_ring' => json_decode($mask['geographic_ring'] ?: 'null', true),
                'source' => $mask['source'], 'calibration_revision' => (int) $mask['calibration_revision']
            ) : null;
            $map['annotations'] = $this->db->fetchAll("SELECT id, source_side, image_x, image_y, longitude, latitude, status, calibration_revision FROM `{$this->annotations}` WHERE map_id = ? ORDER BY id", array($map['id']));
            foreach ($map['annotations'] as &$annotation) {
                $annotation['id'] = (int) $annotation['id'];
                $annotation['calibration_revision'] = (int) $annotation['calibration_revision'];
                foreach (array('image_x', 'image_y', 'longitude', 'latitude') as $key) {
                    if ($annotation[$key] !== null) { $annotation[$key] = (float) $annotation[$key]; }
                }
            }
            unset($annotation);
        }
        unset($map);
        return $maps;
    }

    private function importMasks()
    {
        foreach ($this->all() as $map) {
            if ($map['mask']) { continue; }
            $snapshot = json_decode($this->db->fetchOne("SELECT source_snapshot FROM `{$this->maps}` WHERE id = ?", array($map['id'])), true);
            $selector = $snapshot['items'][0]['target']['selector']['value'] ?? '';
            if (!preg_match('/<polygon\b[^>]*\bpoints="([^"]+)"/i', $selector, $match)) { continue; }
            $ring = array();
            foreach (preg_split('/\s+/', trim($match[1])) as $pair) {
                $parts = explode(',', $pair);
                if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) { $ring = array(); break; }
                $ring[] = array((float) $parts[0], (float) $parts[1]);
            }
            if (count($ring) < 3) { continue; }
            if ($ring[0] !== $ring[count($ring)-1]) { $ring[] = $ring[0]; }
            try { $geo = (new WalkingTour_HistoricalMapTransform($map))->footprint($ring); }
            catch (RuntimeException $error) { $geo = null; }
            $this->db->query("INSERT IGNORE INTO `{$this->masks}` (map_id, image_ring, geographic_ring, source, calibration_revision) VALUES (?, ?, ?, 'imported', ?)",
                array($map['id'], json_encode($ring), $geo ? json_encode($geo) : null, $map['calibration_revision']));
        }
    }

    public function find($id)
    {
        foreach ($this->all() as $map) { if ($map['id'] === (int) $id) { return $map; } }
        throw new InvalidArgumentException('Historical map not found.');
    }

    public function estimate(array $map, $side, $coordinates)
    {
        if (!in_array($side, array('image', 'modern'), true)) { throw new InvalidArgumentException('Choose an image or modern map position.'); }
        $coordinates = WalkingTour_HistoricalMapGeometry::coordinate($coordinates,
            $side === 'image' ? $map['image_width'] : 180, $side === 'image' ? $map['image_height'] : 85);
        if ($side === 'image' && min($coordinates) < 0) { throw new InvalidArgumentException('Choose a position inside the original image.'); }
        $image = $side === 'image' ? $coordinates : null;
        $geo = $side === 'modern' ? $coordinates : null;
        $status = 'unavailable';
        try {
            $transform = new WalkingTour_HistoricalMapTransform($map);
            if ($image) {
                $candidate = $transform->forward($image);
                $roundtrip = $candidate ? $transform->inverse($candidate) : null;
                if ($roundtrip && hypot($roundtrip[0]-$image[0], $roundtrip[1]-$image[1]) < 1) { $geo = $candidate; }
            } else { $image = $transform->inverse($geo); }
            if ($image && $geo) { $status = $transform->covered($image) ? 'estimated' : 'extrapolated'; }
        } catch (RuntimeException $error) { /* Preserve the original click without inventing a counterpart. */ }
        return array('source_side' => $side, 'image_x' => $image ? $image[0] : null,
            'image_y' => $image ? $image[1] : null, 'longitude' => $geo ? $geo[0] : null,
            'latitude' => $geo ? $geo[1] : null, 'status' => $status,
            'calibration_revision' => $map['calibration_revision']);
    }

    /** One map lock serializes point and mask writes; a stale client never overwrites a new revision. */
    public function mutate($id, $revision, $operation, array $payload)
    {
        if (!is_int($id) || $id <= 0 || !is_int($revision) || $revision < 1) { throw new InvalidArgumentException('A map ID and revision are required.'); }
        $adapter = $this->db->getAdapter();
        $adapter->beginTransaction();
        try {
            $current = $this->db->fetchOne("SELECT revision FROM `{$this->maps}` WHERE id = ? FOR UPDATE", array($id));
            if (!$current) { throw new InvalidArgumentException('Historical map not found.'); }
            // An annotation retry after a lost response must not create a duplicate point.
            $requestId = $payload['request_id'] ?? '';
            if ($operation === 'annotation' && is_string($requestId) && preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $requestId)) {
                $existing = $this->db->fetchOne("SELECT id FROM `{$this->annotations}` WHERE map_id = ? AND request_id = ?", array($id, $requestId));
                if ($existing) { $adapter->commit(); return $this->find($id); }
            }
            if ((int) $current !== $revision) { throw new RuntimeException('This map was updated by another editor. Your draft has been kept. Reload the latest data and review before saving.', 409); }
            $map = $this->find($id);
            if ($operation === 'mask') {
                $ring = WalkingTour_HistoricalMapGeometry::ring($payload['ring'] ?? null);
                $this->db->query("INSERT INTO `{$this->masks}` (map_id, image_ring, geographic_ring, source, calibration_revision) VALUES (?, NULL, ?, 'custom', ?) ON DUPLICATE KEY UPDATE geographic_ring = VALUES(geographic_ring), source = 'custom', calibration_revision = VALUES(calibration_revision)",
                    array($id, json_encode($ring), $map['calibration_revision']));
            } elseif ($operation === 'annotation') {
                if (!is_string($requestId) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $requestId)) { throw new InvalidArgumentException('A valid request ID is required.'); }
                $point = $this->estimate($map, $payload['side'] ?? null, $payload['coordinates'] ?? null);
                $this->db->query("INSERT INTO `{$this->annotations}` (map_id, source_side, image_x, image_y, longitude, latitude, status, calibration_revision, request_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    array($id, $point['source_side'], $point['image_x'], $point['image_y'], $point['longitude'], $point['latitude'], $point['status'], $point['calibration_revision'], $requestId));
            } else { throw new InvalidArgumentException('Unknown map operation.'); }
            $this->db->query("UPDATE `{$this->maps}` SET revision = revision + 1 WHERE id = ?", array($id));
            $adapter->commit();
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
        return $this->find($id);
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `{$this->annotations}`");
        $this->db->query("DROP TABLE IF EXISTS `{$this->masks}`");
        $this->db->query("DROP TABLE IF EXISTS `{$this->points}`");
        $this->db->query("DROP TABLE IF EXISTS `{$this->maps}`");
    }
}
