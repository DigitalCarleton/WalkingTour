<?php echo head(array('title' => html_escape($map['title']), 'bodyclass' => 'historical-maps show')); ?>
<?php echo flash(); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps')); ?>">Back to Historical Maps</a></p>
    <p><a class="button" href="<?php echo html_escape(url('historical-maps/edit/' . $map['id'])); ?>">Edit map information</a>
        <a class="button green" href="<?php echo html_escape(public_url('historical-maps') . '?map_id=' . $map['id']); ?>">View maps</a></p>
    <?php if ($map['source_kind'] === 'allmaps'): ?>
        <p>Calibration is imported from Allmaps and is read-only on this website. The saved snapshot stays fixed until an explicit refresh.</p>
    <?php else: ?>
        <p>This legacy map remains readable. Its saved calibration and annotations are retained for migration. Website calibration editing and direct uploads are retired.</p>
    <?php endif; ?>
    <dl>
        <dt>Original image size</dt><dd><?php echo $map['image_width']; ?> × <?php echo $map['image_height']; ?> pixels</dd>
        <dt>Image source</dt><dd><?php echo $map['source_kind'] === 'upload' ? 'Legacy uploaded image' : 'IIIF image'; ?></dd>
        <?php if ($map['source_kind'] === 'upload'): ?>
        <dt>Saved image</dt><dd><a href="<?php echo html_escape($map['image_url']); ?>" target="_blank" rel="noopener noreferrer">View uploaded image</a></dd>
        <?php else: ?>
        <dt>IIIF image service</dt><dd><?php echo html_escape($map['image_service']); ?></dd>
        <dt>IIIF manifest</dt><dd><?php echo html_escape($map['manifest_url'] ?: 'Not provided'); ?></dd>
        <?php endif; ?>
        <dt>Provenance source</dt><dd><?php echo html_escape($map['source_url'] ?: 'Not provided'); ?></dd>
        <?php if ($map['source_kind'] === 'allmaps'): ?>
        <dt>Resolved annotation source</dt><dd><?php echo html_escape($map['allmaps']['resolved_id']); ?></dd>
        <dt>Allmaps map identity</dt><dd><?php echo html_escape($map['allmaps']['annotation_id']); ?></dd>
        <dt>Content hash (SHA-256)</dt><dd><?php echo html_escape($map['allmaps']['content_hash']); ?></dd>
        <dt>Imported at (UTC)</dt><dd><?php echo html_escape($map['allmaps']['imported_at']); ?></dd>
        <dt>Transformation</dt><dd><?php echo html_escape($map['transformation']); ?></dd>
        <?php else: ?>
        <dt>Retained legacy control points</dt><dd><?php echo count($map['control_points']); ?></dd>
        <dt>Retained legacy annotations</dt><dd><?php echo count($map['annotations']); ?></dd>
        <dt>Legacy calibration revision</dt><dd><?php echo $map['calibration_revision']; ?></dd>
        <?php endif; ?>
    </dl>
</div>
<?php echo foot(); ?>
