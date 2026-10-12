/* global jQuery */
(function (window, $) {
    'use strict';
    window.WalkingTourHistoricalPlaces = function (root, historical, modern) {
        var L = window.L, generation = 0, request, decisionRequest, writeRequest, selected, pairs = {}, record;
        var decisions = {}, transform, canEdit = false, csrf, draft = null, draftMarker, busy = false, excluded = 0;
        function el(id) { return document.getElementById(id); }
        function apiError(xhr) {
            try { var body = xhr.responseJSON || JSON.parse(xhr.responseText || '{}'); return typeof body.error === 'string' ? body.error : ''; } catch (e) { return ''; }
        }
        function feedback(text) { el('historical-decision-status').textContent = text; }
        function decision(id) { return decisions[id] || {pixel: null, visibility: 'default', note: '', needs_review: false}; }
        function cancelDraft() {
            draft = null; if (draftMarker) { historical.removeLayer(draftMarker); draftMarker = null; }
            el('historical-position-draft').hidden = true;
        }
        function visible(id) { return decision(id).visibility !== 'hide'; }
        function position(pair) {
            var d = decision(pair.place.item_id);
            return d.pixel && !d.needs_review ? d.pixel : pair.estimate;
        }
        function paintHistorical(pair) {
            if (pair.historical) { historicalLayer.removeLayer(pair.historical); }
            var pixel = position(pair), d = decision(pair.place.item_id);
            pair.historical = pixel && pair.place.item_id === selected && visible(selected) ? marker([-pixel[1], pixel[0]], pair.place, !d.pixel || d.needs_review, true) : null;
            if (pair.historical && d.pixel && !d.needs_review) { pair.historical.options.icon.options.className += ' is-override'; }
        }
        function details() {
            var pair = pairs[selected]; if (!pair) { return; }
            var d = decision(selected), hasPosition = !!position(pair);
            el('historical-place-status').textContent = d.needs_review ? 'The saved position needs review after an image change. ' + (hasPosition ? 'The current automatic estimate is shown.' : 'No reliable historical estimate is available; the modern location is shown.') :
                (d.pixel ? 'Reviewed historical position override. This does not change Allmaps calibration.' :
                (hasPosition ? 'Estimated historical position. Compare with the original image.' : 'No reliable historical counterpart is available within this map. The modern location is shown.'));
            if (!visible(selected)) { el('historical-place-status').textContent = 'Hidden on this map. Restore Default or Show to display this Item again. ' + el('historical-place-status').textContent; }
            el('historical-place-visibility').value = d.visibility;
            el('historical-place-editor').hidden = !canEdit;
            el('historical-place-note').value = d.note;
            el('historical-position-reset').disabled = busy || !d.pixel;
        }
        function setBusy(value) {
            busy = value; picker.disabled = value;
            if (draftMarker && draftMarker.dragging) { if (value) { draftMarker.dragging.disable(); } else { draftMarker.dragging.enable(); } }
            root.querySelectorAll('#historical-place-editor button, #historical-place-editor input, #historical-place-editor textarea, #historical-place-editor select, #historical-show-hidden, #historical-map-list button').forEach(function (button) { button.disabled = value; });
            if (!value) { el('historical-position-reset').disabled = !decision(selected).pixel; }
        }
        function save(operation, value) {
            if (!canEdit || busy || !pairs[selected]) { return; }
            var stamp = generation, itemId = selected;
            setBusy(true); feedback('Saving…');
            writeRequest = $.ajax({url: base + 'historical-map-decision', type: 'POST', contentType: 'application/json', dataType: 'json', timeout: 20000,
                data: JSON.stringify({map_id: record.id, item_id: itemId, revision: record.revision, csrf_token: csrf, operation: operation, value: value})});
            writeRequest.done(function (data) {
                if (stamp !== generation) { return; }
                record.revision = data.revision; decisions[itemId] = data.decision;
                if (operation === 'position') { cancelDraft(); paintHistorical(pairs[itemId]); locate(); refreshStats(); }
                if (operation === 'visibility') {
                    cancelDraft(); if (value === 'hide') { el('historical-show-hidden').checked = true; }
                    paintVisibility(pairs[itemId]); refreshPicker(); refreshStats();
                }
                details(); feedback('Decision saved.');
            }).fail(function (xhr) {
                if (stamp !== generation) { return; }
                feedback(apiError(xhr) || 'The save response was not received. Your draft is kept. Reload and review the saved decision before retrying.');
                el('historical-decision-reload').hidden = false;
            }).always(function () { if (stamp === generation) { setBusy(false); } });
        }
        function updateDraft(pixel) {
            if (busy || !draft) { return; }
            draft.pixel = pixel;
            el('historical-pixel-x').value = pixel[0]; el('historical-pixel-y').value = pixel[1];
            if (draftMarker) { draftMarker.setLatLng([-pixel[1], pixel[0]]); }
            else {
                draftMarker = L.marker([-pixel[1], pixel[0]], {draggable: true, title: 'Unsaved historical position', icon: L.divIcon({className: 'historical-place-marker is-draft', html:'●', iconSize:[24,24],iconAnchor:[12,12]})}).addTo(historical);
                draftMarker.on('dragend', function () { var p = draftMarker.getLatLng(); updateDraft([p.lng, -p.lat]); });
            }
        }
        el('historical-place-adjust').addEventListener('click', function () {
            if (!canEdit || busy || !pairs[selected]) { return; }
            cancelDraft(); draft = {itemId: selected}; el('historical-position-draft').hidden = false;
            var pair = pairs[selected], d = decision(selected), p = d.pixel || position(pair);
            if (p) { updateDraft(p); historical.setView([-p[1],p[0]], -1); }
            else { el('historical-pixel-x').value = ''; el('historical-pixel-y').value = ''; }
            feedback('Unsaved adjustment. Save to keep it; switching maps or Items cancels this draft.');
        });
        historical.on('click', function (event) { if (draft) { updateDraft([event.latlng.lng, -event.latlng.lat]); } });
        ['historical-pixel-x','historical-pixel-y'].forEach(function (id) { el(id).addEventListener('change', function () {
            var x = el('historical-pixel-x').value, y = el('historical-pixel-y').value;
            if (x !== '' && y !== '' && Number.isFinite(Number(x)) && Number.isFinite(Number(y))) { updateDraft([Number(x),Number(y)]); }
        }); });
        el('historical-position-save').addEventListener('click', function () {
            var x = el('historical-pixel-x').value, y = el('historical-pixel-y').value;
            if (x === '' || y === '' || !Number.isFinite(Number(x)) || !Number.isFinite(Number(y))) { feedback('Enter two finite pixel coordinates.'); return; }
            save('position', [Number(x), Number(y)]);
        });
        el('historical-position-cancel').addEventListener('click', function () { cancelDraft(); feedback('Adjustment cancelled.'); });
        el('historical-position-reset').addEventListener('click', function () { save('position', null); });
        el('historical-visibility-save').addEventListener('click', function () { save('visibility', el('historical-place-visibility').value); });
        el('historical-show-hidden').addEventListener('change', refreshPicker);
        el('historical-note-save').addEventListener('click', function () { save('note', el('historical-place-note').value); });
        el('historical-decision-reload').addEventListener('click', function () { window.location.reload(); });
        function paintVisibility(pair) {
            modernLayer.removeLayer(pair.modern); if (pair.historical) { historicalLayer.removeLayer(pair.historical); }
            if (visible(pair.place.item_id)) {
                modernLayer.addLayer(pair.modern);
                if (pair.place.item_id === selected) { locate(); }
            }
        }
        function addOption(pair) {
            var id = pair.place.item_id;
            if (!visible(id) && !(canEdit && el('historical-show-hidden').checked)) { return; }
            var option = document.createElement('option'); option.value = id; option.textContent = pair.place.title + (visible(id) ? '' : ' (hidden)'); picker.appendChild(option);
        }
        function refreshPicker() {
            picker.textContent = ''; var empty = document.createElement('option'); empty.value = ''; empty.textContent = 'Choose a place'; picker.appendChild(empty);
            Object.keys(pairs).forEach(function (key) { addOption(pairs[key]); });
            if (selected && (!visible(selected) && !(canEdit && el('historical-show-hidden').checked))) { clearSelection(); }
            picker.value = selected ? String(selected) : '';
        }
        function refreshStats() {
            var shown = 0, paired = 0, hidden = 0;
            Object.keys(pairs).forEach(function (key) { if (!visible(key)) { hidden++; } else { shown++; if (position(pairs[key])) { paired++; } } });
            status.textContent = shown + ' places within map coverage · ' + paired + ' historical positions · ' + hidden + ' hidden · ' + excluded + ' outside coverage or without a reliable estimate.';
        }
        function cluster(map) {
            return L.markerClusterGroup({animate: false, showCoverageOnHover: false, chunkedLoading: true, chunkInterval: 40, chunkDelay: 10,
                iconCreateFunction: function (group) { return L.divIcon({className: 'historical-place-cluster', html: '<span>' + group.getChildCount() + '</span>', iconSize: [38, 38]}); }
            }).addTo(map);
        }
        var modernLayer = cluster(modern), historicalLayer = L.layerGroup().addTo(historical);
        var status = document.getElementById('modern-map-status');
        var picker = document.getElementById('historical-place-select');
        var panel = document.getElementById('historical-place-details');
        var base = root.dataset.apiBase.replace(/\/?$/, '/');
        function marker(position, place, estimated, showPopup) {
            // Geolocation uses Leaflet's standard blue pin. Each marker gets its
            // own icon options so an override never changes other Items' pins.
            var icon = new L.Icon.Default({className: 'historical-place-marker' + (estimated ? ' is-estimated' : '')});
            var m = L.marker(position, {icon: icon, title: place.title, keyboard: true});
            m.on('add', function () { if (m.getElement()) { m.getElement().classList.toggle('is-selected', place.item_id === selected); } });
            m.on('click', function () { select(place.item_id); });
            if (!showPopup) { return m; }
            var content = document.createElement('div'); content.className = 'geolocation_balloon';
            var heading = document.createElement('div'); heading.className = 'geolocation_balloon_title';
            var title = document.createElement('a'); title.textContent = place.title; title.href = place.url;
            heading.appendChild(title); content.appendChild(heading);
            if (place.thumbnail_url && /^(?:https?:\/\/|\/(?!\/))/i.test(place.thumbnail_url)) {
                var thumbnail = document.createElement('div'); thumbnail.className = 'geolocation_balloon_thumbnail';
                var link = document.createElement('a'); link.href = place.url;
                var image = document.createElement('img'); image.src = place.thumbnail_url; image.alt = place.title;
                image.addEventListener('load', function () { if (m.isPopupOpen()) { m.getPopup().update(); } });
                image.addEventListener('error', function () { thumbnail.hidden = true; if (m.isPopupOpen()) { m.getPopup().update(); } });
                link.appendChild(image); thumbnail.appendChild(link); content.appendChild(thumbnail);
            }
            if (place.description) {
                var description = document.createElement('p'); description.className = 'geolocation_balloon_description';
                description.textContent = place.description.length > 150 ? place.description.slice(0,150).trim() + '…' : place.description;
                content.appendChild(description);
            }
            m.bindPopup(content, {className: 'historical-place-popup', maxWidth: 300, minWidth: 180, autoPanPadding: [20,20]});
            return m;
        }
        function locate() {
            var pair = pairs[selected]; if (!pair || !visible(selected)) { return; }
            var stamp = generation, id = selected;
            modern.closePopup();
            modernLayer.zoomToShowLayer(pair.modern, function () {
                if (stamp !== generation || id !== selected) { return; }
                if (pair.modern.getElement()) { pair.modern.getElement().classList.add('is-selected'); }
            });
            // The historical pane contains only the selected counterpart, never
            // the complete place layer or clusters. Details open on this pane only.
            historicalLayer.clearLayers();
            if (!pair.historical) { paintHistorical(pair); }
            if (pair.historical) {
                var p = position(pair);
                if (p) {
                    historicalLayer.addLayer(pair.historical);
                    historical.setView([-p[1],p[0]], historical.getMaxZoom(), {animate: false});
                    pair.historical.openPopup();
                }
            }
        }
        function select(id) {
            var pair = pairs[id]; if (!pair) { return; }
            if (busy) { return; }
            cancelDraft(); feedback(''); selected = id; picker.value = String(id); panel.hidden = false;
            document.getElementById('historical-place-title').textContent = pair.place.title;
            document.getElementById('historical-place-description').textContent = pair.place.description;
            document.getElementById('historical-place-link').href = pair.place.url;
            details();
            Object.keys(pairs).forEach(function (key) { [pairs[key].modern, pairs[key].historical].forEach(function (m) { if (m && m.getElement()) { m.getElement().classList.toggle('is-selected', key === String(id)); } }); });
            locate();
        }
        function clearSelection() {
            cancelDraft(); selected = null; panel.hidden = true; picker.value = '';
            historicalLayer.clearLayers(); modern.closePopup(); feedback('');
            Object.keys(pairs).forEach(function (key) { var m = pairs[key].modern; if (m.getElement()) { m.getElement().classList.remove('is-selected'); } });
        }
        picker.addEventListener('change', function () { if (this.value) { select(Number(this.value)); } else if (!busy) { clearSelection(); } });
        document.getElementById('historical-place-locate').addEventListener('click', locate);
        function load(map) {
            var current = ++generation; record = map; selected = null; pairs = {}; excluded = 0;
            if (request) { request.abort(); }
            if (decisionRequest) { decisionRequest.abort(); }
            cancelDraft(); decisions = {}; canEdit = false; csrf = null; busy = false;
            picker.disabled = false; el('historical-show-hidden').checked = false; el('historical-hidden-label').hidden = true; el('historical-place-editor').hidden = true; el('historical-decision-reload').hidden = true;
            modern.removeLayer(modernLayer); historical.removeLayer(historicalLayer);
            modernLayer = cluster(modern); historicalLayer = L.layerGroup().addTo(historical); panel.hidden = true;
            picker.textContent = ''; var empty = document.createElement('option'); empty.value = ''; empty.textContent = 'Choose a place'; picker.appendChild(empty);
            status.textContent = 'Loading public Omeka places…';
            var error = ''; transform = null;
            try { transform = window.WalkingTourAllmaps.create(map); } catch (e) { error = e.message; }
            function page(after) {
                request = $.ajax({url: base + 'historical-map-places', data: {after: after}, dataType: 'json', timeout: 20000});
                request.done(function (data) {
                    if (current !== generation) { return; }
                    var offset = 0;
                    function batch() {
                        if (current !== generation) { return; }
                        var end = Math.min(offset + 30, data.places.length), modernBatch = [];
                        for (; offset < end; offset++) {
                            var place = data.places[offset];
                            // Scope is set by the live geographic location and imported
                            // calibration, before applying any editorial display/position.
                            var estimate = transform ? transform.estimate(place.coordinates) : null;
                            if (!estimate) { excluded++; continue; }
                            var m = marker([place.coordinates[1], place.coordinates[0]], place, false);
                            if (visible(place.item_id)) { modernBatch.push(m); }
                            pairs[place.item_id] = {place: place, modern: m, historical: null, estimate: estimate};
                            addOption(pairs[place.item_id]);
                        }
                        modernLayer.addLayers(modernBatch);
                        refreshStats(); if (error) { status.textContent += ' ' + error; }
                        if (offset < data.places.length) { window.setTimeout(batch, 0); }
                        else if (data.next !== null) { page(data.next); }
                    }
                    batch();
                }).fail(function (xhr, text) {
                    if (current !== generation || text === 'abort') { return; }
                    status.textContent = apiError(xhr) || 'Places could not be loaded. Reload the page to retry.';
                });
            }
            decisionRequest = $.ajax({url: base + 'historical-map-decisions', data: {map_id: map.id}, dataType: 'json', timeout: 20000});
            decisionRequest.done(function (data) {
                if (current !== generation) { return; }
                if (data.revision !== record.revision) { status.textContent = 'This map changed while loading. Reload the page to use its latest version.'; return; }
                data.decisions.forEach(function (d) { decisions[d.item_id] = d; });
                canEdit = data.can_edit; csrf = data.csrf_token; el('historical-hidden-label').hidden = !canEdit; page(0);
            }).fail(function (xhr, text) {
                if (current !== generation || text === 'abort') { return; }
                status.textContent = apiError(xhr) || 'Place decisions could not load. Reload the page to retry.';
            });
        }
        function clear() {
            generation++; if (request) { request.abort(); } if (decisionRequest) { decisionRequest.abort(); }
            cancelDraft(); canEdit = false; pairs = {}; selected = null; panel.hidden = true;
            modern.removeLayer(modernLayer); historical.removeLayer(historicalLayer);
        }
        return {load: load, clear: clear};
    };
}(window, jQuery));
