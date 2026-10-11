<?php echo head(array('title' => 'Historical Maps', 'bodyclass' => 'historical-maps browse')); ?>
<?php echo flash(); ?>
<div class="table-actions"><a class="add button small green" href="<?php echo html_escape(url('historical-maps/import')); ?>">Import from Allmaps</a></div>
<div id="primary">
    <p>Calibrate maps in Allmaps, import their annotation URLs, and browse the original historical images beside a modern map.</p>
    <?php if (!$maps): ?><p>No historical maps are available yet. Import an Allmaps annotation to begin.</p><?php else: ?>
    <div class="map-data-table" role="region" aria-label="Historical map list" tabindex="0"><table class="simple">
        <thead><tr><th scope="col">Title</th><th scope="col">Source</th><th scope="col">Imported at (UTC)</th><th scope="col">Actions</th></tr></thead>
        <tbody><?php foreach ($maps as $map): ?><tr>
            <td><a href="<?php echo html_escape(url('historical-maps/show/' . $map['id'])); ?>"><?php echo html_escape($map['title']); ?></a></td>
            <td><?php echo $map['source_kind'] === 'allmaps' ? 'Allmaps snapshot' : (($map['migration']['state'] ?? '') === 'archived' ? 'Archived record' : 'Pending Allmaps migration'); ?></td>
            <td><?php echo html_escape($map['allmaps']['imported_at'] ?? 'Not imported from Allmaps'); ?></td>
            <td><?php if ($map['source_kind'] === 'allmaps'): ?><a href="<?php echo html_escape(public_url('historical-maps') . '?map_id=' . $map['id']); ?>">View maps</a><?php else: ?>Inspect this record before migration<?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php echo foot(); ?>
