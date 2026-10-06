<?php
$editorUrl = public_url('walking-tour') . '?map_id=' . $map['id'];
$coordinate = function ($value, $precision) { return $value === null ? '—' : number_format($value, $precision, '.', ''); };
echo head(array('title' => html_escape($map['title']), 'bodyclass' => 'historical-maps show'));
?>
<?php echo flash(); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps')); ?>">Back to Historical Maps</a></p>
    <p><a class="button" href="<?php echo html_escape(url('historical-maps/edit/' . $map['id'])); ?>">Edit map information</a>
        <a class="button green" href="<?php echo html_escape(public_url('walking-tour') . '?map_id=' . $map['id']); ?>">Open map editor</a></p>
    <?php if (!$map['control_points']): ?><p>This map has no calibration yet. Open the map editor and place matching points on both maps to confirm control pairs.</p><?php endif; ?>
    <dl>
        <dt>Original image size</dt><dd><?php echo $map['image_width']; ?> × <?php echo $map['image_height']; ?> pixels</dd>
        <dt>Control points</dt><dd><?php echo count($map['control_points']); ?></dd>
        <dt>Annotations</dt><dd><?php echo count($map['annotations']); ?></dd>
        <dt>Calibration revision</dt><dd><?php echo $map['calibration_revision']; ?></dd>
        <dt>IIIF image service</dt><dd><?php echo html_escape($map['image_service']); ?></dd>
        <dt>IIIF source</dt><dd><?php echo html_escape($map['manifest_url']); ?></dd>
        <dt>Provenance source</dt><dd><?php echo html_escape($map['source_url']); ?></dd>
    </dl>
    <p><a href="#map-controls">Control points</a> · <a href="#map-annotations">Annotations</a> · <a href="#map-history">Calibration history</a></p>

    <h2 id="map-controls">Control points</h2>
    <p>Confirmed pairs participate in calibration. Use the map editor to adjust or delete them.</p>
    <?php if (!$map['control_points']): ?><p>No control points have been confirmed.</p><?php else: ?>
    <div class="map-data-table" role="region" aria-label="Control point coordinates" tabindex="0">
        <table class="simple"><thead><tr><th scope="col">Point</th><th scope="col">Image X</th><th scope="col">Image Y</th><th scope="col">Longitude</th><th scope="col">Latitude</th><th scope="col">Actions</th></tr></thead><tbody>
        <?php foreach ($map['control_points'] as $point): ?>
            <tr><th scope="row"><?php echo $point['ordinal']; ?></th>
                <td><?php echo $coordinate($point['image_x'], 2); ?></td><td><?php echo $coordinate($point['image_y'], 2); ?></td>
                <td><?php echo $coordinate($point['longitude'], 6); ?></td><td><?php echo $coordinate($point['latitude'], 6); ?></td>
                <td><a href="<?php echo html_escape($editorUrl . '&point_type=control&point_id=' . $point['id']); ?>">Locate on maps</a></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endif; ?>

    <h2 id="map-annotations">Annotations</h2>
    <p>Ordinary annotations do not participate in calibration. An unavailable counterpart is shown as —.</p>
    <?php if (!$map['annotations']): ?><p>No ordinary annotations have been saved.</p><?php else: ?>
    <div class="map-data-table" role="region" aria-label="Annotation coordinates" tabindex="0">
        <table class="simple"><thead><tr><th scope="col">Point</th><th scope="col">Source side</th><th scope="col">Status</th><th scope="col">Image X</th><th scope="col">Image Y</th><th scope="col">Longitude</th><th scope="col">Latitude</th><th scope="col">Calibration revision</th><th scope="col">Actions</th></tr></thead><tbody>
        <?php foreach ($map['annotations'] as $point): ?>
            <tr><th scope="row">A<?php echo $point['id']; ?></th><td><?php echo $point['source_side'] === 'image' ? 'Historical image' : 'Modern map'; ?></td>
                <td><?php echo html_escape(ucfirst($point['status'])); ?></td>
                <td><?php echo $coordinate($point['image_x'], 2); ?></td><td><?php echo $coordinate($point['image_y'], 2); ?></td>
                <td><?php echo $coordinate($point['longitude'], 6); ?></td><td><?php echo $coordinate($point['latitude'], 6); ?></td>
                <td><?php echo $point['calibration_revision']; ?></td>
                <td><a href="<?php echo html_escape($editorUrl . '&point_type=annotation&point_id=' . $point['id']); ?>">Locate on maps</a></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endif; ?>

    <h2 id="map-history">Calibration history</h2>
    <p>Up to 50 changes are listed from newest to oldest. To undo the latest available calibration change, use the map editor.</p>
    <?php if (!$history['changes']): ?><p>No calibration changes have been recorded on this page.</p><?php else: ?>
    <div class="map-data-table" role="region" aria-label="Calibration change records" tabindex="0">
        <table class="simple"><thead><tr><th scope="col">Change</th><th scope="col">Operation</th><th scope="col">Controls before change</th><th scope="col">State</th></tr></thead><tbody>
        <?php $operations = array('confirm' => 'Confirm or adjust pair', 'delete' => 'Delete control pair', 'undo_calibration' => 'Undo calibration change'); ?>
        <?php foreach ($history['changes'] as $change): ?>
            <tr><th scope="row"><?php echo $change['id']; ?></th><td><?php echo html_escape($operations[$change['operation']] ?? $change['operation']); ?></td>
                <td><?php echo $change['controls_before'] === null ? '—' : $change['controls_before']; ?></td>
                <td><?php echo $change['undone'] ? 'Undone' :
                    (($map['undo_calibration']['change_id'] ?? null) === $change['id'] ? 'Available to undo' : 'Recorded'); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endif; ?>
    <?php if ($olderPage): ?><a href="<?php echo html_escape(url('historical-maps/show/' . $map['id']) . '#map-history'); ?>">Newest changes</a><?php endif; ?>
    <?php if ($history['older_than']): ?><a href="<?php echo html_escape(url('historical-maps/show/' . $map['id']) . '?before=' . $history['older_than'] . '#map-history'); ?>">Older changes</a><?php endif; ?>
</div>
<?php echo foot(); ?>
