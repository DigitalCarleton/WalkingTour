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
        <h1 id="marker-count"></h1>
        <a href="#" id="toggle-map-button" class="on" style="display: none;"><span class="screen-reader-text">Map On</span></a>
        <a id="filter-button"><span class="screen-reader-text">Filters</span></a>
        <div id="filters">
            <h1>Select Filters</h1>
            <label for="map-coverage">Map Era</label>
            <select id="map-coverage" name="map-coverage">
                <option value="0">All Map Eras</option>
                <?php foreach ($this->map_coverages as $map_coverage): ?>
                <option value="<?php echo $map_coverage; ?>"><?php echo $map_coverage; ?></option>
                <?php endforeach; ?>
            </select>
            <label for="tour-type">Tours (in progress)</label>
            <select id="tour-type" name="tour-type">
                <option value="0">All Tours</option>
                <?php foreach ($this->tour_types as $tour_type_id => $tour_type): ?>
                <option value="<?php echo $tour_type_id; ?>"><?php echo $tour_type; ?></option>
                <?php endforeach; ?>
            </select>
            <div id="place-type-div" style="display: none;">
                <p>Place Types</p>
                <label class="on"><input type="checkbox" name="place-type-all" value="0" checked="checked"/> All Place Types</label>
                <?php foreach ($this->place_types as $place_type): ?>
                <label><input type="checkbox" name="place-type" value="<?php echo htmlspecialchars($place_type); ?>" /> <?php echo $place_type; ?></label>
                <?php endforeach; ?>
            </div>
            <div id="event-type-div" style="display: none;">
                <p>Event Types</p>
                <label class="on"><input type="checkbox" name="event-type-all" value="0" checked="checked"/> All Event Types</label>
                <?php foreach ($this->event_types as $event_type): ?>
                <label><input type="checkbox" name="event-type" value="<?php echo htmlspecialchars($event_type); ?>" /> <?php echo $event_type; ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div id="first-time">
            <div class="overlay"></div>
            <div class="tooltip">
                <p><?php echo get_option('mall_map_filter_tooltip'); ?></p>
                <button class="button"><?php echo get_option('mall_map_tooltip_button'); ?></button>
            </div>
        </div>

        <div id ="info-panel-container" style="display: none;">
            <div id="info-panel">
                <div style="height: 100%;display: flex;flex-direction: column;">
                <a href="#" class="back-button">Back to Map</a>
                <h1 id="info-panel-name"></h1>
                <div id="info-panel-content"></div>
                </div>
            </div>
        </div>

        <div>
        <div id="map">
            <a href="#" id="locate-button" class="disabled"><span class="screen-reader-text">Make me center</span></a>
        </div>

        </div>
    </div>
    <?php echo foot(); ?>
