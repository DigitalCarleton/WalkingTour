<?php $address = ''; ?>
<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_filter_tooltip"><?php echo __('Filter Tooltip Text'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php echo __('The text that highlights the map filters the first time a user visits the map.'); ?>
        </p>
        <div class="input-block">
            <textarea cols="50" name="walking_tour_filter_tooltip" id="walking_tour_filter_tooltip"
                rows="4"><?php echo get_option('walking_tour_filter_tooltip'); ?></textarea>
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_tooltip_button"><?php echo __('Tooltip Button Text'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('The text that appears on the button to close the tooltip.'); ?></p>
        <div class="input-block">
            <input type="text" name="walking_tour_tooltip_button" id="walking_tour_tooltip_button"
                value="<?php echo get_option('walking_tour_tooltip_button'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_detail_button"><?php echo __('Exhibit Button Text'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('The text that appears on the button to view item details.'); ?></p>
        <div class="input-block">
            <input type="text" name="walking_tour_detail_button" id="walking_tour_detail_button"
                value="<?php echo get_option('walking_tour_detail_button'); ?>" />
        </div>
    </div>
</div>

<?php if (plugin_is_active('ExhibitBuilder')): ?>
    <div class="field">
        <div class="two columns alpha">
            <label for="walking_tour_exhibit_button"><?php echo __('Exhibit Button Text'); ?></label>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation"><?php echo __('The text that appears on the button to view the exhibit.'); ?></p>
            <div class="input-block">
                <input type="text" name="walking_tour_exhibit_button" id="walking_tour_exhibit_button"
                    value="<?php echo get_option('walking_tour_exhibit_button'); ?>" />
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_center"><?php echo __('Map Center Coordinates'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the default starting place.'); ?></p>
        <div class="input-block">
            <input type="text" name="walking_tour_center" id="walking_tour_center"
                value="<?php echo get_option('walking_tour_center'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_default_zoom"><?php echo __('Map Default Zoom'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the default zoom of the map.'); ?></p>
        <div class="input-block">
            <input type="text" name="walking_tour_default_zoom" id="walking_tour_default_zoom"
                value="<?php echo get_option('walking_tour_default_zoom'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_max_zoom"><?php echo __('Map Max Zoom'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the max zoom of the map.'); ?></p>
        <div class="input-block">
            <input type="text" name="walking_tour_max_zoom" id="walking_tour_max_zoom"
                value="<?php echo get_option('walking_tour_max_zoom'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_min_zoom"><?php echo __('Map Min Zoom'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the min zoom of the map.'); ?></p>
        <div class="input-block">
            <input type="text" name="walking_tour_min_zoom" id="walking_tour_min_zoom"
                value="<?php echo get_option('walking_tour_min_zoom'); ?>" />
        </div>
    </div>
</div>
<!-- [41.9001702, 12.4698422] -->
<input type="hidden" name="geolocation[latitude]" value="<?php echo $lat; ?>">
<input type="hidden" name="geolocation[longitude]" value="<?php echo $lng; ?>">
<input type="hidden" name="geolocation[zoom_level]" value="<?php echo $zoom; ?>">
<input type="hidden" name="geolocation[map_type]" value="Leaflet">

<div class="field">
    <div id="location_form" class="two columns alpha">
        <label for="geolocation_address"><?php echo __('Find a Location by Address:'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <input type="text" name="geolocation[address]" id="geolocation_address" value="<?php echo $address; ?>">
        <button type="button" name="geolocation_find_location_by_address"
            id="geolocation_find_location_by_address"><?php echo __('Find'); ?></button>
    </div>
</div>
<div id="omeka-map-form" class="geolocation-map"></div>
<?php
echo js_tag('geocoder');
$geocoder = json_encode(get_option('geolocation_geocoder'));
?>
<script>
    var omekaGeolocationForm = new OmekaMapForm('omeka-map-form', { latitude: 41.9001702, longitude: 12.4698422, zoomLevel: 14 }, { "basemap": "<?php echo get_option('geolocation_basemap'); ?>", "form": { "id": "location_form", "posted": false }, 'confirmLocationChange': true });
    var geocoder = new OmekaGeocoder(<?php echo $geocoder; ?>);

    var curCenter = <?php echo get_option('walking_tour_center'); ?>;
    console.log(curCenter);
    if (curCenter.length != 0) {
        omekaGeolocationForm.addMarker({
            'lat': curCenter[0],
            'lng': curCenter[1]
        })
    }

    jQuery(document).ready(function () {
        jQuery("#omeka-map-form").on('click', function (event) {
            // var point = event.latlng.wrap();
            var lat = jQuery('input[name = "geolocation[latitude]"]').val();
            var lng = jQuery('input[name = "geolocation[longitude]"]').val();
            console.log(lat, lng)
            jQuery('#walking_tour_center').val(`[${lat}, ${lng}]`);
        })
        // Make the Find By Address button lookup the geocode of an address and add a marker.
        jQuery('#geolocation_find_location_by_address').on('click', function (event) {
            event.preventDefault();
            var address = jQuery('#geolocation_address').val();
            geocoder.geocode(address).then(function (coords) {
                var point = L.latLng(coords);
                console.log(point)
                // jQuery('#walking_tour_center').val(`[${point.lat}, ${point.lng}]`);
                var marker = omekaGeolocationForm.setMarker(point);
                if (marker === false) {
                    jQuery('#geolocation_address').val('');
                    jQuery('#geolocation_address').focus();
                }
                jQuery('#walking_tour_center').val(`[${point.lat}, ${point.lng}]`);
            }, function () {
                alert('Error: "' + address + '" was not found!');
            });
        });

        // Make the return key in the geolocation address input box click the button to find the address.
        jQuery('#geolocation_address').on('keydown', function (event) {
            if (event.which == 13) {
                event.preventDefault();
                jQuery('#geolocation_find_location_by_address').click();
            }
        });
    });
</script>