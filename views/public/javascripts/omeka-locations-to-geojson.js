const fs = require('fs');
const path = require('path');

const pluginRoot = path.join(__dirname, '..', '..', '..');
const inputPath = path.join(pluginRoot, 'omeka_locations.json');
const outputPath = path.join(pluginRoot, 'views', 'public', 'data', 'omeka-locations.geojson');

const exportRows = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
const locationsTable = exportRows.find((row) => row.type === 'table' && row.name === 'omeka_locations');

if (!locationsTable || !Array.isArray(locationsTable.data)) {
  throw new Error('Could not find omeka_locations table data in export.');
}

const features = locationsTable.data
  .map((row) => {
    const latitude = Number(row.latitude);
    const longitude = Number(row.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
      return null;
    }

    return {
      type: 'Feature',
      geometry: {
        type: 'Point',
        coordinates: [longitude, latitude]
      },
      properties: {
        id: Number(row.id),
        item_id: Number(row.item_id),
        zoom_level: Number(row.zoom_level),
        map_type: row.map_type,
        address: row.address || ''
      }
    };
  })
  .filter(Boolean);

const geojson = {
  type: 'FeatureCollection',
  name: 'omeka_locations',
  features
};

fs.writeFileSync(outputPath, `${JSON.stringify(geojson, null, 2)}\n`);
console.log(`Wrote ${features.length} features to ${outputPath}`);