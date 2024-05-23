<?php $view = get_view();
$center = get_option('walking_tour_center');
if (strlen($center) != 0) {
    $center = explode(",", $center);
    $lat = (float) $center[0];
    $lng = (float) $center[1];
} else {
    $lat = 0.0;
    $lng = 0.0;
}
?>
<fieldset>
    <legend><?php echo __('Help Text Settings'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <label for="walking_tour_filter_tooltip"><?php echo __('First Time User Help Text'); ?></label>
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
            <label for="walking_tour_detail_button"><?php echo __('Item Button Text'); ?></label>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('The text that appears on each tour stop on the button to view item details.'); ?>
            </p>
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
                <p class="explanation">
                    <?php echo __('The text that appears on each tour stop on the button to view a linked exhibit.'); ?>
                </p>
                <div class="input-block">
                    <input type="text" name="walking_tour_exhibit_button" id="walking_tour_exhibit_button"
                        value="<?php echo get_option('walking_tour_exhibit_button'); ?>" />
                </div>
            </div>
        </div>
    <?php endif; ?>
</fieldset>

<fieldset>
    <legend><?php echo __('Map Settings'); ?></legend>


    <div class="field">
        <div id="location_form" class="two columns alpha">
            <label for="walking_tour_center"><?php echo __('Default Map Center Coordinates'); ?></label>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __("Controls the default starting place."); ?>
                <?php echo __("Search for an address or place and click Find, or enter coordinates in the format latitude, longitude."); ?>
            </p>
            <div style="display: flex; column-gap: 5px;">
                <input type="text" name="walking_tour_center" id="walking_tour_center"
                    value="<?php echo get_option('walking_tour_center'); ?>" />
                <button type="button" name="geolocation_find_location_by_address"
                    id="geolocation_find_location_by_address"><?php echo __('Find'); ?></button>
            </div>
        </div>
    </div>
    <div id="omeka-map-form" class="geolocation-map" style="margin-bottom: 20px;"></div>

    <div class="field">
        <div class="two columns alpha">
            <label for="walking_tour_default_zoom"><?php echo __('Map Default Zoom'); ?></label>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('Controls the default zoom of the map. Allowable value range from 0 (world level) to 18 (street level).'); ?>
            </p>
            <div class="input-block">
                <input type="text" name="walking_tour_default_zoom" id="walking_tour_default_zoom"
                    value="<?php echo get_option('walking_tour_default_zoom'); ?>" />
            </div>
        </div>
    </div>

    <div class="field">
        <div class="two columns alpha">
            <label for="walking_tour_auto_fit"><?php echo __('Auto-fit to Locations'); ?></label>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('If checked, the default location and zoom settings above '
                    . 'will be ignored on the map. Instead, the map will '
                    . 'automatically pan and zoom to fit all the locations added to walking tours.');
                ?>
            </p>
            <div class="input-block">
                <?php
                echo $view->formCheckbox(
                    'walking_tour_auto_fit',
                    true,
                    array('checked' => (boolean) get_option('walking_tour_auto_fit'))
                );
                ?>
            </div>
        </div>
    </div>

    <div class="field">
        <div class="two columns alpha">
            <label for="walking_tour_min_zoom"><?php echo __('Map Min Zoom'); ?></label>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('Controls the minimum zoom level relative to the default zoom. Eg: if default zoom is 14 and Min Zoom stop is set to 1, the minimum zoom level of the map will be 13.'); ?>
            </p>
            <div class="input-block">
                <input type="text" name="walking_tour_min_zoom" id="walking_tour_min_zoom"
                    value="<?php echo get_option('walking_tour_min_zoom'); ?>" />
            </div>
        </div>
    </div>

    <div class="field">
        <div class="two columns alpha">
            <label for="walking_tour_max_zoom"><?php echo __('Map Max Zoom'); ?></label>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('Controls the maximum zoom level relative to the default zoom. Eg: if default zoom is 14 and Max Zoom stop is set to 1, the maximum zoom level of the map will be 15.'); ?>
            </p>
            <div class="input-block">
                <input type="text" name="walking_tour_max_zoom" id="walking_tour_max_zoom"
                    value="<?php echo get_option('walking_tour_max_zoom'); ?>" />
            </div>
        </div>
    </div>

    <!-- [41.9001702, 12.4698422] -->
    <input type="hidden" name="geolocation[latitude]" value="<?php echo $lat; ?>">
    <input type="hidden" name="geolocation[longitude]" value="<?php echo $lng; ?>">
    <input type="hidden" name="geolocation[zoom_level]" value="<?php echo $zoom; ?>">
    <input type="hidden" name="geolocation[map_type]" value="Leaflet">

</fieldset>

<?php
echo js_tag('geocoder');
$geocoder = json_encode(get_option('geolocation_geocoder'));
?>

<script>
    var curCenter = "<?php echo get_option('walking_tour_center'); ?>".split(',');
    var omekaGeolocationForm = new OmekaMapForm('omeka-map-form', { latitude: curCenter[0], longitude: curCenter[1], zoomLevel: 14 }, { "basemap": "<?php echo get_option('geolocation_basemap'); ?>", "form": { "id": "location_form", "posted": false }, 'confirmLocationChange': true });
    var geocoder = new OmekaGeocoder(<?php echo $geocoder; ?>);

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
            if (lat != null && lng != null) {
                console.log(lat, lng);
                jQuery('#walking_tour_center').val(`${lat}, ${lng}`);
            }
        })
        // Make the Find By Address button lookup the geocode of an address and add a marker.
        jQuery('#geolocation_find_location_by_address').on('click', function (event) {
            event.preventDefault();
            var address = jQuery('#walking_tour_center').val();
            geocoder.geocode(address).then(function (coords) {
                var point = L.latLng(coords);
                console.log(point)
                var marker = omekaGeolocationForm.setMarker(point);
                if (marker === false) {
                    jQuery('#walking_tour_center').val('');
                    jQuery('#walking_tour_center').focus();
                }
                jQuery('#walking_tour_center').val(`${point.lat}, ${point.lng}`);
            }, function () {
                alert('Error: "' + address + '" was not found!');
            });
        });

        // Make the return key in the geolocation address input box click the button to find the address.
        jQuery('#walking_tour_center').on('keydown', function (event) {
            if (event.which == 13) {
                event.preventDefault();
                jQuery('#geolocation_find_location_by_address').click();
            }
        });
    });
</script>