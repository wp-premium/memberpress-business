<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="mepr-integration-<?php echo $obj->id; ?>" class="mepr-integration" data-id="<?php echo $obj->id; ?>">
  <div class="mepr-integration-delete">
    <a href=""><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
  </div>
  <div class="mp-row">
    <div class="mepr-integration-setup-form">
      <input type="hidden" value="<?php echo $obj->id; ?>" name="<?php echo "{$mepr_options->integrations_str}[{$obj->id}][id]"; ?>" />
      <input type="hidden" value="1" name="<?php echo "{$mepr_options->integrations_str}[{$obj->id}][saved]"; ?>" />
      <div class="mp-row">
        <strong><?php _e('Name:', 'memberpress'); ?></strong>
        <input type="text" id="<?php echo "{$mepr_options->integrations_str}-{$obj->id}-label"; ?>" name="<?php echo "{$mepr_options->integrations_str}[{$obj->id}][label]"; ?>" value="<?php echo $obj->label; ?>" />
      </div>
      <div class="mp-row">
        <strong><?php _e('Gateway:', 'memberpress'); ?></strong>
        <?php
        if(isset($obj->settings->gateway) && isset($obj->settings->saved) && $obj->settings->saved): ?>
          <input type="hidden" value="<?php echo $obj->settings->gateway; ?>" name="<?php echo "{$mepr_options->integrations_str}[{$obj->id}][gateway]"; ?>" />
          <span><?php echo $obj->name; ?></span>
          <?php if(!empty($obj->icon)): ?>
            <div><img src="<?php echo $obj->icon; ?>" height="32px" /></div>
          <?php endif; ?>
        <?php
        else:
          MeprOptionsHelper::gateways_dropdown("{$mepr_options->integrations_str}[{$obj->id}][gateway]", isset($obj->settings->gateway)?$obj->settings->gateway:'', $obj->id);
        endif; ?>
      </div>
      <div class="mp-row">
        <strong><?php _e('ID:', 'memberpress'); ?></strong>
        <?php echo $obj->id; ?>
      </div>
      <div class="mp-row">
        <div><label><input type="checkbox" id="<?php echo "{$mepr_options->integrations_str}-{$obj->id}-use_label"; ?>" name="<?php echo "{$mepr_options->integrations_str}[{$obj->id}][use_label]"; ?>" value="<?php echo $obj->use_label; ?>" <?php checked($obj->use_label); ?> />&nbsp;&nbsp;<?php _e('Show Payment Label', 'memberpress'); ?></label></div>
        <div><label><input type="checkbox" id="<?php echo "{$mepr_options->integrations_str}-{$obj->id}-use_icon"; ?>" name="<?php echo "{$mepr_options->integrations_str}[{$obj->id}][use_icon]"; ?>" value="<?php echo $obj->use_icon; ?>" <?php checked($obj->use_icon); ?> />&nbsp;&nbsp;<?php _e('Show Payment Icon', 'memberpress'); ?></label></div>
        <div><label><input type="checkbox" id="<?php echo "{$mepr_options->integrations_str}-{$obj->id}-use_desc"; ?>" name="<?php echo "{$mepr_options->integrations_str}[{$obj->id}][use_desc]"; ?>" value="<?php echo $obj->use_desc; ?>" <?php checked($obj->use_desc); ?> />&nbsp;&nbsp;<?php _e('Show Payment Description', 'memberpress'); ?></label></div>
      </div>
    </div>
    <div class="mepr-integration-gateway-form">
      <?php
        if($obj instanceof MeprBaseRealGateway)
          $obj->display_options_form();
      ?>
    </div>
  </div>
</div>

