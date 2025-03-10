$(document).ready(function () {
    walkingTourJs()
});

function walkingTourJs() {
    var imported = document.createElement("script");
    document.head.appendChild(imported);
    // Set map height to be window height minus header height.
    var windowheight = $(window).height();
    $('#map').css('height', windowheight - 54);

    var MAP_URL_TEMPLATE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}';

    var MAP_CENTER;
    var MAP_ZOOM;  // MAP_ZOOM controls the default zoom of the map
    var MAP_MIN_ZOOM_STOP;
    var MAP_MAX_ZOOM_STOP;
    var LOCATE_BOUNDS;
    var EXHIBIT_BUTTON_TEXT;
    var DETAIL_BUTTON_TEXT;
    var IS_AUTO_FIT;

    var map;
    var historicMapLayer;
    var markers;
    var jqXhr;
    var locationMarker;
    var markerData;
    var allItems = {};
    var allMarkers = {};

 
 
    /*
     * JQuery Setup
     */

    // Check for user's first time visiting. Wait to locate the user after displaying tooltip on the first visit.
    if (!($.cookie('myCookie'))) {
        $('#first-time').show();
        $('.tooltip-locate').toggle();
        $.cookie('myCookie', 'visited', { path: '/', expires: 10000 });
    }

    $("#first-time > div.tooltip > button").on('click', function () {
        $('.tooltip').fadeToggle();
        $('.tooltip-locate').fadeToggle();
    });

    $("#first-time > div.tooltip-locate > button").on('click', function () {
        $('#first-time').hide();
    });

    // Set up the dialog window.
    $('#dialog').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false
    });

    // Handle the filter form.
    $('#filter-button').click(function (e) {
        e.preventDefault();

        // First close any popups
        var popupDiv = $('.leaflet-pane .leaflet-popup-pane')
        if (popupDiv.children().length > 0) {
            popupDiv.empty()
        }

        var filterButton = $(this);
        var clicks = filterButton.data('clicks');
        if (clicks) {
            filterButton.removeClass('on').
                find('.screen-reader-text').
                html('Filters');
            $('#filters').fadeToggle(200, 'linear');
        } else {
            filterButton.addClass('on').
                find('.screen-reader-text').
                html('Back to Map');
            $('#filters').fadeToggle(200, 'linear');
        }
        filterButton.data('clicks', !clicks);
    });

    $('#tour-confirm-button').click(function (e) {
        e.preventDefault();
        var filterButton = $('#filter-button');
        var tourSelected = []
        var tourTypeCheck = $('input[name=place-type]:checked')
        var curTourSelected;
        var itemIDList = [];
        var tour_id;

        if (tourTypeCheck.length) {
            tourTypeCheck.each(function () {
                tour_id = this.value;
                tourSelected.push(markerData[this.value].walkingPath);
                curTourSelected = markerData[this.value];
            });
        } else {
            var toursToPlot = Object.keys(markerData);
            toursToPlot.forEach((ele) => {
                tourSelected.push(markerData[ele].walkingPath);
            });
        }

        filterButton.removeClass('on').
            find('.screen-reader-text').
            html('Filters');
        $('#filters').fadeToggle(200, 'linear');

        if (curTourSelected) {
            curTourSelected.Data.features.forEach(ele => {
                itemIDList.push(ele.properties.id)
            })

            $('#info-panel-container').fadeToggle(200, 'linear');
            $('#toggle-map-button + .back-button').show();
            populateTourIntroPopup(itemIDList, curTourSelected, tour_id);
        }
        let polylineGroup = L.featureGroup(tourSelected);
        let bounds = polylineGroup.getBounds();
        map.fitBounds(bounds);
    })

    // Revert form to default and display all markers.
    $('#all-button').click(function (e) {
        e.preventDefault();
        revertFormState();
    });

    // Handle locate button.
    $('#locate-button').click(function (e) {
        e.preventDefault();
        $(this).toggleClass('loading');
        if (locationMarker) {
            map.removeLayer(locationMarker)
            locationMarker = null;
        }
        map.stopLocate();
        map.locate({ watch: true });
    });

    // Toggle historic map layer on and off.
    $('#toggle-map-button').click(function (e) {
        e.preventDefault();
        var toggleMapButton = $(this);
        var clicks = toggleMapButton.data('clicks');
        if (clicks) {
            toggleMapButton.addClass('on');
            toggleMapButton.find('.screen-reader-text').html('Map On');
            map.addLayer(historicMapLayer);
        } else {
            if (historicMapLayer) {
                toggleMapButton.removeClass('on');
                toggleMapButton.find('.screen-reader-text').html('Map Off');
                map.removeLayer(historicMapLayer);
            }
        }
        toggleMapButton.data('clicks', !clicks);
    });

    // Toggle map filters
    $('#filters div label').click(function () {
        var checkboxLabel = $(this);
        if (checkboxLabel.find('input[type=checkbox]').is(':checked')) {
            checkboxLabel.addClass('on');
        } else {
            checkboxLabel.removeClass('on');
        }
    });

    // Filter historic map layer.
    $('#map-coverage').change(function () {
        if (historicMapLayer) {
            removeHistoricMapLayer();
        }
        if ('0' == $('#map-coverage').val()) {
            $('#toggle-map-button').hide();
        } else {
            addHistoricMapLayer();
        }
        doFilters();
    });

    $('#map-coverage,#tour-type').on('touchstart touchend', function (event) {
        event.stopPropagation();
    });

    // Filter place type.
    $('input[name=place-type]').change(function () {
        // allow only one box checked
        $('input[name=place-type]:checked').not(this).prop('checked', false).
            parent().removeClass('on');
        // Handle all place types checkbox.
        var placeTypeAll = $('input[name=place-type-all]');
        if ($('input[name=place-type]:checked').length) {
            placeTypeAll.prop('checked', false).parent().removeClass('on');
        } else {
            placeTypeAll.prop('checked', true).parent().addClass('on');
        }
        doFilters();
    });

    // Handle the all place types checkbox.
    $('input[name=place-type-all]').change(function () {
        // Uncheck all place types.
        $('input[name=place-type]:checked').prop('checked', false).
            parent().removeClass('on');
        doFilters();
    });

    // Filter event type.
    $('input[name=event-type]').change(function () {
        // Handle all event types checkbox.
        var eventTypeAll = $('input[name=event-type-all]');
        if ($('input[name=event-type]:checked').length) {
            eventTypeAll.prop('checked', false).parent().removeClass('on');
        } else {
            eventTypeAll.prop('checked', true).parent().addClass('on');
        }
        doFilters();
    });

    // Handle the all event types checkbox.
    $('input[name=event-type-all]').change(function () {
        // Uncheck all event types.
        $('input[name=event-type]:checked').prop('checked', false).
            parent().removeClass('on');
        doFilters();
    });

    // Handle the info panel back button.
    $('a.back-button').click(function (e) {
        e.preventDefault();
        $('#info-panel-container').fadeToggle(200, 'linear');
    });

    /*
     * Query backend
     */

    window.onload = function () {
        jqXhr = $.post('walking-tour/index/map-config', function (response) {
            mapSetUp(response);
            doQuery();
        })
    };

    // Retain previous form state, if needed.
    retainFormState();

    function mapLocateCenter(map){
        map.flyTo(MAP_CENTER, MAP_ZOOM);
    }

    /*
     * Setup map layer
     *
     * Call only once during set up
     */
    function mapSetUp(response) {
        EXHIBIT_BUTTON_TEXT = response['walking_tour_exhibit_button']
        DETAIL_BUTTON_TEXT = response['walking_tour_detail_button']
        MAP_ZOOM = parseInt(response["walking_tour_default_zoom"])
        MAP_MAX_ZOOM_STOP = parseInt(response['walking_tour_max_zoom'])
        MAP_MIN_ZOOM_STOP = parseInt(response['walking_tour_min_zoom'])
        MAP_CENTER = parse1DArrayPoint("[" + response['walking_tour_center'] + ']')
        IS_AUTO_FIT = response["walking_tour_auto_fit"] == '1'
        // Set the base map layer.
        var minZoom = MAP_ZOOM - MAP_MIN_ZOOM_STOP;
        var maxZoom = MAP_ZOOM + MAP_MAX_ZOOM_STOP;
        map = L.map('map', {
            center: MAP_CENTER,
            minZoom: minZoom,
            maxZoom: maxZoom,
            zoom: minZoom,
            zoomControl: false
        });
        LOCATE_BOUNDS = map.getBounds();
        map.setZoom(MAP_ZOOM);

        map.addLayer(L.tileLayer(MAP_URL_TEMPLATE));
        map.addControl(L.control.zoom({ position: 'topleft' }));
        var extentControl = L.Control.extend({
            options: {
                position: 'topleft'
            },
            onAdd: function (map) {
                var container = L.DomUtil.create('div', 'extentControl');
                $(container).attr('id', 'extent-control');
                $(container).css('width', '26px').css('height', '26px').css('outline', '1px black');
                $(container).addClass('extentControl-disabled')
                $(container).addClass('leaflet-bar')
                $(container).on('click', function () {
                    mapLocateCenter(map);
                });
                return container;
            }
        })
        map.addControl(new extentControl());
        map.attributionControl.setPrefix('Tiles &copy; Esri');

        map.on('zoomend', function () {
            if (map.getZoom() == minZoom) {
                $('#extent-control').addClass('extentControl-disabled')
            } else {
                $('#extent-control').removeClass('extentControl-disabled')
            }
        })

        // Handle location found.
        map.on('locationfound', function (e) {
            if (!locationMarker) {
                $("#locate-button").toggleClass('loading');
            }
            // User within location bounds. Set the location marker.
            if (L.latLngBounds(LOCATE_BOUNDS).contains(e.latlng)) {
                if (locationMarker) {
                    // Remove the existing location marker before adding to map.
                    map.removeLayer(locationMarker);
                } else {
                    // Pan to location only on first locate.
                    map.panTo(e.latlng);
                }
                locationMarker = L.marker(e.latlng, {
                    icon: L.icon({
                        iconUrl: 'plugins/WalkingTour/views/public/images/location.png',
                        iconSize: [25, 25]
                    })
                });
                locationMarker.addTo(map).bindPopup("You are within " + e.accuracy / 2 + " meters from this point");
                // User outside location bounds.
            } else {
                var locateMeters = e.latlng.distanceTo(map.options.center);
                var locateMiles = Math.ceil((locateMeters * 0.000621371) * 100) / 100;
                alert('Cannot locate your location. You are ' + locateMiles + ' miles from the map bounds.');
                map.stopLocate();
            }
        });

        // Handle location error.
        map.on('locationerror', function () {
            $("#locate-button").toggleClass('loading');
            map.stopLocate();
            alert('Location Error, Please try again.');
            console.log('location error')
        });
    }

    /*
     * Query backend for tour info
     *
     * Call only once during set up
     */
    function doQuery() {
        const markerFontHtmlStyles = `
        transform: rotate(-45deg);
        color:white;
        text-align: center;
        padding: 0.2rem 0 0.18rem 0;
        font-size: 15px;
        `

        // correctly formats coordinates as [lat, long] (API returns [long, lat])
        function orderCoords(path) {
            var directions = [];
            for (var i = 0; i < path.length; i++) {
                directions.push([path[i][1], path[i][0]]);
            }
            return directions;
        }

        async function getOverallPath(points, key) {
            var pointsParam = []
            points.forEach(ele => {
                pointsParam.push([ele.lng, ele.lat])
            })
            url = "https://api.openrouteservice.org/v2/directions/foot-walking/geojson"
            const response = await fetch(url, {
                method: "POST", // *GET, POST, PUT, DELETE, etc.
                headers: {
                    'Accept': 'application/json, application/geo+json, application/gpx+xml, img/png; charset=utf-8',
                    "Content-Type": "application/json",
                    'Authorization': key
                    // 'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `{"coordinates": ${JSON.stringify(pointsParam)}}`, // body data type must match "Content-Type" header
            })
            return response.json();
        }

        var key = "5b3ce3597851110001cf62489dde4c6690bc423bb86bd99921c5da77";
        var url;
        var itemArray = []
        var tourToItem = {}
        var markerBounds = L.latLngBounds();
        jqXhr = $.post('walking-tour/index/query', function (response) {
            markerData = response;
            dataArray = Object.entries(markerData)
            for (const tour in markerData) {
                itemArray = itemArray.concat(markerData[tour]['Data']['features'])
            }
            let requests = dataArray.map(([tourId, value]) => {
                return new Promise((resolve) => {
                    var numMarker = 1;
                    var response = value["Data"];
                    var itemIDList = [];

                    response.features.forEach(ele => {
                        itemIDList.push(ele.properties.id)
                    })
                    tourToItem[tourId] = itemIDList;
                    markerList = []
                    var geoJsonLayer = L.geoJson(response.features, {
                        // adds the correct number to each marker based on order of tour
                        pointToLayer: function (feature, latlng) {
                            var numberIcon = L.divIcon({
                                className: "my-custom-pin",
                                iconSize: [25, 41],
                                iconAnchor: [12, 40],
                                popupAnchor: [0, -5],
                                html: `<span style="${getMarkerHTML(feature.properties["marker-color"])}" > <p style="${markerFontHtmlStyles}"> ${numMarker} </p> </spam>`
                            });
                            numMarker++;
                            markerBounds.extend(latlng);
                            return L.marker(latlng, { icon: numberIcon });
                        },
                        onEachFeature: function (feature, layer) {
                            layer.on('click', function (e) {
                                // center click location
                                map.flyTo(e.latlng,MAP_ZOOM + MAP_MAX_ZOOM_STOP);
                                // Close the filtering
                                var filterButton = $('filter-button');
                                filterButton.removeClass('on').
                                    find('.screen-reader-text').
                                    html('Filters');
                                $('#filters').fadeOut(200, 'linear');

                                var marker = this;
                                response = allItems[`${tourId}:${feature.properties.id}`]
                                if (response == undefined) {
                                    $.post('walking-tour/index/get-item', { id: feature.properties.id, tour: tourId }, function (response) {
                                        allItems[`${tourId}:${feature.properties.id}`] = response;
                                        featureOnclickAction(response, layer, marker, itemIDList, value, tourId);
                                    })
                                } else {
                                    featureOnclickAction(response, layer, marker, itemIDList, value, tourId);
                                }

                            });

                        }
                    });
                    markerData[tourId].allMarker = markerList;
                    markerData[tourId].geoJson = geoJsonLayer;
                    var walkingPath = [];
                    var json_content = response.features;
                    var pointList = [];
                    for (var i = 0; i < json_content.length; i++) {
                        lat = json_content[i].geometry.coordinates[1];
                        lng = json_content[i].geometry.coordinates[0];
                        var point = new L.LatLng(lat, lng);
                        pointList[i] = point;
                    }
                    getOverallPath(pointList, key).then((data) => {
                        var distance = data["features"][0]["properties"]["summary"]["distance"];
                        var duration = data["features"][0]["properties"]["summary"]["duration"];

                        var path = data["features"][0]["geometry"]["coordinates"];
                        path = orderCoords(path);
                        for (var p of path) {
                            walkingPath.push(p);
                        }
                        var tourPolyline = new L.Polyline(walkingPath, {
                            color: value["Color"],
                            weight: 3,
                            opacity: 1,
                            smoothFactor: 1
                        });

                        markerData[tourId].walkingPath = tourPolyline;
                        
                        tourPolyline.bindPopup(duration + " minutes, " + distance + " meters");
                        polylines.on('popupopen', function (e) {
                            var popup = e.popup;
                            popup.setContent('Coordinates');
                        });
                        tourPolyline.on('mouseover', function (e) {
                            this.openPopup();
                        });
                        tourPolyline.on('mouseout', function (e) {
                            this.closePopup();
                        });

                        markerData[tourId].walkingPath.on('click', function (e) {
                            console.log('test');
                        });

                        resolve()
                    });
                });
            })
            Promise.all(requests).then(() => {
                createCustomCSS();
                if (IS_AUTO_FIT){
                    map.fitBounds(markerBounds, {padding: [10, 10]})
                    mapLocateCenter = function(map) {
                        map.fitBounds(markerBounds, {padding: [10, 10]})
                    } 
                    var curZoom = map._zoom;
                    map.setMaxZoom( curZoom + MAP_MAX_ZOOM_STOP);
                    map.setMinZoom( curZoom - MAP_MIN_ZOOM_STOP);
                        // map["options"]["minZoom"] = curZoom - MAP_MIN_ZOOM_STOP                   
                }
                doFilters();
            });
        });
    }

    /*
     * Filter markers.
     *
     * This must be called on every form change.
     */
    function doFilters() {
        // Remove the current markers.
        if (markers) {
            map.removeLayer(markers);
        }

        var mapCoverage = $('#map-coverage');
        var tourTypeCheck = $('input[name=place-type]:checked')

        var toursToPlot = [];
        var mapToPlot;
        // Handle each filter
        if ('0' != mapCoverage.val()) {
            mapToPlot = mapCoverage.val();
        }

        if (tourTypeCheck.length) {
            tourTypeCheck.each(function () {
                toursToPlot.push(this.value);
            });
        } else {
            toursToPlot = Object.keys(markerData);
        }

        var pathToPlot = [];
        var markerLayers = [];
        var numMarkers = 0;

        // handle the GeoJSON response, and add markers.
        toursToPlot.forEach(ele => {
            numMarkers += markerData[ele].Data.features.length;
            markerLayers.push(markerData[ele].geoJson);
            pathToPlot.push(markerData[ele].walkingPath);
            markerData[ele].walkingPath.openPopup();
        });
        //response is an array of coordinate;
        var item = (1 == numMarkers) ? 'item' : 'items';
        $('#marker-count').text(numMarkers + " " + item);

        try {
            markers = new L.layerGroup();
        }
        catch (err) {
            console.log(err)
        }

        markerLayers.forEach(ele => {
            markers.addLayer(ele);
        })
        pathToPlot.forEach(ele => {
            ele.addTo(markers);
        })
        map.addLayer(markers);
    }

    /*
     * Setup color for each tour
     *
     */
    function createCustomCSS() {
        var style = document.createElement('style')
        var css = ""
        for (const tour_id in markerData) {
            var color = markerData[tour_id]['Color']

            if (color.length == 0){
                color = "#000000"
            }
            
            var rgb = hexToRgb(color)
            css += `#filters div label.label${tour_id}:before {
                        background-color: ${color} !important;
                    }
                    #filters div label.label${tour_id} {
                        background-color: rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.15) !important; 
                        color: ${color} !important;
                    }
                    #filters div label.label${tour_id}.on:before {
                        background-color: rgba(33, 201, 0, 1) !important;
                    }\n`
        }
        style.innerHTML = css;
        document.body.appendChild(style);
    }

    /*
     * Setup popups for each item and tour 
    */

    function featureOnclickAction(response, layer, marker, itemIDList, value, tourId) {
        var popupContent = '<h3>' + response.title + '</h3>';
        if (response.thumbnail) {
            popupContent += '<a href="#" class="open-info-panel">' + response.thumbnail + '</a><br/>';
        }
        popupContent += '<a href="#" class="open-info-panel button">View More Info</a>';
        if (!layer.getPopup()) {
            marker.bindPopup(popupContent, { maxWidth: 200, offset: L.point(0, -40) }).openPopup();
            allMarkers[response.id] = marker;
        }

        window.setTimeout(function () {
            layer.getPopup().update()
            $('.open-info-panel').click(function (e) {
                e.preventDefault();
                $('#info-panel-container').fadeToggle(200, 'linear');
                $('#toggle-map-button + .back-button').show();
                marker.closePopup();
            });
        }, 500);

        // Populate the item info panel.
        populatePopup(itemIDList, value, response, itemIDList.findIndex((ele) => ele == response.id), tourId);
    }

    function populateTourIntroPopup(itemIDList, value, tour_id) {
        $('.next-button').unbind("click");
        $('.prev-button').unbind("click");

        $('.prev-button').addClass('off');
        $('.next-button').addClass('off');

        document.getElementById("info-panel-name").innerHTML = value["Tour Name"];
        $('.panel-title').css("backgroundColor", value['Color'])
        var content = $('#info-panel-content');
        content.empty();

        var infoContent = ""
        var rightContent = "";
        // click title to show the popup on map
        if (value.Description != "") {
            rightContent += '<p>' + value.Description + '</p>'
        } else {
            rightContent += "<p> No descriptions available. </p>"
        }

        if (value.Credits != "") {
            rightContent += "<h2 class = credits> Credits </h2>"
            rightContent += '<p>' + value.Credits + '</p>'
        }
        rightContent += '<p><a href="#" class="button" id="start-tour" target="_blank">Start Tour</a></p>';
        window.setTimeout(function () {
            $('#start-tour').click(function (e) {
                var newId = itemIDList[0]
                popupButtonEvent(e, newId, itemIDList, value, tour_id);
            })
        }, 500)
        infoContent += '<div class = "content-container"> <div class ="article">' + rightContent + '</div></div>';

        content.append('<div class = "info-content">' + infoContent + '</div>')
    }

    function populatePopup(itemIDList, value, response, numPopup, tour_id) {
        var numPopup = itemIDList.findIndex((ele) => ele == response.id);
        var coor = value.Data.features[numPopup].geometry.coordinates;
        map.flyTo([coor[1], coor[0]], MAP_ZOOM + MAP_MAX_ZOOM_STOP);

        $('.next-button').unbind("click");
        $('.prev-button').unbind("click");

        if (numPopup + 1 == itemIDList.length) {
            $('.next-button').addClass('off');
        } else {
            $('.next-button').removeClass('off');
            $('.next-button').unbind("click");
            window.setTimeout(function () {
                $('.next-button').click(function (e) {
                    var newId = itemIDList[numPopup + 1]
                    // value.allMarker[numPopup].closePopup();
                    popupButtonEvent(e, newId, itemIDList, value, tour_id);
                })
            }, 500)
        }

        if (numPopup - 1 == -1 || numPopup == -1) {
            $('.prev-button').addClass('off');
        } else {
            $('.prev-button').removeClass('off');
            window.setTimeout(function () {
                $('.prev-button').click(function (e) {
                    var newId = itemIDList[numPopup - 1]
                    // value.allMarker[numPopup].closePopup();
                    popupButtonEvent(e, newId, itemIDList, value, tour_id);
                })
            }, 500)
        }

        document.getElementById("info-panel-name").innerHTML = value["Tour Name"] + ` #${numPopup + 1}`;
        $('.panel-title').css("backgroundColor", value['Color'])
        var content = $('#info-panel-content');
        content.empty();

        var infoContent = ""
        var leftContent = "";
        var rightContent = "";
        if (response.fullsize) {
            leftContent += response.fullsize;
            infoContent += '<div class = "image-container">' + leftContent + '</div>';
        }

        // click title to show the popup on map
        rightContent += `<h2 class = info-panel-title>` + response.title + '</h2>'
        if (response.abstract) {
            rightContent += '<p>' + response.abstract + '</p>';
        } else if (response.description) {
            rightContent += '<p>' + response.description + '</p>';
        } else {
            rightContent += '<p>No descriptions available.</p>';
        }
        rightContent += '<div class = "popupButton">'
        rightContent += '<a href="' + response.url + '" class="button" target="_blank">'+ DETAIL_BUTTON_TEXT +'</a>';
        if (response.exhibitUrl != ""){
            rightContent += '<a href="' + response.exhibitUrl + '" class="button" target="_blank">'+ EXHIBIT_BUTTON_TEXT +'</a>';
        }
        rightContent += '</div>'
        infoContent += '<div class = "content-container"> <div class ="article">' + rightContent + '</div></div>';

        content.append('<div class = "info-content">' + infoContent + '</div>')
    }

    function getMarkerHTML(color) {
        let markerHtmlStyles = `
        background-color: ${color};
        width: 1.7rem;
        height: 1.7rem;
        display: block;
        left: -0.5rem;
        top: -0.5rem;
        position: relative;
        border-radius: 1.5rem 1.5rem 0;
        transform: rotate(45deg);`
        return markerHtmlStyles;
    }

    function popupButtonEvent(e, id, itemIDList, value, tour_id) {
        e.preventDefault();
        var response = allItems[`${tour_id}:${id}`]
        if (response == undefined) {
            $.post('walking-tour/index/get-item', { id: id, tour: tour_id }, function (response) {
                allItems[`${tour_id}:${id}`] = response;
                populatePopup(itemIDList, value, response, itemIDList.findIndex((ele) => ele == response.id), tour_id);
            })
        } else {
            populatePopup(itemIDList, value, response, itemIDList.findIndex((ele) => ele == response.id), tour_id)
        }
    }


    /*
     * Add the historic map layer.
     */
    function addHistoricMapLayer() {
        // Get the historic map data
        var getData = { 'text': $('#map-coverage').val() };
        $.get('walking-tour/index/historic-map-data', getData, function (response) {
            historicMapLayer = L.tileLayer(
                response.url,
                { tms: true, opacity: 1.00 }
            );
            map.addLayer(historicMapLayer);
            $('#toggle-map-button').show();

            // Set the map title as the map attribution prefix.
            map.attributionControl.setPrefix(response.title);
        });
    }

    /*
     * Remove the historic map layer.
     */
    function removeHistoricMapLayer() {
        $('#toggle-map-button').data('clicks', false).hide();
        map.removeLayer(historicMapLayer);
        map.attributionControl.setPrefix('');
    }

    /*
     * Helper Functions
     */

    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function parse1DArrayPoint(text) {
        ptn = text.split(",")
        ptn.forEach(function (ele, index) {
            temp_ele = ele.replace(/\s+/g, '');
            temp_ele = temp_ele.replace('[', '');
            temp_ele = temp_ele.replace(']', '');
            this[index] = parseFloat(temp_ele)
        }, ptn);
        return ptn;
    }

    function parse2DArrayPoint(text) {
        ptn = text.match(/(\[-?[0-9]*.[0-9]*, -?[0-9]*.[0-9]*\])/g)
        b_new = []
        ptn.forEach(ele => {
            temp_ele = ele.split(",")
            temp_ele.forEach(function (ele_inner, index_inner) {
                temp_ele_inner = ele_inner.replace(/\s+/g, '');
                temp_ele_inner = temp_ele_inner.replace('[', '');
                temp_ele_inner = temp_ele_inner.replace(']', '');
                this[index_inner] = parseFloat(temp_ele_inner)
            }, temp_ele)
            b_new.push(temp_ele)
        })
        return b_new
    }

        /*
     * Revert to default (original) form state.
     */
    function revertFormState() {
        if (historicMapLayer) {
            removeHistoricMapLayer();
        }

        $('#map-coverage').val('0');
        $('#tour-type').val('0');

        $('#place-type-div').hide({ duration: 'fast' });
        $('input[name=place-type-all]').prop('checked', true).
            parent().addClass('on');
        $('input[name=place-type]:checked').prop('checked', false).
            parent().removeClass('on');

        $('#event-type-div').hide({ duration: 'fast' });
        $('input[name=event-type-all]').prop('checked', true).
            parent().addClass('on');
        $('input[name=event-type]:checked').prop('checked', false).
            parent().removeClass('on');

        doFilters();
    }

    /*
     * Retain previous form state.
     *
     * Acts on the assumption that all browsers will preserve the form state
     * when navigating back to the map from another page.
     */
    function retainFormState() {
        if ('Place' == $('#tour-type').find(':selected').text()) {
            var placeTypes = $('input[name=place-type]:checked');
            if (placeTypes.length) {
                $('input[name=place-type-all]').parent().removeClass('on');
                placeTypes.parent().addClass('on');
            }
            $('#place-type-div').show({ duration: 'fast' });
        }
        if ('Event' == $('#tour-type').find(':selected').text()) {
            var eventTypes = $('input[name=event-type]:checked');
            if (eventTypes.length) {
                $('input[name=event-type-all]').parent().removeClass('on');
                eventTypes.parent().addClass('on');
            }
            $('#event-type-div').show({ duration: 'fast' });
        }
    }

    /*
     * Revert to default (original) form state.
     */
    function revertFormState() {
        if (historicMapLayer) {
            removeHistoricMapLayer();
        }

        $('#map-coverage').val('0');
        $('#tour-type').val('0');

        $('#place-type-div').hide({ duration: 'fast' });
        $('input[name=place-type-all]').prop('checked', true).
            parent().addClass('on');
        $('input[name=place-type]:checked').prop('checked', false).
            parent().removeClass('on');

        $('#event-type-div').hide({ duration: 'fast' });
        $('input[name=event-type-all]').prop('checked', true).
            parent().addClass('on');
        $('input[name=event-type]:checked').prop('checked', false).
            parent().removeClass('on');

        doFilters();
    }

    /*
     * Retain previous form state.
     *
     * Acts on the assumption that all browsers will preserve the form state
     * when navigating back to the map from another page.
     */
    function retainFormState() {
        if ('Place' == $('#tour-type').find(':selected').text()) {
            var placeTypes = $('input[name=place-type]:checked');
            if (placeTypes.length) {
                $('input[name=place-type-all]').parent().removeClass('on');
                placeTypes.parent().addClass('on');
            }
            $('#place-type-div').show({ duration: 'fast' });
        }
        if ('Event' == $('#tour-type').find(':selected').text()) {
            var eventTypes = $('input[name=event-type]:checked');
            if (eventTypes.length) {
                $('input[name=event-type-all]').parent().removeClass('on');
                eventTypes.parent().addClass('on');
            }
            $('#event-type-div').show({ duration: 'fast' });
        }
    }
}
