<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<li class="mepr-custom-field postbox">
  <label><?php _e('Name:', 'memberpress'); ?></label>
  <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][name]" value="<?php echo __(stripslashes($line->field_name), 'memeberpress', 'memberpress'); ?>" />

  <label><?php _e('Type:', 'memberpress'); ?></label>
  <select name="mepr-custom-fields[<?php echo $random_id; ?>][type]" class="mepr-custom-fields-select" data-value="<?php echo $random_id; ?>">
    <option value="text" <?php selected($line->field_type, 'text'); ?>><?php _e('Text', 'memberpress'); ?></option>
    <option value="email" <?php selected($line->field_type, 'email'); ?>><?php _e('Email', 'memberpress'); ?></option>
    <option value="date" <?php selected($line->field_type, 'date'); ?>><?php _e('Date', 'memberpress'); ?></option>
    <option value="textarea" <?php selected($line->field_type, 'textarea'); ?>><?php _e('Textarea', 'memberpress'); ?></option>
    <option value="checkbox" <?php selected($line->field_type, 'checkbox'); ?>><?php _e('Checkbox', 'memberpress'); ?></option>
    <option value="dropdown" <?php selected($line->field_type, 'dropdown'); ?>><?php _e('Dropdown', 'memberpress'); ?></option>
    <option value="multiselect" <?php selected($line->field_type, 'multiselect'); ?>><?php _e('Multi-Select', 'memberpress'); ?></option>
    <option value="radios" <?php selected($line->field_type, 'radios'); ?>><?php _e('Radio Buttons', 'memberpress'); ?></option>
    <option value="checkboxes" <?php selected($line->field_type, 'checkboxes'); ?>><?php _e('Checkboxes', 'memberpress'); ?></option>
  </select>

  <label><?php _e('Default Value(s):', 'memberpress'); ?></label>
  <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][default]" value="<?php echo stripslashes($line->default_value); ?>" />

  <input type="checkbox" name="mepr-custom-fields[<?php echo $random_id; ?>][signup]" id="mepr-custom-fields-signup-<?php echo $random_id; ?>" <?php checked($line->show_on_signup); ?> />
  <label for="mepr-custom-fields-signup-<?php echo $random_id; ?>"><?php _e('Show at Signup', 'memberpress'); ?></label>

  &nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="mepr-custom-fields[<?php echo $random_id; ?>][required]" id="mepr-custom-fields-required-<?php echo $random_id; ?>" <?php checked($line->required); ?> />
  <label for="mepr-custom-fields-required-<?php echo $random_id; ?>"><?php _e('Required', 'memberpress'); ?></label>

  <input type="hidden" name="mepr-custom-fields-index[]" value="<?php echo $random_id; ?>" />

  <a href="" class="mepr-custom-field-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
  <div id="dropdown-hidden-options-<?php echo $random_id; ?>" <?php echo $hide; ?>>
  <?php
    if(empty($hide))
    {
      ?>
      <ul class="custom_options_list">
      <?php

      if( empty($line->options) ) {
        ?>
        <li>
          <label><?php _e('Option Name:', 'memberpress'); ?></label>
          <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][option][]" value="" />

          <label><?php _e('Option Value:', 'memberpress'); ?></label>
          <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][value][]" value="" />

          <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
        </li>
        <?php
      }
      else {
        foreach($line->options as $option) {
          ?>
          <li>
            <label><?php _e('Option Name:', 'memberpress'); ?></label>
            <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][option][]" value="<?php echo stripslashes($option->option_name); ?>" />

            <label><?php _e('Option Value:', 'memberpress'); ?></label>
            <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][value][]" value="<?php echo stripslashes($option->option_value); ?>" />

            <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
          </li>
          <?php
        }
      }

      ?>
        <a href="" id="mepr-add-new-option" title="Add Option" data-value="<?php echo $random_id; ?>"><i class="mp-icon mp-icon-plus-circled mp-16"></i></a>
      </ul>
      <?php
    }
  ?>
  </div>

  <input type="hidden" name="mepr-custom-fields[<?php echo $random_id; ?>][slug]" value="<?php echo (!empty($line->field_key))?$line->field_key:__('mepr_none', 'memberpress'); ?>" />
  <?php if(!empty($line->field_key)): ?>
    &nbsp;&nbsp;&nbsp;
    <?php MeprAppHelper::info_tooltip( 'mepr-custom-fields-slug',
                                       __('Slug', 'memberpress'),
                                       __('The slug is a unique key used to store these custom fields in the usermeta database table used by WordPress. The slug is auto-generated when you save the Options. As of version 1.1.0 all new slugs are prefixed with "mepr_", this does not apply to fields created before 1.1.0.', 'memberpress') ); ?>
    <?php _e('Slug:', 'memberpress'); ?> <?php echo $line->field_key; ?>
  <?php endif; ?>
</li>
