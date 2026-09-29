<?php echo head(array('bodyclass' => 'map')); ?>
<?php echo link_to_home_page('<span class="screen-reader-text">Home</span>', array('id' => 'home-button')); ?>
<div id="dialog"></div>
<div role="main">
    <?php if (false): // Legacy tour controls are retained for future restoration. ?>
    <div class="map-title">
        <h1 id="marker-count"></h1>
        <a href="#" id="toggle-map-button" class="on" style="display: none;"><span class="screen-reader-text">Map
                On</span></a>
        <a id="filter-button"><span class="screen-reader-text">Filters</span></a>
    </div>
    <div id="filters">
        <div id="tour-type-div">
            <div class="tour-filter">
                <p>Tours</p>
                <a href="#" class="button" id="tour-confirm-button">Choose tour</a>
            </div>
            <label class="on"><input type="checkbox" name="place-type-all" value="0" checked="checked" /> All
                Tours</label>
            <?php foreach ($this->tour_types['id'] as $tour_type_id => $tour_type): ?>
                <label class="label<?php echo $tour_type_id ?>"><input type="checkbox" name="place-type"
                        value="<?php echo $tour_type_id; ?>" /> <?php echo $tour_type; ?></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div id="first-time">
        <div class="overlay"></div>
        <div class="tooltip">
            <p><?php echo get_option('walking_tour_filter_tooltip'); ?></p>
            <button class="button"><?php echo html_escape(get_option('walking_tour_tooltip_button') ?: 'OK'); ?></button>
        </div>
        <div class="tooltip-locate">
            <p>Click here to see your position if you are on location.</p>
            <button class="button"><?php echo html_escape(get_option('walking_tour_tooltip_button') ?: 'OK'); ?></button>
        </div>
    </div>

    <div id="info-panel-container" style="display: none;">
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

    <?php endif; ?>
    <section id="dual-map" aria-label="Historical and modern maps"
        data-locations-url="<?php echo html_escape(src('omeka-locations', 'data', 'geojson')); ?>"
        data-catalog-url="<?php echo html_escape(url('walking-tour/index/historical-maps')); ?>"
        data-search-endpoint="<?php echo html_escape(get_option('walking_tour_search_endpoint') ?: 'https://photon.komoot.io/api/'); ?>"
        data-api-base="<?php echo html_escape(url('walking-tour/index/')); ?>">
        <div class="dual-map-toolbar">
            <p>Explore the same places across two maps.</p>
            <button type="button" id="map-list-toggle" aria-controls="historical-map-catalog" aria-expanded="true">Hide map list</button>
        </div>
        <div id="map-edit-panel" class="map-edit-panel" hidden>
            <p id="map-edit-status" role="status" aria-live="polite"></p>
            <div class="map-edit-actions">
                <button type="button" id="map-edit-save" disabled>Save</button>
                <button type="button" id="map-edit-undo" hidden>Undo last vertex</button>
                <button type="button" id="map-edit-reload" hidden>Reload latest data</button>
                <button type="button" id="map-edit-cancel">Cancel</button>
            </div>
        </div>
        <div class="dual-map-layout">
            <section class="dual-map-pane" aria-labelledby="historical-map-heading">
                <div class="dual-map-heading">
                    <h2 id="historical-map-heading">Historical map</h2>
                    <button type="button" id="historical-map-fit" disabled>View full image</button>
                </div>
                <div id="historical-map" role="region" aria-label="Historical image. Use arrow keys to pan and plus or minus to zoom."></div>
                <div class="dual-map-feedback">
                    <p id="historical-map-status" role="status" aria-live="polite">Loading historical maps…</p>
                    <button type="button" id="historical-map-retry" hidden>Retry</button>
                </div>
            </section>
            <section class="dual-map-pane" aria-labelledby="modern-map-heading">
                <div class="dual-map-heading">
                    <h2 id="modern-map-heading">Modern map</h2>
                    <button type="button" id="control-points-fit" disabled>View map area</button>
                </div>
                <div id="map" role="region" aria-label="Modern map with historical-map control points">
                    <a href="#" id="locate-button" aria-label="Show my location"><span class="screen-reader-text">Show my location</span></a>
                </div>
                <p id="omeka-locations-status" class="dual-map-feedback" role="status" hidden>Loading Omeka locations…</p>
                <p id="modern-map-status" class="dual-map-feedback" role="status">Shared control points for the selected historical map</p>
            </section>
            <aside id="historical-map-catalog" aria-labelledby="map-catalog-heading">
                <h2 id="map-catalog-heading">Map library</h2>
                <div id="historical-map-list"></div>
                <p class="control-point-key"><span aria-hidden="true">●</span> Control points</p>
                <p class="mask-key"><span aria-hidden="true">▱</span> Map footprint / saved mask</p>
                <p class="annotation-key"><span aria-hidden="true">●</span> Estimated annotations</p>
                <p id="map-editor-access" role="status"></p>
                <p>Matching numbers identify the same place on both maps.</p>
                <p id="control-point-selection" role="status" aria-live="polite">Select a numbered point to locate its pair.</p>
                <div id="historical-map-sources"></div>
            </aside>
        </div>
    </section>
</div>
<?php echo foot(); ?>
