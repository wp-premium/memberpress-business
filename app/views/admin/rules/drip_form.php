<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

?>

<div>
  <input type="checkbox" name="<?php echo MeprRule::$drip_enabled_str; ?>" id="<?php echo MeprRule::$drip_enabled_str; ?>" <?php checked((!empty($rule->drip_enabled))); ?> /> <label for="<?php echo MeprRule::$drip_enabled_str; ?>"><strong><?php _e('Enable Drip', 'memberpress'); ?></strong></label>
  <div id="mepr-rules-drip-area">
    <label><?php _e('Drip this content', 'memberpress'); ?></label>
    <input type="text" name="<?php echo MeprRule::$drip_amount_str; ?>" size="3" value="<?php echo (int)$rule->drip_amount; ?>" />
    <?php MeprRulesHelper::time_units_dropdown($rule, MeprRule::$drip_unit_str); ?>
    <?php _e('after', 'memberpress'); ?>
    <?php MeprRulesHelper::drip_expires_after_dropdown($rule, MeprRule::$drip_after_str); ?>
    <input type="text" name="<?php echo MeprRule::$drip_after_fixed_str; ?>" id="<?php echo MeprRule::$drip_after_fixed_str; ?>" class="mepr-date-picker" value="<?php echo $rule->drip_after_fixed; ?>" />
  </div>
</div>
<div>
  <input type="checkbox" name="<?php echo MeprRule::$expires_enabled_str; ?>" id="<?php echo MeprRule::$expires_enabled_str; ?>" <?php checked((!empty($rule->expires_enabled))); ?> /> <label for="<?php echo MeprRule::$expires_enabled_str; ?>"><strong><?php _e('Enable Expiration', 'memberpress'); ?></strong></label>
  <div id="mepr-rules-expires-area">
    <label><?php _e('Expire this content', 'memberpress'); ?></label>
    <input type="text" name="<?php echo MeprRule::$expires_amount_str; ?>" size="3" value="<?php echo (int)$rule->expires_amount; ?>" />
    <?php MeprRulesHelper::time_units_dropdown($rule, MeprRule::$expires_unit_str); ?>
    <?php _e('after', 'memberpress'); ?>
    <?php MeprRulesHelper::drip_expires_after_dropdown($rule, MeprRule::$expires_after_str); ?>
    <input type="text" name="<?php echo MeprRule::$expires_after_fixed_str; ?>" id="<?php echo MeprRule::$expires_after_fixed_str; ?>" class="mepr-date-picker" value="<?php echo $rule->expires_after_fixed; ?>" />
  </div>
</div>
