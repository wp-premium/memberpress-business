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
            $txn      = $sub = new MeprTransaction($s->id);
            $pm       = $txn->payment_method();
            $prd      = $txn->product();
            $group    = $prd->group();
            $default  = _x('Never', 'ui', 'memberpress');
            if($txn->txn_type == MeprTransaction::$fallback_str && $mepr_current_user->subscription_in_group($group)) {
              //Skip fallback transactions when user has an active sub in the fallback group
              continue;
            }
          }
          else {
            $is_sub   = true;
            $sub      = new MeprSubscription($s->id);
            $txn      = $sub->latest_txn();
            $pm       = $sub->payment_method();
            $prd      = $sub->product();
            $group    = $prd->group();

            if($txn == false || !($txn instanceof MeprTransaction) || $txn->id <= 0) {
              $default = _x('Unknown', 'ui', 'memberpress');
            }
            else if(trim($txn->expires_at) == MeprUtils::db_lifetime() or empty($txn->expires_at)) {
              $default = _x('Never', 'ui', 'memberpress');
            }
            else {
              $default = _x('Unknown', 'ui', 'memberpress');
            }
          }

          $mepr_options = MeprOptions::fetch();
          $alt          = !$alt; // Facilitiates the alternating lines
        ?>
          <tr id="mepr-subscription-row-<?php echo $s->id; ?>" class="mepr-subscription-row <?php echo (isset($alt) && !$alt)?'mepr-alt-row':''; ?>">
            <td data-label="<?php _ex('Membership', 'ui', 'memberpress'); ?>">
              <!-- MEMBERSHIP ACCESS URL -->
              <?php if(isset($prd->access_url) && !empty($prd->access_url)): ?>
                <div class="mepr-account-product"><a href="<?php echo stripslashes($prd->access_url); ?>"><?php echo MeprHooks::apply_filters('mepr-account-subscr-product-name', $prd->post_title, $txn); ?></a></div>
              <?php else: ?>
                <div class="mepr-account-product"><?php echo MeprHooks::apply_filters('mepr-account-subscr-product-name', $prd->post_title, $txn); ?></div>
              <?php endif; ?>

              <?php if($txn != false && $txn instanceof MeprTransaction && !$txn->is_sub_account()): ?>
                <div class="mepr-account-subscr-id"><?php echo $s->subscr_id; ?></div>
              <?php endif; ?>
            </td>
            <td data-label="<?php _ex('Terms', 'ui', 'memberpress'); ?>">
              <div class="mepr-account-auto-rebill">
                <?php
                  if($txn != false && $txn instanceof MeprTransaction && $txn->is_sub_account()) {
                    ?>
                    <div class="mepr-account-sub-account-auto-rebill">
                      <?php _ex('Sub Account', 'ui', 'memberpress'); ?>
                      <?php MeprHooks::do_action('mepr_account_subscriptions_sub_account_auto_rebill', $txn); ?>
                    </div>
                    <?php
                  }
                  else {
                    if($is_sub):
                      echo ($s->status == MeprSubscription::$active_str)?_x('Enabled', 'ui', 'memberpress'):MeprAppHelper::human_readable_status($s->status, 'subscription');
                    elseif(is_null($s->expires_at) or $s->expires_at == MeprUtils::db_lifetime()):
                      _ex('Lifetime', 'ui', 'memberpress');
                    else:
                      _ex('None', 'ui', 'memberpress');
                    endif;
                  }
                ?>
              </div>
              <?php if($prd->register_price_action != 'hidden'): ?>
                <div class="mepr-account-terms">
                  <?php
                    if($txn != false && $txn instanceof MeprTransaction && $txn->is_sub_account()) {
                      MeprHooks::do_action('mepr_account_subscriptions_sub_account_terms', $txn);
                    }
                    else {
                      if($prd->register_price_action == 'custom' && !empty($prd->register_price)) {
                        //Add coupon in if one was used eh
                        $coupon_str = '';
                        if($is_sub) {
                          $subscr = new MeprSubscription($s->id);

                          if($subscr->coupon_id && ($coupon = new MeprCoupon($subscr->coupon_id)) && isset($coupon->ID) && $coupon->ID) {
                            $coupon_str = ' ' . _x('with coupon', 'ui', 'memberpress') . ' ' . $coupon->post_title;
                          }
                        }

                        echo stripslashes($prd->register_price) . $coupon_str;
                      }
                      else if($txn != false && $txn instanceof MeprTransaction) {
                        echo MeprTransactionsHelper::format_currency($txn);
                      }
                    }
                  ?>
                </div>
              <?php endif; ?>
              <?php if($txn != false && $txn instanceof MeprTransaction && !$txn->is_sub_account && $is_sub && ($nba = $sub->next_billing_at)): ?>
                <div class="mepr-account-rebill"><?php printf(_x('Next Billing: %s', 'ui', 'memberpress'), MeprAppHelper::format_date($nba)); ?></div>
              <?php endif; ?>
            </td>
            <td data-label="<?php _ex('Active', 'ui', 'memberpress'); ?>"><div class="mepr-account-active"><?php echo $s->active; ?></div></td>
            <td data-label="<?php _ex('Created', 'ui', 'memberpress'); ?>">
              <?php if($txn != false && $txn instanceof MeprTransaction && $txn->is_sub_account()): ?>
                <div>--</div>
              <?php else: ?>
                <div class="mepr-account-created-at"><?php echo MeprAppHelper::format_date($s->created_at); ?></div>
              <?php endif; ?>
            </td>
            <td data-label="<?php _ex('Expires', 'ui', 'memberpress'); ?>">
              <?php if($txn != false && $txn instanceof MeprTransaction && $txn->is_sub_account()): ?>
                <div>--</div>
              <?php else: ?>
                <div class="mepr-account-expires-at">
                  <?php if($txn != false && $txn instanceof MeprTransaction && $txn->txn_type == MeprTransaction::$payment_str || ($is_sub && !$sub->in_grace_period())) {
                          echo MeprAppHelper::format_date($s->expires_at, $default);
                        }
                        elseif($txn != false && $txn instanceof MeprTransaction && $txn->txn_type == MeprTransaction::$fallback_str) {
                          _ex('Never', 'ui', 'memberpress');
                        }
                        else {
                          _ex('processing', 'ui', 'memberpress');
                        }
                  ?>
                </div>
              <?php endif; ?>
            </td>
            <td data-label="<?php _ex('Card Expires', 'ui', 'memberpress'); ?>">
              <?php if($txn != false && $txn instanceof MeprTransaction && $txn->is_sub_account()): ?>
                <div>--</div>
              <?php else: ?>
                <?php if( ($exp_mo = $sub->cc_exp_month) && ($exp_yr = $sub->cc_exp_year) ): ?>
                  <?php $cc_class = (($sub->cc_expiring_before_next_payment())?' mepr-inactive':''); ?>
                  <div class="mepr-account-cc-exp<?php echo $cc_class; ?>"><?php printf(_x('%1$02d-%2$d', 'ui', 'memberpress'), $exp_mo, $exp_yr); ?></div>
                <?php else: //Need a placeholder for responsive ?>
                  <div>&zwnj;</div>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td data-label="<?php _ex('Actions', 'ui', 'memberpress'); ?>">
                <div class="mepr-account-actions">
                  <?php
                  if($txn != false && $txn instanceof MeprTransaction && ($txn->is_sub_account() || $txn->txn_type == MeprTransaction::$fallback_str)) {
                    echo '--';
                  }
                  else {
                    if( $is_sub && $pm instanceof MeprBaseRealGateway &&
                        ( $s->status == MeprSubscription::$active_str ||
                          $s->status == MeprSubscription::$suspended_str ||
                          strpos($s->active, 'mepr-active') !== false ) ) {
                      $subscription = new MeprSubscription($s->id);

                      if(!$subscription->in_grace_period()) { //Don't let people change shiz until a payment has come through yo
                        $pm->print_user_account_subscription_row_actions($subscription);
                      }
                    }
                    elseif(!$is_sub && !empty($prd->ID)) {
                      if($prd->is_renewable() && $prd->is_renewal()) {
                        ?>
                          <a href="<?php echo $prd->url(); ?>" class="mepr-account-row-action mepr-account-renew"><?php _ex('Renew', 'ui', 'memberpress'); ?></a>
                        <?php
                      }

                      if($txn != false && $txn instanceof MeprTransaction && $group !== false && strpos($s->active, 'mepr-inactive') === false) {
                        MeprAccountHelper::group_link($txn);
                      }
                      elseif(/*$group !== false &&*/ strpos($s->active, 'mepr-inactive') !== false /*&& !$prd->is_renewable()*/) {
                        if($prd->can_you_buy_me()) {
                          MeprAccountHelper::purchase_link($prd);
                        }
                      }
                    }
                    else {
                      if($prd->can_you_buy_me()) {
                        if($group !== false) {
                          MeprAccountHelper::purchase_link($prd);
                        }
                        else if($txn != false && $txn instanceof MeprTransaction) {
                          $sub_in_group   = $mepr_current_user->subscription_in_group($group);
                          $life_in_group  = $mepr_current_user->lifetime_subscription_in_group($group);
                          if(!$sub_in_group && !$life_in_group) { //$prd is in group, but user has no other active subs in this group, so let's show the change plan option
                            MeprAccountHelper::purchase_link($prd, _x('Re-Subscribe', 'ui', 'memberpress'));
                            MeprAccountHelper::group_link($txn);
                          }
                        }
                      }
                    }

                    MeprHooks::do_action('mepr-account-subscriptions-actions', $mepr_current_user, $s, $txn, $is_sub);

                  }
                  ?>
                  &zwnj; <!-- Responsiveness when no actions present -->
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
