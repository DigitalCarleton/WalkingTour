console.log("start")
/*
 * Leaflet-IIIF 1.1.1
 * IIIF Viewer for Leaflet
 * by Jack Reed, @mejackreed
 */

const Iiif = L.TileLayer.extend({
    options: {
      continuousWorld: true,
      tileSize: 256,
      updateWhenIdle: true,
      tileFormat: 'jpg',
      fitBounds: true
    },
  
    initialize: function(url, options) {
      options = typeof options !== 'undefined' ? options : {};
  
      if (options.maxZoom) {
        this._customMaxZoom = true;
      }
  
      // Check for explicit tileSize set
      if (options.tileSize) {
        this._explicitTileSize = true;
      }
  
      // Check for an explicit quality
      if (options.quality) {
        this._explicitQuality = true;
      }
  
      options = L.setOptions(this, options);
      this._infoDeferred = new $.Deferred();
      this._infoUrl = url;
      this._baseUrl = this._templateUrl();
      this._getInfo();
    },
    getTileUrl: function(coords) {
      var _this = this,
        x = coords.x,
        y = (coords.y),
        zoom = _this._getZoomForUrl(),
        scale = Math.pow(2, _this.maxNativeZoom - zoom),
        tileBaseSize = _this.options.tileSize * scale,
        minx = (x * tileBaseSize),
        miny = (y * tileBaseSize),
        maxx = Math.min(minx + tileBaseSize, _this.x),
        maxy = Math.min(miny + tileBaseSize, _this.y);
      
      var xDiff = (maxx - minx);
      var yDiff = (maxy - miny);
  
      return L.Util.template(this._baseUrl, L.extend({
        format: _this.options.tileFormat,
        quality: _this.quality,
        region: [minx, miny, xDiff, yDiff].join(','),
        rotation: 0,
        size: Math.ceil(xDiff / scale) + ','
      }, this.options));
    },
    onAdd: function(map) {
      var _this = this;
  
      // Wait for deferred to complete
      $.when(_this._infoDeferred).done(function() {
  
        // Set maxZoom for map
        map._layersMaxZoom = _this.maxZoom;
  
        // Call add TileLayer
        L.TileLayer.prototype.onAdd.call(_this, map);
  
        if (_this.options.fitBounds) {
          _this._fitBounds();
        }
  
        // Reset tile sizes to handle non 256x256 IIIF tiles
        _this.on('tileload', function(tile, url) {
  
          var height = tile.tile.naturalHeight,
            width = tile.tile.naturalWidth;
  
          // No need to resize if tile is 256 x 256
          if (height === 256 && width === 256) return;
  
          tile.tile.style.width = width + 'px';
          tile.tile.style.height = height + 'px';
  
        });
      });
    },
    _fitBounds: function() {
      var _this = this;
  
      // Find best zoom level and center map
      var initialZoom = _this._getInitialZoom(_this._map.getSize());
      var imageSize = _this._imageSizes[initialZoom];
      var sw = _this._map.options.crs.pointToLatLng(L.point(0, imageSize.y), initialZoom);
      var ne = _this._map.options.crs.pointToLatLng(L.point(imageSize.x, 0), initialZoom);
      var bounds = L.latLngBounds(sw, ne);
  
      _this._map.fitBounds(bounds, true);
    },
    _getInfo: function() {
      var _this = this;
  
      // Look for a way to do this without jQuery
      $.getJSON(_this._infoUrl)
        .done(function(data) {
          _this.y = data.height;
          _this.x = data.width;
  
          var tierSizes = [],
            imageSizes = [],
            scale,
            width_,
            height_,
            tilesX_,
            tilesY_;
  
          // Set quality based off of IIIF version
          if (data.profile instanceof Array) {
            _this.profile = data.profile[0];
          }else {
            _this.profile = data.profile;
          }
  
          _this._setQuality();
  
          // Unless an explicit tileSize is set, use a preferred tileSize
          if (!_this._explicitTileSize) {
            // Set the default first
            _this.options.tileSize = 256;
            if (data.tiles) {
              // Image API 2.0 Case
              _this.options.tileSize = data.tiles[0].width;
            } else if (data.tile_width){
              // Image API 1.1 Case
              _this.options.tileSize = data.tile_width;
            }
          }
  
          function ceilLog2(x) {
            return Math.ceil(Math.log(x) / Math.LN2);
          };
  
          // Calculates maximum native zoom for the layer
          _this.maxNativeZoom = Math.max(ceilLog2(_this.x / _this.options.tileSize),
            ceilLog2(_this.y / _this.options.tileSize));
          
          // Enable zooming further than native if maxZoom option supplied
          if (_this._customMaxZoom && _this.options.maxZoom > _this.maxNativeZoom) {
            _this.maxZoom = _this.options.maxZoom;
          }
          else {
            _this.maxZoom = _this.maxNativeZoom;
          }
          
          for (var i = 0; i <= _this.maxZoom; i++) {
            scale = Math.pow(2, _this.maxNativeZoom - i);
            width_ = Math.ceil(_this.x / scale);
            height_ = Math.ceil(_this.y / scale);
            tilesX_ = Math.ceil(width_ / _this.options.tileSize);
            tilesY_ = Math.ceil(height_ / _this.options.tileSize);
            tierSizes.push([tilesX_, tilesY_]);
            imageSizes.push(L.point(width_,height_));
          }
  
          _this._tierSizes = tierSizes;
          _this._imageSizes = imageSizes;
  
          // Resolved Deferred to initiate tilelayer load
          _this._infoDeferred.resolve();
        });
    },
  
    _setQuality: function() {
      var _this = this;
      var profileToCheck = _this.profile;
  
      if (_this._explicitQuality) {
        return;
      }
  
      // If profile is an object
      if (typeof(profileToCheck) === 'object') {
        profileToCheck = profileToCheck['@id'];
      }
  
      // Set the quality based on the IIIF compliance level
      switch (true) {
        case /^http:\/\/library.stanford.edu\/iiif\/image-api\/1.1\/compliance.html.*$/.test(profileToCheck):
          _this.options.quality = 'native';
          break;
        // Assume later profiles and set to default
        default:
          _this.options.quality = 'default';
          break;
      }
    },
  
    _infoToBaseUrl: function() {
      return this._infoUrl.replace('info.json', '');
    },
    _templateUrl: function() {
      return this._infoToBaseUrl() + '{region}/{size}/{rotation}/{quality}.{format}';
    },
    _isValidTile: function(coords) {
      var _this = this,
        zoom = _this._getZoomForUrl(),
        sizes = _this._tierSizes[zoom],
        x = coords.x,
        y = (coords.y);
  
      if (!sizes) return false;
      if (x < 0 || sizes[0] <= x || y < 0 || sizes[1] <= y) {
        return false;
      }else {
        return true;
      }
    },
    _getInitialZoom: function (mapSize) {
      var _this = this,
        tolerance = 0.8,
        imageSize;
  
      for (var i = _this.maxNativeZoom; i >= 0; i--) {
        imageSize = this._imageSizes[i];
        if (imageSize.x * tolerance < mapSize.x && imageSize.y * tolerance < mapSize.y) {
          return i;
        }
      }
      // return a default zoom
      return 2;
    }
  });
  
