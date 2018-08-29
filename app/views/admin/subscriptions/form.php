<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<input type="hidden" name="action" value="<?php echo $_REQUEST['action']; ?>" />
<?php wp_nonce_field( 'mepr_create_or_update_subscription', 'mepr_subscriptions_nonce' ); ?>

<tr valign="top">
  <th scope="row"><label for="subscr_id"><?php _e('Subscription Number*:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="subscr_id" id="subscr_id" value="<?php echo (empty($sub->subscr_id)) ? uniqid() : $sub->subscr_id; ?>" class="regular-text" />
    <p class="description"><?php _e('A unique subscription number for this subscription. Only edit this if you absolutely have to.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="user_login"><?php _e('User*:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="user_login" id="user_login" class="mepr_suggest_user" value="<?php echo $sub->user_login; ?>" autocomplete="off" />
    <p class="description"><?php _e('The user for this subscription.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="product_id"><?php _e('Membership*:', 'memberpress'); ?></label></th>
  <td>
    <?php $products = MeprCptModel::all('MeprProduct'); ?>
    <select name="product_id" id="product_id" class="mepr-membership-dropdown" data-expires_at_field_id="expires_at">
      <?php foreach($products as $product): ?>
        <option value="<?php echo $product->ID; ?>" <?php selected( $sub->product_id, $product->ID ); ?>><?php echo $product->post_title; ?></option>
      <?php endforeach; ?>
    </select>
    <p class="description"><?php _e('The membership that was purchased', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="price"><?php _e('Sub-Total*:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo $mepr_options->currency_symbol; ?></span>
    <input type="text" name="price" id="price" value="<?php echo MeprUtils::format_float($sub->price); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php _e('The sub-total (amount before tax) of this subscription', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="tax_amount"><?php _e('Tax Amount:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo $mepr_options->currency_symbol; ?></span>
    <input type="text" name="tax_amount" id="tax_amount" value="<?php echo MeprUtils::format_float($sub->tax_amount); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php _e('The amount of taxes for this subscription', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="tax_rate"><?php _e('Tax Rate:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="tax_rate" id="tax_rate" value="<?php echo MeprUtils::format_float($sub->tax_rate, 3); ?>" class="regular-text" style="width:95px !important;"/>
    <span><?php echo '%'; ?></span>
    <p class="description"><?php _e('The tax rate in percentage. (Ex: 10.000 for 10%)', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="status"><?php _e('Status*:', 'memberpress'); ?></label></th>
  <td>
    <select name="status" id="status">
      <option value="<?php echo MeprSubscription::$active_str; ?>" <?php echo selected( $sub->status, MeprSubscription::$active_str ); ?>><?php _e('Active', 'memberpress'); ?></option>
      <option value="<?php echo MeprSubscription::$pending_str; ?>" <?php echo selected( $sub->status, MeprSubscription::$pending_str ); ?>><?php _e('Pending', 'memberpress'); ?></option>
      <option value="<?php echo MeprSubscription::$suspended_str; ?>" <?php echo selected( $sub->status, MeprSubscription::$suspended_str ); ?>><?php _e('Suspended', 'memberpress'); ?></option>
      <option value="<?php echo MeprSubscription::$cancelled_str; ?>" <?php echo selected( $sub->status, MeprSubscription::$cancelled_str ); ?>><?php _e('Cancelled', 'memberpress'); ?></option>
    </select>
    <p class="description"><?php _e('The current status of the subscription', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label><?php _e('Gateway:', 'memberpress'); ?></label></th>
  <td>
    <?php echo MeprSubscriptionsHelper::payment_methods_dropdown('gateway', $sub->gateway); ?>
    <p class="description"><?php _e('The payment method associated with this subscription.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label><?php _e('Created (UTC/GMT):', 'memberpress'); ?></label></th>
  <td>
    <?php MeprTransactionsHelper::transaction_created_at_field( 'created_at', $sub->created_at ); ?>
    <p class="description"><?php _e('The date that this subscription was created on. This field is displayed in UTC/GMT.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="trial"><?php _e('Trial:', 'memberpress'); ?></label></th>
  <td>
    <input type="checkbox" name="trial" id="trial" <?php echo checked($sub->trial); ?> />
    <p class="description"><?php _e('The trial period for this subscription', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="trial_days"><?php _e('Trial Days:', 'memberpress'); ?></label></th>
  <td>
    <input name="trial_days" id="trial_days" type="text" size="2" value="<?php echo $sub->trial_days; ?>" />
    <p class="description"><?php _e('The number of days for this trial period', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="trial_amount"><?php printf(__('Trial Amount (%s):', 'memberpress'), $mepr_options->currency_symbol); ?></label></th>
  <td>
    <span><?php echo $mepr_options->currency_symbol; ?></span>
    <input type="text" name="trial_amount" id="trial_amount" value="<?php echo MeprUtils::format_float($sub->trial_amount); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php _e('The sub-total (amount before tax) of this subscription', 'memberpress'); ?></p>
  </td>
</tr>
