<?php echo head(array('title' => 'Upload a Historical Map', 'bodyclass' => 'historical-maps upload')); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps')); ?>">Back to Historical Maps</a> ·
        <a href="<?php echo html_escape(url('historical-maps/add')); ?>">Import from IIIF instead</a></p>
    <?php if ($error): ?><p class="error" role="alert"><?php echo html_escape($error); ?> Choose the image again before retrying.</p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="<?php echo html_escape(url('historical-maps/upload')); ?>">
        <?php echo $csrf; ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo WalkingTour_HistoricalMapUpload::MAX_BYTES; ?>">
        <div class="field"><div class="two columns alpha"><label for="map-image">Image</label></div>
            <div class="inputs five columns omega"><input id="map-image" name="image" type="file" accept="image/jpeg,image/png" required>
                <p class="explanation">JPEG or PNG, up to 20 MB and 25 million pixels (20,000 pixels per side). Server limits may be lower.</p></div></div>
        <div class="field"><div class="two columns alpha"><label for="map-title">Title</label></div>
            <div class="inputs five columns omega"><input id="map-title" name="title" type="text" maxlength="255" required value="<?php echo html_escape($values['title']); ?>"></div></div>
        <div class="field"><div class="two columns alpha"><label for="source-url">Provenance source URL</label></div>
            <div class="inputs five columns omega"><input id="source-url" name="source_url" type="url" value="<?php echo html_escape($values['source_url']); ?>">
                <p class="explanation">Optional HTTPS link to the source or catalog record. No IIIF address is needed.</p></div></div>
        <p>The image will be visible in the public map library. It starts without control points. Upload the original image before placing points; the saved image cannot be replaced.</p>
        <input class="submit big green button" type="submit" value="Upload image">
    </form>
</div>
<?php echo foot(); ?>
