<?php echo head(array('title' => 'Delete Historical Map', 'bodyclass' => 'historical-maps delete')); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps/edit/' . $map['id'])); ?>">Cancel and return to editing</a></p>
    <?php if ($error): ?><p class="error" role="alert"><?php echo html_escape($error); ?></p>
        <p><a href="<?php echo html_escape(url('historical-maps/delete/' . $map['id'])); ?>">Reload confirmation with the latest data</a></p><?php endif; ?>
    <h2><?php echo html_escape($map['title']); ?></h2>
    <?php if ($map['source_kind'] === 'allmaps'): ?>
        <p>This permanently deletes this site's map record and imported Allmaps snapshot.</p>
    <?php else: ?>
        <p>This permanently deletes the map record, <?php echo count($map['control_points']); ?> legacy control points,
            <?php echo count($map['annotations']); ?> legacy annotations, its saved mask, and calibration history.</p>
    <?php endif; ?>
    <?php if ($map['source_kind'] === 'upload'): ?>
        <p>The uploaded image file will also be removed from this site's configured storage.</p>
    <?php else: ?>
        <p>The external IIIF image remains at its source. Only this site's map record and saved data are removed.</p>
    <?php endif; ?>
    <p>Other historical maps and Walking Tour content are not affected. This deletion cannot be undone.</p>
    <form method="post" action="<?php echo html_escape(url('historical-maps/delete/' . $map['id'])); ?>">
        <?php echo $csrf; ?>
        <input name="revision" type="hidden" value="<?php echo html_escape($values['revision']); ?>">
        <p><label><input name="confirm_delete" type="checkbox" value="1" required> I understand that this map and its saved data will be permanently deleted.</label></p>
        <input class="submit big red button" type="submit" value="Permanently delete map">
    </form>
</div>
<?php echo foot(); ?>
