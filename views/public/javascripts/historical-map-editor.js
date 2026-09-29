/* global jQuery */
(function (window, $) {
    'use strict';
    window.WalkingTourMapEditor = function (options) {
        var L = window.L, root = options.root, historical = options.historical, modern = options.modern;
        var record, canEdit = false, csrf, mode = null, draft = null, busy = false, pending = null, generation = 0;
        var vertices = [], closed = false, restoreDoubleClick = false;
        var imageSaved = L.layerGroup().addTo(historical), modernSaved = L.layerGroup().addTo(modern);
        var imageDraft = L.layerGroup().addTo(historical), modernDraft = L.layerGroup().addTo(modern);
        var footprint = L.layerGroup().addTo(modern), imageOutline = L.layerGroup().addTo(historical);
        var panel = document.getElementById('map-edit-panel'), status = document.getElementById('map-edit-status');
        var save = document.getElementById('map-edit-save'), undo = document.getElementById('map-edit-undo');
        var reload = document.getElementById('map-edit-reload'), cancelButton = document.getElementById('map-edit-cancel');
        var tools = [];
        function pixel(x, y) { return L.latLng(-y, x); }
        function message(text) { panel.hidden = false; status.textContent = text; }
        function errorText(xhr) {
            var data = xhr.responseJSON;
            if (!data) { try { data = JSON.parse(xhr.responseText); } catch (_) { /* Use safe fallback. */ } }
            return data && data.error || 'The request failed. Your draft has been kept. Please retry.';
        }
        function hasDraft() { return !!draft || vertices.length > 0 || !!pending; }
        function updateTools() {
            tools.forEach(function (tool) {
                tool.button.disabled = !canEdit || !record || busy;
                tool.button.setAttribute('aria-pressed', String(mode === tool.mode));
                tool.button.title = canEdit ? tool.label : 'Map editing is currently unavailable';
            });
            cancelButton.disabled = busy;
            undo.disabled = busy || !vertices.length;
            save.disabled = !canEdit || busy || !draft || (mode === 'mask' && !closed);
            root.classList.toggle('is-drawing-mask', mode === 'mask');
        }
        function reset() {
            generation++;
            if (pending) { pending.abort(); pending = null; }
            if (restoreDoubleClick) { modern.doubleClickZoom.enable(); restoreDoubleClick = false; }
            mode = null; draft = null; vertices = []; closed = false;
            cancelButton.textContent = 'Cancel';
            imageDraft.clearLayers(); modernDraft.clearLayers(); panel.hidden = true;
            undo.hidden = true; reload.hidden = true; updateTools();
        }
        function begin(next) {
            if (busy) { return; }
            if (hasDraft()) { message('Save or cancel the current draft before starting another edit.'); return; }
            reset(); mode = next;
            if (next === 'mask') {
                restoreDoubleClick = modern.doubleClickZoom.enabled(); modern.doubleClickZoom.disable();
                undo.hidden = false;
                message('Click to draw a mask. Add at least 3 vertices, then select the first vertex to close the polygon.');
            } else { message('Click the ' + (next === 'image' ? 'historical image' : 'modern map') + ' to add a point. Its counterpart is an estimate.'); }
            updateTools();
        }
        function addTool(map, type, label, svg) {
            var control = L.control({position: 'topright'});
            control.onAdd = function () {
                var container = L.DomUtil.create('div', 'leaflet-bar map-drawing-tool');
                var button = document.createElement('button');
                button.type = 'button'; button.innerHTML = svg; button.setAttribute('aria-label', label);
                button.setAttribute('aria-pressed', 'false');
                button.addEventListener('click', function () { begin(type); });
                container.appendChild(button); L.DomEvent.disableClickPropagation(container);
                L.DomEvent.disableScrollPropagation(container);
                tools.push({button: button, mode: type, label: label});
                return container;
            };
            control.addTo(map);
        }
        var pencil = '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 16L16 4l4 4L8 20l-5 1zM13 7l4 4"/></svg>';
        var polygon = '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 11L10 4l10 4-3 12z"/><circle cx="4" cy="11" r="2"/><circle cx="10" cy="4" r="2"/><circle cx="20" cy="8" r="2"/><circle cx="17" cy="20" r="2"/></svg>';
        addTool(historical, 'image', 'Add an image annotation', pencil);
        addTool(modern, 'modern', 'Add a map annotation', pencil);
        addTool(modern, 'mask', 'Draw a map mask', polygon);

        function drawMask() {
            modernDraft.clearLayers();
            if (vertices.length > 1) {
                (closed ? L.polygon(vertices, {color: '#f549b2', weight: 4, fillOpacity: .08}) :
                    L.polyline(vertices, {color: '#f549b2', weight: 4, dashArray: '6 5'})).addTo(modernDraft);
            }
            vertices.forEach(function (position, index) {
                var marker = L.marker(position, {keyboard: true, title: index === 0 ? 'Close mask' : 'Mask vertex ' + (index + 1),
                    icon: L.divIcon({className: 'mask-vertex', html: String(index + 1), iconSize: [24, 24], iconAnchor: [12, 12]})});
                marker.on('click', function (event) {
                    if (event.originalEvent) { L.DomEvent.stopPropagation(event.originalEvent); }
                    if (busy || closed || index !== 0) { return; }
                    if (vertices.length < 3) { message('Add at least 3 vertices before closing the mask.'); return; }
                    closed = true;
                    var ring = vertices.map(function (v) { return [v.lng, v.lat]; }); ring.push(ring[0].slice());
                    draft = {operation: 'mask', ring: ring, revision: record.revision};
                    drawMask(); message('Mask complete. Save to replace the current map mask, or cancel to keep it.'); updateTools();
                });
                marker.on('add', function () {
                    marker.getElement().setAttribute('role', 'button');
                    marker.getElement().setAttribute('aria-label', index === 0 ? 'Close mask' : 'Mask vertex ' + (index + 1));
                });
                marker.addTo(modernDraft);
            });
            updateTools();
        }
        function annotationMarkers(point, isDraft) {
            var pair = [], label = isDraft ? 'New' : 'A' + point.id;
            [[historical, imageSaved, imageDraft, point.image_x === null ? null : pixel(point.image_x, point.image_y)],
                [modern, modernSaved, modernDraft, point.longitude === null ? null : L.latLng(point.latitude, point.longitude)]]
                .forEach(function (entry) {
                    if (!entry[3]) { return; }
                    var marker = L.marker(entry[3], {title: label + ' — ' + point.status, keyboard: true,
                        icon: L.divIcon({className: 'annotation-marker' + (isDraft ? ' is-draft' : ''), html: label, iconSize: [34, 28], iconAnchor: [17, 14]})});
                    marker.on('add', function () {
                        marker.getElement().setAttribute('role', 'button');
                        marker.getElement().setAttribute('aria-label', label + ' — ' + point.status);
                    });
                    marker.addTo(isDraft ? entry[2] : entry[1]); pair.push({map: entry[0], marker: marker});
                    marker.on('click', function () {
                        root.querySelectorAll('.annotation-marker.is-selected').forEach(function (el) { el.classList.remove('is-selected'); });
                        pair.forEach(function (p) { p.map.panTo(p.marker.getLatLng()); p.marker.getElement().classList.add('is-selected'); });
                        document.getElementById('control-point-selection').textContent = label + ': ' + point.status + ' position. Not a calibration point.';
                        root.dispatchEvent(new CustomEvent('walkingtour:annotation-selected', {bubbles: true, detail: {mapId: record.id, annotation: Object.assign({}, point)}}));
                    });
                });
        }
        function renderSaved() {
            imageSaved.clearLayers(); modernSaved.clearLayers(); footprint.clearLayers(); imageOutline.clearLayers();
            if (record.mask) {
                if (record.mask.geographic_ring) {
                    L.polygon(record.mask.geographic_ring.map(function (p) { return [p[1], p[0]]; }),
                        {color: '#f549b2', weight: 4, fillOpacity: .04, interactive: false}).addTo(footprint);
                }
                if (record.mask.image_ring) {
                    L.polygon(record.mask.image_ring.map(function (p) { return pixel(p[0], p[1]); }),
                        {color: '#f549b2', weight: 3, fill: false, interactive: false}).addTo(imageOutline);
                }
            }
            (record.annotations || []).forEach(function (point) { annotationMarkers(point, false); });
            document.getElementById('modern-map-status').textContent = record.mask && record.mask.geographic_ring ?
                (record.mask.source === 'custom' ? 'Saved map mask' : 'Estimated footprint from the imported image boundary') : 'No geographic mask is available yet.';
        }
        function previewPoint(side, coordinates) {
            var current = ++generation;
            if (pending) { pending.abort(); }
            imageDraft.clearLayers(); modernDraft.clearLayers();
            draft = null; updateTools(); message('Estimating the matching position…');
            pending = $.ajax({url: root.dataset.apiBase + 'historical-map-estimate', dataType: 'json', timeout: 15000,
                data: {map_id: record.id, side: side, x: coordinates[0], y: coordinates[1]}})
                .done(function (response) {
                    if (current !== generation) { return; }
                    draft = {operation: 'annotation', side: side, coordinates: coordinates,
                        revision: response.revision, request_id: window.crypto.randomUUID ? window.crypto.randomUUID() :
                            Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)};
                    annotationMarkers(response.point, true);
                    var messages = {estimated: 'Estimated pair. Save this annotation or cancel. It will not change calibration.',
                        extrapolated: 'Outside the control-point coverage. This estimated position needs review before use.',
                        unavailable: 'No reliable counterpart was found. You can save the original point; manual pairing will be available in the calibration stage.'};
                    message(messages[response.point.status]); updateTools();
                }).fail(function (xhr, reason) {
                    if (current !== generation || reason === 'abort') { return; }
                    // Preserve the source click, even if the estimate request fails.
                    draft = {operation: 'annotation', side: side, coordinates: coordinates, revision: record.revision,
                        request_id: Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)};
                    annotationMarkers({image_x: side === 'image' ? coordinates[0] : null, image_y: side === 'image' ? coordinates[1] : null,
                        longitude: side === 'modern' ? coordinates[0] : null, latitude: side === 'modern' ? coordinates[1] : null, status: 'unavailable'}, true);
                    message(errorText(xhr)); updateTools();
                }).always(function () { if (current === generation) { pending = null; } });
        }
        historical.on('click', function (event) {
            if (mode !== 'image' || busy || hasDraft()) { return; }
            var coords = [event.latlng.lng, -event.latlng.lat];
            if (coords[0] < 0 || coords[1] < 0 || coords[0] > record.image_width || coords[1] > record.image_height) {
                message('Choose a position inside the original image.'); return;
            }
            previewPoint('image', coords);
        });
        modern.on('click', function (event) {
            if (busy) { return; }
            var position = event.latlng.wrap();
            if (Math.abs(position.lat) > 85) { return; }
            if (mode === 'mask' && !closed) {
                if (vertices.length >= 500) { message('A mask may contain up to 500 vertices. Close or undo the polygon.'); return; }
                vertices.push(position); drawMask();
            } else if (mode === 'modern' && !hasDraft()) { previewPoint('modern', [position.lng, position.lat]); }
        });
        undo.addEventListener('click', function () {
            if (busy) { return; }
            vertices.pop(); draft = null; closed = false; drawMask(); message('Continue drawing, then select the first vertex to close the mask.');
        });
        cancelButton.addEventListener('click', function () { if (!busy) { reset(); } });
        save.addEventListener('click', function () {
            if (!draft || busy) { return; }
            busy = true; updateTools(); message('Saving…');
            $.ajax({url: root.dataset.apiBase + 'historical-map-edit', method: 'POST', contentType: 'application/json', dataType: 'json', timeout: 20000,
                data: JSON.stringify(Object.assign({}, draft, {map_id: record.id, csrf_token: csrf}))})
                .done(function (response) {
                    busy = false; Object.assign(record, response.map); options.onSaved(record);
                    reset(); renderSaved(); message('Saved. Your changes are available on other devices.');
                    cancelButton.textContent = 'Done';
                }).fail(function (xhr) {
                    busy = false; message(errorText(xhr)); reload.hidden = xhr.status !== 409; updateTools();
                });
        });
        reload.addEventListener('click', function () {
            if (busy) { return; }
            busy = true; updateTools(); reload.disabled = true;
            $.ajax({url: root.dataset.catalogUrl, dataType: 'json', timeout: 15000})
                .done(function (response) {
                    var fresh = response.maps.filter(function (map) { return map.id === record.id; })[0];
                    if (!fresh) { message('This map is no longer available. Your draft has been kept.'); return; }
                    csrf = response.csrf_token; canEdit = response.can_edit;
                    Object.assign(record, fresh); options.onSaved(record); renderSaved();
                    if (draft.operation === 'annotation') { previewPoint(draft.side, draft.coordinates); }
                    else { draft.revision = fresh.revision; message('Latest data loaded. Review your mask, then choose Save or Cancel.'); }
                    reload.hidden = true;
                }).fail(function (xhr) { message(errorText(xhr)); })
                .always(function () { busy = false; reload.disabled = false; updateTools(); });
        });
        window.addEventListener('beforeunload', function (event) {
            if (hasDraft() || busy) { event.preventDefault(); event.returnValue = ''; }
        });
        updateTools();
        return {
            access: function (allowed, token) {
                canEdit = !!allowed; csrf = token;
                document.getElementById('map-editor-access').textContent = canEdit ? 'Add points or draw a mask. No sign-in required.' : 'Map editing is currently unavailable. Please reload the page.';
                updateTools();
            },
            canSwitch: function () {
                if (hasDraft() || busy) { message('Save or cancel the current draft before switching maps.'); return false; }
                return true;
            },
            load: function (map) { reset(); record = map; renderSaved(); updateTools(); }
        };
    };
}(window, jQuery));