const tiff = function(url, options) {
return new Iiif(url, options);
};

const getPackages = async function() {
    const allmapsAnnotation = await import("https://unpkg.com/@allmaps/annotation?module")
    const allmapsTransform = await import("https://unpkg.com/@allmaps/transform?module")
    
    return [allmapsAnnotation, allmapsTransform]
}

console.log(tiff)
async function main() {
    let packages = await getPackages();
    console.log(packages);
    console.log(tiff)
    walkingTourJs(packages[0], packages[1], tiff)
  }

main()

//   .then(([allmapsAnnotation, allmapsTransform]) => {
//     // Both modules loaded successfully
//     // Use the imported modules
//     console.log(L.tileLayer)
//     walkingTourJs(allmapsAnnotation, allmapsTransform, L.tileLayer.iiif)
//   })
//   .catch((error) => {
//     // An error occurred while loading one of the modules
//     console.error('Error loading modules:', error);
//   });


function walkingTourJs(allmapsAnnotation, allmapsTransform, iiif) {
    var imported = document.createElement("script");
    document.head.appendChild(imported);
    // Set map height to be window height minus header height.
    var windowheight = $(window).height();
    $('#map').css('height', windowheight - 54);

    var MAP_URL_TEMPLATE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}';
    const annotationUrl = 'https://annotations.allmaps.org/manifests/47574ee029cca631'

    var MAP_CENTER;
    var MAP_ZOOM;  // MAP_ZOOM controls the default zoom of the map
    var MAP_MIN_ZOOM;
    var MAP_MAX_ZOOM;
    var MAP_MAX_BOUNDS;  // MAP_MAX_BOUNDS controls the boundaries of the map
    var LOCATE_BOUNDS;
    var EXHIBIT_BUTTON_TEXT;
    var DETAIL_BUTTON_TEXT;

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

    // window.onload = function () {
    //     jqXhr = $.post('walking-tour/index/map-config', function (response) {
    //         const mapConfig = response
    //         mapSetUp(mapConfig, "https://iiif.digitalcommonwealth.org/iiif/2/commonwealth:ht250943q")
    //         fetch(annotationUrl)
    //             .then(response => {
    //                 // Check if the request was successful
    //                 if (!response.ok) {
    //                     throw new Error('Network response was not ok');
    //                 }
    //                 // Parse the response as JSON
    //                 return response.json();
    //             })
    //             .then(data => {
    //                 const maps = allmapsAnnotation.parseAnnotation(data)
    //                 const transformer = new allmapsTransform.GcpTransformer(maps[0].gcps);
    //                 console.log(maps)
    //                 doQuery(transformer, maps);
    //             })
    //             .catch(error => {
    //                 // Handle any errors that occurred during the fetch
    //                 console.error('Fetch error:', error);
    //             });
    //     })
    // };

    fetch(annotationUrl)
        .then(response => {
            // Check if the request was successful
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            // Parse the response as JSON
            return response.json();
        })
        .then(data => {
            const maps = allmapsAnnotation.parseAnnotation(data)
            const transformer = new allmapsTransform.GcpTransformer(maps[0].gcps);
            console.log(maps)
            mapSetUp(maps[0])
            doQuery(transformer, maps);
        })
        .catch(error => {
            // Handle any errors that occurred during the fetch
            console.error('Fetch error:', error);
        });

    // Retain previous form state, if needed.
    retainFormState();

    /*
     * Setup map layer
     *
     * Call only once during set up
     */
    function mapSetUp(maps) {
        console.log(maps.resource.height)
        console.log( $('#map').height())
        var zoom = maps.resource.height / $('#map').height()
        console.log(zoom)
        map = L.map('map', {
            center: [0, 0],
            crs: L.CRS.Simple,
            zoom: 0
          });
        iiif(maps.resource.id+'/info.json', {
            fitBounds: true,
            setMaxBounds: true
        }).addTo(map)

        map.on('zoomend', function() {
            console.log(map.getZoom())
        });
        // map.setZoom(zoom)
        // var bounds = L.bounds(L.point(maps.resourceMask[0][0], maps.resourceMask[0][1]), L.point(maps.resourceMask[2][0], maps.resourceMask[2][1]));
        
        // console.log(bounds)
        // map.fitBounds(bounds)
        console.log(map.getBounds(), map.getZoom())
        console.log(L.CRS.Simple.latLngToPoint(map.getBounds(), 0))
        map.onZoom
        // var rect = L.rectangle(map.getBounds(), {color: 'blue', weight: 1}).on('click', function (e) {
        //     // There event is event object
        //     // there e.type === 'click'
        //     // there e.lanlng === L.LatLng on map
        //     // there e.target.getLatLngs() - your rectangle coordinates
        //     // but e.target !== rect
        //     console.info(e);
        // }).addTo(map);

        // EXHIBIT_BUTTON_TEXT = response['walking_tour_exhibit_button']
        // DETAIL_BUTTON_TEXT = response['walking_tour_detail_button']
        // MAP_MAX_ZOOM = parseInt(response['walking_tour_max_zoom'])
        // MAP_MIN_ZOOM = parseInt(response['walking_tour_min_zoom'])
        // MAP_CENTER = parse1DArrayPoint(response['walking_tour_center'])
        // MAP_ZOOM = parseInt(response["walking_tour_default_zoom"])
        // MAP_MAX_BOUNDS = parse2DArrayPoint(response["walking_tour_max_bounds"])
        // // Set the base map layer.
        // map = L.map('map', {
        //     center: MAP_CENTER,
        //     zoom: MAP_MIN_ZOOM,
        //     minZoom: MAP_MIN_ZOOM,
        //     maxZoom: MAP_MAX_ZOOM,
        //     zoomControl: false
        // });
        // LOCATE_BOUNDS = map.getBounds();
        // map.setZoom(MAP_ZOOM);

        // map.addLayer(L.tileLayer(MAP_URL_TEMPLATE));
        // map.addControl(L.control.zoom({ position: 'topleft' }));
        // var extentControl = L.Control.extend({
        //     options: {
        //         position: 'topleft'
        //     },
        //     onAdd: function (map) {
        //         var container = L.DomUtil.create('div', 'extentControl');
        //         $(container).attr('id', 'extent-control');
        //         $(container).css('width', '26px').css('height', '26px').css('outline', '1px black');
        //         $(container).addClass('extentControl-disabled')
        //         $(container).addClass('leaflet-bar')
        //         $(container).on('click', function () {
        //             map.flyTo(MAP_CENTER, MAP_ZOOM);
        //         });
        //         return container;
        //     }
        // })
        // map.addControl(new extentControl());
        // map.attributionControl.setPrefix('Tiles &copy; Esri');

        // const warpedMapLayer = new Allmaps.WarpedMapLayer(annotationUrl)
        // map.addLayer(warpedMapLayer);

        // map.on('zoomend', function () {
        //     if (map.getZoom() == MAP_MIN_ZOOM) {
        //         $('#extent-control').addClass('extentControl-disabled')
        //     } else {
        //         $('#extent-control').removeClass('extentControl-disabled')
        //     }
        // })

        // Handle location found.
        // map.on('locationfound', function (e) {
        //     if (!locationMarker) {
        //         $("#locate-button").toggleClass('loading');
        //     }
        //     // User within location bounds. Set the location marker.
        //     if (L.latLngBounds(LOCATE_BOUNDS).contains(e.latlng)) {
        //         if (locationMarker) {
        //             // Remove the existing location marker before adding to map.
        //             map.removeLayer(locationMarker);
        //         } else {
        //             // Pan to location only on first locate.
        //             map.panTo(e.latlng);
        //         }
        //         locationMarker = L.marker(e.latlng, {
        //             icon: L.icon({
        //                 iconUrl: 'plugins/WalkingTour/views/public/images/location.png',
        //                 iconSize: [25, 25]
        //             })
        //         });
        //         locationMarker.addTo(map).bindPopup("You are within " + e.accuracy / 2 + " meters from this point");
        //         // User outside location bounds.
        //     } else {
        //         var locateMeters = e.latlng.distanceTo(map.options.center);
        //         var locateMiles = Math.ceil((locateMeters * 0.000621371) * 100) / 100;
        //         alert('Cannot locate your location. You are ' + locateMiles + ' miles from the map bounds.');
        //         map.stopLocate();
        //     }
        // });

        // // Handle location error.
        // map.on('locationerror', function () {
        //     $("#locate-button").toggleClass('loading');
        //     map.stopLocate();
        //     alert('Location Error, Please try again.');
        //     console.log('location error')
        // });
    }

    /*
     * Query backend for tour info
     *
     * Call only once during set up
     */
    function doQuery(transformer) {
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

        var yx = L.latLng;

        var xy = function(x, y) {
            if (Array.isArray(x)) {    // When doing xy([x, y]);
                return yx(x[1], x[0]);
            }
            return yx(y, x);  // When doing xy(x, y);
        };
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
                    // console.log(transformer)
                    // console.log(response.features)

                    newList = []

                    response.features = response.features.map(ele => {
                        var test = transformer.transformBackward(
                            ele.geometry
                        )
                        test = test.map(ele => {return ele/7})
                        // console.log(test)
                        itemIDList.push(ele.properties.id)
                        return({
                            ...ele,
                            geometry: test
                        })
                    })
                    console.log(response.features)
                    tourToItem[tourId] = itemIDList;
                    markerList = []

                    response.features.forEach(feature => {
                        var numberIcon = L.divIcon({
                            className: "my-custom-pin",
                            iconSize: [25, 41],
                            iconAnchor: [12, 40],
                            popupAnchor: [0, -5],
                            html: `<span style="${getMarkerHTML(feature.properties["marker-color"])}" > <p style="${markerFontHtmlStyles}"> ${numMarker} </p> </spam>`
                        });
                        numMarker++;
                        
                        var marker = L.marker(xy(feature.geometry[0], feature.geometry[1]), { icon: numberIcon });
                        marker.on('click', function (e) {
                            // center click location
                            map.flyTo(e.latlng, 0);
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



                                    var popupContent = '<h3>' + response.title + '</h3>';
                                    if (response.thumbnail) {
                                        popupContent += '<a href="#" class="open-info-panel">' + response.thumbnail + '</a><br/>';
                                    }
                                    popupContent += '<a href="#" class="open-info-panel button">View More Info</a>';
                                    if (!marker.getPopup()) {
                                        marker.bindPopup(popupContent, { maxWidth: 200, offset: L.point(0, -40) }).openPopup();
                                        allMarkers[response.id] = marker;
                                    }
                                })
                            } else {
                                var popupContent = '<h3>' + response.title + '</h3>';
                                if (response.thumbnail) {
                                    popupContent += '<a href="#" class="open-info-panel">' + response.thumbnail + '</a><br/>';
                                }
                                popupContent += '<a href="#" class="open-info-panel button">View More Info</a>';
                                if (!marker.getPopup()) {
                                    marker.bindPopup(popupContent, { maxWidth: 200, offset: L.point(0, -40) }).openPopup();
                                    allMarkers[response.id] = marker;
                                }
                            }
                        })
                        marker.addTo(map)
                    })



                    // var geoJsonLayer = L.geoJson(response.features, {
                    //     // adds the correct number to each marker based on order of tour
                    //     pointToLayer: function (feature, latlng) {
                    //         var numberIcon = L.divIcon({
                    //             className: "my-custom-pin",
                    //             iconSize: [25, 41],
                    //             iconAnchor: [12, 40],
                    //             popupAnchor: [0, -5],
                    //             html: `<span style="${getMarkerHTML(feature.properties["marker-color"])}" > <p style="${markerFontHtmlStyles}"> ${numMarker} </p> </spam>`
                    //         });
                    //         numMarker++;
                    //         console.log(latlng)
                    //         return L.marker(latlng, { icon: numberIcon });
                    //     },
                    //     onEachFeature: function (feature, layer) {
                    //         layer.on('click', function (e) {
                    //             // center click location
                    //             map.flyTo(e.latlng,MAP_MAX_ZOOM);
                    //             // Close the filtering
                    //             var filterButton = $('filter-button');
                    //             filterButton.removeClass('on').
                    //                 find('.screen-reader-text').
                    //                 html('Filters');
                    //             $('#filters').fadeOut(200, 'linear');

                    //             var marker = this;
                    //             response = allItems[`${tourId}:${feature.properties.id}`]
                    //             if (response == undefined) {
                    //                 $.post('walking-tour/index/get-item', { id: feature.properties.id, tour: tourId }, function (response) {
                    //                     allItems[`${tourId}:${feature.properties.id}`] = response;
                    //                     featureOnclickAction(response, layer, marker, itemIDList, value, tourId);
                    //                 })
                    //             } else {
                    //                 featureOnclickAction(response, layer, marker, itemIDList, value, tourId);
                    //             }

                    //         });

                    //     }
                    // });
                    markerData[tourId].allMarker = markerList;
                    // var json_content = response.features;
                    // var pointList = [];
                    // for (var i = 0; i < json_content.length; i++) {
                    //     lat = json_content[i].geometry.coordinates[1];
                    //     lng = json_content[i].geometry.coordinates[0];
                    //     var point = new L.LatLng(lat, lng);
                    //     pointList[i] = point;
                    // }
                    // getOverallPath(pointList, key).then((data) => {
                    //     var path = data["features"][0]["geometry"]["coordinates"];
                    //     path = orderCoords(path);
                    //     for (var p of path) {
                    //         walkingPath.push(p);
                    //     }
                    //     var tourPolyline = new L.Polyline(walkingPath, {
                    //         color: value["Color"],
                    //         weight: 3,
                    //         opacity: 1,
                    //         smoothFactor: 1
                    //     });
                    //     markerData[tourId].walkingPath = tourPolyline;
                    //     resolve()
                    // });
                });
            })
            Promise.all(requests).then(() => {
                createCustomCSS();
                // doFilters();
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
        map.flyTo([coor[1], coor[0]], MAP_MAX_ZOOM);

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
