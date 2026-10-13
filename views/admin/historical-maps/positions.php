<?php echo head(array('title' => 'Adjust historical positions', 'bodyclass' => 'historical-maps positions')); ?>
<p><a href="<?php echo html_escape(url('historical-maps/show/' . $map['id'])); ?>">Back to map record</a></p>
<?php $editorMode = true; include WALKINGTOUR_PLUGIN_DIR . '/views/shared/historical-map-viewer.php'; ?>
<?php echo foot(); ?>
