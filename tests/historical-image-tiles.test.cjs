const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Capture the Leaflet tile subclass to check its real region requests independently of a browser.
let tiles;
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,
    '../views/public/javascripts/historical-maps.js'), 'utf8'), {
    window: {L: {TileLayer: {extend(definition) { tiles = definition; }}}},
    jQuery(ready) { ready(); }
});
const layer = {_url: 'https://example.test/iiif/image', options: {
    tileSize: 256, imageWidth: 10268, imageHeight: 6368
}};

test('overview requests use image pixels at negative Simple CRS zoom levels', () => {
    assert.equal(tiles.getTileUrl.call(layer, {x: 1, y: 0, z: -4}),
        'https://example.test/iiif/image/4096,0,4096,4096/256,256/0/default.jpg');
});
test('right and bottom edge requests are clipped to the original image', () => {
    assert.equal(tiles.getTileUrl.call(layer, {x: 2, y: 1, z: -4}),
        'https://example.test/iiif/image/8192,4096,2076,2272/130,142/0/default.jpg');
});
test('native zoom requests retain full image resolution', () => {
    assert.equal(tiles.getTileUrl.call(layer, {x: 6, y: 11, z: 0}),
        'https://example.test/iiif/image/1536,2816,256,256/256,256/0/default.jpg');
});
