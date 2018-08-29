<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="wrap">
  <div class="icon32"></div>
  <h2><?php _e('Manually Add a New Member', 'memberpress'); ?></h2>

  <?php MeprView::render('/admin/errors', compact('errors','message')); ?>
  <form action="" method="post">
    <input type="hidden" name="action" value="create" />
    <?php wp_nonce_field('mepr_create_member', 'mepr_members_nonce'); ?>
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">
            <label for="member[user_login]"><?php _e('Username', 'memberpress'); ?></label>
          </th>
          <td>
            <input type="text" id="member_user_login" name="member[user_login]" class="regular-text" value="<?php echo esc_attr($member->user_login); ?>" autocomplete="off" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="member[user_email]"><?php _e('Email', 'memberpress'); ?></label>
          </th>
          <td>
            <input type="email" id="member_user_email" name="member[user_email]" class="regular-text" value="<?php echo esc_attr($member->user_email); ?>" autocomplete="off" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="member[first_name]"><?php _e('First Name', 'memberpress'); ?></label>
          </th>
          <td>
            <input type="text" id="member_first_name" name="member[first_name]" class="regular-text" value="<?php echo esc_attr($member->first_name); ?>" autocomplete="off" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="member[last_name]"><?php _e('Last Name', 'memberpress'); ?></label>
          </th>
          <td>
            <input type="text" id="member_last_name" name="member[last_name]" class="regular-text" value="<?php echo esc_attr($member->last_name); ?>" autocomplete="off" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="member[user_pass]"><?php _e('Password', 'memberpress'); ?></label>
          </th>
          <td>
            <span class="mepr-password-toggle">
              <button type="button" class="button button-secondary"><?php _e('Show password', 'memberpress'); ?></button>
            </span>
            <span class="mepr-password-input-wrapper mepr-hidden">
              <input type="text" name="member[user_pass]" id="member_user_pass" class="regular-text" autocomplete="off" value="<?php echo esc_attr($member->password); ?>" />
              <span class="mepr-password-input-buttons">
                <button type="button" class="mepr-hide-button button button-secondary">
                  <span class="dashicons dashicons-hidden"></span>
                  <span class="text" data-show="<?php _e('Show', 'memberpress'); ?>" data-hide="<?php _e('Hide', 'memberpress'); ?>"><?php _e('Hide', 'memberpress'); ?></span>
                </button>
                <button type="button" class="mepr-cancel-button button button-secondary">
                  <span class="text"><?php _e('Cancel', 'memberpress'); ?></span>
                </button>
              </span>
            </span>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="transaction[product_id]"><?php _e('Membership', 'memberpress'); ?></label>
            <?php
              MeprAppHelper::info_tooltip('mepr_transaction_product_id',
                __('Initial Membership', 'memberpress'),
                __('Select the initial membership you\'d like your new member to be part of.', 'memberpress'));
            ?>
          </th>
          <td>
            <?php MeprTransactionsHelper::transaction_membership_field( 'transaction[product_id]', $transaction->product_id, 'transaction_expires_at', 'transaction_product_id' ); ?>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="member[send_notification]"><?php _e('Send User Notification', 'memberpress'); ?></label>
          </th>
          <td>
            <input type="checkbox" id="member_send_notification" name="member[send_notification]" <?php checked($member->send_notification); ?> />
            <span><?php _e('Send the new member an email with their username', 'memberpress'); ?></span>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="transaction[send_welcome]"><?php _e('Send Welcome Email', 'memberpress'); ?></label>
          </th>
          <td>
            <input type="checkbox" id="transaction_send_welcome" name="transaction[send_welcome]" <?php checked($transaction->send_welcome); ?> />
            <span><?php _e('Send the new member a membership welcome email', 'memberpress'); ?></span>
          </td>
        </tr>
      </tbody>
    </table>
    <h3 class="mepr-page-title mepr-advanced-link"><a href="" class="mepr-toggle-link button button-default" data-box="mepr-advanced"><?php _e('Advanced', 'memberpress'); ?></a></h3>
    <div class="mepr-sub-box-white mepr-advanced mepr-hidden" style="display: none;">
      <div class="mepr-arrow mepr-white mepr-up mepr-sub-box-arrow"> </div>
      <p><?php _e('When a new member is added manually, an initial MemberPress transaction is created to grant them access to your membership. You can modify the details of this initial transaction here.', 'memberpress'); ?></p>
      <table class="form-table" id="mepr-advanced-box">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="transaction[trans_num]"><?php _e('Trans Num', 'memberpress'); ?></label>
              <?php
                MeprAppHelper::info_tooltip('mepr_transaction_trans_num',
                  __('Transaction Number', 'memberpress'),
                  __('This is typically the id of the transaction that is generated by the gateway. Typically this value won\'t need to be changed unless you\'re referencing a transaction that happened outside of MemberPress for this user and membership.', 'memberpress'));
              ?>
            </th>
            <td>
              <input type="text" id="transaction_trans_num" name="transaction[trans_num]" class="regular-text" value="<?php echo $transaction->trans_num; ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="transaction_amount"><?php _e('Amount', 'memberpress'); ?></label>
              <?php
                MeprAppHelper::info_tooltip('mepr_transaction_amount',
                  __('Amount', 'memberpress'),
                  __('This is the amount the member initially paid for the membership. Unless money has changed hands outside of MemberPress (and it\'s being recorded here) then we recommend this value stay at zero so your repots will remain accurate.', 'memberpress'));
              ?>
            </th>
            <td>
              <span><?php echo $mepr_options->currency_symbol; ?></span>
              <input type="text" id="transaction_amount" name="transaction[amount]" value="<?php echo MeprAppHelper::format_currency($transaction->amount,false,false); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="transaction[status]"><?php _e('Status', 'memberpress'); ?></label>
            </th>
            <td>
              <?php MeprTransactionsHelper::statuses_dropdown('transaction[status]', $transaction->status); ?>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="transaction[gateway]"><?php _e('Payment Method', 'memberpress'); ?></label>
              <?php
                MeprAppHelper::info_tooltip('mepr_transaction_gateway',
                  __('Payment Method', 'memberpress'),
                  __('Unless this is recording an actual transaction made outside of MemberPress but with one of your gateways, we recommend that this remain set to \'manual\'', 'memberpress')
                );
              ?>
            </th>
            <td>
              <?php echo MeprTransactionsHelper::payment_methods_dropdown('transaction[gateway]', $transaction->gateway); ?>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="transaction[created_at]"><?php _e('Created', 'memberpress'); ?></label>
            </th>
            <td>
              <?php
                MeprTransactionsHelper::transaction_created_at_field(
                  'transaction[created_at]',
                  $transaction->created_at,
                  'transaction_created_at'
                );
              ?>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="transaction[expires_at]"><?php _e('Expires', 'memberpress'); ?></label>
              <?php
                MeprAppHelper::info_tooltip('mepr_transaction_expires_at',
                  __('Expires At', 'memberpress'),
                  __('This refences the date and time that the user\'s membership granted by a transaction will expire and need to be renewed to continue access.', 'memberpress')
                );
              ?>
            </th>
            <td>
              <?php
                MeprTransactionsHelper::transaction_expires_at_field(
                  'transaction[expires_at]',
                  'transaction_product_id',
                  'transaction_created_at',
                  $transaction->expires_at,
                  'transaction_expires_at'
                );
              ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php /*
    <div>&nbsp;</div>
    <h3 class="mepr-page-title"><a href="" class="mepr-toggle-link" data-box="mepr-custom-fields"><?php _e('Custom Fields'); ?></a></h3>
    <div class="mepr-sub-box-white mepr-custom-fields mepr-hidden" style="display: none;">
      <div class="mepr-arrow mepr-white mepr-up mepr-sub-box-arrow"> </div>
      <table class="form-table" id="mepr-custom-fields-box">
        <tbody>
          <?php MeprUsersHelper::render_editable_custom_fields(); ?>
        </tbody>
      </table>
    </div>
    */ ?>
    <p class="submit">
      <input type="submit" id="submit" class="button button-primary" value="<?php _e('Create', 'memberpress'); ?>" />
    </p>
  </form>
</div>

