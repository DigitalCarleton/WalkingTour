/* global jQuery, L, WalkingTourHistoricalMaps */
(function ($) {
    'use strict';
    $(function () {
        var root = document.getElementById('dual-map');
        if (!root) { return; }
        var apiBase = root.dataset.apiBase.replace(/\/?$/, '/');
        $.post(apiBase + 'map-config').done(function (config) {
            var center = String(config.walking_tour_center || '41.895, 12.48').split(',').map(Number);
            if (center.length !== 2 || !center.every(Number.isFinite)) { center = [41.895, 12.48]; }
            var zoom = Number(config.walking_tour_default_zoom) || 13;
            var minZoom = Number(config.walking_tour_min_zoom) || 2;
            var maxZoom = Number(config.walking_tour_max_zoom) || 19;
            if (maxZoom < zoom) { maxZoom = Math.max(19, zoom); }
            if (minZoom > zoom) { minZoom = Math.min(2, zoom); }
            var map = L.map('map', {
                center: center,
                zoom: zoom, minZoom: minZoom, maxZoom: maxZoom
            });
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri', maxNativeZoom: 18, maxZoom: maxZoom
            }).addTo(map);
            var ExtentControl = L.Control.extend({
                options: {position: 'topleft'},
                onAdd: function () {
                    var button = L.DomUtil.create('a', 'extentControl leaflet-bar');
                    button.id = 'extent-control';
                    button.href = '#';
                    button.title = 'Reset map view';
                    button.setAttribute('aria-label', 'Reset map view');
                    button.style.width = '36px';
                    button.style.height = '36px';
                    L.DomEvent.on(button, 'click', function (event) {
                        L.DomEvent.stop(event);
                        map.flyTo(center, zoom);
                    });
                    return button;
                }
            });
            map.addControl(new ExtentControl);
            var locationMarker;
            $('#locate-button').on('click', function (event) {
                event.preventDefault();
                $(this).addClass('loading');
                map.locate({setView: true, maxZoom: 16});
            });
            map.on('locationfound', function (event) {
                $('#locate-button').removeClass('loading');
                if (locationMarker) { map.removeLayer(locationMarker); }
                locationMarker = L.circleMarker(event.latlng, {radius: 7, color: '#247eae', fillOpacity: 1}).addTo(map);
            });
            map.on('locationerror', function () {
                $('#locate-button').removeClass('loading');
                $('#modern-map-status').text('Your location could not be determined. You can still explore the map.');
            });
            WalkingTourHistoricalMaps.start(map);
        }).fail(function () {
            $('#modern-map-status').text('Map settings could not be loaded. Please reload the page.');
            $('#historical-map-status').text('Maps could not start. Please reload the page.');
        });
    });
})(jQuery);
