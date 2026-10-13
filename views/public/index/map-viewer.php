<?php echo head(array('title' => 'Historical Maps', 'bodyclass' => 'map historical-maps')); ?>
<?php echo link_to_home_page('<span class="screen-reader-text">Home</span>', array('id' => 'home-button')); ?>
<?php $editorMode = false; include WALKINGTOUR_PLUGIN_DIR . '/views/shared/historical-map-viewer.php'; ?>
<?php echo foot(); ?>
