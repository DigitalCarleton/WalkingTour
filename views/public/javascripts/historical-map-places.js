/* global jQuery */
(function (window, $) {
    'use strict';
    window.WalkingTourHistoricalPlaces = function (root, historical, modern) {
        var L = window.L, generation = 0, request, selected, pairs = {}, record;
        var modernLayer = L.layerGroup().addTo(modern), historicalLayer = L.layerGroup().addTo(historical);
        var status = document.getElementById('modern-map-status');
        var picker = document.getElementById('historical-place-select');
        var panel = document.getElementById('historical-place-details');
        var base = root.dataset.apiBase.replace(/\/?$/, '/');
        function marker(position, place, estimated) {
            var icon = L.divIcon({className: 'historical-place-marker' + (estimated ? ' is-estimated' : ''), html: '●', iconSize: [24, 24], iconAnchor: [12, 12]});
            var m = L.marker(position, {icon: icon, title: place.title, keyboard: true});
            var label = document.createElement('span'); label.textContent = place.title; m.bindPopup(label);
            m.on('click', function () { select(place.item_id); });
            return m;
        }
        function locate() {
            var pair = pairs[selected]; if (!pair) { return; }
            modern.setView(pair.modern.getLatLng(), Math.max(modern.getZoom(), 16)); pair.modern.openPopup();
            if (pair.historical) { historical.setView(pair.historical.getLatLng(), Math.max(historical.getZoom(), -1)); pair.historical.openPopup(); }
        }
        function select(id) {
            var pair = pairs[id]; if (!pair) { return; }
            selected = id; picker.value = String(id); panel.hidden = false;
            document.getElementById('historical-place-title').textContent = pair.place.title;
            document.getElementById('historical-place-description').textContent = pair.place.description;
            document.getElementById('historical-place-link').href = pair.place.url;
            document.getElementById('historical-place-status').textContent = pair.historical ? 'Estimated historical position. Compare with the original image.' : 'No reliable historical counterpart is available within this map. The modern location is shown.';
            Object.keys(pairs).forEach(function (key) { [pairs[key].modern, pairs[key].historical].forEach(function (m) { if (m && m.getElement()) { m.getElement().classList.toggle('is-selected', key === String(id)); } }); });
            locate();
        }
        picker.addEventListener('change', function () { if (this.value) { select(Number(this.value)); } });
        document.getElementById('historical-place-locate').addEventListener('click', locate);
        function load(map) {
            var current = ++generation; record = map; selected = null; pairs = {};
            if (request) { request.abort(); }
            modernLayer.clearLayers(); historicalLayer.clearLayers(); panel.hidden = true;
            picker.textContent = ''; var empty = document.createElement('option'); empty.value = ''; empty.textContent = 'Choose a place'; picker.appendChild(empty);
            status.textContent = 'Loading public Omeka places…';
            var transform, error = '', count = 0, paired = 0;
            try { transform = window.WalkingTourAllmaps.create(map); } catch (e) { error = e.message; }
            function page(after) {
                request = $.ajax({url: base + 'historical-map-places', data: {after: after}, dataType: 'json', timeout: 20000});
                request.done(function (data) {
                    if (current !== generation) { return; }
                    var offset = 0;
                    function batch() {
                        if (current !== generation) { return; }
                        var end = Math.min(offset + 30, data.places.length);
                        for (; offset < end; offset++) {
                            var place = data.places[offset], pixel = transform ? transform.estimate(place.coordinates) : null;
                            var m = marker([place.coordinates[1], place.coordinates[0]], place, false).addTo(modernLayer);
                            var h = pixel ? marker([-pixel[1], pixel[0]], place, true).addTo(historicalLayer) : null;
                            pairs[place.item_id] = {place: place, modern: m, historical: h}; count++; if (h) { paired++; }
                            var option = document.createElement('option'); option.value = place.item_id; option.textContent = place.title; picker.appendChild(option);
                        }
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
            page(0);
        }
        return {load: load};
    };
}(window, jQuery));
