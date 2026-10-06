<?php echo head(array('title' => $map['title'], 'bodyclass' => 'historical-maps show')); ?>
<?php echo flash(); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps')); ?>">Back to Historical Maps</a></p>
    <p><a class="button green" href="<?php echo html_escape(public_url('walking-tour') . '?map_id=' . $map['id']); ?>">Open map editor</a></p>
    <dl>
        <dt>Original image size</dt><dd><?php echo $map['image_width']; ?> × <?php echo $map['image_height']; ?> pixels</dd>
        <dt>Control points</dt><dd><?php echo count($map['control_points']); ?></dd>
        <dt>Annotations</dt><dd><?php echo count($map['annotations']); ?></dd>
        <dt>Calibration revision</dt><dd><?php echo $map['calibration_revision']; ?></dd>
        <dt>IIIF image service</dt><dd><?php echo html_escape($map['image_service']); ?></dd>
        <dt>IIIF source</dt><dd><?php echo html_escape($map['manifest_url']); ?></dd>
        <dt>Provenance source</dt><dd><?php echo html_escape($map['source_url']); ?></dd>
    </dl>
</div>
<?php echo foot(); ?>
