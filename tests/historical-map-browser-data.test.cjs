const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const clone = value => JSON.parse(JSON.stringify(value));

function map(id, mask = [[0,0],[1000,0],[1000,1000],[0,1000]]) {
    return {id, revision: 1, title: 'Map ' + id, source_kind: 'allmaps', image_width: 1000, image_height: 1000,
        georeference: {transformation: {type: 'polynomial', options: {order: 1}}, resourceCrs: {definition: 'EPSG:4326'},
            resourceMask: mask, gcps: [[0,0],[1000,0],[0,1000],[1000,1000]].map(resource => ({resource, geo: [12 + resource[0]/100000, 42 - resource[1]/100000]}))}};
}
function harness() {
    const server = {maps: [map(1), map(2), map(3, [[0,0],[1000,0],[1000,300],[300,300],[300,1000],[0,1000]])],
        places: [{item_id: 10, title: 'Church', coordinates: [12.006, 41.994], description: 'Church description'},
            {item_id: 11, title: 'Other place', coordinates: [12.001, 41.999]}], decisions: {}, failures: new Set(), calls: []};
    const jquery = {Deferred() {
        let resolve, reject;
        const promise=new Promise((yes,no)=>{resolve=yes;reject=no;});
        const deferred={resolve,reject,promise:()=>deferred,done(fn){promise.then(fn,()=>{});return deferred;},fail(fn){promise.catch(fn);return deferred;}};
        return deferred;
    }, ajax(options) {
        const handlers = {};
        const request = {done(fn) { handlers.done = fn; return request; }, fail(fn) { handlers.fail = fn; return request; }};
        server.calls.push(options);
        setImmediate(() => {
            if (server.failures.has(options.data?.map_id)) { handlers.fail(); return; }
            if (options.url === '/catalog') { handlers.done({maps: clone(server.maps)}); }
            else if (options.url.endsWith('historical-map-places')) { handlers.done({places: clone(server.places), next: null}); }
            else {
                const saved = server.maps.find(m => m.id === options.data.map_id);
                handlers.done({revision: saved.revision, decisions: clone(server.decisions[saved.id] || [])});
            }
        });
        return request;
    }};
    const window = {setTimeout};
    vm.runInNewContext(fs.readFileSync(path.join(root, 'views/public/javascripts/allmaps-transform.js'), 'utf8'), {window});
    vm.runInNewContext(fs.readFileSync(path.join(root, 'views/public/javascripts/live-map-places.js'), 'utf8'), {window, jQuery: jquery});
    vm.runInNewContext(fs.readFileSync(path.join(root, 'views/public/javascripts/historical-map-browser-data.js'), 'utf8'), {window, jQuery: jquery});
    const data = window.WalkingTourHistoricalBrowserData({dataset: {catalogUrl: '/catalog', apiBase: '/api/'}});
    return {data, server, window};
}

