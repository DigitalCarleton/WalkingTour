<?php

/** Live public Item locations and safe excerpts, independent of tour membership. */
class WalkingTour_HistoricalMapPlaces
{
    private $db;
    public function __construct($db) { $this->db = $db; }

    public function page($after = 0)
    {
        if (!plugin_is_active('Geolocation')) { throw new RuntimeException('Enable Geolocation and add Item locations in the Map tab.'); }
        if (!is_int($after) || $after < 0) { throw new InvalidArgumentException('Use a valid Item cursor.'); }
        $db = $this->db;
        $rows = $db->fetchAll("SELECT i.id, l.longitude, l.latitude, l.address FROM `$db->Item` i
            JOIN `$db->Location` l ON l.item_id = i.id WHERE i.public = 1 AND i.id > ? ORDER BY i.id LIMIT 501", array($after));
        $more = count($rows) > 500; $rows = array_slice($rows, 0, 500); $texts = array(); $thumbnails = array();
        if ($rows) {
            $ids = array_map('intval', array_column($rows, 'id'));
            $data = $db->fetchAll("SELECT t.record_id, e.name, t.text FROM `$db->ElementText` t
                JOIN `$db->Element` e ON e.id = t.element_id JOIN `$db->ElementSet` s ON s.id = e.element_set_id
                WHERE t.record_type = 'Item' AND t.record_id IN (" . implode(',', $ids) . ")
                AND s.name = 'Dublin Core' AND e.name IN ('Title', 'Description') ORDER BY t.id");
            foreach ($data as $text) {
                if (!isset($texts[$text['record_id']][$text['name']])) {
                    $texts[$text['record_id']][$text['name']] = mb_substr(trim(html_entity_decode(strip_tags($text['text']), ENT_QUOTES, 'UTF-8')), 0, $text['name'] === 'Title' ? 255 : 1000);
                }
            }
            // Use Omeka's File/storage API in one batch, as Geolocation's balloon does.
            $files = $db->getTable('File')->fetchObjects("SELECT f.* FROM `$db->File` f
                WHERE f.item_id IN (" . implode(',', $ids) . ") AND f.has_derivative_image = 1
                ORDER BY f.item_id, f.`order`, f.id");
            foreach ($files as $file) {
                if (!isset($thumbnails[$file->item_id])) { $thumbnails[$file->item_id] = $file->getWebPath('thumbnail'); }
            }
        }
        $places = array();
        foreach ($rows as $row) {
            if (!is_numeric($row['longitude']) || !is_numeric($row['latitude'])) { continue; }
            $lon = (float) $row['longitude']; $lat = (float) $row['latitude'];
            if (!is_finite($lon) || !is_finite($lat) || abs($lon) > 180 || abs($lat) > 90) { continue; }
            $id = (int) $row['id'];
            $places[] = array('item_id' => $id, 'coordinates' => array($lon, $lat),
                'title' => $texts[$id]['Title'] ?? ('Item ' . $id), 'description' => $texts[$id]['Description'] ?? '',
                'address' => mb_substr(trim(html_entity_decode(strip_tags($row['address'] ?? ''), ENT_QUOTES, 'UTF-8')), 0, 1000),
                'thumbnail_url' => $thumbnails[$id] ?? null, 'url' => public_url('items/show/' . $id));
        }
        return array('places' => $places, 'next' => $more ? (int) end($rows)['id'] : null);
    }

    public function requirePublic($id)
    {
        $db = $this->db;
        if (!is_int($id) || $id < 1 || !$db->fetchOne("SELECT i.id FROM `$db->Item` i JOIN `$db->Location` l ON i.id = l.item_id WHERE i.id = ? AND i.public = 1", array($id))) {
            throw new InvalidArgumentException('A public Item with a Geolocation location is required.');
        }
    }
}
