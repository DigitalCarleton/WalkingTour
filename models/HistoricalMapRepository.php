<?php

require_once __DIR__ . "/HistoricalMapIiif.php";
require_once __DIR__ . "/HistoricalMapUpload.php";
require_once __DIR__ . "/HistoricalMapDecisions.php";

/** Imported Allmaps maps and Item decisions. Pending/archive metadata is admin-only. */
class WalkingTour_HistoricalMapRepository
{
    private $db;
    private $maps;
    public function __construct($db)
    {
        $this->db = $db;
        $this->maps = $db->prefix . 'walking_tour_maps';
    }

    public function install()
    {
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
        (new WalkingTour_HistoricalMapDecisions($this->db))->install();
        foreach (array('period_label'=>"VARCHAR(255) NOT NULL DEFAULT ''", 'period_start'=>'INT NULL', 'period_end'=>'INT NULL') as $column => $definition) {
            if (!$this->db->fetchOne("SHOW COLUMNS FROM `{$this->maps}` LIKE '{$column}'")) { $this->db->query("ALTER TABLE `{$this->maps}` ADD `{$column}` {$definition}"); }
        }
        if (!$this->db->fetchOne("SHOW COLUMNS FROM `{$this->maps}` LIKE 'migration_receipt'")) {
            $this->db->query("ALTER TABLE `{$this->maps}` ADD migration_receipt MEDIUMTEXT NULL");
        }
        // Fresh initialization never creates legacy tables or seeds examples. Existing old tables
        // are touched only by the explicit recovery/migration/contraction tool.
    }

    public function all()
    {
        return $this->readMaps(false);
    }

