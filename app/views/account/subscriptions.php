<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

MeprHooks::do_action('mepr_before_account_subscriptions', $mepr_current_user);

if(!empty($subscriptions)) {
  $alt = false;
  ?>
  <div class="mp_wrapper">
    <table id="mepr-account-subscriptions-table" class="mepr-account-table">
      <thead>
        <tr>
          <th><?php _ex('Membership', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Subscription', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Active', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Created', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Expires', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Card Exp.', 'ui', 'memberpress'); ?></th>
          <th> </th>
          <?php MeprHooks::do_action('mepr-account-subscriptions-th', $mepr_current_user, $subscriptions); ?>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach($subscriptions as $s):
          if(trim($s->sub_type) == 'transaction') {
            $is_sub   = false;
            $txn      = $sub = new MeprTransaction($s->ID);
            $pm       = $txn->payment_method();
            $prd      = $txn->product();
            $default  = _x('Never', 'ui', 'memberpress');
          }
          else {
            $is_sub   = true;
            $sub      = new MeprSubscription($s->ID);
            $txn      = $sub->latest_txn();
            $pm       = $sub->payment_method();
            $prd      = $sub->product();
            if(trim($txn->expires_at) == MeprUtils::mysql_lifetime() or empty($txn->expires_at)) {
              $default = _x('Never', 'ui', 'memberpress');
            }
            else {
              $default = _x('Unknown', 'ui', 'memberpress');
            }
          }

          $mepr_options = MeprOptions::fetch();
          $alt          = !$alt; // Facilitiates the alternating lines
        ?>
          <tr id="mepr-subscription-row-<?php echo $s->ID; ?>" class="mepr-subscription-row <?php echo (isset($alt) && !$alt)?'mepr-alt-row':''; ?>">
            <td data-label="<?php _ex('Membership', 'ui', 'memberpress'); ?>">
              <!-- MEMBERSHIP ACCESS URL -->
              <?php if(isset($prd->access_url) && !empty($prd->access_url)): ?>
                <div class="mepr-account-product"><a href="<?php echo stripslashes($prd->access_url); ?>"><?php echo MeprHooks::apply_filters('mepr-account-subscr-product-name', $prd->post_title, $txn); ?></a></div>
              <?php else: ?>
                <div class="mepr-account-product"><?php echo MeprHooks::apply_filters('mepr-account-subscr-product-name', $prd->post_title, $txn); ?></div>
              <?php endif; ?>

              <div class="mepr-account-subscr-id"><?php echo $s->subscr_id; ?></div>
            </td>
            <td data-label="<?php _ex('Terms', 'ui', 'memberpress'); ?>">
              <div class="mepr-account-auto-rebill">
                <?php
                  if($is_sub):
                    echo ($s->status == MeprSubscription::$active_str)?_x('Enabled', 'ui', 'memberpress'):MeprAppHelper::human_readable_status($s->status, 'subscription');
                  elseif(is_null($s->expires_at) or $s->expires_at == MeprUtils::mysql_lifetime()):
                    _ex('Lifetime', 'ui', 'memberpress');
                  else:
                    _ex('None', 'ui', 'memberpress');
                  endif;
                ?>
              </div>
              <?php if($prd->register_price_action != 'hidden'): ?>
                <div class="mepr-account-terms">
                  <?php
                    if($prd->register_price_action == 'custom' && !empty($prd->register_price)) {
                      echo stripslashes($prd->register_price);
                    }
                    else {
                      echo MeprTransactionsHelper::format_currency($txn);
                    }
                  ?>
                </div>
              <?php endif; ?>
              <?php if($is_sub && ($nba = $sub->next_billing_at)): ?>
                <div class="mepr-account-rebill"><?php printf(_x('Next Billing: %s', 'ui', 'memberpress'), MeprAppHelper::format_date($nba)); ?></div>
              <?php endif; ?>
            </td>
            <td data-label="<?php _ex('Active', 'ui', 'memberpress'); ?>"><div class="mepr-account-active"><?php echo $s->active; ?></div></td>
            <td data-label="<?php _ex('Created', 'ui', 'memberpress'); ?>"><div class="mepr-account-created-at"><?php echo MeprAppHelper::format_date($s->created_at); ?></div></td>
            <td data-label="<?php _ex('Expires', 'ui', 'memberpress'); ?>"><div class="mepr-account-expires-at"><?php if($txn->txn_type == MeprTransaction::$payment_str || ($is_sub && !$sub->in_grace_period())) { echo MeprAppHelper::format_date($s->expires_at, $default); } else { _ex('processing', 'ui', 'memberpress'); } ?></div></td>
            <td data-label="<?php _ex('Card Expires', 'ui', 'memberpress'); ?>">
              <?php if( ($exp_mo = $sub->cc_exp_month) && ($exp_yr = $sub->cc_exp_year) ): ?>
                <?php $cc_class = (($sub->cc_expiring_before_next_payment())?' mepr-inactive':''); ?>
                <div class="mepr-account-cc-exp<?php echo $cc_class; ?>"><?php printf(_x('%1$02d-%2$d', 'ui', 'memberpress'), $exp_mo, $exp_yr); ?></div>
              <?php else: //Need a placeholder for responsive ?>
                <div>&zwnj;</div>
              <?php endif; ?>
            </td>
            <td data-label="<?php _ex('Actions', 'ui', 'memberpress'); ?>">
              <div class="mepr-account-actions">
                <?php
                  if($is_sub && $pm instanceof MeprBaseRealGateway && ($s->status == MeprSubscription::$active_str || $s->status == MeprSubscription::$suspended_str || strpos($s->active, 'mepr-active') !== false)) {
                    $pm->print_user_account_subscription_row_actions($s->ID);
                  }
                  elseif(!$is_sub && !empty($prd->ID)) {
                    if($prd->is_renewal()) {
                      ?>
                        <a href="<?php echo $prd->url(); ?>" class="mepr-account-row-action mepr-account-renew"><?php _ex('Renew', 'ui', 'memberpress'); ?></a>
                      <?php
                    }

                    if($prd->group() !== false) {
                      MeprAccountHelper::group_link($prd);
                    }
                    elseif(strpos($s->active, 'mepr-inactive') !== false) {
                      if($prd->can_you_buy_me()) {
                        MeprAccountHelper::purchase_link($prd);
                      }
                    }
                  }
                  else {
                    if($prd->can_you_buy_me()) {
                      MeprAccountHelper::purchase_link($prd);
                    }
                  }
                ?>
              </div>
            </td>
            <?php MeprHooks::do_action('mepr-account-subscriptions-td', $mepr_current_user, $s, $txn, $is_sub); ?>
          </tr>
        <?php endforeach; ?>
        <?php MeprHooks::do_action('mepr-account-subscriptions-table', $mepr_current_user, $subscriptions); ?>
      </tbody>
    </table>
    <div id="mepr-subscriptions-paging">
      <?php if($prev_page): ?>
        <a href="<?php echo "{$account_url}{$delim}action=subscriptions&currpage={$prev_page}"; ?>">&lt;&lt; <?php _ex('Previous Page', 'ui', 'memberpress'); ?></a>
      <?php endif; ?>
      <?php if($next_page): ?>
        <a href="<?php echo "{$account_url}{$delim}action=subscriptions&currpage={$next_page}"; ?>" style="float:right;"><?php _ex('Next Page', 'ui', 'memberpress'); ?> &gt;&gt;</a>
      <?php endif; ?>
    </div>
    <div style="clear:both"></div>
  </div>
  <?php
}
else {
  _ex('You have no active subscriptions to display.', 'ui', 'memberpress');
}

MeprHooks::do_action('mepr_account_subscriptions', $mepr_current_user);
