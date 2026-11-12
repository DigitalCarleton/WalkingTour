<div role="main">
    <section id="historical-map-browser" aria-label="Historical Maps"
        data-catalog-url="<?php echo html_escape(public_url('walking-tour/index/historical-maps')); ?>"
        data-api-base="<?php echo html_escape(public_url('walking-tour/index/')); ?>">
        <div class="historical-browser-toolbar">
            <p>Choose a place to explore it across historical maps.</p>
            <button type="button" id="browser-reload">Reload maps and places</button>
        </div>
        <div class="historical-browser-layout">
            <section class="historical-browser-pane" aria-labelledby="browser-map-title">
                <div class="historical-browser-heading">
                    <h2 id="browser-map-title">Historical map</h2>
                    <button type="button" id="browser-fit" disabled>View full map</button>
                </div>
                <div id="browser-map" role="region" aria-label="Historical map. Use arrow keys to pan and plus or minus to zoom."></div>
                <p id="browser-image-status" role="status" aria-live="polite"></p>
                <button type="button" id="browser-image-retry" hidden>Retry image</button>
            </section>
            <aside class="historical-browser-sidebar" aria-label="Places and map library">
                <label for="browser-place">Find a place</label>
                <select id="browser-place" disabled><option value="">Choose a place</option></select>
                <button type="button" id="browser-clear" hidden>Clear place selection</button>
                <section id="browser-place-details" aria-labelledby="browser-place-title" hidden>
                    <h2 id="browser-place-title"></h2>
                    <img id="browser-place-image" alt="" hidden>
                    <p id="browser-place-description"></p>
                    <a id="browser-place-link">View full Item</a>
                    <p id="browser-position-status" role="status" aria-live="polite"></p>
                </section>
                <section aria-labelledby="browser-library-title">
                    <h2 id="browser-library-title">Map library</h2>
                    <p id="browser-library-status" role="status" aria-live="polite">Loading maps and places…</p>
                    <div id="browser-map-list"></div>
                </section>
                <div id="browser-sources"></div>
            </aside>
        </div>
    </section>
</div>