test('one site matches two maps; a concave third mask excludes it despite its bounding box', async () => {
    const {data} = harness(); await data.refresh();
    const rows = await data.mapsForPlace(10);
    assert.deepEqual(Array.from(rows, r => r.map.id), [1,2]);
    assert.ok(rows.every(r => r.covered && Math.hypot(r.pixel[0]-600,r.pixel[1]-600)<.1));
    assert.deepEqual(Array.from(data.places(), p => p.item_id), [10,11], 'place selection is independent of the active map');
});
test('an unusable inverse keeps a covered map without inventing its marker', async () => {
    const {data, window} = harness();
    const create = window.WalkingTourAllmaps.create;
    window.WalkingTourAllmaps.create = record => { const t = create(record); return record.id === 2 ? {...t, estimate: () => null} : t; };
    await data.refresh(); const rows = await data.mapsForPlace(10);
    assert.deepEqual(Array.from(rows, r => r.map.id), [1,2]);
    assert.equal(rows[1].covered, true); assert.equal(rows[1].pixel, null);
});
test('a failed footprint and inverse are explicitly unknown, not outside coverage', async () => {
    const {data, window} = harness();
    const create = window.WalkingTourAllmaps.create;
    window.WalkingTourAllmaps.create = record => record.id === 2 ? {footprint: () => null, estimate: () => null} : create(record);
    await data.refresh(); const rows = await data.mapsForPlace(10);
    assert.equal(rows.find(r => r.map.id === 2).covered, null);
    assert.equal(rows.find(r => r.map.id === 2).pixel, null);
});
test('map visibility decisions apply to the library and displayed markers', async () => {
    const {data, server} = harness(); server.decisions[2] = [{item_id: 10, visibility: 'hide'}];
    await data.refresh();
    assert.deepEqual(Array.from(await data.mapsForPlace(10), r => r.map.id), [1]);
    assert.ok(!(await data.placesForMap(2)).some(r => r.place.item_id === 10));
});
test('reviewed overrides are preserved; stale overrides use estimates; outside places remain excluded', async () => {
    const {data, server} = harness();
    server.decisions[1] = [{item_id: 10, pixel: [700,700], visibility: 'default', needs_review: false}];
    server.decisions[2] = [{item_id: 10, pixel: [700,700], visibility: 'default', needs_review: true}];
    server.decisions[3] = [{item_id: 10, pixel: [100,100], visibility: 'show'}];
    await data.refresh(); const rows = await data.mapsForPlace(10);
    assert.deepEqual(clone(rows[0].pixel), [700,700]); assert.equal(rows[0].estimated, false);
    assert.ok(Math.hypot(rows[1].pixel[0]-600,rows[1].pixel[1]-600)<.1); assert.equal(rows[1].estimated, true);
    assert.deepEqual(Array.from(rows, r => r.map.id), [1,2]);
});
test('refresh sees location, coverage, visibility, publication, and metadata updates', async () => {
    const {data, server} = harness(); await data.refresh(); await data.mapsForPlace(10);
    server.places[0].coordinates = [12.001,41.999]; server.places[0].title = 'Renamed church';
    await data.refresh(); assert.equal(data.place(10).title, 'Renamed church');
    assert.deepEqual(Array.from(await data.mapsForPlace(10), r => r.map.id), [1,2,3]);
    server.maps[1] = map(2, [[800,800],[1000,800],[1000,1000],[800,1000]]); server.maps[1].revision++;
    server.decisions[3] = [{item_id:10,visibility:'hide'}];
    await data.refresh(); assert.deepEqual(Array.from(await data.mapsForPlace(10), r => r.map.id), [1]);
    server.places = server.places.filter(p => p.item_id !== 10); server.maps = server.maps.slice(1);
    await data.refresh(); assert.equal(data.place(10), undefined);
    assert.equal((await data.mapsForPlace(10)).length, 0); assert.equal(data.maps().length, 2);
});
test('cached decisions avoid duplicate reads and refresh invalidates them', async () => {
    const {data, server} = harness(); await data.refresh(); await data.mapsForPlace(10); await data.placesForMap(1); await data.mapsForPlace(10);
    assert.equal(server.calls.filter(c => c.url.endsWith('historical-map-decisions')).length, 3);
    await data.refresh(); await data.mapsForPlace(10);
    assert.equal(server.calls.filter(c => c.url.endsWith('historical-map-decisions')).length, 6);
    assert.ok(server.calls.every(c => !c.type || c.type === 'GET'), 'public browsing never writes');
});
test('decision load failures do not silently remove maps and can be retried', async () => {
    const {data, server} = harness(); server.failures.add(2); await data.refresh();
    let rows = await data.mapsForPlace(10);
    assert.ok(rows.find(r => r.map.id === 2).error);
    server.failures.clear(); rows = await data.mapsForPlace(10);
    assert.equal(rows.find(r => r.map.id === 2).error, undefined);
});
test('concurrent map edits reject mismatched decisions until the catalog is refreshed', async () => {
    const {data, server} = harness(); await data.refresh(); server.maps[1].revision++;
    const rows=await data.mapsForPlace(10);
    assert.match(rows.find(r=>r.map.id===2).error,/changed/);
    await data.refresh();
    assert.equal((await data.mapsForPlace(10)).find(r=>r.map.id===2).error,undefined);
});
