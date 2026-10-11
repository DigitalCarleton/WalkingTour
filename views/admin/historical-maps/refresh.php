<?php echo head(array('title'=>'Refresh from Allmaps', 'bodyclass'=>'historical-maps refresh')); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps/show/' . $map['id'])); ?>">View latest map record</a></p>
    <h2><?php echo html_escape($map['title']); ?></h2>
    <p>Review and publish a saved Allmaps snapshot. Public pages never fetch newer calibration automatically. Local Item decisions and the represented period are preserved.</p>
    <?php if ($error): ?><p class="error" role="alert"><?php echo html_escape($error); ?></p><?php endif; ?>
    <?php if ($map['source_kind'] !== 'allmaps'): ?>
    <p>Migrate this legacy map to Allmaps before refreshing its calibration.</p>
    <?php elseif (!$preview): ?>
    <form method="post" action="<?php echo html_escape(url('historical-maps/refresh/' . $map['id'])); ?>">
        <?php echo $csrf; ?><input type="hidden" name="revision" value="<?php echo html_escape($values['revision']); ?>"><input type="hidden" name="step" value="preview">
        <label for="refresh-url">Allmaps annotation URL</label><input id="refresh-url" name="annotation_url" type="url" required maxlength="2048" value="<?php echo html_escape($values['annotation_url']); ?>">
        <p>An unqualified URL fetches the current source snapshot. A URL containing @ keeps that requested version. Change the URL deliberately to review a different version of the same map.</p>
        <input type="submit" value="Preview refresh" class="submit big green button">
    </form>
    <?php else: $image = $preview['image']; $snapshot = json_decode($image['source_snapshot'], true); ?>
    <h3>Review selected map refresh</h3>
    <dl>
        <dt>Selected Allmaps map</dt><dd><?php echo html_escape($snapshot['annotation']['id']); ?></dd>
        <dt>Requested source</dt><dd><?php echo html_escape($image['source_url']); ?></dd>
        <dt>Resolved source</dt><dd><?php echo html_escape($snapshot['resolved_id']); ?></dd>
        <dt>Current hash (SHA-256)</dt><dd><?php echo html_escape($map['allmaps']['content_hash']); ?></dd>
        <dt>Reviewed hash (SHA-256)</dt><dd><?php echo html_escape($snapshot['content_hash']); ?></dd>
        <dt>Current original image</dt><dd><?php echo html_escape($map['image_service']); ?> · <?php echo $map['image_width']; ?> × <?php echo $map['image_height']; ?> pixels</dd>
        <dt>Reviewed original image</dt><dd><?php echo html_escape($image['image_service']); ?> · <?php echo $image['image_width']; ?> × <?php echo $image['image_height']; ?> pixels</dd>
        <dt>Current transformation</dt><dd><?php echo html_escape(json_encode($map['georeference']['transformation'])); ?></dd>
        <dt>Reviewed transformation</dt><dd><?php echo html_escape(json_encode($snapshot['map']['transformation'])); ?></dd>
        <dt>Current resource mask (original pixels)</dt><dd><?php echo html_escape(json_encode($map['georeference']['resourceMask'])); ?></dd>
        <dt>Reviewed resource mask (original pixels)</dt><dd><?php echo html_escape(json_encode($snapshot['map']['resourceMask'])); ?></dd>
    </dl>
    <form method="post" action="<?php echo html_escape(url('historical-maps/refresh/' . $map['id'])); ?>">
        <?php echo $csrf; ?><input name="step" type="hidden" value="publish"><input name="preview_token" type="hidden" value="<?php echo html_escape($values['preview_token']); ?>">
        <?php if ($preview['image_changed']): ?>
        <p class="error">The original image identity or dimensions changed. Saved pixel overrides will be retained as needing review and will not be applied until an editor reviews and saves their position again. Visibility and notes are preserved.</p>
        <label><input name="review_image_change" type="checkbox" value="1" required> I reviewed the changed image space and will review the affected historical positions.</label>
        <?php else: ?><p>The original image space is unchanged. Saved position overrides, visibility decisions and notes remain valid.</p><?php endif; ?>
        <input type="submit" value="Publish reviewed refresh" class="submit big green button">
    </form>
    <p><a href="<?php echo html_escape(url('historical-maps/refresh/' . $map['id'])); ?>">Cancel and start a new preview</a></p>
    <?php endif; ?>
</div>
<?php echo foot(); ?>
