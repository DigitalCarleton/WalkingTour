<?php
require_once __DIR__ . '/HistoricalMapAllmaps.php';

/** Prepare one selected source map; never substitute another map from an AnnotationPage. */
class WalkingTour_HistoricalMapRefresh
{
    private $reader;
    public function __construct($reader = null) { $this->reader = $reader ?: new WalkingTour_HistoricalMapAllmaps; }

    public static function identity($id)
    {
        // Version suffixes describe a provider snapshot, not a different map.
        return preg_replace('#^(https://annotations\.allmaps\.org/maps/[a-f0-9]{16})@[a-f0-9]{16}$#D', '$1', $id);
    }

    public static function imageChanged(array $map, array $image)
    {
        return $map['image_service'] !== $image['image_service'] || (int) $map['image_width'] !== $image['image_width'] || (int) $map['image_height'] !== $image['image_height'];
    }

    public function prepare(array $map, $url)
    {
        if ($map['source_kind'] !== 'allmaps') { throw new InvalidArgumentException('Only imported Allmaps maps can be refreshed. Migrate this legacy map first.'); }
        $images = $this->reader->review($url); $selected = array();
        foreach ($images as $image) {
            $snapshot = json_decode($image['source_snapshot'], true);
            if (self::identity($snapshot['annotation']['id']) === self::identity($map['allmaps']['annotation_id'])) { $selected[] = $image; }
        }
        if (count($selected) !== 1) { throw new InvalidArgumentException('This source must contain exactly one version of the selected Allmaps map. Its saved snapshot was kept. Choose that map\'s annotation URL.'); }
        return $this->reader->checkImage($selected[0]);
    }

    public function checkImage(array $image) { return $this->reader->checkImage($image); }
}
