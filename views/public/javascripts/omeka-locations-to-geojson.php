<?php

$pluginRoot = dirname(__DIR__, 3);
$inputPath = $pluginRoot . '/omeka_locations.json';
$outputDir = $pluginRoot . '/views/public/data';
$outputPath = $outputDir . '/omeka-locations.geojson';

if (!file_exists($inputPath)) {
    fwrite(STDERR, "Could not find omeka_locations.json at $inputPath\n");
    exit(1);
}

$exportRows = json_decode(file_get_contents($inputPath), true);

if (!is_array($exportRows)) {
    fwrite(STDERR, "Could not parse omeka_locations.json\n");
    exit(1);
}

$locationsTable = null;

foreach ($exportRows as $row) {
    if (
        isset($row['type'], $row['name'])
        && $row['type'] === 'table'
        && $row['name'] === 'omeka_locations'
    ) {
        $locationsTable = $row;
        break;
    }
}

if (!$locationsTable || !isset($locationsTable['data']) || !is_array($locationsTable['data'])) {
    fwrite(STDERR, "Could not find omeka_locations table data in export.\n");
    exit(1);
}

$features = array();

foreach ($locationsTable['data'] as $row) {
    $latitude = isset($row['latitude']) ? (float) $row['latitude'] : null;
    $longitude = isset($row['longitude']) ? (float) $row['longitude'] : null;

    if (!is_finite($latitude) || !is_finite($longitude)) {
        continue;
    }

    $features[] = array(
        'type' => 'Feature',
        'geometry' => array(
            'type' => 'Point',
            'coordinates' => array($longitude, $latitude),
        ),
        'properties' => array(
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'item_id' => isset($row['item_id']) ? (int) $row['item_id'] : null,
            'zoom_level' => isset($row['zoom_level']) ? (int) $row['zoom_level'] : null,
            'map_type' => isset($row['map_type']) ? $row['map_type'] : '',
            'address' => isset($row['address']) ? $row['address'] : '',
        ),
    );
}

$geojson = array(
    'type' => 'FeatureCollection',
    'name' => 'omeka_locations',
    'features' => $features,
);

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

file_put_contents(
    $outputPath,
    json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

echo 'Wrote ' . count($features) . " features to $outputPath\n";