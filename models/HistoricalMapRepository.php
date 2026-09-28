<?php

/** Persistent catalog and independently editable copies of imported control points. */
class WalkingTour_HistoricalMapRepository
{
    private $db;
    private $maps;
    private $points;

    public function __construct($db)
    {
        $this->db = $db;
        $this->maps = $db->prefix . 'walking_tour_maps';
        $this->points = $db->prefix . 'walking_tour_control_points';
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

        $this->seed();
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

        $this->db->query('START TRANSACTION');
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
            $this->db->query('COMMIT');
        } catch (Exception $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        }
    }

    public function all()
    {
        $maps = $this->db->fetchAll("SELECT id, slug, title, image_service, image_width,
            image_height, manifest_url, source_url, transformation, revision
            FROM `{$this->maps}` ORDER BY id");
        foreach ($maps as &$map) {
            foreach (array('id', 'image_width', 'image_height', 'revision') as $key) {
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
        }
        unset($map);
        return $maps;
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `{$this->points}`");
        $this->db->query("DROP TABLE IF EXISTS `{$this->maps}`");
    }
}
