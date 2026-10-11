<?php echo head(array('title' => 'Import workflow changed', 'bodyclass' => 'historical-maps')); ?>
<div id="primary">
    <p><?php echo html_escape($message); ?></p>
    <p><a class="button green" href="<?php echo html_escape(url('historical-maps/import')); ?>">Import from Allmaps</a></p>
    <p><a href="<?php echo html_escape(url('historical-maps')); ?>">Back to Historical Maps</a></p>
</div>
<?php echo foot(); ?>
