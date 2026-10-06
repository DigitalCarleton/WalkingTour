<?php echo head(array('title' => 'Edit Historical Map Information', 'bodyclass' => 'historical-maps edit')); ?>
<div id="primary">
    <p><a href="<?php echo html_escape(url('historical-maps/show/' . $map['id'])); ?>">View latest map record</a></p>
    <?php if ($error): ?><p class="error" role="alert"><?php echo html_escape($error); ?></p><?php endif; ?>
    <form method="post" action="<?php echo html_escape(url('historical-maps/edit/' . $map['id'])); ?>">
        <?php echo $csrf; ?>
        <input name="revision" type="hidden" value="<?php echo html_escape($values['revision']); ?>">
        <div class="field"><div class="two columns alpha"><label for="map-title">Title</label></div>
            <div class="inputs five columns omega"><input id="map-title" name="title" type="text" maxlength="255" required value="<?php echo html_escape($values['title']); ?>"></div></div>
        <div class="field"><div class="two columns alpha"><label for="source-url">Provenance source URL</label></div>
            <div class="inputs five columns omega"><input id="source-url" name="source_url" type="url" value="<?php echo html_escape($values['source_url']); ?>">
                <p class="explanation">Optional HTTPS link to the source or catalog record.</p></div></div>
        <p>The saved image and pixel dimensions remain fixed so saved coordinates continue to refer to the same image. Use the map editor to adjust point pairs.</p>
        <input class="submit big green button" type="submit" value="Save map information">
    </form>
</div>
<?php echo foot(); ?>
