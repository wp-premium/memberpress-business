<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

if(!empty($payments)) {
  ?>
  <div class="mp_wrapper">
    <table id="mepr-account-payments-table" class="mepr-account-table">
      <thead>
        <tr>
          <th><?php _ex('Date', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Total', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Membership', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Method', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Status', 'ui', 'memberpress'); ?></th>
          <th><?php _ex('Invoice', 'ui', 'memberpress'); ?></th>
          <?php MeprHooks::do_action('mepr_account_payments_table_header'); ?>
        </tr>
      </thead>
      <tbody>
        <?php
          foreach($payments as $payment):
            $alt = (isset($alt) && !$alt);
            $txn = new MeprTransaction($payment->id);
            $pm  = $txn->payment_method();
            $prd = $txn->product();
        ?>
            <tr class="mepr-payment-row <?php echo ($alt)?'mepr-alt-row':''; ?>">
              <td data-label="<?php _ex('Date', 'ui', 'memberpress'); ?>"><?php echo MeprAppHelper::format_date($payment->created_at); ?></td>
              <td data-label="<?php _ex('Total', 'ui', 'memberpress'); ?>"><?php echo MeprAppHelper::format_currency( $payment->total <= 0.00 ? $payment->amount : $payment->total ); ?></td>

              <!-- MEMBERSHIP ACCESS URL -->
              <?php if(isset($prd->access_url) && !empty($prd->access_url)): ?>
                <td data-label="<?php _ex('Membership', 'ui', 'memberpress'); ?>"><a href="<?php echo stripslashes($prd->access_url); ?>"><?php echo MeprHooks::apply_filters('mepr-account-payment-product-name', $prd->post_title, $txn); ?></a></td>
              <?php else: ?>
                <td data-label="<?php _ex('Membership', 'ui', 'memberpress'); ?>"><?php echo MeprHooks::apply_filters('mepr-account-payment-product-name', $prd->post_title, $txn); ?></td>
              <?php endif; ?>

              <td data-label="<?php _ex('Method', 'ui', 'memberpress'); ?>"><?php echo (is_object($pm)?$pm->label:_x('Unknown', 'ui', 'memberpress')); ?></td>
              <td data-label="<?php _ex('Status', 'ui', 'memberpress'); ?>"><?php echo MeprAppHelper::human_readable_status($payment->status); ?></td>
              <td data-label="<?php _ex('Invoice', 'ui', 'memberpress'); ?>"><?php echo $payment->trans_num; ?></td>
              <?php MeprHooks::do_action('mepr_account_payments_table_row',$payment); ?>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div id="mepr-payments-paging">
      <?php if($prev_page): ?>
        <a href="<?php echo $account_url.$delim.'action=payments&currpage='.$prev_page; ?>">&lt;&lt; <?php _ex('Previous Page', 'ui', 'memberpress'); ?></a>
      <?php endif; ?>
      <?php if($next_page): ?>
        <a href="<?php echo $account_url.$delim.'action=payments&currpage='.$next_page; ?>" style="float:right;"><?php _ex('Next Page', 'ui', 'memberpress'); ?> &gt;&gt;</a>
      <?php endif; ?>
    </div>
    <div style="clear:both"></div>
  </div>
  <?php
}
else {
  ?><div class="mp-wrapper mp-no-subs"><?php
    _ex('You have no completed payments to display.', 'ui', 'memberpress');
  ?></div><?php
}

MeprHooks::do_action('mepr_account_payments', $mepr_current_user);
