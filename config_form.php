<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_filter_tooltip"><?php echo __('Filter Tooltip Text'); ?></label>    
    </div>    
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('The text that highlights the map filters the first time a user visits the map.'); ?></p>
        <div class="input-block">        
            <textarea cols="50" name="walking_tour_filter_tooltip" id="walking_tour_filter_tooltip" rows="4"><?php echo get_option('walking_tour_filter_tooltip'); ?></textarea>
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_tooltip_button"><?php echo __('Tooltip Button Text'); ?></label>    
    </div>    
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('The text that appears on the button to close the tooltip.'); ?></p>
        <div class="input-block">        
            <input type="text" name="walking_tour_tooltip_button" id="walking_tour_tooltip_button" value="<?php echo get_option('walking_tour_tooltip_button'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_center"><?php echo __('Map Center Coordinates'); ?></label>    
    </div>    
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the default starting place.'); ?></p>
        <div class="input-block">        
            <input type="text" name="walking_tour_center" id="walking_tour_center" value="<?php echo get_option('walking_tour_center'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_default_zoom"><?php echo __('Map Default Zoom'); ?></label>    
    </div>    
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the default zoom of the map.'); ?></p>
        <div class="input-block">        
            <input type="text" name="walking_tour_default_zoom" id="walking_tour_default_zoom" value="<?php echo get_option('walking_tour_default_zoom'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_max_zoom"><?php echo __('Map Max Zoom'); ?></label>    
    </div>    
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the max zoom of the map.'); ?></p>
        <div class="input-block">        
            <input type="text" name="walking_tour_max_zoom" id="walking_tour_max_zoom" value="<?php echo get_option('walking_tour_max_zoom'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_min_zoom"><?php echo __('Map Min Zoom'); ?></label>    
    </div>    
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the min zoom of the map.'); ?></p>
        <div class="input-block">        
            <input type="text" name="walking_tour_min_zoom" id="walking_tour_min_zoom" value="<?php echo get_option('walking_tour_min_zoom'); ?>" />
        </div>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="walking_tour_max_bounds"><?php echo __('Map Max Bounds'); ?></label>    
    </div>    
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Controls the max bounds of the map.'); ?></p>
        <div class="input-block">        
            <input type="text" name="walking_tour_max_bounds" id="walking_tour_max_bounds" value="<?php echo get_option('walking_tour_max_bounds'); ?>" />
        </div>
    </div>
</div>