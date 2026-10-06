<?php echo head(array('title' => 'Historical Maps', 'bodyclass' => 'historical-maps browse')); ?>
<?php echo flash(); ?>
<div class="table-actions"><a class="add button small green" href="<?php echo html_escape(url('historical-maps/upload')); ?>">Upload image</a>
    <a class="button small" href="<?php echo html_escape(url('historical-maps/add')); ?>">Import from IIIF</a></div>
<div id="primary">
    <p>Manage historical maps and open their paired image and geographic map.</p>
    <?php if (!$maps): ?>
        <p>No historical maps are available yet.</p>
    <?php else: ?>
    <div class="map-data-table" role="region" aria-label="Historical map list" tabindex="0"><table class="simple">
        <thead><tr><th scope="col">Title</th><th scope="col">Control points</th><th scope="col">Annotations</th><th scope="col">Calibration revision</th><th scope="col">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($maps as $map): ?>
            <tr>
                <td><a href="<?php echo html_escape(url('historical-maps/show/' . $map['id'])); ?>"><?php echo html_escape($map['title']); ?></a></td>
                <td><?php echo count($map['control_points']); ?></td>
                <td><?php echo count($map['annotations']); ?></td>
                <td><?php echo $map['calibration_revision']; ?></td>
                <td><a href="<?php echo html_escape(public_url('walking-tour') . '?map_id=' . $map['id']); ?>">Open map editor</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php echo foot(); ?>
