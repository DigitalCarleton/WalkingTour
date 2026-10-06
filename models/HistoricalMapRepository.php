<?php

require_once __DIR__ . "/HistoricalMapTransform.php";
require_once __DIR__ . "/HistoricalMapGeometry.php";
require_once __DIR__ . "/HistoricalMapIiif.php";
require_once __DIR__ . "/HistoricalMapUpload.php";

/** Persistent catalog and independently editable copies of imported control points. */
class WalkingTour_HistoricalMapRepository
{
    private $db;
    private $maps;
    private $points;
    private $masks;
    private $annotations;
    private $changes;

    public function __construct($db)
    {
        $this->db = $db;
        $this->maps = $db->prefix . 'walking_tour_maps';
        $this->points = $db->prefix . 'walking_tour_control_points';
        $this->masks = $db->prefix . 'walking_tour_map_masks';
        $this->annotations = $db->prefix . 'walking_tour_annotations';
        $this->changes = $db->prefix . 'walking_tour_map_changes';
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
        if (!$this->db->fetchOne("SHOW COLUMNS FROM `{$this->maps}` LIKE 'next_control_ordinal'")) {
            $this->db->query("ALTER TABLE `{$this->maps}` ADD next_control_ordinal INT UNSIGNED NOT NULL DEFAULT 1");
            $this->db->query("UPDATE `{$this->maps}` m SET next_control_ordinal =
                (SELECT COALESCE(MAX(p.ordinal), 0) + 1 FROM `{$this->points}` p WHERE p.map_id = m.id)");
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
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->changes}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            map_id INT UNSIGNED NOT NULL,
            request_id VARCHAR(64) NOT NULL,
            operation VARCHAR(24) NOT NULL,
            calibration_before MEDIUMTEXT NULL,
            undone TINYINT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY request_per_map (map_id, request_id),
            KEY map_changes (map_id, id)
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
            $this->db->query("UPDATE `{$this->maps}` SET next_control_ordinal = ? WHERE id = ?",
                array(count($body['features']) + 1, $mapId));
            $adapter->commit();
        } catch (Exception $exception) {
            $adapter->rollBack();
            throw $exception;
        }
    }

