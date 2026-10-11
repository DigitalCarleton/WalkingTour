<?php
require_once __DIR__ . '/HistoricalMapAllmaps.php';
require_once __DIR__ . '/HistoricalMapUpload.php';
require_once __DIR__ . '/HistoricalMapPlaces.php';

/** Operator-only recovery and reviewed migration. Never used to estimate public places. */
class WalkingTour_HistoricalMapMigration
{
    const LEGACY = array('walking_tour_control_points', 'walking_tour_map_masks', 'walking_tour_annotations', 'walking_tour_map_changes');
    private $db;
    private $reader;

    public function __construct($db, $reader = null)
    {
        $this->db = $db;
        $this->reader = $reader ?: new WalkingTour_HistoricalMapAllmaps;
    }

    private function exists($name)
    {
        return (bool) $this->db->fetchOne('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', array($this->db->prefix . $name));
    }

    private function rows($name)
    {
        if (!$this->exists($name)) { return array(); }
        $order = in_array($name, array('walking_tour_map_masks', 'walking_tour_place_annotations'), true) ? 'map_id' : 'id';
        if ($name === 'walking_tour_place_annotations') { $order .= ', item_id'; }
        return $this->db->fetchAll('SELECT * FROM `' . $this->db->prefix . $name . '` ORDER BY ' . $order);
    }

