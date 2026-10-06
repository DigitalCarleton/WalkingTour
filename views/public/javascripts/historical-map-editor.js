/* global jQuery */
(function (window, $) {
    'use strict';
    window.WalkingTourMapEditor = function (options) {
        var L = window.L, root = options.root, historical = options.historical, modern = options.modern;
        var record, canEdit = false, csrf, mode = null, draft = null, busy = false, pending = null, generation = 0;
        var selected = null, draftPoint = null, draftMarkers = [], savedPairs = {};
        var vertices = [], closed = false, restoreDoubleClick = false;
        var imageSaved = L.layerGroup().addTo(historical), modernSaved = L.layerGroup().addTo(modern);
        var imageDraft = L.layerGroup().addTo(historical), modernDraft = L.layerGroup().addTo(modern);
        var footprint = L.layerGroup().addTo(modern), imageOutline = L.layerGroup().addTo(historical);
        var panel = document.getElementById('map-edit-panel'), status = document.getElementById('map-edit-status');
        var save = document.getElementById('map-edit-save'), undo = document.getElementById('map-edit-undo');
        var confirm = document.getElementById('map-edit-confirm'), adjust = document.getElementById('map-edit-adjust');
        var calibrationUndo = document.getElementById('map-calibration-undo');
        var reload = document.getElementById('map-edit-reload'), cancelButton = document.getElementById('map-edit-cancel');
        var selection = document.getElementById('control-point-selection'), tools = [];
        var adjustments = document.getElementById('map-point-adjustments');
        var applyCoordinates = document.getElementById('map-point-apply');
        var fields = ['image_x', 'image_y', 'longitude', 'latitude'];
        var inputs = ['map-point-x', 'map-point-y', 'map-point-longitude', 'map-point-latitude'].map(function (id) { return document.getElementById(id); });
        function pixel(x, y) { return L.latLng(-y, x); }
        function requestId() {
            return window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() :
                Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
        }
        function message(text) { panel.hidden = false; status.textContent = text; }
        function errorText(xhr) {
            var data = xhr.responseJSON;
            if (!data) { try { data = JSON.parse(xhr.responseText); } catch (_) { /* Use safe fallback. */ } }
            return data && data.error || 'The request failed. Your draft has been kept. Please retry.';
        }
        function hasDraft() { return !!draft || vertices.length > 0 || !!pending; }
        function completePair() {
            return draftPoint && draftPoint.image_x !== null && draftPoint.image_y !== null &&
                draftPoint.longitude !== null && draftPoint.latitude !== null;
        }
        function updateTools() {
            tools.forEach(function (tool) {
                tool.button.disabled = !canEdit || !record || busy;
                tool.button.setAttribute('aria-pressed', String(mode === tool.mode));
                tool.button.title = canEdit ? tool.label : 'Sign in with an editor account to edit maps';
            });
            cancelButton.disabled = busy;
            undo.disabled = busy || !vertices.length;
            save.hidden = !draft || draft.operation === 'confirm';
            save.disabled = !canEdit || busy || !draft || (mode === 'mask' && !closed);
            save.textContent = draft && draft.operation === 'delete' ? 'Retry deletion' : 'Save';
            confirm.hidden = !draftPoint;
            confirm.disabled = !canEdit || busy || !completePair();
            confirm.textContent = draft && draft.point_type === 'control' ? 'Save calibration pair' : 'Confirm pair';
            adjust.hidden = !selected || !!draft || !canEdit || mode === 'delete';
            adjust.disabled = busy;
            adjustments.hidden = !draftPoint;
            applyCoordinates.disabled = busy || !canEdit;
            inputs.forEach(function (input, index) {
                input.disabled = busy || !canEdit;
                input.value = draftPoint && draftPoint[fields[index]] !== null ? draftPoint[fields[index]] : '';
            });
            if (record) { inputs[0].max = record.image_width; inputs[1].max = record.image_height; }
            calibrationUndo.hidden = !canEdit || !record || !record.undo_calibration;
            calibrationUndo.disabled = busy || hasDraft();
            draftMarkers.forEach(function (marker) {
                if (!marker.dragging) { return; }
                if (busy || !canEdit) { marker.dragging.disable(); } else { marker.dragging.enable(); }
            });
            root.classList.toggle('is-drawing-mask', mode === 'mask');
            root.classList.toggle('is-deleting-points', mode === 'delete');
        }
        function reset() {
            generation++;
            if (pending) { pending.abort(); pending = null; }
            if (restoreDoubleClick) { modern.doubleClickZoom.enable(); restoreDoubleClick = false; }
            mode = null; draft = null; selected = null; draftPoint = null; draftMarkers = [];
            vertices = []; closed = false;
            cancelButton.textContent = 'Cancel';
            imageDraft.clearLayers(); modernDraft.clearLayers(); panel.hidden = true;
            undo.hidden = true; reload.hidden = true;
            root.querySelectorAll('.is-selected').forEach(function (el) { el.classList.remove('is-selected'); el.setAttribute('aria-pressed', 'false'); });
            updateTools();
        }
        function begin(next) {
            if (busy || !canEdit || !record) { return; }
            if (hasDraft()) { message('Save or cancel the current draft before starting another edit.'); return; }
            var sameMode = mode === next;
            reset(); renderSaved();
            if (sameMode) { return; }
            mode = next;
            if (next === 'mask') {
                restoreDoubleClick = modern.doubleClickZoom.enabled(); modern.doubleClickZoom.disable();
                undo.hidden = false;
                message('Click to draw a mask. Add at least 3 vertices, then select the first vertex to close the polygon.');
            } else if (next === 'delete') {
                message('Delete mode: select an annotation or control point on either map to delete its pair. Select the trash tool again or Cancel to exit.');
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
        var trash = '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 6h18M8 6V3h8v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg>';
        addTool(historical, 'image', 'Add an image annotation', pencil);
        addTool(modern, 'modern', 'Add a map annotation', pencil);
        addTool(historical, 'delete', 'Delete a point pair', trash);
        addTool(modern, 'delete', 'Delete a point pair', trash);
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
                    draft = {operation: 'mask', ring: ring, revision: record.revision, request_id: requestId()};
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
        function labelFor(point, type) { return type === 'control' ? String(point.ordinal) : 'A' + point.id; }
        function selectPoint(point, type, pair) {
            if (busy) { return; }
            if (hasDraft()) { message('Save or cancel the current draft before selecting another point.'); return; }
            if (mode === 'delete') {
                draft = {operation: 'delete', point_type: type, point_id: point.id, revision: record.revision, request_id: requestId()};
                submit(draft, 'Point pair deleted.'); return;
            }
            if (mode) { reset(); }
            selected = {point: point, type: type};
            root.querySelectorAll('.is-selected').forEach(function (el) { el.classList.remove('is-selected'); el.setAttribute('aria-pressed', 'false'); });
            pair.forEach(function (p) {
                p.map.panTo(p.marker.getLatLng());
                var el = p.marker.getElement();
                if (el) { el.classList.add('is-selected'); el.setAttribute('aria-pressed', 'true'); }
            });
            var label = type === 'control' ? 'Control point ' + point.ordinal : labelFor(point, type);
            selection.textContent = type === 'control' ? label + ' selected on both maps.' : label + ': ' + point.status + ' position. Not a calibration point.';
            if (canEdit) { message(label + ' selected. Choose Adjust pair to refine its positions' + (type === 'control' ? '.' : ' and confirm it as a control point.')); }
            root.dispatchEvent(new CustomEvent(type === 'control' ? 'walkingtour:control-point-selected' : 'walkingtour:annotation-selected',
                {bubbles: true, detail: {mapId: record.id, annotation: Object.assign({}, point), point: Object.assign({}, point)}}));
            updateTools();
        }
        function pointMarkers(point, type, isDraft) {
            var pair = [], label = isDraft && !point.id ? 'New' : labelFor(point, type);
            var control = type === 'control';
            [[historical, imageSaved, imageDraft, point.image_x === null ? null : pixel(point.image_x, point.image_y), 'image'],
                [modern, modernSaved, modernDraft, point.longitude === null ? null : L.latLng(point.latitude, point.longitude), 'modern']]
                .forEach(function (entry) {
                    if (!entry[3]) { return; }
                    var title = control ? 'Control point ' + label : label + ' — ' + point.status;
                    var marker = L.marker(entry[3], {title: title, keyboard: true, riseOnHover: true, draggable: isDraft && canEdit, bubblingMouseEvents: false,
                        icon: L.divIcon({className: (control ? 'control-point-marker' : 'annotation-marker') + (isDraft ? ' is-draft is-selected' : ''),
                            html: label, iconSize: control ? [30, 30] : [34, 28], iconAnchor: control ? [15, 15] : [17, 14]})});
                    marker.on('add', function () {
                        marker.getElement().setAttribute('role', 'button');
                        marker.getElement().setAttribute('aria-label', title);
                        marker.getElement().setAttribute('aria-pressed', 'false');
                    });
                    marker.addTo(isDraft ? entry[2] : entry[1]); pair.push({map: entry[0], marker: marker});
                    marker.on('click', function (event) {
                        if (event.originalEvent) { L.DomEvent.stopPropagation(event.originalEvent); }
                        if (!isDraft) { selectPoint(point, type, pair); }
                    });
                    if (isDraft) {
                        draftMarkers.push(marker);
                        marker.on('dragend', function () {
                            var position = marker.getLatLng();
                            if (!setEndpoint(entry[4], entry[4] === 'image' ? [position.lng, -position.lat] : [position.wrap().lng, position.lat])) {
                                marker.setLatLng(entry[4] === 'image' ? pixel(point.image_x, point.image_y) : [point.latitude, point.longitude]);
                            }
                        });
                    }
                });
            if (!isDraft) { savedPairs[type + ':' + point.id] = pair; }
        }
        function renderDraft() {
            imageDraft.clearLayers(); modernDraft.clearLayers(); draftMarkers = [];
            if (draftPoint) { pointMarkers(draftPoint, draft.point_type === 'control' ? 'control' : 'annotation', true); }
            updateTools();
        }
        function pairPayload() {
            draft.image = draftPoint.image_x === null ? null : [draftPoint.image_x, draftPoint.image_y];
            draft.geographic = draftPoint.longitude === null ? null : [draftPoint.longitude, draftPoint.latitude];
        }
        function setEndpoint(side, coords) {
            if (busy || !draftPoint || !canEdit) { return false; }
            if (!coords.every(Number.isFinite)) { message('Enter two finite coordinates for this endpoint.'); return false; }
            if (side === 'image' && (coords[0] < 0 || coords[1] < 0 || coords[0] > record.image_width || coords[1] > record.image_height)) {
                message('Choose a position inside the original image.'); return false;
            }
            if (side === 'modern' && (Math.abs(coords[0]) > 180 || Math.abs(coords[1]) > 85)) {
                message('Choose a position inside the supported geographic range.'); return false;
            }
            if (side === 'image') { draftPoint.image_x = coords[0]; draftPoint.image_y = coords[1]; }
            else { draftPoint.longitude = coords[0]; draftPoint.latitude = coords[1]; }
            draft.operation = 'confirm'; draft.point_type = draft.point_type || 'new'; draft.request_id = requestId();
            draftPoint.status = 'pending confirmation'; pairPayload();
            renderDraft();
            message('Position adjusted. The other endpoint is fixed. Confirm the pair to update calibration, or Cancel to discard the edit.');
            return true;
        }
        function readCoordinates() {
            if (!draftPoint || busy || !canEdit) { return false; }
            var values = inputs.map(function (input) { return input.value === '' ? null : Number(input.value); });
            if (values.some(function (value) { return value !== null && !Number.isFinite(value); }) ||
                (values[0] === null) !== (values[1] === null) || (values[2] === null) !== (values[3] === null)) {
                message('Enter both coordinates for each endpoint.'); return false;
            }
            if (values[0] !== null && (values[0] < 0 || values[1] < 0 || values[0] > record.image_width || values[1] > record.image_height)) {
                message('Choose a position inside the original image.'); return false;
            }
            if (values[2] !== null && (Math.abs(values[2]) > 180 || Math.abs(values[3]) > 85)) {
                message('Choose a position inside the supported geographic range.'); return false;
            }
            if (values.some(function (value, index) { return value !== draftPoint[fields[index]]; })) {
                fields.forEach(function (field, index) { draftPoint[field] = values[index]; });
                draft.operation = 'confirm'; draft.point_type = draft.point_type || 'new'; draft.request_id = requestId();
                draftPoint.status = 'pending confirmation'; pairPayload(); renderDraft();
                message('Coordinates applied. Confirm the pair to update calibration, or Cancel to discard the edit.');
            }
            return true;
        }
        applyCoordinates.addEventListener('click', readCoordinates);
        function renderSaved() {
            imageSaved.clearLayers(); modernSaved.clearLayers(); footprint.clearLayers(); imageOutline.clearLayers();
            savedPairs = {};
            if (!record) { return; }
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
            function visible(point, type) { return !draft || draft.point_type !== type || draft.point_id !== point.id || draft.operation === 'delete'; }
            (record.control_points || []).forEach(function (point) { if (visible(point, 'control')) { pointMarkers(point, 'control', false); } });
            (record.annotations || []).forEach(function (point) { if (visible(point, 'annotation')) { pointMarkers(point, 'annotation', false); } });
            document.getElementById('modern-map-status').textContent = record.mask && record.mask.geographic_ring ?
                (record.mask.source === 'custom' ? 'Saved map mask' : 'Estimated footprint from the imported image boundary') : 'No geographic mask is available yet.';
        }
        function previewPoint(side, coordinates) {
            var current = ++generation;
            if (pending) { pending.abort(); }
            imageDraft.clearLayers(); modernDraft.clearLayers();
            draft = null; draftPoint = null; updateTools(); message('Estimating the matching position…');
            function makeDraft(point, revision) {
                draft = {operation: 'annotation', side: side, coordinates: coordinates, revision: revision, request_id: requestId()};
                draftPoint = point; renderDraft();
            }
            pending = $.ajax({url: root.dataset.apiBase + 'historical-map-estimate', dataType: 'json', timeout: 15000,
                data: {map_id: record.id, side: side, x: coordinates[0], y: coordinates[1]}})
                .done(function (response) {
                    if (current !== generation) { return; }
                    // A newer estimate must not authorize a stale editor state.
                    makeDraft(response.point, record.revision);
                    var messages = {estimated: 'Estimated pair. Save as an annotation, or review both positions and Confirm pair to update calibration.',
                        extrapolated: 'Outside the control-point coverage. Review or adjust both positions before confirming.',
                        unavailable: 'No reliable counterpart was found. Click the other map to supply its position, or save only the original point.'};
                    message(messages[response.point.status]); updateTools();
                }).fail(function (xhr, reason) {
                    if (current !== generation || reason === 'abort') { return; }
                    makeDraft({image_x: side === 'image' ? coordinates[0] : null, image_y: side === 'image' ? coordinates[1] : null,
                        longitude: side === 'modern' ? coordinates[0] : null, latitude: side === 'modern' ? coordinates[1] : null, status: 'unavailable'}, record.revision);
                    message(errorText(xhr)); updateTools();
                }).always(function () { if (current === generation) { pending = null; updateTools(); } });
        }
        historical.on('click', function (event) {
            if (busy || !canEdit || !record) { return; }
            var coords = [event.latlng.lng, -event.latlng.lat];
            if (draftPoint && draftPoint.image_x === null) { setEndpoint('image', coords); return; }
            if (mode !== 'image' || hasDraft()) { return; }
            if (coords[0] < 0 || coords[1] < 0 || coords[0] > record.image_width || coords[1] > record.image_height) {
                message('Choose a position inside the original image.'); return;
            }
            previewPoint('image', coords);
        });
        modern.on('click', function (event) {
            if (busy || !canEdit || !record) { return; }
            var position = event.latlng.wrap();
            if (Math.abs(position.lat) > 85) { return; }
            if (draftPoint && draftPoint.longitude === null) { setEndpoint('modern', [position.lng, position.lat]); return; }
            if (mode === 'mask' && !closed) {
                if (vertices.length >= 500) { message('A mask may contain up to 500 vertices. Close or undo the polygon.'); return; }
                vertices.push(position); drawMask();
            } else if (mode === 'modern' && !hasDraft()) { previewPoint('modern', [position.lng, position.lat]); }
        });
        adjust.addEventListener('click', function () {
            if (!selected || busy || !canEdit) { return; }
            cancelButton.textContent = 'Cancel';
            draftPoint = Object.assign({}, selected.point);
            draft = {operation: 'confirm', point_type: selected.type, point_id: draftPoint.id, revision: record.revision, request_id: requestId()};
            mode = 'adjust'; pairPayload(); renderSaved(); renderDraft();
            message('Drag either endpoint to adjust it; the other stays fixed. If an endpoint is missing, click its map to add it. Confirm to save the calibration pair.');
        });
        undo.addEventListener('click', function () {
            if (busy) { return; }
            vertices.pop(); draft = null; closed = false; drawMask(); message('Continue drawing, then select the first vertex to close the mask.');
        });
        cancelButton.addEventListener('click', function () { if (!busy) { reset(); renderSaved(); } });
        function submit(payload, successMessage) {
            if (busy || !canEdit) { return; }
            var deleteMode = mode === 'delete';
            busy = true; updateTools(); message('Saving…');
            $.ajax({url: root.dataset.apiBase + 'historical-map-edit', method: 'POST', contentType: 'application/json', dataType: 'json', timeout: 20000,
                data: JSON.stringify(Object.assign({}, payload, {map_id: record.id, csrf_token: csrf}))})
                .done(function (response) {
                    var changedCalibration = record.calibration_revision !== response.map.calibration_revision;
                    busy = false; Object.assign(record, response.map); options.onSaved(record);
                    reset(); renderSaved();
                    selection.textContent = 'Select a numbered point to locate its pair.';
                    message(successMessage || 'Saved. Your changes are available on other devices.');
                    if (deleteMode) { mode = 'delete'; message('Point pair deleted. Select another point to delete, or Cancel to exit delete mode.'); }
                    else { cancelButton.textContent = 'Done'; }
                    updateTools();
                    if (changedCalibration) {
                        root.dispatchEvent(new CustomEvent('walkingtour:calibration-updated', {bubbles: true,
                            detail: {mapId: record.id, calibrationRevision: record.calibration_revision}}));
                    }
                }).fail(function (xhr) {
                    busy = false; message(errorText(xhr)); reload.hidden = xhr.status !== 409; updateTools();
                });
        }
        save.addEventListener('click', function () {
            if (draftPoint && !readCoordinates()) { return; }
            if (draft && draft.operation !== 'confirm') { submit(draft); }
        });
        confirm.addEventListener('click', function () {
            if (!draft || !readCoordinates() || !completePair() || busy) { return; }
            draft.operation = 'confirm'; draft.point_type = draft.point_type || 'new'; pairPayload(); updateTools();
            submit(draft, 'Calibration saved. Confirmed pairs stay fixed; estimated counterparts have been updated.');
        });
        calibrationUndo.addEventListener('click', function () {
            if (!record || !record.undo_calibration || hasDraft() || busy || !canEdit) { return; }
            reset();
            draft = {operation: 'undo_calibration', change_id: record.undo_calibration.change_id, revision: record.revision, request_id: requestId()};
            submit(draft, 'Latest calibration change undone. Estimated counterparts have been updated.');
        });
        reload.addEventListener('click', function () {
            if (busy || !draft) { return; }
            busy = true; updateTools(); reload.disabled = true;
            $.ajax({url: root.dataset.catalogUrl, dataType: 'json', timeout: 15000})
                .done(function (response) {
                    var fresh = response.maps.filter(function (map) { return map.id === record.id; })[0];
                    if (!fresh) { message('This map is no longer available. Your draft has been kept.'); return; }
                    csrf = response.csrf_token; canEdit = response.can_edit;
                    Object.assign(record, fresh); options.onSaved(record); renderSaved();
                    // Preserve manually adjusted endpoints instead of replacing them with new estimates.
                    if (draft.operation === 'annotation') { previewPoint(draft.side, draft.coordinates); }
                    else { draft.revision = fresh.revision; message('Latest data loaded. Your draft has been kept. Review it before saving again.'); }
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
                document.getElementById('map-editor-access').textContent = canEdit ? 'Editor tools enabled.' : 'Sign in with an editor account to add points or draw a mask.';
                updateTools();
            },
            canSwitch: function () {
                if (hasDraft() || busy) { message('Save or cancel the current draft before switching maps.'); return false; }
                return true;
            },
            load: function (map) { reset(); record = map; renderSaved(); updateTools(); },
            focus: function (type, id) {
                if (!record || !savedPairs[type + ':' + id]) { return false; }
                var points = type === 'control' ? record.control_points : record.annotations;
                var point = points.filter(function (entry) { return entry.id === id; })[0];
                if (!point) { return false; }
                selectPoint(point, type, savedPairs[type + ':' + id]);
                return true;
            }
        };
    };
}(window, jQuery));