    public function all()
    {
        $maps = $this->db->fetchAll("SELECT id, slug, title, image_service, image_width,
            image_height, manifest_url, source_url, source_snapshot, transformation, revision, calibration_revision, next_control_ordinal
            FROM `{$this->maps}` ORDER BY id");
        foreach ($maps as &$map) {
            $snapshot = json_decode($map['source_snapshot'], true);
            $map['source_kind'] = ($snapshot['source_kind'] ?? '') === 'upload' ? 'upload' : 'iiif';
            $map['image_url'] = $map['source_kind'] === 'upload' ? Zend_Registry::get('storage')->getUri(WalkingTour_HistoricalMapUpload::storagePath($snapshot)) : null;
            $map['image_quality'] = ($snapshot['image_quality'] ?? '') === 'native' ? 'native' : 'default';
            unset($map['source_snapshot']);
            foreach (array('id', 'image_width', 'image_height', 'revision', 'calibration_revision', 'next_control_ordinal') as $key) {
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
            $change = $this->latestCalibrationChange($map['id']);
            $map['undo_calibration'] = $change && !$change['undone'] ? array('change_id' => (int) $change['id']) : null;
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

    private function metadata($title, $source)
    {
        if (!is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > 255) {
            throw new InvalidArgumentException('Enter a title of 1 to 255 characters.');
        }
        if (!is_string($source)) { throw new InvalidArgumentException('Enter a valid source URL.'); }
        $source = trim($source);
        if ($source !== '') { WalkingTour_HistoricalMapIiif::url($source); }
        return array(trim($title), $source);
    }

    public function createMap(array $image)
    {
        list($title, $source) = $this->metadata($image['title'] ?? null, $image['source_url'] ?? '');
        foreach (array('image_width', 'image_height') as $field) {
            if (!is_int($image[$field] ?? null) || $image[$field] < 1 || $image[$field] > 1000000) {
                throw new InvalidArgumentException('Valid original image dimensions are required.');
            }
        }
        $snapshot = $image['source_snapshot'] ?? '';
        if (!is_string($snapshot) || strlen($snapshot) > 6500000 || !is_array(json_decode($snapshot, true))) {
            throw new InvalidArgumentException('Valid image source metadata is required.');
        }
        $metadata = json_decode($snapshot, true);
        $uploaded = ($metadata['source_kind'] ?? '') === 'upload';
        if ($uploaded) {
            WalkingTour_HistoricalMapUpload::storagePath($metadata);
            $checksum = $metadata['sha256'] ?? '';
            if (!is_string($checksum) || !preg_match('/^[a-f0-9]{64}$/D', $checksum) || ($image['image_service'] ?? '') !== 'upload:' . $checksum) {
                throw new InvalidArgumentException('Invalid uploaded image reference.');
            }
            $service = 'upload:' . $checksum;
            $manifest = '';
        } else {
            $service = rtrim(WalkingTour_HistoricalMapIiif::url($image['image_service'] ?? ''), '/');
            $manifest = WalkingTour_HistoricalMapIiif::url($image['manifest_url'] ?? '');
        }
        $adapter = $this->db->getAdapter();
        $adapter->beginTransaction();
        try {
            if ($this->db->fetchOne("SELECT id FROM `{$this->maps}` WHERE image_service = ? FOR UPDATE", array($service))) {
                throw new InvalidArgumentException('This image is already in the map library. Open its existing record instead.');
            }
            $this->db->query("INSERT INTO `{$this->maps}` (slug, title, image_service, image_width, image_height, manifest_url,
                source_url, source_snapshot, transformation, next_control_ordinal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'thinPlateSpline', 1)",
                array(($uploaded ? 'upload-' : 'iiif-') . substr(hash('sha256', $service), 0, 32), $title, $service, $image['image_width'], $image['image_height'], $manifest, $source, $snapshot));
            $id = (int) $this->db->fetchOne('SELECT LAST_INSERT_ID()');
            $ring = array(array(0, 0), array($image['image_width'], 0), array($image['image_width'], $image['image_height']), array(0, $image['image_height']), array(0, 0));
            $this->db->query("INSERT INTO `{$this->masks}` (map_id, image_ring, geographic_ring, source, calibration_revision) VALUES (?, ?, NULL, 'imported', 1)",
                array($id, json_encode($ring)));
            $adapter->commit();
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
        return $this->find($id);
    }

    public function updateMetadata($id, $revision, $title, $source)
    {
        list($title, $source) = $this->metadata($title, $source);
        if (!is_int($id) || $id < 1 || !is_int($revision) || $revision < 1) { throw new InvalidArgumentException('A map ID and revision are required.'); }
        $adapter = $this->db->getAdapter();
        $adapter->beginTransaction();
        try {
            $current = $this->db->fetchOne("SELECT revision FROM `{$this->maps}` WHERE id = ? FOR UPDATE", array($id));
            if (!$current) { throw new InvalidArgumentException('Historical map not found.'); }
            if ((int) $current !== $revision) { throw new RuntimeException('This map changed after you opened the form. Your entered values have been kept. Open the latest record in another tab and review before saving.', 409); }
            $this->db->query("UPDATE `{$this->maps}` SET title = ?, source_url = ?, revision = revision + 1 WHERE id = ?", array($title, $source, $id));
            $adapter->commit();
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
        return $this->find($id);
    }

    /** Read a bounded calibration history; request receipts and ordinary deletions stay internal. */
    public function calibrationHistory($id, $before = null)
    {
        if (!is_int($id) || $id < 1 || ($before !== null && (!is_int($before) || $before < 1))) {
            throw new InvalidArgumentException('Choose a valid map and history page.');
        }
        $params = array($id);
        $where = '';
        if ($before !== null) { $where = ' AND id < ?'; $params[] = $before; }
        $rows = $this->db->fetchAll("SELECT id, operation, calibration_before, undone FROM `{$this->changes}`
            WHERE map_id = ? AND (calibration_before IS NOT NULL OR operation = 'undo_calibration'){$where} ORDER BY id DESC LIMIT 51", $params);
        $more = count($rows) > 50;
        if ($more) { array_pop($rows); }
        foreach ($rows as &$row) {
            $snapshot = json_decode($row['calibration_before'] ?: 'null', true);
            $row['id'] = (int) $row['id'];
            $row['undone'] = (bool) $row['undone'];
            $row['controls_before'] = isset($snapshot['control_points']) ? count($snapshot['control_points']) : null;
            unset($row['calibration_before']);
        }
        unset($row);
        return array('changes' => $rows, 'older_than' => $more ? end($rows)['id'] : null);
    }

    public function estimate(array $map, $side, $coordinates, $transform = null)
    {
        if (!in_array($side, array('image', 'modern'), true)) { throw new InvalidArgumentException('Choose an image or modern map position.'); }
        $coordinates = WalkingTour_HistoricalMapGeometry::coordinate($coordinates,
            $side === 'image' ? $map['image_width'] : 180, $side === 'image' ? $map['image_height'] : 85);
        if ($side === 'image' && min($coordinates) < 0) { throw new InvalidArgumentException('Choose a position inside the original image.'); }
        $image = $side === 'image' ? $coordinates : null;
        $geo = $side === 'modern' ? $coordinates : null;
        $status = 'unavailable';
        try {
            if ($transform === false) { throw new RuntimeException('Calibration is unavailable.'); }
            if ($transform === null) { $transform = new WalkingTour_HistoricalMapTransform($map); }
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

    private function latestCalibrationChange($id)
    {
        return $this->db->fetchRow("SELECT id, calibration_before, undone FROM `{$this->changes}`
            WHERE map_id = ? AND calibration_before IS NOT NULL ORDER BY id DESC LIMIT 1", array($id));
    }

    private function target(array $map, $type, $pointId)
    {
        if (!is_int($pointId) || $pointId <= 0 || !in_array($type, array('annotation', 'control'), true)) {
            throw new InvalidArgumentException('Choose a saved annotation or control point.');
        }
        $points = $type === 'control' ? $map['control_points'] : $map['annotations'];
        foreach ($points as $point) { if ($point['id'] === $pointId) { return $point; } }
        throw new InvalidArgumentException('This point no longer exists on the selected map. Reload the latest data.');
    }

    private function confirmPair(array $map, array $payload)
    {
        $type = $payload['point_type'] ?? 'new';
        if (!in_array($type, array('new', 'annotation', 'control'), true)) {
            throw new InvalidArgumentException('Choose a valid point type.');
        }
        $target = $type === 'new' ? null : $this->target($map, $type, $payload['point_id'] ?? null);
        $image = WalkingTour_HistoricalMapGeometry::coordinate($payload['image'] ?? null, $map['image_width'], $map['image_height']);
        $geo = WalkingTour_HistoricalMapGeometry::coordinate($payload['geographic'] ?? null, 180, 85);
        if (min($image) < 0) { throw new InvalidArgumentException('Choose a position inside the original image.'); }
        if ($type !== 'control' && count($map['control_points']) >= 200) {
            throw new InvalidArgumentException('A map supports up to 200 control points.');
        }
        foreach ($map['control_points'] as $point) {
            if ($type === 'control' && $point['id'] === $target['id']) { continue; }
            if (hypot($point['image_x'] - $image[0], $point['image_y'] - $image[1]) < .01 ||
                hypot($point['longitude'] - $geo[0], $point['latitude'] - $geo[1]) < 1e-10) {
                throw new InvalidArgumentException('This position already belongs to another control point. Adjust that point instead.');
            }
        }
        if ($type === 'control') {
            $this->db->query("UPDATE `{$this->points}` SET image_x = ?, image_y = ?, longitude = ?, latitude = ? WHERE map_id = ? AND id = ?",
                array($image[0], $image[1], $geo[0], $geo[1], $map['id'], $target['id']));
        } else {
            $this->db->query("INSERT INTO `{$this->points}` (map_id, ordinal, image_x, image_y, longitude, latitude) VALUES (?, ?, ?, ?, ?, ?)",
                array($map['id'], $map['next_control_ordinal'], $image[0], $image[1], $geo[0], $geo[1]));
            $this->db->query("UPDATE `{$this->maps}` SET next_control_ordinal = next_control_ordinal + 1 WHERE id = ?", array($map['id']));
            if ($type === 'annotation') {
                $this->db->query("DELETE FROM `{$this->annotations}` WHERE map_id = ? AND id = ?", array($map['id'], $target['id']));
            }
        }
        // Invalid fits must roll back both the promotion and its allocated number.
        $updated = $this->find($map['id']);
        if (count($updated['control_points']) >= 3) {
            try { new WalkingTour_HistoricalMapTransform($updated); }
            catch (RuntimeException $error) { throw new InvalidArgumentException('These control points cannot form a stable calibration. Adjust the pair before confirming.'); }
        }
    }

    private function annotationSnapshot($mapId, array $point)
    {
        // Retain the original annotation and request ID before promotion, for undo/retry.
        return $this->db->fetchRow("SELECT * FROM `{$this->annotations}` WHERE map_id = ? AND id = ?", array($mapId, $point['id']));
    }

    private function recalibrate($id)
    {
        $this->db->query("UPDATE `{$this->maps}` SET calibration_revision = calibration_revision + 1 WHERE id = ?", array($id));
        $map = $this->find($id);
        try { $transform = new WalkingTour_HistoricalMapTransform($map); }
        catch (RuntimeException $error) { $transform = false; }
        foreach ($map['annotations'] as $point) {
            $coordinates = $point['source_side'] === 'image' ? array($point['image_x'], $point['image_y']) : array($point['longitude'], $point['latitude']);
            $estimated = $this->estimate($map, $point['source_side'], $coordinates, $transform);
            $this->db->query("UPDATE `{$this->annotations}` SET image_x = ?, image_y = ?, longitude = ?, latitude = ?, status = ?, calibration_revision = ? WHERE map_id = ? AND id = ?",
                array($estimated['image_x'], $estimated['image_y'], $estimated['longitude'], $estimated['latitude'], $estimated['status'], $map['calibration_revision'], $id, $point['id']));
        }
        if ($map['mask'] && $map['mask']['source'] === 'imported') {
            $geo = $transform && $map['mask']['image_ring'] ? $transform->footprint($map['mask']['image_ring']) : null;
            $this->db->query("UPDATE `{$this->masks}` SET geographic_ring = ?, calibration_revision = ? WHERE map_id = ?",
                array($geo ? json_encode($geo) : null, $map['calibration_revision'], $id));
        }
    }

    private function undoCalibration(array $map, array $payload)
    {
        $change = $this->latestCalibrationChange($map['id']);
        if (!$change || $change['undone'] || !is_int($payload['change_id'] ?? null) || $payload['change_id'] !== (int) $change['id']) {
            throw new InvalidArgumentException('The latest calibration change is no longer available to undo. Reload the latest data.');
        }
        $snapshot = json_decode($change['calibration_before'], true);
        if (!is_array($snapshot) || !isset($snapshot['control_points'])) { throw new RuntimeException('The calibration history is invalid.'); }
        $this->db->query("DELETE FROM `{$this->points}` WHERE map_id = ?", array($map['id']));
        foreach ($snapshot['control_points'] as $point) {
            $this->db->query("INSERT INTO `{$this->points}` (id, map_id, ordinal, image_x, image_y, longitude, latitude) VALUES (?, ?, ?, ?, ?, ?, ?)",
                array($point['id'], $map['id'], $point['ordinal'], $point['image_x'], $point['image_y'], $point['longitude'], $point['latitude']));
        }
        if (!empty($snapshot['annotation'])) {
            $p = $snapshot['annotation'];
            $this->db->query("INSERT INTO `{$this->annotations}` (id, map_id, source_side, image_x, image_y, longitude, latitude, status, calibration_revision, request_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array($p['id'], $map['id'], $p['source_side'], $p['image_x'], $p['image_y'], $p['longitude'], $p['latitude'], $p['status'], $p['calibration_revision'], $p['request_id']));
        }
        // Keep the numbering high-water mark: deleted or undone numbers are not reused.
        $this->db->query("UPDATE `{$this->changes}` SET undone = 1 WHERE map_id = ? AND id = ?", array($map['id'], $change['id']));
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
            $validRequest = is_string($requestId) && preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $requestId);
            if (in_array($operation, array('confirm', 'delete', 'undo_calibration'), true) && !$validRequest) {
                throw new InvalidArgumentException('A valid request ID is required.');
            }
            if ($validRequest && $this->db->fetchOne("SELECT id FROM `{$this->changes}` WHERE map_id = ? AND request_id = ?", array($id, $requestId))) {
                $adapter->commit(); return $this->find($id);
            }
            if ($operation === 'annotation' && is_string($requestId) && preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $requestId)) {
                $existing = $this->db->fetchOne("SELECT id FROM `{$this->annotations}` WHERE map_id = ? AND request_id = ?", array($id, $requestId));
                if ($existing) { $adapter->commit(); return $this->find($id); }
            }
            if ((int) $current !== $revision) { throw new RuntimeException('This map was updated by another editor. Your draft has been kept. Reload the latest data and review before saving.', 409); }
            $map = $this->find($id);
            $calibrationBefore = null;
            if ($operation === 'mask') {
                $ring = WalkingTour_HistoricalMapGeometry::ring($payload['ring'] ?? null);
                $this->db->query("INSERT INTO `{$this->masks}` (map_id, image_ring, geographic_ring, source, calibration_revision) VALUES (?, NULL, ?, 'custom', ?) ON DUPLICATE KEY UPDATE geographic_ring = VALUES(geographic_ring), source = 'custom', calibration_revision = VALUES(calibration_revision)",
                    array($id, json_encode($ring), $map['calibration_revision']));
            } elseif ($operation === 'annotation') {
                if (!is_string($requestId) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $requestId)) { throw new InvalidArgumentException('A valid request ID is required.'); }
                $point = $this->estimate($map, $payload['side'] ?? null, $payload['coordinates'] ?? null);
                $this->db->query("INSERT INTO `{$this->annotations}` (map_id, source_side, image_x, image_y, longitude, latitude, status, calibration_revision, request_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    array($id, $point['source_side'], $point['image_x'], $point['image_y'], $point['longitude'], $point['latitude'], $point['status'], $point['calibration_revision'], $requestId));
            } elseif ($operation === 'confirm') {
                $restoredAnnotation = null;
                if (($payload['point_type'] ?? null) === 'annotation') {
                    $target = $this->target($map, 'annotation', $payload['point_id'] ?? null);
                    $restoredAnnotation = $this->annotationSnapshot($id, $target);
                }
                $calibrationBefore = array('control_points' => $map['control_points'], 'annotation' => $restoredAnnotation);
                $this->confirmPair($map, $payload);
                $this->recalibrate($id);
            } elseif ($operation === 'delete') {
                $type = $payload['point_type'] ?? null;
                $target = $this->target($map, $type, $payload['point_id'] ?? null);
                $table = $type === 'control' ? $this->points : $this->annotations;
                $this->db->query("DELETE FROM `{$table}` WHERE map_id = ? AND id = ?", array($id, $target['id']));
                if ($type === 'control') {
                    $calibrationBefore = array('control_points' => $map['control_points'], 'annotation' => null);
                    $this->recalibrate($id);
                }
            } elseif ($operation === 'undo_calibration') {
                $this->undoCalibration($map, $payload);
                $this->recalibrate($id);
            } else { throw new InvalidArgumentException('Unknown map operation.'); }
            if ($validRequest) {
                $this->db->query("INSERT INTO `{$this->changes}` (map_id, request_id, operation, calibration_before) VALUES (?, ?, ?, ?)",
                    array($id, $requestId, $operation, $calibrationBefore !== null ? json_encode($calibrationBefore) : null));
            }
            $this->db->query("UPDATE `{$this->maps}` SET revision = revision + 1 WHERE id = ?", array($id));
            $adapter->commit();
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
        return $this->find($id);
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `{$this->changes}`");
        $this->db->query("DROP TABLE IF EXISTS `{$this->annotations}`");
        $this->db->query("DROP TABLE IF EXISTS `{$this->masks}`");
        $this->db->query("DROP TABLE IF EXISTS `{$this->points}`");
        $this->db->query("DROP TABLE IF EXISTS `{$this->maps}`");
    }
}
