<?php echo head(array('title' => 'Historical Maps', 'bodyclass' => 'map historical-maps')); ?>
<?php echo link_to_home_page('<span class="screen-reader-text">Home</span>', array('id' => 'home-button')); ?>
<div role="main">
    <section id="dual-map" aria-label="Historical and modern maps"
        data-catalog-url="<?php echo html_escape(url('walking-tour/index/historical-maps')); ?>"
        data-search-endpoint="<?php echo html_escape(get_option('walking_tour_search_endpoint') ?: 'https://photon.komoot.io/api/'); ?>"
        data-api-base="<?php echo html_escape(url('walking-tour/index/')); ?>">
        <div class="dual-map-toolbar">
            <p>Explore the same places across two maps.</p>
            <button type="button" id="map-list-toggle" aria-controls="historical-map-catalog" aria-expanded="true">Hide map list</button>
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
                    <button type="button" id="map-area-fit" disabled>View map area</button>
                </div>
                <div id="map" role="region" aria-label="Modern geographic map">
                    <a href="#" id="locate-button" aria-label="Show my location"><span class="screen-reader-text">Show my location</span></a>
                </div>
                <p id="omeka-locations-status" class="dual-map-feedback" role="status" hidden>Loading Omeka locations…</p>
                <p id="modern-map-status" class="dual-map-feedback" role="status">Explore the area beside the historical image.</p>
            </section>
            <aside id="historical-map-catalog" aria-labelledby="map-catalog-heading">
                <h2 id="map-catalog-heading">Map library</h2>
                <div id="historical-map-list"></div>
                <div id="historical-map-sources"></div>
            </aside>
        </div>
    </section>
</div>
<?php echo foot(); ?>
