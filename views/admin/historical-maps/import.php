<?php echo head(array('title' => 'Import from Allmaps', 'bodyclass' => 'historical-maps import')); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps')); ?>">Back to Historical Maps</a></p>
    <p>Find a IIIF source, georeference it in <a href="https://editor.allmaps.org/" target="_blank" rel="noopener noreferrer">Allmaps Editor</a>, then paste its exported annotation URL here.</p>
    <?php if ($error): ?><p class="error" role="alert"><?php echo html_escape($error); ?></p><?php endif; ?>
    <?php if (!$images): ?>
    <form method="post" action="<?php echo html_escape(url('historical-maps/import')); ?>">
        <?php echo $csrf; ?><input name="step" type="hidden" value="preview">
        <div class="field"><div class="two columns alpha"><label for="annotation-url">Allmaps annotation URL</label></div>
            <div class="inputs five columns omega"><input id="annotation-url" name="annotation_url" type="url" required maxlength="2048" value="<?php echo html_escape($values['annotation_url']); ?>">
                <p class="explanation">Use an HTTPS Annotation or AnnotationPage URL. Version-qualified URLs containing @ are supported. This imports a saved calibration snapshot, not a live editing connection.</p></div></div>
        <input class="submit big green button" type="submit" value="Preview maps">
    </form>
    <?php else: ?>
    <h2>Choose maps to import</h2>
    <p>Source: <?php echo html_escape($values['annotation_url']); ?></p>
    <p><?php echo count($images); ?> map(s) found. Select the maps you want; each map becomes a separate catalog record. Images are checked before any selected map is published.</p>
    <form method="post" action="<?php echo html_escape(url('historical-maps/import')); ?>">
        <?php echo $csrf; ?><input name="step" type="hidden" value="import">
        <input name="preview_token" type="hidden" value="<?php echo html_escape($values['preview_token']); ?>">
        <?php foreach ($images as $index => $image): $snapshot = json_decode($image['source_snapshot'], true); ?>
        <fieldset class="field">
            <legend>Map <?php echo $index + 1; ?></legend>
            <p><label><input name="map_indexes[]" type="checkbox" value="<?php echo $index; ?>" <?php if (isset($selectedIndexes) ? in_array((string) $index, $selectedIndexes, true) : count($images) === 1): ?>checked<?php endif; ?>> Import this map</label></p>
            <p><label for="map-title-<?php echo $index; ?>">Title</label>
                <input id="map-title-<?php echo $index; ?>" name="titles[<?php echo $index; ?>]" type="text" maxlength="255" value="<?php echo html_escape($image['title']); ?>"></p>
            <p><?php echo $image['image_width']; ?> × <?php echo $image['image_height']; ?> pixels · <?php echo html_escape($image['transformation']); ?></p>
            <p><?php echo html_escape($snapshot['annotation']['id']); ?></p>
            <p><?php echo html_escape($image['image_service']); ?></p>
        </fieldset>
        <?php endforeach; ?>
        <input class="submit big green button" type="submit" value="Import selected maps">
    </form>
    <p><a href="<?php echo html_escape(url('historical-maps/import')); ?>">Preview another annotation URL</a></p>
    <?php endif; ?>
</div>
<?php echo foot(); ?>
