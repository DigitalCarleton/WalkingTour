<?php
require_once __DIR__ . '/HistoricalMapPlaces.php';

/** Item-bound editorial decisions, separate from retired free annotations. */
class WalkingTour_HistoricalMapDecisions
{
    private $db;
    private $table;
    private $maps;

    public function __construct($db)
    {
        $this->db = $db;
        $this->table = $db->prefix . 'walking_tour_place_annotations';
        $this->maps = $db->prefix . 'walking_tour_maps';
    }

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->table}` (
            map_id INT UNSIGNED NOT NULL, item_id INT UNSIGNED NOT NULL,
            image_x DOUBLE NULL, image_y DOUBLE NULL,
            visibility VARCHAR(16) NOT NULL DEFAULT 'default', note TEXT NULL,
            needs_review TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (map_id, item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    private function decision($row)
    {
        return array('item_id' => (int) $row['item_id'],
            'pixel' => $row['image_x'] === null ? null : array((float) $row['image_x'], (float) $row['image_y']),
            'visibility' => $row['visibility'], 'note' => $row['note'] ?: '', 'needs_review' => (bool) $row['needs_review']);
    }

    public function publicRows($mapId)
    {
        $db = $this->db;
        $rows = $db->fetchAll("SELECT a.* FROM `{$this->table}` a JOIN `$db->Item` i ON i.id = a.item_id
            JOIN `$db->Location` l ON l.item_id = i.id WHERE a.map_id = ? AND i.public = 1 ORDER BY a.item_id", array($mapId));
        return array_map(array($this, 'decision'), $rows);
    }

    /** All writes share the map revision lock, including future refresh and metadata writes. */
    public function save($id, $revision, $itemId, $operation, $value)
    {
        if (!is_int($id) || $id < 1 || !is_int($revision) || $revision < 1) { throw new InvalidArgumentException('A map ID and revision are required.'); }
        $adapter = $this->db->getAdapter(); $adapter->beginTransaction();
        try {
            $map = $this->db->fetchRow("SELECT * FROM `{$this->maps}` WHERE id = ? FOR UPDATE", array($id));
            if (!$map) { throw new InvalidArgumentException('Historical map not found.'); }
            if ((int) $map['revision'] !== $revision) { throw new RuntimeException('This map changed. Your draft is kept. Reload the latest decisions and review before saving again.', 409); }
            $snapshot = json_decode($map['source_snapshot'], true);
            if (($snapshot['source_kind'] ?? '') !== 'allmaps') { throw new InvalidArgumentException('Import an Allmaps annotation before editing place decisions.'); }
            (new WalkingTour_HistoricalMapPlaces($this->db))->requirePublic($itemId);
            $row = $this->db->fetchRow("SELECT * FROM `{$this->table}` WHERE map_id = ? AND item_id = ?", array($id, $itemId));
            if (!$row) { $row = array('item_id'=>$itemId, 'image_x'=>null, 'image_y'=>null, 'visibility'=>'default', 'note'=>'', 'needs_review'=>0); }
            if ($operation === 'position') {
                if ($value !== null && (!is_array($value) || count($value) !== 2 || array_keys($value) !== array(0,1))) { throw new InvalidArgumentException('Choose original-image pixel coordinates or reset the position.'); }
                if ($value !== null) {
                    foreach ($value as $coordinate) {
                        if ((!is_float($coordinate) && !is_int($coordinate)) || !is_finite($coordinate)) { throw new InvalidArgumentException('Pixel coordinates must be finite numbers.'); }
                    }
                    if ($value[0] < 0 || $value[1] < 0 || $value[0] > $map['image_width'] || $value[1] > $map['image_height']) { throw new InvalidArgumentException('Choose a position inside the original image dimensions.'); }
                }
                // Explicit overrides may be outside the resource mask, but not outside the image.
                $row['image_x'] = $value === null ? null : $value[0]; $row['image_y'] = $value === null ? null : $value[1]; $row['needs_review'] = 0;
            } elseif ($operation === 'note') {
                if (!is_string($value) || mb_strlen($value) > 2000) { throw new InvalidArgumentException('Enter a note of at most 2000 characters.'); }
                $row['note'] = trim($value);
            } else { throw new InvalidArgumentException('Unknown place decision.'); }
            if ($row['image_x'] === null && $row['visibility'] === 'default' && $row['note'] === '') {
                $this->db->query("DELETE FROM `{$this->table}` WHERE map_id = ? AND item_id = ?", array($id, $itemId));
            } else {
                $this->db->query("INSERT INTO `{$this->table}` (map_id, item_id, image_x, image_y, visibility, note, needs_review) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE image_x = VALUES(image_x), image_y = VALUES(image_y), visibility = VALUES(visibility), note = VALUES(note), needs_review = VALUES(needs_review)",
                    array($id, $itemId, $row['image_x'], $row['image_y'], $row['visibility'], $row['note'], $row['needs_review']));
            }
            $this->db->query("UPDATE `{$this->maps}` SET revision = revision + 1 WHERE id = ?", array($id));
            $adapter->commit();
            return array('revision'=>$revision + 1, 'decision'=>$this->decision($row));
        } catch (Exception $error) { $adapter->rollBack(); throw $error; }
    }
}
