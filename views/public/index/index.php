<?php queue_js_file('leaflet.markercluster'); ?>
<?php queue_js_string('
        var hackRemember = L.DomUtil.TRANSITION;
        L.DomUtil.TRANSITION = false;
        L.DomUtil.TRANSITION = hackRemember;
'); ?>
<?php echo head(array('bodyclass' => 'map')); ?>
    <?php echo link_to_home_page('<span class="screen-reader-text">Home</span>', array('id' => 'home-button')); ?>
    <div id="dialog"></div>
    <div role="main">
        <div class = "map-title">
            <h1 id="marker-count"></h1>
            <a href="#" id="toggle-map-button" class="on" style="display: none;"><span class="screen-reader-text">Map On</span></a>
            <a id="filter-button"><span class="screen-reader-text">Filters</span></a>
        </div>
        <div id="filters">
            <div id="tour-type-div">
                <div class="tour-filter">
                    <p>Tours</p>
                    <a href="#" class="button" id="tour-confirm-button">Choose tour</a>
                </div>
                <label class="on"><input type="checkbox" name="place-type-all" value="0" checked="checked"/> All Tours</label>
                <?php foreach ($this->tour_types['id'] as $tour_type_id => $tour_type): ?>
                <label class="label<?php echo $tour_type_id ?>"><input type="checkbox" name="place-type" value="<?php echo $tour_type_id; ?>"/> <?php echo $tour_type; ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div id="first-time">
            <div class="overlay"></div>
            <div class="tooltip">
                <p><?php echo get_option('walking_tour_filter_tooltip'); ?></p>
                <button class="button"><?php echo get_option('walking_tour_tooltip_button'); ?></button>
            </div>
            <div class="tooltip-locate">
                <p>Click here to see your position if you are on location.</p>
                <button class="button"><?php echo get_option('walking_tour_tooltip_button'); ?></button>
            </div>
        </div>

        <div id ="info-panel-container" style="display: none;">
            <div id="info-panel">
                <div style="height: 100%;display: flex;flex-direction: column;">
                <a href="#" class="back-button">Back to Map</a>
                
                <div class='panel-title'>
                    <a href="#" class="prev-button"></a>
                    <h1 id="info-panel-name"></h1>
                    <a href="#" class="next-button"></a>
                </div>
                <div id="info-panel-content"></div>
                </div>
            </div>
        </div>

        <div>
        <div id="map">
            <a href="#" id="locate-button"><span class="screen-reader-text">Make me center</span></a>
        </div>

        </div>
    </div>
    <?php echo foot(); ?>
