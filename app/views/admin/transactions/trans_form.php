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
    <?php wp_nonce_field( 'mepr_create_or_update_transaction', 'mepr_transactions_nonce' ); ?>
    <input type="text" name="user_login" id="user_login" class="mepr_suggest_user" value="<?php echo $user_login; ?>" autocomplete="off" />
    <p class="description"><?php _e('The user who made this transaction.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="product_id"><?php _e('Membership*:', 'memberpress'); ?></label></th>
  <td>
    <?php $prds = MeprCptModel::all('MeprProduct'); ?>
    <select name="product_id" id="product_id" class="mepr-membership-dropdown" data-expires_at_field_id="expires_at">
      <?php foreach($prds as $product): ?>
        <option value="<?php echo $product->ID; ?>" <?php selected( $txn->product_id, $product->ID ); ?>><?php echo $product->post_title; ?></option>
      <?php endforeach; ?>
    </select>
    <p class="description"><?php _e('The membership that was purchased', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="amount"><?php _e('Sub-Total*:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo $mepr_options->currency_symbol; ?></span>
    <input type="text" name="amount" id="amount" value="<?php echo MeprUtils::format_float($txn->amount); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php _e('The sub-total (amount before tax) of this transaction', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="tax_amount"><?php _e('Tax Amount*:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo $mepr_options->currency_symbol; ?></span>
    <input type="text" name="tax_amount" id="tax_amount" value="<?php echo MeprUtils::format_float($txn->tax_amount); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php _e('The amount of taxes for this transaction', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="tax_rate"><?php _e('Tax Rate*:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="tax_rate" id="tax_rate" value="<?php echo MeprUtils::format_float($txn->tax_rate, 3); ?>" class="regular-text" style="width:95px !important;"/>
    <span><?php echo '%'; ?></span>
    <p class="description"><?php _e('The tax rate in percentage. (Ex: 10.000 for 10%)', 'memberpress'); ?></p>
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
  <th scope="row"><label><?php _e('Created (UTC/GMT):', 'memberpress'); ?></label></th>
  <td>
    <?php MeprTransactionsHelper::transaction_created_at_field( 'created_at', $txn->created_at ); ?>
    <p class="description"><?php _e('The date that the transaction was created on. This field is displayed in UTC/GMT.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="expires_at"><?php _e('Expiration Date (UTC/GMT):', 'memberpress'); ?></label></th>
  <td>
    <?php MeprTransactionsHelper::transaction_expires_at_field( 'expires_at', 'product_id', 'created_at', $txn->expires_at ); ?>
    <p class="description"><?php _e('The date that the transaction will expire. This is used to determine how long the user will have access to the membership until another transaction needs to be made. This field is displayed in UTC/GMT', 'memberpress'); ?></p>
    <p class="description"><?php _e('<b>Note:</b> Blank indicates a <b>lifetime</b> expiration.','memberpress'); ?></p>
  </td>
</tr>
