<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="mp-pm-desc-<?php echo $payment_method->id; ?>" class="mepr-payment-method-desc-text mepr-hidden">
  <div class="mepr-checkout-form">
    <div class="mp-form-row">
      <div class="mp-form-label">
        <label><?php _ex('Credit Card Number', 'ui', 'memberpress'); ?></label>
        <span class="cc-error"><?php _ex('Invalid Credit Card Number', 'ui', 'memberpress'); ?></span>
      </div>
      <input type="tel" class="mepr-form-input card-number cc-number validation" pattern="\d*" autocomplete="cc-number" required>
    </div>
    <input type="hidden" name="mepr-cc-type" class="cc-type" value="" />
    <div class="mp-form-row">
      <div class="mp-form-label">
        <label><?php _ex('Expiration', 'ui', 'memberpress'); ?></label>
        <span class="cc-error"><?php _ex('Invalid Expiration', 'ui', 'memberpress'); ?></span>
      </div>
      <input type="tel" class="mepr-form-input cc-exp validation" pattern="\d*" autocomplete="cc-exp" placeholder="<?php _ex('mm/yy', 'ui', 'memberpress'); ?>" required>
    </div>
    <div class="mp-form-row">
      <div class="mp-form-label">
        <label><?php _ex('CVC', 'ui', 'memberpress'); ?></label>
        <span class="cc-error"><?php _ex('Invalid CVC Code', 'ui', 'memberpress'); ?></span>
      </div>
      <input type="tel" class="mepr-form-input card-cvc cc-cvc validation" pattern="\d*" autocomplete="off" required>
    </div>
  </div>
</div>
