<?php echo head(array('title' => 'Historical Maps', 'bodyclass' => 'map historical-maps')); ?>
<?php echo link_to_home_page('<span class="screen-reader-text">Home</span>', array('id' => 'home-button')); ?>
<div role="main">
    <section id="dual-map" aria-label="Historical and modern maps"
        data-locations-url="<?php echo html_escape(src('omeka-locations', 'data', 'geojson')); ?>"
        data-catalog-url="<?php echo html_escape(url('walking-tour/index/historical-maps')); ?>"
        data-search-endpoint="<?php echo html_escape(get_option('walking_tour_search_endpoint') ?: 'https://photon.komoot.io/api/'); ?>"
        data-api-base="<?php echo html_escape(url('walking-tour/index/')); ?>">
        <div class="dual-map-toolbar">
            <p>Explore the same places across two maps.</p>
            <button type="button" id="map-calibration-undo" hidden>Undo calibration change</button>
            <button type="button" id="map-list-toggle" aria-controls="historical-map-catalog" aria-expanded="true">Hide map list</button>
        </div>
        <div id="map-edit-panel" class="map-edit-panel" hidden>
            <p id="map-edit-status" role="status" aria-live="polite"></p>
            <div id="map-point-adjustments" class="map-point-adjustments" hidden>
                <label>Image X<input id="map-point-x" type="number" step="1" min="0"></label>
                <label>Image Y<input id="map-point-y" type="number" step="1" min="0"></label>
                <label>Longitude<input id="map-point-longitude" type="number" step="0.00001" min="-180" max="180"></label>
                <label>Latitude<input id="map-point-latitude" type="number" step="0.00001" min="-85" max="85"></label>
                <button type="button" id="map-point-apply">Apply coordinates</button>
            </div>
            <div class="map-edit-actions">
                <button type="button" id="map-edit-save" disabled>Save</button>
                <button type="button" id="map-edit-confirm" hidden>Confirm pair</button>
                <button type="button" id="map-edit-adjust" hidden>Adjust pair</button>
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
