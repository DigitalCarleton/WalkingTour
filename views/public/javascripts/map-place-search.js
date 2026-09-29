/* global jQuery */
(function (window, $) {
    'use strict';
    window.WalkingTourPlaceSearch = function (map, endpoint) {
        var L = window.L;
        var control = L.control({position: 'topright'});
        control.onAdd = function () {
            var box = L.DomUtil.create('div', 'map-place-search is-collapsed');
            box.innerHTML = '<button type="button" class="search-toggle" aria-label="Open place search" title="Search places" aria-expanded="false" aria-controls="map-place-panel">' +
                '<svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/></svg></button>' +
                '<div id="map-place-panel" hidden><form role="search"><label class="screen-reader-text" for="map-place-query">Search places</label>' +
                '<input id="map-place-query" type="search" placeholder="Search places…" autocomplete="off" maxlength="160" aria-controls="map-place-results">' +
                '<button type="submit" aria-label="Search places" title="Search places">⌕</button>' +
                '<button type="button" class="close-search" aria-label="Close place search" title="Close place search">×</button></form>' +
                '<div id="map-place-results" class="map-place-results" hidden></div>' +
                '<p class="search-feedback" role="status" aria-live="polite" hidden></p>' +
                '<button type="button" class="clear-search">Clear search</button>' +
                '<a class="search-attribution" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">Search: Photon / © OpenStreetMap</a></div>';
            L.DomEvent.disableClickPropagation(box);
            L.DomEvent.disableScrollPropagation(box);
            var input = box.querySelector('input');
            var toggle = box.querySelector('.search-toggle');
            var panel = box.querySelector('#map-place-panel');
            var results = box.querySelector('.map-place-results');
            var feedback = box.querySelector('.search-feedback');
            var timer, request, generation = 0, selectedMarker;
            var cache = new Map();
            function message(text) { feedback.textContent = text; feedback.hidden = !text; }
            function cancel() {
                clearTimeout(timer); generation++;
                if (request) { request.abort(); request = null; }
            }
            function collapse() {
                cancel(); results.hidden = true; message('');
                panel.hidden = true; toggle.hidden = false;
                box.classList.add('is-collapsed');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
            toggle.addEventListener('click', function () {
                panel.hidden = false; toggle.hidden = true;
                box.classList.remove('is-collapsed');
                toggle.setAttribute('aria-expanded', 'true');
                input.focus();
            });
            box.querySelector('.close-search').addEventListener('click', collapse);
            box.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !panel.hidden) {
                    event.preventDefault(); event.stopPropagation(); collapse();
                }
            });
            function render(features) {
                results.textContent = '';
                features.forEach(function (feature) {
                    var coords = feature.geometry && feature.geometry.coordinates;
                    if (!coords || !Number.isFinite(coords[0]) || !Number.isFinite(coords[1]) || Math.abs(coords[0]) > 180 || Math.abs(coords[1]) > 85) { return; }
                    var properties = feature.properties || {};
                    var parts = [properties.name, properties.street, properties.city, properties.state, properties.country]
                        .filter(function (part, i, all) { return typeof part === 'string' && part && all.indexOf(part) === i; });
                    var label = parts.join(', ') || coords.join(', ');
                    var button = document.createElement('button');
                    button.type = 'button'; button.textContent = label;
                    button.addEventListener('click', function () {
                        cancel();
                        input.value = label; results.hidden = true;
                        var position = [coords[1], coords[0]];
                        var bounds = properties.extent;
                        if (bounds && bounds.length === 4 && bounds.every(Number.isFinite) &&
                            bounds[0] <= bounds[2] && bounds[3] <= bounds[1] && Math.abs(bounds[0]) <= 180 && Math.abs(bounds[2]) <= 180 && Math.abs(bounds[1]) <= 85 && Math.abs(bounds[3]) <= 85) {
                            map.fitBounds([[bounds[3], bounds[0]], [bounds[1], bounds[2]]], {padding: [35, 35], maxZoom: 16});
                        } else { map.setView(position, Math.min(15, map.getMaxZoom())); }
                        if (selectedMarker) { map.removeLayer(selectedMarker); }
                        selectedMarker = L.circleMarker(position, {radius: 8, color: '#146eae', fillOpacity: .4}).addTo(map);
                        var text = document.createElement('span'); text.textContent = label;
                        selectedMarker.bindPopup(text);
                        map.attributionControl.addAttribution('Search: Photon / &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>');
                        message('Located ' + label + '.');
                        input.focus();
                    });
                    results.appendChild(button);
                });
                results.hidden = !results.children.length;
                message(results.children.length ? results.children.length + ' places found. Select a result.' : 'No places found. Try another name.');
            }
            function search() {
                var query = input.value.trim();
                if (query.length < 3) { message('Enter at least 3 characters.'); return; }
                var key = query.toLowerCase();
                if (cache.has(key)) { render(cache.get(key)); return; }
                var current = ++generation;
                message('Searching places…');
                request = $.ajax({url: endpoint, data: {q: query, lang: 'en', limit: 6}, dataType: 'json', timeout: 10000})
                    .done(function (data) {
                        if (current !== generation) { return; }
                        var features = Array.isArray(data.features) ? data.features.slice(0, 6) : [];
                        if (cache.size >= 40) { cache.delete(cache.keys().next().value); }
                        cache.set(key, features); render(features);
                    }).fail(function (_, reason) {
                        if (current !== generation || reason === 'abort') { return; }
                        message('Place search is unavailable. Try again shortly.');
                    });
            }
            input.addEventListener('input', function () {
                cancel(); results.hidden = true; message('');
                if (input.value.trim().length >= 3) { timer = setTimeout(search, 600); }
            });
            box.querySelector('form').addEventListener('submit', function (event) { event.preventDefault(); cancel(); search(); });
            box.querySelector('.clear-search').addEventListener('click', function () {
                cancel(); input.value = ''; results.hidden = true; message('');
                if (selectedMarker) { map.removeLayer(selectedMarker); selectedMarker = null; }
                input.focus();
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' && !results.hidden && results.firstChild) { event.preventDefault(); results.firstChild.focus(); }
            });
            results.addEventListener('keydown', function (event) {
                var buttons = Array.prototype.slice.call(results.children);
                var index = buttons.indexOf(document.activeElement);
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    var next = index + (event.key === 'ArrowDown' ? 1 : -1);
                    if (next < 0) { input.focus(); } else if (next < buttons.length) { buttons[next].focus(); }
                }
            });
            return box;
        };
        control.addTo(map);
    };
}(window, jQuery));
