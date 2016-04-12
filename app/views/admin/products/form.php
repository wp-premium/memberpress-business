<?php
  if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
  $mepr_options = MeprOptions::fetch();
?>

<div id="mepr-products-form" class="inside">
  <p>
    <strong><?php printf(__('Price (%s):', 'memberpress'), $mepr_options->currency_symbol); ?></strong>
  </p>
  <p>
    <input name="<?php echo MeprProduct::$price_str; ?>" id="<?php echo MeprProduct::$price_str; ?>" type="text" value="<?php echo MeprUtils::format_float($product->price); ?>" />
  </p>
  <p>
    <strong><?php _e('Billing Type:', 'memberpress'); ?></strong>
  </p>
  <p>
    <select id="mepr-product-billing-type">
      <option value="recurring"<?php echo (($product->period_type!='lifetime')?' selected="selected"':''); ?>><?php _e('Recurring', 'memberpress'); ?></option>
      <option value="single"<?php echo (($product->period_type=='lifetime')?' selected="selected"':''); ?>><?php _e('One-Time', 'memberpress'); ?></option>
    </select>
  </p>

  <input type="hidden"
         id="<?php echo MeprProduct::$period_str; ?>"
         name="<?php echo MeprProduct::$period_str; ?>"
         value="<?php echo $product->period; ?>">
  <input type="hidden"
         id="<?php echo MeprProduct::$period_type_str; ?>"
         name="<?php echo MeprProduct::$period_type_str; ?>"
         value="<?php echo $product->period_type; ?>">

  <div id="mepr-non-recurring-options" class="mepr-hidden">
  <?php /*
    <table id="mepr-interval-options">
  */ ?>
    <p>
      <strong><?php _e('Access:', 'memberpress'); ?></strong>
    </p>
    <p>
      <label class="screen-reader-text" for="<?php echo MeprProduct::$expire_type_str; ?>"><?php _e('Access:', 'memberpress'); ?></label>
      <select name="<?php echo MeprProduct::$expire_type_str; ?>" id="<?php echo MeprProduct::$expire_type_str; ?>">
        <option value="none"<?php selected($product->expire_type,"none"); ?>><?php _e('Lifetime', 'memberpress'); ?>&nbsp;</option>
        <option value="delay"<?php selected($product->expire_type,"delay"); ?>><?php _e('Expire', 'memberpress'); ?>&nbsp;</option>
        <option value="fixed"<?php selected($product->expire_type,"fixed"); ?>><?php _e('Fixed Expire', 'memberpress'); ?>&nbsp;</option>
      </select>
    </p>
    <div class="mepr-product-expire-delay mepr-sub-box">
      <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
      <p class="mepr-clear-top">
        <strong><?php _e('Expire After:', 'memberpress'); ?></strong>
      </p>
      <p>
        <input type="text" size="2"
               name="<?php echo MeprProduct::$expire_after_str; ?>"
               id="<?php echo MeprProduct::$expire_after_str; ?>"
               value="<?php echo $product->expire_after; ?>" />
        <select name="<?php echo MeprProduct::$expire_unit_str; ?>"
                id="<?php echo MeprProduct::$expire_unit_str; ?>">
          <option value="days"<?php selected($product->expire_unit,'days'); ?>><?php _e("days", 'memberpress'); ?></option>
          <option value="weeks"<?php selected($product->expire_unit,'weeks'); ?>><?php _e("weeks", 'memberpress'); ?></option>
          <option value="months"<?php selected($product->expire_unit,'months'); ?>><?php _e("months", 'memberpress'); ?></option>
          <option value="years"<?php selected($product->expire_unit,'years'); ?>><?php _e("years", 'memberpress'); ?></option>
        </select>
      </p>
      <p>
        <input type="checkbox"
               id="<?php echo MeprProduct::$allow_renewal_str; ?>"
               name="<?php echo MeprProduct::$allow_renewal_str; ?>"
               <?php checked($product->allow_renewal); ?> />
        <?php _e('Allow Renewals', 'memberpress'); ?>
      </p>
    </div>
    <div class="mepr-product-expire-fixed mepr-sub-box">
      <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
      <p class="mepr-clear-top">
        <strong><?php _e('Expire On:', 'memberpress'); ?></strong>
      </p>
      <p>
        <input type="text"
               class="mepr-date-picker"
               name="<?php echo MeprProduct::$expire_fixed_str; ?>"
               id="<?php echo MeprProduct::$expire_fixed_str; ?>"
               value="<?php echo $product->expire_fixed; ?>" />
      </p>
    </div>
  </div>

  <div id="mepr-recurring-options" class="mepr-hidden">
    <p>
      <strong><?php _e('Interval:', 'memberpress'); ?></strong>
    </p>
    <p>
      <?php echo MeprProductsHelper::preset_period_dropdown( MeprProduct::$period_str,
                                                             MeprProduct::$period_type_str
                                                           ); ?>
      <div id="mepr-product-custom-period" class="mepr-hidden mepr-product-custom-period mepr-sub-box">
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
        <input type="text" size="2"
               id="<?php echo MeprProduct::$period_str; ?>-custom"
               name="<?php echo MeprProduct::$period_str; ?>-custom" />
        <?php echo MeprProductsHelper::period_type_dropdown( MeprProduct::$period_type_str ); ?>
      </div>
    </p>
    <div class="mepr-product-trial-box">
      <?php $checked = (isset($product->trial) && $product->trial)?'checked="checked"':''; ?>
      <p>
        <input type="checkbox" name="<?php echo MeprProduct::$trial_str; ?>" id="<?php echo MeprProduct::$trial_str; ?>" <?php echo $checked; ?> /> <label for="_mepr_product_trial"><?php _e('Trial Period', 'memberpress'); ?></label>
        <?php MeprAppHelper::info_tooltip( 'mepr-product-trial-days',
                                           __('Trial Days Price Text', 'memberpress'),
                                           __('Day count values here that are multiples of 365 will show as years, muplitples of 30 will show as months, multiples of 7 will show as weeks ... otherwise the trial will show up as days.', 'memberpress') ); ?>
        <div id="disable-trial-notice" class="mepr-meta-sub-pane" data-value="<?php _e('Price must be greater than 0.00 to choose recurring subscriptions.', 'memberpress'); ?>" class="mepr_hidden"></div>
      </p>
      <div class="mepr-product-trial-hidden mepr-sub-box">
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
        <p class="mepr-clear-top">
          <strong><?php _e('Trial Duration (Days):', 'memberpress'); ?></strong>
        </p>
        <p>
          <input name="<?php echo MeprProduct::$trial_days_str; ?>" id="<?php echo MeprProduct::$trial_days_str; ?>" type="text" size="2" value="<?php echo $product->trial_days; ?>" />
        </p>
        <p>
          <strong><?php printf(__('Trial Amount (%s):', 'memberpress'), $mepr_options->currency_symbol); ?></strong>
        </p>
        <p>
          <input name="<?php echo MeprProduct::$trial_amount_str; ?>" id="<?php echo MeprProduct::$trial_amount_str; ?>" size="7" type="text" value="<?php echo MeprUtils::format_float($product->trial_amount); ?>" />
        </p>
      </div>
    </div>
    <div class="mepr-product-cycles-box">
      <?php $checked = (isset($product->limit_cycles) && $product->limit_cycles)?'checked="checked"':''; ?>
      <p>
        <input type="checkbox" name="<?php echo MeprProduct::$limit_cycles_str; ?>" id="<?php echo MeprProduct::$limit_cycles_str; ?>" <?php echo $checked; ?> /> <label for="_mepr_product_limit_cycles"><?php _e('Limit Payment Cycles', 'memberpress'); ?></label>
      </p>
      <div class="mepr-product-limit-cycles-hidden mepr-sub-box">
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
        <p class="mepr-clear-top">
          <strong><?php _e('Max # of Payments:', 'memberpress'); ?></strong>
        </p>
        <p>
          <input name="<?php echo MeprProduct::$limit_cycles_num_str; ?>" id="<?php echo MeprProduct::$limit_cycles_num_str ?>" type="text" size="2" value="<?php echo $product->limit_cycles_num; ?>" />
        </p>
        <p>
          <strong><?php _e('Access After Last Cycle:', 'memberpress'); ?></strong>
        </p>
        <p>
          <select name="<?php echo MeprProduct::$limit_cycles_action_str; ?>" id="<?php echo MeprProduct::$limit_cycles_action_str; ?>">
            <option value="expire" <?php selected('expire',$product->limit_cycles_action); ?>><?php _e('Expire Access','memberpress'); ?></option>
            <option value="lifetime" <?php selected('lifetime',$product->limit_cycles_action); ?>><?php _e('Lifetime Acess','memberpress'); ?></option>
          </select>
        </p>
      </div>
    </div>
  </div>

  <!-- The NONCE below prevents post meta from being blanked on move to trash -->
  <input type="hidden" name="<?php echo MeprProduct::$nonce_str; ?>" value="<?php echo wp_create_nonce(MeprProduct::$nonce_str.wp_salt()); ?>" />
</div>
