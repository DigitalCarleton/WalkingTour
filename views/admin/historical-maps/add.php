<?php echo head(array('title' => 'Add a Historical Map', 'bodyclass' => 'historical-maps add')); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps')); ?>">Back to Historical Maps</a></p>
    <?php if ($error): ?><p class="error" role="alert"><?php echo html_escape($error); ?></p><?php endif; ?>
    <form method="post" action="<?php echo html_escape(url('historical-maps/add')); ?>">
        <?php echo $csrf; ?>
        <div class="field"><div class="two columns alpha"><label for="iiif-url">IIIF source URL</label></div>
            <div class="inputs five columns omega"><input id="iiif-url" name="iiif_url" type="url" required value="<?php echo html_escape($values['iiif_url']); ?>">
                <p class="explanation">Use an HTTPS Presentation API 2 or 3 manifest, or an Image API 1, 2, or 3 info.json URL.</p></div></div>
        <div class="field"><div class="two columns alpha"><label for="image-number">Image number</label></div>
            <div class="inputs five columns omega"><input id="image-number" name="image_number" type="number" min="1" max="1000" required value="<?php echo html_escape($values['image_number']); ?>">
                <p class="explanation">Choose the image's position in the manifest, starting at 1. Use 1 for a single image URL. Presentation API 2 uses the first sequence.</p></div></div>
        <div class="field"><div class="two columns alpha"><label for="map-title">Title</label></div>
            <div class="inputs five columns omega"><input id="map-title" name="title" type="text" maxlength="255" value="<?php echo html_escape($values['title']); ?>">
                <p class="explanation">Leave blank to use the source title. The new map starts without control points.</p></div></div>
        <input class="submit big green button" type="submit" value="Add historical map">
    </form>
</div>
<?php echo foot(); ?>
