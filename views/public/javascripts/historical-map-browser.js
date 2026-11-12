/* global jQuery */
(function (window, $) {
    'use strict';
    $(function () {
        var root = document.getElementById('historical-map-browser');
        if (!root) { return; }
        var L = window.L, data = window.WalkingTourHistoricalBrowserData(root);
        var map = L.map('browser-map', {crs: L.CRS.Simple, minZoom: -7, maxZoom: 1, zoomSnap: .1, zoomDelta: .5});
        map.setView([0, 0], -5);
        var selected = null, active = null, image, mask, markers, selectedMarker;
        var imageBounds, overviewZoom, generation = 0, imageGeneration = 0, ready = false;
        var picker = el('browser-place'), list = el('browser-map-list');

        function el(id) { return document.getElementById(id); }
        function imagePosition(pixel) { return [-pixel[1], pixel[0]]; }
        function safeUrl(url) { return typeof url === 'string' && /^(?:https?:\/\/|\/(?!\/))/i.test(url); }
        function message(text) { el('browser-library-status').textContent = text; }

        // The public viewer uses original IIIF image regions, just as the admin
        // viewer does. It never warps the image into geographic map tiles.
        var ImageTiles = L.TileLayer.extend({
            getTileUrl: function (coords) {
                var scale = Math.pow(2, -coords.z), span = this.options.tileSize * scale;
                var x = Math.round(coords.x * span), y = Math.round(coords.y * span);
                var width = Math.min(span, this.options.imageWidth - x), height = Math.min(span, this.options.imageHeight - y);
                return this._url + '/' + [x, y, width, height].join(',') + '/' + Math.ceil(width / scale) + ',/0/' +
                    (this.options.imageQuality === 'native' ? 'native' : 'default') + '.jpg';
            },
            _initTile: function (tile) {
                L.TileLayer.prototype._initTile.call(this, tile);
                tile.style.width = 'auto'; tile.style.height = 'auto';
            }
        });

        function clearMarkers() {
            if (markers) { map.removeLayer(markers); markers = null; }
            if (selectedMarker) { map.removeLayer(selectedMarker); selectedMarker = null; }
        }
        function clearMap() {
            imageGeneration++;
            clearMarkers();
            if (image) { map.removeLayer(image); image = null; }
            if (mask) { map.removeLayer(mask); mask = null; }
            active = null; imageBounds = null;
            el('browser-map-title').textContent = 'Historical map';
            el('browser-fit').disabled = true;
            el('browser-image-status').textContent = '';
            el('browser-image-retry').hidden = true;
            el('browser-sources').textContent = '';
        }
        function sources(record) {
            var container = el('browser-sources'); container.textContent = '';
            [['Image source (IIIF)', record.manifest_url || record.image_service + '/info.json'], ['Allmaps annotation', record.source_url]].forEach(function (entry) {
                if (!safeUrl(entry[1])) { return; }
                var link = document.createElement('a'); link.textContent = entry[0]; link.href = entry[1];
                link.target = '_blank'; link.rel = 'noopener noreferrer'; container.appendChild(link);
            });
        }
        function loadImage(record) {
            var current = ++imageGeneration;
            if (image) { map.removeLayer(image); }
            el('browser-image-status').textContent = 'Loading historical image…';
            el('browser-image-retry').hidden = true;
            var failed = new Set();
            var attribution = document.createElement('a'); attribution.textContent = record.title;
            if (safeUrl(record.source_url)) { attribution.href = record.source_url; attribution.target = '_blank'; attribution.rel = 'noopener noreferrer'; }
            image = new ImageTiles(record.image_service, {imageWidth: record.image_width, imageHeight: record.image_height,
                imageQuality: record.image_quality, tileSize: 256, bounds: imageBounds, noWrap: true,
                minZoom: map.getMinZoom(), maxZoom: 1, maxNativeZoom: 0, attribution: 'Image: ' + attribution.outerHTML});
            image.on('tileerror', function (event) {
                if (current !== imageGeneration) { return; }
                failed.add(event.tile);
                el('browser-image-status').textContent = 'The historical image could not fully load. Place details and the map library remain available.';
                el('browser-image-retry').hidden = false;
            });
            image.on('tileload tileunload', function (event) { failed.delete(event.tile); });
            image.on('load', function () {
                if (current === imageGeneration && !failed.size) { el('browser-image-status').textContent = ''; el('browser-image-retry').hidden = true; }
            });
            image.addTo(map);
        }
        function showImage(record) {
            if (active && active.id === record.id && active.revision === record.revision) { return; }
            clearMap(); active = record;
            imageBounds = L.latLngBounds(imagePosition([0, record.image_height]), imagePosition([record.image_width, 0]));
            var size = map.getSize();
            var minimum = Math.min(-7, Math.floor(Math.log(Math.max(1, Math.min(size.x, size.y) - 40) / Math.max(record.image_width, record.image_height)) / Math.LN2) - 1);
            map.setMinZoom(minimum); map.setMaxBounds(imageBounds.pad(.2));
            map.fitBounds(imageBounds, {padding: [20, 20], animate: false}); overviewZoom = map.getZoom();
            el('browser-map-title').textContent = record.title;
            el('browser-fit').disabled = false;
            mask = L.polygon(record.georeference.resourceMask.map(imagePosition), {color: '#b64380', weight: 2, fill: false, interactive: false}).addTo(map);
            sources(record); loadImage(record);
        }

        function showDetails() {
            var place = data.place(selected), thumbnail = el('browser-place-image');
            el('browser-place-details').hidden = !place;
            el('browser-clear').hidden = !place;
            picker.value = place ? String(place.item_id) : '';
            el('browser-position-status').textContent = '';
            thumbnail.hidden = true; thumbnail.removeAttribute('src');
            if (!place) { return; }
            el('browser-place-title').textContent = place.title;
            el('browser-place-description').textContent = place.description || 'No description is available for this place.';
            var link = el('browser-place-link'); link.hidden = !safeUrl(place.url);
            link.removeAttribute('href'); if (!link.hidden) { link.href = place.url; }
            if (safeUrl(place.thumbnail_url)) { thumbnail.alt = place.title; thumbnail.src = place.thumbnail_url; thumbnail.hidden = false; }
        }
        el('browser-place-image').addEventListener('error', function () { this.hidden = true; });

        function showLibrary(rows) {
            list.textContent = '';
            rows.forEach(function (row) {
                var button = document.createElement('button'); button.type = 'button'; button.dataset.mapId = row.map.id;
                button.textContent = row.map.title;
                button.setAttribute('aria-pressed', String(!!active && active.id === row.map.id));
                if (row.error || row.covered === null || (selected && !row.pixel)) {
                    var label = document.createElement('small');
                    label.textContent = row.error ? 'Data unavailable — retry loading' : (row.covered === null ? 'Coverage needs review' : 'Position needs review');
                    button.appendChild(label);
                }
                button.addEventListener('click', function () { displayMap(row.map, rows); });
                list.appendChild(button);
            });
        }
        function mark(relation, chosen) {
            var icon = new L.Icon.Default({className: 'historical-browser-marker' + (chosen ? ' is-selected' : '')});
            var pin = L.marker(imagePosition(relation.pixel), {icon: icon, title: relation.place.title, keyboard: true, zIndexOffset: chosen ? 1000 : 0});
            pin.on('click', function () { choose(relation.place.item_id); });
            return pin;
        }
        async function displayMap(record, rows) {
            var current = ++generation;
            clearMarkers(); showImage(record); showLibrary(rows);
            el('browser-position-status').textContent = selected ? 'Locating this place…' : '';
            try {
                var relations = await data.placesForMap(record.id);
                if (current !== generation) { return; }
                markers = L.markerClusterGroup({animate: false, showCoverageOnHover: false, chunkedLoading: true, chunkInterval: 40, chunkDelay: 10,
                    iconCreateFunction: function (group) { return L.divIcon({className: 'historical-browser-cluster', html: '<span>' + group.getChildCount() + '</span>', iconSize: [38, 38]}); }}).addTo(map);
                var pins = [], chosen;
                relations.forEach(function (r) {
                    if (r.place.item_id === selected) { chosen = r; }
                    if (r.pixel && r.place.item_id !== selected) { pins.push(mark(r, false)); }
                });
                markers.addLayers(pins);
                if (selected) {
                    if (chosen && chosen.pixel) {
                        selectedMarker = mark(chosen, true).addTo(map);
                        map.setView(imagePosition(chosen.pixel), Math.min(1, Math.max(map.getZoom(), overviewZoom + .75)), {animate: false});
                        el('browser-position-status').textContent = chosen.estimated ? 'Estimated position on this map.' : 'Reviewed position on this map.';
                    } else {
                        el('browser-position-status').textContent = chosen && chosen.covered === null ? 'Coverage and the historical position need review for this map.' : 'No reliable historical position is available on this map. Your place selection is kept.';
                    }
                }
            } catch (error) {
                if (current !== generation) { return; }
                el('browser-position-status').textContent = error.message;
                message(error.message);
            }
        }

        async function choose(id) {
            if (!ready) { return; }
            var current = ++generation;
            selected = data.place(id) ? id : null;
            clearMarkers(); showDetails(); list.textContent = '';
            message(selected ? 'Finding maps for this place…' : 'Showing all available maps.');
            var rows = selected ? await data.mapsForPlace(selected) : data.maps().map(function (record) { return {map: record}; });
            if (current !== generation) { return; }
            showLibrary(rows);
            if (!rows.length) {
                clearMap();
                message(selected ? 'No available historical maps cover this place.' : 'No historical maps are available yet.');
                if (selected) { el('browser-position-status').textContent = 'Your place selection is kept. Choose another place or clear the selection.'; }
                return;
            }
            message(selected ? rows.length + (rows.length === 1 ? ' map for ' : ' maps for ') + data.place(selected).title + '.' : 'Showing all ' + rows.length + ' available maps.');
            var row = rows.filter(function (r) { return active && r.map.id === active.id; })[0] || rows[0];
            await displayMap(row.map, rows);
        }

        async function reload() {
            var current = ++generation, previousId = selected, previousMapId = active && active.id;
            ready = false; picker.disabled = true; el('browser-reload').disabled = true;
            clearMap(); list.textContent = ''; message('Loading maps and public Omeka places…');
            el('browser-place-details').hidden = true; el('browser-clear').hidden = true;
            try {
                await data.refresh();
                if (current !== generation) { return; }
                picker.textContent = ''; var option = document.createElement('option'); option.value = ''; option.textContent = 'Choose a place'; picker.appendChild(option);
                data.places().forEach(function (place) {
                    var item = document.createElement('option'); item.value = place.item_id; item.textContent = place.title; picker.appendChild(item);
                });
                ready = true; picker.disabled = false;
                var params = new URLSearchParams(window.location.search);
                var requestedMapId = previousMapId || Number(params.get('map_id'));
                var requested = data.maps().filter(function (r) { return r.id === requestedMapId; })[0];
                // Retain only the identity here. showImage must load fresh records.
                active = requested ? {id: requested.id} : null;
                var requestedItem = previousId || Number(params.get('item_id'));
                await choose(requestedItem);
            } catch (error) {
                if (current !== generation) { return; }
                selected = null; showDetails(); message(error.message);
            } finally { el('browser-reload').disabled = false; }
        }
        picker.addEventListener('change', function () { choose(Number(this.value)); });
        el('browser-clear').addEventListener('click', function () { choose(null); });
        el('browser-reload').addEventListener('click', reload);
        el('browser-fit').addEventListener('click', function () { if (imageBounds) { map.fitBounds(imageBounds, {padding: [20, 20], animate: false}); } });
        el('browser-image-retry').addEventListener('click', function () { if (active) { loadImage(active); } });
        if (window.ResizeObserver) { new ResizeObserver(function () { map.invalidateSize({pan: false}); }).observe(el('browser-map')); }
        else { window.addEventListener('resize', function () { map.invalidateSize({pan: false}); }); }
        reload();
    });
}(window, jQuery));