    public static function hash($value)
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** New private directory, outside the web tree. Original files are copied, never moved. */
    public function export($directory, $filesRoot = null)
    {
        if (file_exists($directory) || !mkdir($directory, 0700, true)) { throw new InvalidArgumentException('Choose a new private export directory.'); }
        $backup = array('format'=>1, 'database'=>$this->db->fetchOne('SELECT DATABASE()'), 'prefix'=>$this->db->prefix,
            'exported_at'=>gmdate('c'), 'tables'=>array(), 'schemas'=>array(), 'uploads'=>array());
        $adapter = $this->db->getAdapter();
        $adapter->beginTransaction();
        try {
            foreach (array_merge(array('walking_tour_maps', 'walking_tour_place_annotations'), self::LEGACY) as $name) {
                $backup['tables'][$name] = $this->rows($name);
                if ($this->exists($name)) {
                    $schema = $this->db->fetchRow('SHOW CREATE TABLE `' . $this->db->prefix . $name . '`');
                    $backup['schemas'][$name] = $schema['Create Table'];
                }
            }
            foreach ($backup['tables']['walking_tour_maps'] as $map) {
                $snapshot = json_decode($map['source_snapshot'], true);
                if (($snapshot['source_kind'] ?? '') !== 'upload') { continue; }
                $path = WalkingTour_HistoricalMapUpload::storagePath($snapshot);
                $source = $filesRoot ? rtrim($filesRoot, '/') . '/' . $path : '';
                if (!is_file($source) || !is_readable($source)) { throw new RuntimeException('Back up the configured storage files and supply --files-root; missing upload: ' . $path); }
                $destination = $directory . '/files/' . $path;
                if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true)) { throw new RuntimeException('Cannot create upload backup directory.'); }
                if (!copy($source, $destination)) { throw new RuntimeException('Cannot copy uploaded image.'); }
                chmod($destination, 0600);
                $backup['uploads'][] = array('map_id'=>(int) $map['id'], 'path'=>$path, 'sha256'=>hash_file('sha256', $destination));
            }
            $adapter->commit();
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
        self::write($directory . '/backup.json', $backup);
        $report = "Historical Maps recovery inventory\nExported: " . $backup['exported_at'] . "\nBackup SHA-256: " . self::hash($backup) . "\n";
        foreach ($backup['tables'] as $name=>$rows) { $report .= $name . ': ' . count($rows) . " rows\n"; }
        foreach ($backup['tables']['walking_tour_maps'] as $map) {
            $controls = $this->children($backup, 'walking_tour_control_points', $map['id']);
            $annotations = $this->children($backup, 'walking_tour_annotations', $map['id']);
            $report .= "\nMap " . $map['id'] . ': ' . $map['title'] . "\nSource: " . $map['source_url'] . "\nImage: " . $map['image_service'] . "\nRevision: " . $map['revision'] . '; controls: ' . count($controls) . '; free annotations: ' . count($annotations) . "\n";
        }
        $report .= "\nAll source snapshots, edited controls, masks, free points and history are retained verbatim. No Item association is inferred. Compare reviewed external calibration before migration; re-enter local control edits in Allmaps manually if needed. Uploads without an Allmaps source must be archived with a reason; their original files remain stored. Orphan legacy rows are included in the table export. This export is recovery material, never a public runtime source.\n";
        file_put_contents($directory . '/inventory.txt', $report); chmod($directory . '/inventory.txt', 0600);
        return $backup;
    }

    public static function write($path, $value)
    {
        if (file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", LOCK_EX) === false) { throw new RuntimeException('Cannot write migration artifact.'); }
        chmod($path, 0600);
    }

    public function loadBackup($directory)
    {
        $backup = json_decode(file_get_contents($directory . '/backup.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($backup['format'] ?? null) !== 1 || ($backup['prefix'] ?? '') !== $this->db->prefix || ($backup['database'] ?? '') !== $this->db->fetchOne('SELECT DATABASE()')) {
            throw new InvalidArgumentException('This recovery export does not belong to the selected database and prefix.');
        }
        foreach (array_merge(array('walking_tour_maps', 'walking_tour_place_annotations'), self::LEGACY) as $name) {
            if (!is_array($backup['tables'][$name] ?? null)) { throw new InvalidArgumentException('The recovery export is incomplete.'); }
        }
        foreach ($backup['uploads'] as $upload) {
            $path = WalkingTour_HistoricalMapUpload::storagePath(array('storage_path'=>$upload['path']));
            if (!is_file($directory . '/files/' . $path) || hash_file('sha256', $directory . '/files/' . $path) !== $upload['sha256']) { throw new RuntimeException('An uploaded-image backup is missing or changed.'); }
        }
        return $backup;
    }

    private function children($backup, $table, $id)
    {
        return array_values(array_filter($backup['tables'][$table], function ($row) use ($id) { return (int) $row['map_id'] === (int) $id; }));
    }

    private function legacyUnchanged($backup)
    {
        foreach (self::LEGACY as $name) {
            if ($this->exists($name) && self::hash($this->rows($name)) !== self::hash($backup['tables'][$name])) { throw new RuntimeException('Legacy data changed after export: ' . $name . '. Create a new export and preview.'); }
        }
    }

    /** Explicit map/Item associations supplied by an operator, not proximity guesses. */
    public function preview($backup, $plan)
    {
        if (!is_array($plan['maps'] ?? null) || !$plan['maps'] || count($plan['maps']) > 500) { throw new InvalidArgumentException('Supply 1 to 500 reviewed map actions.'); }
        $this->legacyUnchanged($backup);
        $review = array('format'=>1, 'backup_hash'=>self::hash($backup), 'maps'=>array(), 'created_at'=>gmdate('c'));
        $seen = array();
        foreach ($plan['maps'] as $action) {
            $id = $action['map_id'] ?? null;
            if (!is_int($id) || isset($seen[$id])) { throw new InvalidArgumentException('Each legacy map ID must be supplied once.'); }
            $seen[$id] = true; $old = null;
            foreach ($backup['tables']['walking_tour_maps'] as $map) { if ((int) $map['id'] === $id) { $old = $map; break; } }
            if (!$old || (json_decode($old['source_snapshot'], true)['source_kind'] ?? '') === 'allmaps' || !empty($old['migration_receipt'])) { throw new InvalidArgumentException('Choose an unprocessed legacy map from this export.'); }
            $reason = $action['reason'] ?? '';
            if (!is_string($reason) || trim($reason) === '' || strlen($reason) > 2000) { throw new InvalidArgumentException('Document the reviewed migration or archive decision.'); }
            $entry = array('map_id'=>$id, 'old_hash'=>self::hash($old), 'reason'=>trim($reason), 'action'=>$action['action'] ?? '', 'decisions'=>array());
            if ($entry['action'] === 'archive') {
                if (!empty($action['associations'])) { throw new InvalidArgumentException('Archived annotations remain in recovery storage; do not associate Items until Allmaps import.'); }
            } elseif ($entry['action'] === 'migrate') {
                $images = $this->reader->review($action['annotation_url'] ?? '');
                $selected = array_values(array_filter($images, function ($image) use ($action) { return json_decode($image['source_snapshot'], true)['annotation']['id'] === ($action['annotation_id'] ?? ''); }));
                if (count($selected) !== 1) { throw new InvalidArgumentException('Select the exact reviewed Allmaps annotation ID.'); }
                $image = $this->reader->checkImage($selected[0]);
                if ($old['image_service'] !== $image['image_service'] || (int) $old['image_width'] !== $image['image_width'] || (int) $old['image_height'] !== $image['image_height']) { throw new InvalidArgumentException('Migration requires the same IIIF image identity and dimensions. Uploads must be archived; import their published IIIF image separately.'); }
                $snapshot = json_decode($image['source_snapshot'], true);
                $controls = $this->children($backup, 'walking_tour_control_points', $id);
                $local = array_map(function ($p) { return array('resource'=>array((float)$p['image_x'],(float)$p['image_y']), 'geo'=>array((float)$p['longitude'],(float)$p['latitude'])); }, $controls);
                $sortedLocal = array_map(array(__CLASS__, 'hash'), $local); sort($sortedLocal);
                $sortedSource = array_map(array(__CLASS__, 'hash'), $snapshot['map']['gcps']); sort($sortedSource);
                $entry['local_controls_differ'] = $sortedLocal !== $sortedSource;
                $entry['local_control_count'] = count($controls);
                $entry['external_control_count'] = count($snapshot['map']['gcps']);
                $entry['retained_source_hash'] = self::hash(json_decode($old['source_snapshot'], true));
                if ($entry['local_controls_differ'] && ($action['accept_external_calibration'] ?? false) !== true) { throw new InvalidArgumentException('Local controls differ from Allmaps. Export/re-enter those edits in Allmaps, or explicitly accept_external_calibration with a reviewed reason. No local edits are uploaded automatically.'); }
                $annotations = $this->children($backup, 'walking_tour_annotations', $id); $used = array(); $items = array();
                $associations = $action['associations'] ?? array();
                if (!is_array($associations)) { throw new InvalidArgumentException('Supply reviewed annotation/Item associations as an array.'); }
                foreach ($associations as $association) {
                    $pointId = $association['annotation_id'] ?? null; $itemId = $association['item_id'] ?? null;
                    if (!is_int($pointId) || !is_int($itemId) || isset($used[$pointId]) || isset($items[$itemId])) { throw new InvalidArgumentException('Each reviewed free point and Item may be associated at most once per map.'); }
                    (new WalkingTour_HistoricalMapPlaces($this->db))->requirePublic($itemId);
                    $point = null;
                    foreach ($annotations as $p) { if ((int) $p['id'] === $pointId) { $point = $p; } }
                    if (!$point || $point['image_x'] === null || $point['image_y'] === null || !is_numeric($point['image_x']) || !is_numeric($point['image_y']) || (float)$point['image_x'] < 0 || (float)$point['image_y'] < 0 || (float)$point['image_x'] > $image['image_width'] || (float)$point['image_y'] > $image['image_height']) { throw new InvalidArgumentException('A reviewed annotation must have valid original-image pixels. Unmatched or unavailable points remain in the export.'); }
                    $used[$pointId] = true; $items[$itemId] = true;
                    $entry['decisions'][] = array('annotation_id'=>$pointId, 'item_id'=>$itemId, 'image_x'=>(float)$point['image_x'], 'image_y'=>(float)$point['image_y']);
                }
                $entry['unmatched_annotations'] = count($annotations) - count($used);
                $entry['image'] = $image;
            } else { throw new InvalidArgumentException('Choose migrate or archive.'); }
            $review['maps'][] = $entry;
        }
        return $review;
    }

    public function withLock($callback)
    {
        $name = 'wt-migration-' . substr(hash('sha256', $this->db->prefix), 0, 48);
        if ((int) $this->db->fetchOne('SELECT GET_LOCK(?, 0)', array($name)) !== 1) { throw new RuntimeException('Another map migration or cleanup is running. Retry after it finishes.'); }
        try { return $callback(); }
        finally { $this->db->fetchOne('SELECT RELEASE_LOCK(?)', array($name)); }
    }

    public function apply($backup, $review, $hash)
    {
        return $this->withLock(function () use ($backup, $review, $hash) { return $this->applyReviewed($backup, $review, $hash); });
    }

    private function applyReviewed($backup, $review, $hash)
    {
        if (!is_string($hash) || !hash_equals(self::hash($review), $hash) || ($review['format'] ?? null) !== 1 || ($review['backup_hash'] ?? '') !== self::hash($backup)) { throw new InvalidArgumentException('Review the exact preview hash and its recovery export before applying.'); }
        // Recheck provider availability before any database mutation. Same saved annotation is used.
        foreach ($review['maps'] as $entry) {
            $current = $this->db->fetchRow('SELECT migration_receipt FROM `' . $this->db->prefix . 'walking_tour_maps` WHERE id = ?', array($entry['map_id']));
            $receipt = json_decode($current['migration_receipt'] ?? 'null', true);
            if (($receipt['review_hash'] ?? '') === $hash) { continue; }
            if ($entry['action'] === 'migrate') { $this->reader->checkImage($entry['image']); }
        }
        $adapter = $this->db->getAdapter(); $adapter->beginTransaction(); $result = array('migrated'=>0,'archived'=>0,'unchanged'=>0,'converted'=>0);
        try {
            $this->legacyUnchanged($backup);
            foreach ($review['maps'] as $entry) {
                $id = $entry['map_id']; $maps = $this->db->prefix . 'walking_tour_maps';
                $row = $this->db->fetchRow("SELECT * FROM `$maps` WHERE id = ? FOR UPDATE", array($id));
                $receipt = json_decode($row['migration_receipt'] ?? 'null', true);
                if (($receipt['review_hash'] ?? '') === $hash) { $result['unchanged']++; continue; }
                if (!$row || self::hash($row) !== $entry['old_hash']) { throw new RuntimeException('A map changed after export/preview. No migration was applied.', 409); }
                $receipt = array('state'=>$entry['action'] === 'migrate' ? 'migrated' : 'archived', 'backup_hash'=>self::hash($backup), 'review_hash'=>$hash, 'reason'=>$entry['reason'], 'completed_at'=>gmdate('c'));
                if ($entry['action'] === 'migrate') {
                    $image = $entry['image']; $snapshot = json_decode($image['source_snapshot'], true);
                    $slug = 'allmaps-' . hash('sha256', $snapshot['source_identity']);
                    if ($this->db->fetchOne("SELECT id FROM `$maps` WHERE slug = ? AND id <> ?", array($slug, $id))) { throw new InvalidArgumentException('An imported record already owns this source. Resolve its identity before migration.'); }
                    $this->db->query("UPDATE `$maps` SET slug=?, image_service=?, image_width=?, image_height=?, manifest_url=?, source_url=?, source_snapshot=?, transformation=?, revision=revision+1, migration_receipt=? WHERE id=?",
                        array($slug,$image['image_service'],$image['image_width'],$image['image_height'],$image['manifest_url'],$image['source_url'],$image['source_snapshot'],$image['transformation'],json_encode($receipt),$id));
                    $table = $this->db->prefix . 'walking_tour_place_annotations';
                    foreach ($entry['decisions'] as $decision) {
                        (new WalkingTour_HistoricalMapPlaces($this->db))->requirePublic($decision['item_id']);
                        if ($this->db->fetchOne("SELECT item_id FROM `$table` WHERE map_id=? AND item_id=?", array($id,$decision['item_id']))) { throw new RuntimeException('An Item already has a newer local decision. Resolve it before migration.',409); }
                        $this->db->query("INSERT INTO `$table` (map_id,item_id,image_x,image_y) VALUES (?,?,?,?)", array($id,$decision['item_id'],$decision['image_x'],$decision['image_y']));
                        $result['converted']++;
                    }
                    $result['migrated']++;
                } else {
                    $this->db->query("UPDATE `$maps` SET migration_receipt=?, revision=revision+1 WHERE id=?", array(json_encode($receipt),$id));
                    $result['archived']++;
                }
            }
            $adapter->commit();
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
        return $result;
    }

    /** Exact cleanup preview, including protected orphan rows; never invoked by install/upgrade. */
    public function contractionPreview($backup, $recoveries = array())
    {
        foreach (array('models/HistoricalMapTransform.php', 'views/public/javascripts/historical-map-editor.js',
            'views/public/data/omeka-locations.geojson') as $path) {
            if (is_file(dirname(__DIR__) . '/' . $path)) { throw new RuntimeException('Deploy the complete new runtime before cleanup: old consumer ' . $path . ' remains.'); }
        }
        $this->legacyUnchanged($backup);
        $protectedHashes = array_merge(array(self::hash($backup)),array_map(array(__CLASS__,'hash'),$recoveries));
        $protectedHashes = array_values(array_unique($protectedHashes)); sort($protectedHashes);
        $current = $this->rows('walking_tour_maps');
        foreach ($current as $map) {
            $snapshot = json_decode($map['source_snapshot'], true);
            $receipt = json_decode($map['migration_receipt'] ?? 'null', true);
            if (($snapshot['source_kind'] ?? '') !== 'allmaps' && ($receipt['state'] ?? '') !== 'archived') { throw new RuntimeException('Map ' . $map['id'] . ' is unresolved. Migrate or explicitly archive it before cleanup.'); }
            $hasChildren = false;
            foreach (self::LEGACY as $table) { if ($this->children($backup,$table,$map['id'])) { $hasChildren = true; } }
            if ($hasChildren && !in_array($receipt['state'] ?? '',array('migrated','archived'),true)) { throw new RuntimeException('Map ' . $map['id'] . ' has legacy data without a reviewed completion receipt.'); }
            if ($receipt && !in_array($receipt['backup_hash'] ?? '',$protectedHashes,true)) { throw new RuntimeException('Retain and supply the original recovery export for map ' . $map['id'] . ' with --recovery. Its former source snapshot must remain recoverable.'); }
        }
        $preview = array('format'=>1,'backup_hash'=>self::hash($backup),'maps_hash'=>self::hash(array_map(function ($map) { unset($map['calibration_revision'],$map['next_control_ordinal']); return $map; },$current)),
            'recovery_hashes'=>$protectedHashes,'tables'=>array(),'columns'=>array(),'orphan_rows'=>0,'uploads_preserved'=>count($backup['uploads']));
        $ids = array_map('intval',array_column($current,'id'));
        foreach (self::LEGACY as $name) {
            if (!$this->exists($name)) { continue; }
            $rows = $this->rows($name);
            $preview['tables'][$name] = count($rows);
            foreach ($rows as $row) { if (!in_array((int)$row['map_id'],$ids,true)) { $preview['orphan_rows']++; } }
        }
        foreach (array('calibration_revision','next_control_ordinal') as $column) {
            if ($this->db->fetchOne('SHOW COLUMNS FROM `' . $this->db->prefix . "walking_tour_maps` LIKE '$column'")) { $preview['columns'][]=$column; }
        }
        return $preview;
    }

    public function contract($backup, $review, $hash, $recoveries = array())
    {
        return $this->withLock(function () use ($backup, $review, $hash, $recoveries) {
            if (!is_string($hash) || !hash_equals(self::hash($review),$hash) || ($review['backup_hash'] ?? '') !== self::hash($backup)) { throw new InvalidArgumentException('Review the exact cleanup preview and recovery export before dropping legacy storage.'); }
            $current = $this->contractionPreview($backup,$recoveries);
            if ($current['maps_hash'] !== $review['maps_hash'] || $current['recovery_hashes'] !== $review['recovery_hashes']) { throw new RuntimeException('Maps changed after cleanup preview. Review cleanup again.',409); }
            // DDL autocommits. On interruption the same reviewed operation can resume:
            // absent tables/columns are skipped; every remaining table must still match backup.
            foreach ($current['tables'] as $name=>$count) {
                if (!array_key_exists($name,$review['tables']) || $review['tables'][$name] !== $count) { throw new RuntimeException('Cleanup storage changed after preview.'); }
            }
            foreach ($current['columns'] as $column) { if (!in_array($column,$review['columns'],true)) { throw new RuntimeException('Cleanup columns changed after preview.'); } }
            foreach (array_keys($current['tables']) as $name) { $this->db->query('DROP TABLE `' . $this->db->prefix . $name . '`'); }
            foreach ($current['columns'] as $column) { $this->db->query('ALTER TABLE `' . $this->db->prefix . 'walking_tour_maps` DROP COLUMN `' . $column . '`'); }
            return array('dropped_tables'=>array_keys($current['tables']),'dropped_columns'=>$current['columns'],
                'historical_map_tables'=>array('walking_tour_maps','walking_tour_place_annotations'),'uploads_preserved'=>$current['uploads_preserved']);
        });
    }
}
