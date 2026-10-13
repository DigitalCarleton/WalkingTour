<div role="main">
    <section id="dual-map" aria-label="Historical and modern maps"
        data-editor-mode="<?php echo $editorMode ? 'true' : 'false'; ?>"
        data-map-id="<?php echo $editorMode ? (int) $map['id'] : ''; ?>"
        data-catalog-url="<?php echo html_escape(public_url('walking-tour/index/historical-maps')); ?>"
        data-search-endpoint="<?php echo html_escape(get_option('walking_tour_search_endpoint') ?: 'https://photon.komoot.io/api/'); ?>"
        data-api-base="<?php echo html_escape(public_url('walking-tour/index/')); ?>">
        <div class="dual-map-toolbar">
            <p>Explore the same places across two maps.</p>
            <div class="dual-map-actions">
                <button type="button" id="dual-map-fit" disabled>Fit the map</button>
                <button type="button" id="map-list-toggle" aria-controls="historical-map-catalog" aria-expanded="true">Hide map list</button>
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
                <?php if ($editorMode): ?>
                <section id="historical-place-details" aria-label="Selected place" hidden>
                    <h3 id="historical-place-title"></h3><p id="historical-place-description"></p>
                    <p id="historical-place-status" role="status"></p>
                    <a id="historical-place-link">View Item</a>
                    <button type="button" id="historical-place-locate">Locate on both maps</button>
                    <div id="historical-place-editor" hidden>
                        <p>Adjust this Item on this historical image. Allmaps calibration and modern coordinates stay fixed.</p>
                        <button type="button" id="historical-place-adjust">Adjust historical position</button>
                        <div id="historical-position-draft" hidden>
                            <p>Click the historical image, drag the draft marker, or enter original-image pixels. Placement outside the mask is an explicit editorial override.</p>
                            <label for="historical-pixel-x">Image X</label><input id="historical-pixel-x" type="number" step="any" min="0">
                            <label for="historical-pixel-y">Image Y</label><input id="historical-pixel-y" type="number" step="any" min="0">
                            <button type="button" id="historical-position-save">Save position</button>
                            <button type="button" id="historical-position-cancel">Cancel adjustment</button>
                        </div>
                        <button type="button" id="historical-position-reset">Reset to estimated position</button>
                        <label for="historical-place-visibility">Display on this map</label>
                        <select id="historical-place-visibility"><option value="default">Default</option><option value="show">Show</option><option value="hide">Hide</option></select>
                        <button type="button" id="historical-visibility-save">Save display choice</button>
                        <label for="historical-place-note">Editorial note</label><textarea id="historical-place-note" maxlength="2000"></textarea>
                        <button type="button" id="historical-note-save">Save note</button>
                        <p id="historical-decision-status" role="status" aria-live="polite"></p>
                        <button type="button" id="historical-decision-reload" hidden>Reload latest decisions (discard draft)</button>
                    </div>
                </section>
                <label id="historical-hidden-label" hidden><input id="historical-show-hidden" type="checkbox"> Include hidden places (editor)</label>
                <?php endif; ?>
                <label for="historical-place-select">Find a place</label>
                <select id="historical-place-select"><option value="">Choose a place</option></select>
                <div id="historical-map-sources"></div>
            </aside>
        </div>
    </section>
</div>
