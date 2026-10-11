/* global jQuery */
(function (window, $) {
    'use strict';
    window.WalkingTourHistoricalPlaces = function (root, historical, modern) {
        var L = window.L, generation = 0, request, decisionRequest, writeRequest, selected, pairs = {}, record;
        var decisions = {}, transform, canEdit = false, csrf, draft = null, draftMarker, busy = false;
        function el(id) { return document.getElementById(id); }
        function feedback(text) { el('historical-decision-status').textContent = text; }
        function decision(id) { return decisions[id] || {pixel: null, visibility: 'default', note: '', needs_review: false}; }
        function cancelDraft() {
            draft = null; if (draftMarker) { historical.removeLayer(draftMarker); draftMarker = null; }
            el('historical-position-draft').hidden = true;
        }
        function position(pair) {
            var d = decision(pair.place.item_id);
            return d.pixel && !d.needs_review ? d.pixel : (transform ? transform.estimate(pair.place.coordinates) : null);
        }
        function paintHistorical(pair) {
            if (pair.historical) { historicalLayer.removeLayer(pair.historical); }
            var pixel = position(pair), d = decision(pair.place.item_id);
            pair.historical = pixel ? marker([-pixel[1], pixel[0]], pair.place, !d.pixel || d.needs_review) : null;
            if (pair.historical) {
                if (d.pixel && !d.needs_review) { pair.historical.options.icon.options.className += ' is-override'; }
                historicalLayer.addLayer(pair.historical);
            }
        }
        function details() {
            var pair = pairs[selected]; if (!pair) { return; }
            var d = decision(selected);
            el('historical-place-status').textContent = d.needs_review ? 'The saved position needs review after an image change. Only the current automatic estimate is used.' :
                (d.pixel ? 'Reviewed historical position override. This does not change Allmaps calibration.' :
                (pair.historical ? 'Estimated historical position. Compare with the original image.' : 'No reliable historical counterpart is available within this map. The modern location is shown.'));
            el('historical-place-editor').hidden = !canEdit;
            el('historical-place-note').value = d.note;
            el('historical-position-reset').disabled = busy || !d.pixel;
        }
        function setBusy(value) {
            busy = value; picker.disabled = value;
            if (draftMarker && draftMarker.dragging) { if (value) { draftMarker.dragging.disable(); } else { draftMarker.dragging.enable(); } }
            root.querySelectorAll('#historical-place-editor button, #historical-place-editor input, #historical-place-editor textarea, #historical-map-list button').forEach(function (button) { button.disabled = value; });
            if (!value) { details(); }
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
                if (operation === 'position') { cancelDraft(); paintHistorical(pairs[itemId]); } details(); feedback('Decision saved.');
            }).fail(function (xhr) {
                if (stamp !== generation) { return; }
                feedback((xhr.responseJSON && xhr.responseJSON.error) || 'The save response was not received. Your draft is kept. Reload and review the saved decision before retrying.');
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
        el('historical-note-save').addEventListener('click', function () { save('note', el('historical-place-note').value); });
        el('historical-decision-reload').addEventListener('click', function () { window.location.reload(); });
        function cluster(map) {
            return L.markerClusterGroup({animate: false, showCoverageOnHover: false, chunkedLoading: true, chunkInterval: 40, chunkDelay: 10,
                iconCreateFunction: function (group) { return L.divIcon({className: 'historical-place-cluster', html: '<span>' + group.getChildCount() + '</span>', iconSize: [38, 38]}); }
            }).addTo(map);
        }
        var modernLayer = cluster(modern), historicalLayer = cluster(historical);
        var status = document.getElementById('modern-map-status');
        var picker = document.getElementById('historical-place-select');
        var panel = document.getElementById('historical-place-details');
        var base = root.dataset.apiBase.replace(/\/?$/, '/');
        function marker(position, place, estimated) {
            var icon = L.divIcon({className: 'historical-place-marker' + (estimated ? ' is-estimated' : ''), html: '●', iconSize: [24, 24], iconAnchor: [12, 12]});
            var m = L.marker(position, {icon: icon, title: place.title, keyboard: true});
            var label = document.createElement('span'); label.textContent = place.title; m.bindPopup(label);
            m.on('add', function () { if (m.getElement()) { m.getElement().classList.toggle('is-selected', place.item_id === selected); } });
            m.on('click', function () { select(place.item_id); });
            return m;
        }
        function locate() {
            var pair = pairs[selected]; if (!pair) { return; }
            var stamp = generation, id = selected;
            function reveal(layer, map, m, zoom) {
                map.setView(m.getLatLng(), map.getMaxZoom(), {animate: false});
                var parent = layer.getVisibleParent(m);
                if (parent && parent !== m && parent.spiderfy) { parent.spiderfy(); }
                layer.zoomToShowLayer(m, function () {
                    if (stamp !== generation || id !== selected) { return; }
                    if (m.getElement()) { m.getElement().classList.add('is-selected'); }
                    m.openPopup();
                });
            }
            reveal(modernLayer, modern, pair.modern, 16);
            if (pair.historical) { reveal(historicalLayer, historical, pair.historical, -1); }
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
        picker.addEventListener('change', function () { if (this.value) { select(Number(this.value)); } });
        document.getElementById('historical-place-locate').addEventListener('click', locate);
        function load(map) {
            var current = ++generation; record = map; selected = null; pairs = {};
            if (request) { request.abort(); }
            if (decisionRequest) { decisionRequest.abort(); }
            cancelDraft(); decisions = {}; canEdit = false; csrf = null; busy = false;
            picker.disabled = false; el('historical-place-editor').hidden = true; el('historical-decision-reload').hidden = true;
            modern.removeLayer(modernLayer); historical.removeLayer(historicalLayer);
            modernLayer = cluster(modern); historicalLayer = cluster(historical); panel.hidden = true;
            picker.textContent = ''; var empty = document.createElement('option'); empty.value = ''; empty.textContent = 'Choose a place'; picker.appendChild(empty);
            status.textContent = 'Loading public Omeka places…';
            var error = '', count = 0, paired = 0; transform = null;
            try { transform = window.WalkingTourAllmaps.create(map); } catch (e) { error = e.message; }
            function page(after) {
                request = $.ajax({url: base + 'historical-map-places', data: {after: after}, dataType: 'json', timeout: 20000});
                request.done(function (data) {
                    if (current !== generation) { return; }
                    var offset = 0;
                    function batch() {
                        if (current !== generation) { return; }
                        var end = Math.min(offset + 30, data.places.length), modernBatch = [], historicalBatch = [];
                        for (; offset < end; offset++) {
                            var place = data.places[offset], d = decision(place.item_id), pixel = position({place: place});
                            var m = marker([place.coordinates[1], place.coordinates[0]], place, false);
                            var h = pixel ? marker([-pixel[1], pixel[0]], place, !d.pixel || d.needs_review) : null;
                            if (h && d.pixel && !d.needs_review) { h.options.icon.options.className += ' is-override'; }
                            modernBatch.push(m); if (h) { historicalBatch.push(h); }
                            pairs[place.item_id] = {place: place, modern: m, historical: h}; count++; if (h) { paired++; }
                            var option = document.createElement('option'); option.value = place.item_id; option.textContent = place.title; picker.appendChild(option);
                        }
                        modernLayer.addLayers(modernBatch); historicalLayer.addLayers(historicalBatch);
                        status.textContent = count + ' public places · ' + paired + ' estimated historical positions.' + (error ? ' ' + error : '');
                        if (offset < data.places.length) { window.setTimeout(batch, 0); }
                        else if (data.next !== null) { page(data.next); }
                    }
                    batch();
                }).fail(function (xhr, text) {
                    if (current !== generation || text === 'abort') { return; }
                    status.textContent = (xhr.responseJSON && xhr.responseJSON.error) || 'Places could not be loaded. Reload the page to retry.';
                });
            }
            decisionRequest = $.ajax({url: base + 'historical-map-decisions', data: {map_id: map.id}, dataType: 'json', timeout: 20000});
            decisionRequest.done(function (data) {
                if (current !== generation) { return; }
                if (data.revision !== record.revision) { status.textContent = 'This map changed while loading. Reload the page to use its latest version.'; return; }
                data.decisions.forEach(function (d) { decisions[d.item_id] = d; });
                canEdit = data.can_edit; csrf = data.csrf_token; page(0);
            }).fail(function (xhr, text) {
                if (current !== generation || text === 'abort') { return; }
                status.textContent = (xhr.responseJSON && xhr.responseJSON.error) || 'Place decisions could not load. Reload the page to retry.';
            });
        }
        return {load: load};
    };
}(window, jQuery));
