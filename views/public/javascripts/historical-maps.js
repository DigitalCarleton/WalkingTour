/* global jQuery */
(function (window, $) {
    'use strict';

    // Resolve Leaflet after all host-plugin scripts have loaded. Geolocation may
    // enqueue its own Leaflet asset; both viewers must use the same instance.
    $(function () {
        var L = window.L;

        // CRS.Simple uses x east and y north; image pixels use x right and y down.
        function imageLatLng(x, y) { return L.latLng(-y, x); }

        // Request original IIIF regions, never the Allmaps warped geographic tiles.
        var OriginalImageTiles = L.TileLayer.extend({
            getTileUrl: function (coords) {
                var scale = Math.pow(2, -coords.z);
                var span = this.options.tileSize * scale;
                var x = Math.round(coords.x * span);
                var y = Math.round(coords.y * span);
                var width = Math.min(span, this.options.imageWidth - x);
                var height = Math.min(span, this.options.imageHeight - y);
                return this._url + '/' + [x, y, width, height].join(',') + '/' +
                    Math.ceil(width / scale) + ',/0/' + (this.options.imageQuality === 'native' ? 'native' : 'default') + '.jpg';
            },
            _initTile: function (tile) {
                L.TileLayer.prototype._initTile.call(this, tile);
                // Edge regions are smaller than a full tile; do not stretch them.
                tile.style.width = 'auto';
                tile.style.height = 'auto';
            }
        });

        function start(modernMap) {
            var root = document.getElementById('dual-map');
            if (!root) { return; }
            var historicalMap = L.map('historical-map', {
                crs: L.CRS.Simple, minZoom: -7, maxZoom: 1, zoomSnap: .1, zoomDelta: .5, attributionControl: true
            });
            historicalMap.setView([0, 0], -5);
            var imageLayer;
            var activeMap;
            var imageBounds;
            var geographicBounds;
            var status = document.getElementById('historical-map-status');
            var retry = document.getElementById('historical-map-retry');
            var loadGeneration = 0;
            var editor = window.WalkingTourMapEditor({root: root, historical: historicalMap, modern: modernMap,
                onSaved: function (record) {
                    activeMap = record; updateGeographicBounds(record);
                    if (!status.classList.contains('is-error')) {
                        setStatus('Original image · ' + record.control_points.length + ' shared control points', false);
                    }
                }});
            window.WalkingTourPlaceSearch(modernMap, root.dataset.searchEndpoint);

            function updateGeographicBounds(record) {
                geographicBounds = L.latLngBounds();
                if (record.mask && record.mask.geographic_ring) {
                    record.mask.geographic_ring.forEach(function (p) { geographicBounds.extend([p[1], p[0]]); });
                }
                record.control_points.forEach(function (p) { geographicBounds.extend([p.latitude, p.longitude]); });
                document.getElementById('control-points-fit').disabled = !geographicBounds.isValid();
            }

            function setStatus(message, error) {
                status.textContent = message;
                status.classList.toggle('is-error', !!error);
                retry.hidden = !error;
            }

            function resize() {
                historicalMap.invalidateSize({pan: false});
                modernMap.invalidateSize({pan: false});
            }
            if (window.ResizeObserver) {
                var observer = new ResizeObserver(resize);
                observer.observe(document.getElementById('historical-map'));
                observer.observe(document.getElementById('map'));
            } else {
                window.addEventListener('resize', resize);
            }

            $('#map-list-toggle').on('click', function () {
                var collapsed = root.classList.toggle('catalog-collapsed');
                document.getElementById('historical-map-catalog').hidden = collapsed;
                this.setAttribute('aria-expanded', String(!collapsed));
                this.textContent = collapsed ? 'Show map list' : 'Hide map list';
                resize();
            });
            $('#historical-map-fit').on('click', function () {
                if (imageBounds) { historicalMap.fitBounds(imageBounds, {padding: [20, 20]}); }
            });
            $('#control-points-fit').on('click', function () {
                if (geographicBounds && geographicBounds.isValid()) {
                    modernMap.fitBounds(geographicBounds, {padding: [40, 40]});
                }
            });

            function sourceLink(container, label, url) {
                // Catalog data is text. Only expose HTTP(S) links, never raw HTML.
                if (!/^https?:\/\//i.test(url)) { return; }
                var link = document.createElement('a');
                link.textContent = label;
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                container.appendChild(link);
            }

            function showMap(record) {
                if (!editor.canSwitch()) { return; }
                var generation = ++loadGeneration;
                activeMap = record;
                if (imageLayer) { historicalMap.removeLayer(imageLayer); }
                imageBounds = L.latLngBounds(imageLatLng(0, record.image_height), imageLatLng(record.image_width, 0));
                geographicBounds = L.latLngBounds();
                var size = historicalMap.getSize();
                var minimumZoom = Math.min(-7, Math.floor(Math.log(Math.max(1, Math.min(size.x, size.y) - 40) /
                    Math.max(record.image_width, record.image_height)) / Math.LN2) - 1);
                historicalMap.setMinZoom(minimumZoom);
                historicalMap.setMaxBounds(imageBounds.pad(.2));
                historicalMap.fitBounds(imageBounds, {padding: [20, 20]});
                setStatus('Loading the original historical image…', false);
                var failedTiles = new Set();
                var attribution = document.createElement('a');
                attribution.textContent = record.title;
                attribution.href = /^https?:\/\//i.test(record.source_url) ? record.source_url : record.manifest_url;
                attribution.target = '_blank'; attribution.rel = 'noopener noreferrer';
                imageLayer = new OriginalImageTiles(record.image_service, {
                    imageWidth: record.image_width, imageHeight: record.image_height,
                    imageQuality: record.image_quality,
                    tileSize: 256, minZoom: minimumZoom, maxZoom: 1, maxNativeZoom: 0,
                    bounds: imageBounds, noWrap: true,
                    attribution: 'Image: ' + attribution.outerHTML
                });
                imageLayer.on('tileerror', function (event) {
                    if (generation !== loadGeneration) { return; }
                    failedTiles.add(event.tile);
                    setStatus('Some historical image tiles could not load. The modern map remains available.', true);
                });
                imageLayer.on('tileload tileunload', function (event) { failedTiles.delete(event.tile); });
                imageLayer.on('load', function () {
                    if (generation !== loadGeneration || failedTiles.size) { return; }
                    setStatus('Original image · ' + record.control_points.length + ' shared control points', false);
                });
                imageLayer.addTo(historicalMap);

                updateGeographicBounds(record);
                editor.load(record);
                document.getElementById('historical-map-fit').disabled = false;
                document.getElementById('control-points-fit').disabled = !geographicBounds.isValid();
                document.getElementById('historical-map-heading').title = record.title;
                document.getElementById('control-point-selection').textContent = 'Select a numbered point to locate its pair.';
                var sources = document.getElementById('historical-map-sources');
                sources.textContent = '';
                sourceLink(sources, 'Image source (IIIF)', record.manifest_url);
                sourceLink(sources, 'Provenance source', record.source_url);
                $('#historical-map-list button').each(function () {
                    this.setAttribute('aria-pressed', String(this.dataset.mapId === String(record.id)));
                });
                // Start at the seeded map's area; tour auto-fit may subsequently show the selected route.
                if (geographicBounds.isValid()) {
                    modernMap.fitBounds(geographicBounds, {padding: [40, 40]});
                }
            }

            function loadCatalog() {
                setStatus('Loading historical maps…', false);
                $.ajax({url: root.dataset.catalogUrl, dataType: 'json', timeout: 15000})
                    .done(function (response) {
                        editor.access(response.can_edit, response.csrf_token);
                        var list = document.getElementById('historical-map-list');
                        list.textContent = '';
                        if (!response.maps || !response.maps.length) {
                            setStatus('No historical maps are available yet.', false);
                            return;
                        }
                        response.maps.forEach(function (record) {
                            var button = document.createElement('button');
                            button.type = 'button';
                            button.dataset.mapId = record.id;
                            button.textContent = record.title;
                            button.addEventListener('click', function () { showMap(record); });
                            list.appendChild(button);
                        });
                        var requestedMap = new URLSearchParams(window.location.search).get('map_id');
                        var selectedMap = response.maps.filter(function (record) { return String(record.id) === requestedMap; })[0];
                        showMap(selectedMap || response.maps[0]);
                        var parameters = new URLSearchParams(window.location.search);
                        var pointType = parameters.get('point_type'), pointId = Number(parameters.get('point_id'));
                        if (selectedMap && (pointType === 'control' || pointType === 'annotation') && Number.isInteger(pointId) && pointId > 0) {
                            if (!editor.focus(pointType, pointId)) {
                                document.getElementById('control-point-selection').textContent = 'The linked point is no longer available on this map.';
                            }
                        }
                    }).fail(function () {
                        setStatus('Historical maps could not be loaded. Please retry or contact the site administrator.', true);
                    });
            }
            retry.addEventListener('click', function () {
                if (activeMap) { showMap(activeMap); } else { loadCatalog(); }
            });
            loadCatalog();
        }

        window.WalkingTourHistoricalMaps = {start: start};
    });
}(window, jQuery));
