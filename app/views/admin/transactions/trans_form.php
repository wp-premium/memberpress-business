<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<tr valign="top">
  <th scope="row"><label for="trans_num"><?php _e('Transaction Number*:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="trans_num" id="trans_num" value="<?php echo (empty($txn->trans_num))?uniqid():$txn->trans_num; ?>" class="regular-text" />
    <p class="description"><?php _e('A unique Invoice ID for this Transaction. Only edit this if you absolutely have to.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="user_login"><?php _e('User*:', 'memberpress'); ?></label></th>
  <td>
    <input type="hidden" name="action" value="<?php echo $_REQUEST['action']; ?>" />
    <?php wp_nonce_field( 'memberpress-trans' ); ?>
    <input type="text" name="user_login" id="user_login" class="mepr_suggest_user" value="<?php echo $user_login; ?>" autocomplete="off" />
    <p class="description"><?php _e('The user who made this transaction.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="product_id"><?php _e('Membership*:', 'memberpress'); ?></label></th>
  <td>
    <?php $prds = get_posts(array('post_type' => 'memberpressproduct', 'post_status' => 'publish', 'numberposts' => -1)); ?>
    <select name="product_id" id="product_id">
      <?php foreach($prds as $product): ?>
        <option value="<?php echo $product->ID; ?>" <?php selected( $txn->product_id, $product->ID ); ?>><?php echo $product->post_title; ?></option>
      <?php endforeach; ?>
    </select>
    <p class="description"><?php _e('The membership that was purchased', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="amount"><?php _e('Amount*:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo $mepr_options->currency_symbol; ?></span>
    <input type="text" name="amount" id="amount" value="<?php echo MeprUtils::format_float($txn->amount); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php _e('The amount of this transaction', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="status"><?php _e('Status:', 'memberpress'); ?></label></th>
  <td>
    <select name="status" id="status">
      <option value="<?php echo MeprTransaction::$complete_str; ?>" <?php echo selected( $txn->status, MeprTransaction::$complete_str ); ?>><?php _e('Complete', 'memberpress'); ?></option>
      <option value="<?php echo MeprTransaction::$pending_str; ?>" <?php echo selected( $txn->status, MeprTransaction::$pending_str ); ?>><?php _e('Pending', 'memberpress'); ?></option>
      <option value="<?php echo MeprTransaction::$failed_str; ?>" <?php echo selected( $txn->status, MeprTransaction::$failed_str ); ?>><?php _e('Failed', 'memberpress'); ?></option>
      <option value="<?php echo MeprTransaction::$refunded_str; ?>" <?php echo selected( $txn->status, MeprTransaction::$refunded_str ); ?>><?php _e('Refunded', 'memberpress'); ?></option>
    </select>
    <p class="description"><?php _e('The current status of the transaction', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label><?php _e('Gateway:', 'memberpress'); ?></label></th>
  <td>
    <?php echo MeprTransactionsHelper::payment_methods_dropdown('gateway', $txn->gateway); ?>
    <p class="description"><?php _e('The payment method associated with the transaction.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="subscr_num"><?php _e('Subscription:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="subscr_num" id="subscr_num" class="mepr_suggest_subscr_num" value="<?php echo $subscr_num; ?>" autocomplete="off" />
    <p class="description"><?php _e('The optional subscription to associate this transaction with.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label><?php _e('Created:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="created_at" id="created_at" value="<?php echo MeprAppHelper::format_date($txn->created_at, date('Y-m-d'), 'Y-m-d'); ?>" class="regular-text mepr-date-picker"/>&nbsp;<a href="#" class="mepr-today-button button" data-id="created_at"><?php _e('Today', 'memberpress'); ?></a>
    <p class="description"><?php _e('The date that the transaction was created on.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="expires_at"><?php _e('Expiration Date:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="expires_at" id="expires_at" value="<?php echo MeprAppHelper::format_date($txn->expires_at, '', 'Y-m-d'); ?>" class="regular-text mepr-date-picker"/>&nbsp;<a href="#" class="mepr-default-expiration-button button"><?php _e('Default', 'memberpress'); ?></a>&nbsp;<a href="#" class="mepr-lifetime-expiration-button button"><?php _e('Lifetime', 'memberpress'); ?></a>
    <p class="description"><?php _e('The date that the transaction will expire. This is used to determine how long the user will have access to the membership until another transaction needs to be made.', 'memberpress'); ?></p>
    <p class="description"><?php _e('<b>Note:</b> Blank indicates a <b>lifetime</b> expiration.','memberpress'); ?></p>
  </td>
</tr>
