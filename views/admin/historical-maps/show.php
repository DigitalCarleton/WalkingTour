<?php echo head(array('title' => html_escape($map['title']), 'bodyclass' => 'historical-maps show')); ?>
<?php echo flash(); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps')); ?>">Back to Historical Maps</a></p>
    <p><a class="button" href="<?php echo html_escape(url('historical-maps/edit/' . $map['id'])); ?>">Edit map information</a>
        <?php if ($map['source_kind'] === 'allmaps'): ?><a class="button green" href="<?php echo html_escape(public_url('historical-maps') . '?map_id=' . $map['id']); ?>">View maps</a><?php endif; ?></p>
    <?php if ($map['source_kind'] === 'allmaps'): ?>
        <p><a class="button" href="<?php echo html_escape(url('historical-maps/refresh/' . $map['id'])); ?>">Refresh from Allmaps</a></p>
        <p><a class="button" href="<?php echo html_escape(url('historical-maps/positions/' . $map['id'])); ?>">Adjust historical positions</a></p>
        <p>Calibration is imported from Allmaps and is read-only on this website. The saved snapshot stays fixed until an explicit refresh.</p>
    <?php else: ?>
        <p>This record is pending migration or archived and is not in the public library. Ask the server operator to export/review it with migrate-historical-maps.php. Existing uploaded images remain stored; calibration and unmatched free points belong in the private recovery export.</p>
    <?php endif; ?>
    <?php if ($map['migration']): ?>
        <p>Migration status: <?php echo html_escape($map['migration']['state']); ?> · <?php echo html_escape($map['migration']['reason']); ?></p>
    <?php endif; ?>
    <dl>
        <dt>Represented period</dt><dd><?php echo html_escape($map['period_label'] ?: 'Not specified'); ?></dd>
        <dt>Period years</dt><dd><?php echo html_escape(($map['period_start'] === null ? 'Unknown' : (string) $map['period_start']) . ' to ' . ($map['period_end'] === null ? 'Unknown' : (string) $map['period_end'])); ?></dd>
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
        <?php endif; ?>
    </dl>
</div>
<?php echo foot(); ?>