    private function readMaps($publicOnly)
    {
        $maps = $this->db->fetchAll("SELECT id, slug, title, image_service, image_width,
            image_height, manifest_url, source_url, source_snapshot, transformation, revision,
            period_label, period_start, period_end, migration_receipt FROM `{$this->maps}` ORDER BY id");
        foreach ($maps as &$map) {
            $snapshot = json_decode($map['source_snapshot'], true);
            $map['migration'] = json_decode($map['migration_receipt'] ?? 'null', true);
            $map['source_kind'] = in_array($snapshot['source_kind'] ?? '', array('upload', 'allmaps'), true) ? $snapshot['source_kind'] : 'iiif';
            if ($publicOnly && ($map['source_kind'] !== 'allmaps' || ($map['migration']['state'] ?? '') === 'archived')) { continue; }
            $map['image_url'] = $map['source_kind'] === 'upload' ? Zend_Registry::get('storage')->getUri(WalkingTour_HistoricalMapUpload::storagePath($snapshot)) : null;
            $map['image_quality'] = ($snapshot['image_quality'] ?? '') === 'native' ? 'native' : 'default';
            unset($map['source_snapshot'], $map['migration_receipt']);
            foreach (array('id', 'image_width', 'image_height', 'revision') as $key) { $map[$key] = (int) $map[$key]; }
            foreach (array('period_start','period_end') as $key) { $map[$key] = $map[$key] === null ? null : (int) $map[$key]; }
            if ($map['source_kind'] === 'allmaps') {
                $map['allmaps'] = array('annotation_url'=>$snapshot['annotation_url'], 'resolved_id'=>$snapshot['resolved_id'],
                    'annotation_id'=>$snapshot['annotation']['id'], 'content_hash'=>$snapshot['content_hash'], 'imported_at'=>$snapshot['imported_at']);
                $map['georeference'] = $snapshot['map'];
                $map['geographic_bounds'] = $snapshot['map']['geographic_bounds'];
            }
        }
        unset($map);
        return $publicOnly ? array_values(array_filter($maps, function ($map) {
            return $map['source_kind'] === 'allmaps' && ($map['migration']['state'] ?? '') !== 'archived';
        })) : $maps;
    }

    /** Only validated imported maps enter the public runtime; no legacy storage reads. */
    public function catalog()
    {
        $maps = $this->readMaps(true);
        foreach ($maps as &$map) { unset($map['migration']); }
        unset($map);
        return $maps;
    }

    /** Import selected maps atomically, without copying GCPs into legacy tables. */
    public function importAllmaps(array $images)
    {
        if (!$images || count($images) > 50) { throw new InvalidArgumentException('Select between 1 and 50 maps.'); }
        $adapter = $this->db->getAdapter(); $ids = array();
        $adapter->beginTransaction();
        try {
            foreach ($images as $image) {
                $snapshot = json_decode($image['source_snapshot'] ?? '', true);
                if (($snapshot['source_kind'] ?? '') !== 'allmaps') { throw new InvalidArgumentException('A reviewed Allmaps snapshot is required.'); }
                $slug = 'allmaps-' . hash('sha256', $snapshot['source_identity']);
                $existing = $this->db->fetchRow("SELECT id, source_snapshot FROM `{$this->maps}` WHERE slug = ? FOR UPDATE", array($slug));
                if ($existing) {
                    $before = json_decode($existing['source_snapshot'], true);
                    if (($before['content_hash'] ?? '') !== $snapshot['content_hash']) {
                        throw new InvalidArgumentException('This map already exists with a different Allmaps snapshot. Its saved version was kept. Open the existing record and use Refresh from Allmaps.');
                    }
                    $ids[] = (int) $existing['id']; continue;
                }
                list($title, $source) = $this->metadata($image['title'], $image['source_url']);
                $this->db->query("INSERT INTO `{$this->maps}` (slug, title, image_service, image_width, image_height, manifest_url,
                    source_url, source_snapshot, transformation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    array($slug, $title, $image['image_service'], $image['image_width'], $image['image_height'],
                        $image['manifest_url'], $source, $image['source_snapshot'], $image['transformation']));
                $ids[] = (int) $this->db->fetchOne('SELECT LAST_INSERT_ID()');
            }
            $adapter->commit();
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
        return array_map(array($this, 'find'), array_values(array_unique($ids)));
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

    private function period($period)
    {
        if (!is_array($period) || !is_string($period['label'] ?? null) || mb_strlen(trim($period['label'])) > 255) { throw new InvalidArgumentException('Enter a period label of at most 255 characters.'); }
        $period['label'] = trim($period['label']);
        foreach (array('start','end') as $key) {
            $value = $period[$key] ?? null;
            if ($value === '') { $value = null; }
            if (is_string($value) && preg_match('/^-?[0-9]{1,4}$/D', $value)) { $value = (int) $value; }
            if ($value !== null && (!is_int($value) || $value === 0 || abs($value) > 9999)) { throw new InvalidArgumentException('Period years must be whole years from -9999 to 9999, excluding zero. Use negative years for BCE.'); }
            $period[$key] = $value;
        }
        if ($period['start'] !== null && $period['end'] !== null && $period['start'] > $period['end']) { throw new InvalidArgumentException('The period start must not be after its end.'); }
        return $period;
    }

    public function updateMetadata($id, $revision, $title, $source, $period = null)
    {
        $saved = $this->find($id);
        if ($saved['source_kind'] === 'allmaps' && $source !== $saved['source_url']) { throw new InvalidArgumentException('The imported Allmaps source is fixed. Use the explicit refresh workflow to change its snapshot.'); }
        list($title, $source) = $this->metadata($title, $source);
        if ($period === null) { $period = array('label'=>$saved['period_label'],'start'=>$saved['period_start'],'end'=>$saved['period_end']); }
        $period = $this->period($period);
        if (!is_int($id) || $id < 1 || !is_int($revision) || $revision < 1) { throw new InvalidArgumentException('A map ID and revision are required.'); }
        $adapter = $this->db->getAdapter();
        $adapter->beginTransaction();
        try {
            $current = $this->db->fetchOne("SELECT revision FROM `{$this->maps}` WHERE id = ? FOR UPDATE", array($id));
            if (!$current) { throw new InvalidArgumentException('Historical map not found.'); }
            if ((int) $current !== $revision) { throw new RuntimeException('This map changed after you opened the form. Your entered values have been kept. Open the latest record in another tab and review before saving.', 409); }
            $this->db->query("UPDATE `{$this->maps}` SET title = ?, source_url = ?, period_label = ?, period_start = ?, period_end = ?, revision = revision + 1 WHERE id = ?", array($title, $source, $period['label'], $period['start'], $period['end'], $id));
            $adapter->commit();
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
        return $this->find($id);
    }

    /** Publish a reviewed snapshot and invalidate only overrides in a changed pixel space. */
    public function refreshAllmaps($id, $revision, array $image, $reviewImageChange = false)
    {
        require_once __DIR__ . '/HistoricalMapRefresh.php';
        if (!is_int($id) || $id < 1 || !is_int($revision) || $revision < 1) { throw new InvalidArgumentException('A map ID and revision are required.'); }
        $snapshot = json_decode($image['source_snapshot'] ?? '', true);
        if (($snapshot['source_kind'] ?? '') !== 'allmaps' || !isset($snapshot['annotation']['id'], $snapshot['content_hash'], $snapshot['source_identity']) ||
            hash('sha256', json_encode($snapshot['annotation'], JSON_UNESCAPED_SLASHES)) !== $snapshot['content_hash']) { throw new InvalidArgumentException('A valid reviewed Allmaps snapshot is required.'); }
        $adapter = $this->db->getAdapter(); $adapter->beginTransaction();
        try {
            $row = $this->db->fetchRow("SELECT * FROM `{$this->maps}` WHERE id = ? FOR UPDATE", array($id));
            if (!$row) { throw new InvalidArgumentException('Historical map not found.'); }
            if ((int) $row['revision'] !== $revision) { throw new RuntimeException('This map changed after the refresh preview. Preview the latest map before publishing a refresh.', 409); }
            $before = json_decode($row['source_snapshot'], true);
            if (($before['source_kind'] ?? '') !== 'allmaps' || WalkingTour_HistoricalMapRefresh::identity($before['annotation']['id']) !== WalkingTour_HistoricalMapRefresh::identity($snapshot['annotation']['id'])) {
                throw new InvalidArgumentException('The refresh does not refer to the selected Allmaps map.');
            }
            $imageChanged = WalkingTour_HistoricalMapRefresh::imageChanged($row, $image);
            if ($imageChanged && $reviewImageChange !== true) { throw new InvalidArgumentException('Review the changed original image and dimensions before replacing the snapshot. Saved pixel overrides will need review.'); }
            if (!$imageChanged && $before['content_hash'] === $snapshot['content_hash'] && $row['source_url'] === $image['source_url']) {
                $adapter->commit(); return array('changed'=>false, 'image_changed'=>false, 'revision'=>$revision);
            }
            $slug = 'allmaps-' . hash('sha256', $snapshot['source_identity']);
            if ($this->db->fetchOne("SELECT id FROM `{$this->maps}` WHERE slug = ? AND id <> ?", array($slug, $id))) { throw new InvalidArgumentException('This refreshed map identity already belongs to another catalog record. Review that record first.'); }
            $this->db->query("UPDATE `{$this->maps}` SET slug = ?, image_service = ?, image_width = ?, image_height = ?, manifest_url = ?,
                source_url = ?, source_snapshot = ?, transformation = ?, revision = revision + 1 WHERE id = ?",
                array($slug, $image['image_service'], $image['image_width'], $image['image_height'], $image['manifest_url'], $image['source_url'], $image['source_snapshot'], $image['transformation'], $id));
            if ($imageChanged) {
                $table = $this->db->prefix . 'walking_tour_place_annotations';
                $this->db->query("UPDATE `{$table}` SET needs_review = 1 WHERE map_id = ? AND image_x IS NOT NULL", array($id));
            }
            $adapter->commit();
            return array('changed'=>true, 'image_changed'=>$imageChanged, 'revision'=>$revision + 1);
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
    }

    /** Delete one map and its children; stage uploads so a failed transaction can restore them. */
    public function deleteMap($id, $revision, $storage)
    {
        if (!is_int($id) || $id < 1 || !is_int($revision) || $revision < 1) {
            throw new InvalidArgumentException('A map ID and revision are required.');
        }
        $adapter = $this->db->getAdapter();
        $original = null;
        $staged = null;
        $adapter->beginTransaction();
        try {
            $map = $this->db->fetchRow("SELECT slug, revision, source_snapshot FROM `{$this->maps}` WHERE id = ? FOR UPDATE", array($id));
            if (!$map) { throw new InvalidArgumentException('Historical map not found.'); }
            if ((int) $map['revision'] !== $revision) {
                throw new RuntimeException('This map changed after you opened the confirmation page. Reload this page and review its latest points before deleting.', 409);
            }
            $snapshot = json_decode($map['source_snapshot'], true);
            if (($snapshot['source_kind'] ?? '') === 'upload') {
                $original = WalkingTour_HistoricalMapUpload::storagePath($snapshot);
                $destination = 'original/walkingtour-deleting-' . bin2hex(random_bytes(16)) . '.' . pathinfo($original, PATHINFO_EXTENSION);
                $storage->move($original, $destination);
                $staged = $destination;
            }
            foreach (array($this->db->prefix . 'walking_tour_place_annotations') as $table) {
                $this->db->query("DELETE FROM `{$table}` WHERE map_id = ?", array($id));
            }
            $this->db->query("DELETE FROM `{$this->maps}` WHERE id = ?", array($id));
            $adapter->commit();
        } catch (Exception $error) {
            $adapter->rollBack();
            if ($staged) {
                try { $storage->move($staged, $original); }
                catch (Exception $restoreError) {
                    _log('Historical map deletion rolled back; restore ' . $staged . ' to ' . $original . ': ' . $restoreError->getMessage(), Zend_Log::ERR);
                }
            }
            throw $error;
        }
        if ($staged) {
            try { $storage->delete($staged); }
            catch (Exception $error) {
                _log('Historical map deleted; uploaded image cleanup failed for ' . $staged . ': ' . $error->getMessage(), Zend_Log::ERR);
                return array('image_cleanup_failed' => true);
            }
        }
        return array('image_cleanup_failed' => false);
    }

    public function uninstall()
    {
        // Explicit plugin uninstall still removes its owned storage, including an unfinished upgrade.
        foreach (array('walking_tour_place_annotations', 'walking_tour_map_changes', 'walking_tour_annotations',
            'walking_tour_map_masks', 'walking_tour_control_points', 'walking_tour_maps') as $table) {
            $this->db->query("DROP TABLE IF EXISTS `{$this->db->prefix}{$table}`");
        }
    }
}
