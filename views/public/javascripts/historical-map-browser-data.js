/* global jQuery */
(function (window, $) {
    'use strict';

    // Public browsing reads the same live Omeka data as the admin editor, but
    // owns its selection/catalog independently. Nothing here writes decisions.
    window.WalkingTourHistoricalBrowserData = function (root) {
        var base = root.dataset.apiBase.replace(/\/?$/, '/');
        var maps = [], places = [], byId = {}, entries = {}, generation = 0;

        function json(url, data) {
            return new Promise(function (resolve, reject) {
                $.ajax({url: url, data: data, dataType: 'json', timeout: 20000})
                    .done(resolve).fail(function () { reject(new Error('Data could not be loaded. Use Reload maps and places to try again.')); });
            });
        }

        async function refresh() {
            var current = ++generation;
            // A refresh discards all relationship caches, including failed loads.
            maps = []; places = []; byId = {}; entries = {};
            var livePlaces = new Promise(function (resolve, reject) {
                window.WalkingTourLivePlaces.load(base + 'historical-map-places').done(resolve).fail(reject);
            });
            var result = await Promise.all([json(root.dataset.catalogUrl), livePlaces]);
            if (current !== generation) { return; }
            maps = result[0].maps;
            places = result[1].sort(function (a, b) { return a.title.localeCompare(b.title) || a.item_id - b.item_id; });
            places.forEach(function (place) { byId[place.item_id] = place; });
            maps.forEach(function (map) { entries[map.id] = {map: map, relations: new Map()}; });
        }

        function mapData(id) {
            var entry = entries[id];
            if (!entry) { return Promise.reject(new Error('This map is no longer available. Reload maps and places.')); }
            if (!entry.loading) {
                entry.loading = json(base + 'historical-map-decisions', {map_id: id}).then(function (data) {
                    if (data.revision !== entry.map.revision) { throw new Error('This map changed. Reload maps and places to use its latest version.'); }
                    var decisions = {};
                    data.decisions.forEach(function (decision) { decisions[decision.item_id] = decision; });
                    var transform = null, footprint = null;
                    try {
                        transform = window.WalkingTourAllmaps.create(entry.map);
                        footprint = transform.footprint();
                    } catch (_) { /* Unknown coverage is not proof that a place is outside. */ }
                    return {decisions: decisions, transform: transform, footprint: footprint};
                }).catch(function (error) { entry.loading = null; throw error; });
            }
            return entry.loading;
        }

        async function relation(map, place) {
            var entry = entries[map.id];
            var data = await mapData(map.id);
            // Coordinates are part of the key; a changed location cannot reuse
            // another location's result, even within one browsing session.
            var key = place.item_id + ':' + place.coordinates.join(',');
            if (entry.relations.has(key)) { return entry.relations.get(key); }
            var decision = data.decisions[place.item_id] || {};
            var estimate = data.transform ? data.transform.estimate(place.coordinates) : null;
            var covered = estimate ? true : (data.footprint ? window.WalkingTourAllmaps.inside(place.coordinates, data.footprint) : null);
            var pixel = decision.pixel;
            if (!pixel || decision.needs_review || !pixel.every(Number.isFinite) || pixel[0] < 0 || pixel[1] < 0 || pixel[0] > map.image_width || pixel[1] > map.image_height) { pixel = null; }
            var result = {map: map, place: place, covered: covered,
                eligible: covered !== false && decision.visibility !== 'hide',
                pixel: covered !== false ? (pixel || estimate) : null,
                estimated: !pixel, needsReview: !!decision.needs_review};
            entry.relations.set(key, result);
            return result;
        }

        async function mapsForPlace(id) {
            var place = byId[id], records = maps.slice(), result = [], index = 0, current = generation;
            if (!place) { return []; }
            // Bound simultaneous decision reads when the map collection grows.
            async function worker() {
                while (index < records.length && current === generation) {
                    var offset = index++, map = records[offset];
                    try { result[offset] = await relation(map, place); }
                    catch (error) { result[offset] = {map: map, place: place, eligible: true, covered: null, pixel: null, error: error.message}; }
                }
            }
            await Promise.all(Array.from({length: Math.min(4, records.length)}, worker));
            return result.filter(function (r) { return r.eligible; });
        }

        async function placesForMap(id) {
            var current = generation, map = maps.filter(function (m) { return m.id === id; })[0];
            await mapData(id);
            if (current !== generation) { return []; }
            var result = [], records = places.slice();
            for (var i = 0; i < records.length; i++) {
                if (current !== generation) { return []; }
                var r = await relation(map, records[i]);
                if (r.eligible) { result.push(r); }
                if (i % 30 === 29) { await new Promise(function (resolve) { window.setTimeout(resolve, 0); }); }
            }
            return result;
        }

        return {refresh: refresh, maps: function () { return maps; }, places: function () { return places; },
            place: function (id) { return byId[id]; }, mapsForPlace: mapsForPlace, placesForMap: placesForMap};
    };
}(window, jQuery));
