<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprTransactionsHelper {
  /** Especially for formatting a subscription's price */
  public static function format_currency($txn, $show_symbol=true, $show_prorated=true) {
    $coupon_code = null;
    if($coupon = $txn->coupon()) { $coupon_code = $coupon->post_title; }

    if($obj = $txn->subscription())
      $price = $obj->price;
    else {
      $obj = $txn->product();

      //If the txn is expired -- don't check for pro-rations
      if($txn->is_expired())
        $obj->group_id = null;

      $price = $txn->amount;
    }

    $tax_info = array(
      'rate' => $obj->tax_rate,
      'desc' => $obj->tax_desc
    );

    return MeprAppHelper::format_price_string( $obj, $price, $show_symbol, $coupon_code, $show_prorated, $tax_info );
  }

  // For use in the new/edit transactions form
  public static function payment_methods_dropdown($field_name, $value = 'manual') {
    $mepr_options = MeprOptions::fetch();

    $pms = array_keys($mepr_options->integrations);

    $value = isset($_POST[$field_name]) ? $_POST[$field_name] : $value;

    ?>
    <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-multi-select mepr-payment-methods-select">
      <option value="manual" <?php selected($value,'manual'); ?>><?php _e('Manual', 'memberpress'); ?>&nbsp;</option>
      <?php
        foreach($pms as $pm_id):
          $obj = $mepr_options->payment_method($pm_id);
          if( $obj instanceof MeprBaseRealGateway ):
            ?>
            <option value="<?php echo $obj->id; ?>" <?php selected($value,$obj->id); ?>><?php printf(__('%1$s (%2$s)', 'memberpress'),$obj->label,$obj->name); ?>&nbsp;</option>
            <?php
          endif;
        endforeach;
      ?>
    </select>
    <?php
  }

  public static function get_email_vars() {
    return MeprHooks::apply_filters(
      'mepr_transaction_email_vars',
      array(
        'user_id',
        'user_login',
        'username',
        'user_email',
        'user_first_name',
        'user_last_name',
        'user_full_name',
        'user_address',
        'usermeta:*',
        'membership_type',
        'product_name',
        'coupon_code',
        'trans_num',
        'trans_date',
        'trans_expires_at',
        'trans_gateway',
        'trans_status',
        'payment_amount',
        'blog_name',
        'payment_subtotal',
        'tax_rate',
        'tax_amount',
        'tax_desc',
        'business_name',
        'biz_name',
        'biz_address1',
        'biz_address2',
        'biz_city',
        'biz_state',
        'biz_postcode',
        'biz_country',
        'login_url',
        'account_url'
      )
    );
  }

  public static function get_email_params($txn) {
    $mepr_options = MeprOptions::fetch();
    $usr          = $txn->user();
    $prd          = $txn->product();
    $pm           = $txn->payment_method();
    $cpn          = $txn->coupon();
    $created_at   = MeprAppHelper::format_date($txn->created_at, '');

    if(!isset($txn->expires_at) || empty($txn->expires_at)) {
      $expires_at = __('Unknown', 'memberpress');
    }
    else if($txn->expires_at==MeprUtils::db_lifetime()) {
      $expires_at = __('Never', 'memberpress');
    }
    else {
      //Confirmation txn? Let's guess what the expires at will be then
      if($txn->status == MeprTransaction::$confirmed_str) {
        $sub = $txn->subscription();

        if($sub->trial && $sub->trial_days) {
          $expires_at_ts  = strtotime($txn->created_at) + MeprUtils::days($sub->trial_days);
        }
        else {
          $expires_at_ts  = $prd->get_expires_at(strtotime($txn->created_at));
        }

        $expires_at     = MeprAppHelper::format_date(gmdate('c', $expires_at_ts), '');
      }
      else {
        $expires_at = MeprAppHelper::format_date($txn->expires_at, '');
      }
    }

    if($mepr_options->currency_symbol_after) {
      $payment_amount   = preg_replace('~\$~', '\\\$', sprintf(MeprUtils::format_currency_float($txn->total).'%s', stripslashes($mepr_options->currency_symbol)));
      $payment_subtotal = preg_replace('~\$~', '\\\$', sprintf(MeprUtils::format_currency_float($txn->amount).'%s', stripslashes($mepr_options->currency_symbol)));
      $tax_amount       = preg_replace('~\$~', '\\\$', sprintf(MeprUtils::format_currency_float($txn->tax_amount).'%s', stripslashes($mepr_options->currency_symbol)));
    }
    else {
      $payment_amount   = preg_replace('~\$~', '\\\$', sprintf('%s'.MeprUtils::format_currency_float($txn->total), stripslashes($mepr_options->currency_symbol)));
      $payment_subtotal = preg_replace('~\$~', '\\\$', sprintf('%s'.MeprUtils::format_currency_float($txn->amount), stripslashes($mepr_options->currency_symbol)));
      $tax_amount       = preg_replace('~\$~', '\\\$', sprintf('%s'.MeprUtils::format_currency_float($txn->tax_amount), stripslashes($mepr_options->currency_symbol)));
    }

    //Coupon title
    $cpn = ($cpn !== false)?$cpn->post_title:'';

    $params = array(
      'user_id'          => $usr->ID,
      'user_login'       => $usr->user_login,
      'username'         => $usr->user_login,
      'user_email'       => $usr->user_email,
      'user_first_name'  => $usr->first_name,
      'user_last_name'   => $usr->last_name,
      'user_full_name'   => $usr->full_name(),
      'user_address'     => $usr->formatted_address(),
      'membership_type'  => preg_replace('~\$~', '\\\$', $prd->post_title),
      'product_name'     => preg_replace('~\$~', '\\\$', $prd->post_title),
      'coupon_code'      => $cpn,
      'invoice_num'      => $txn->id,
      'trans_num'        => $txn->trans_num,
      'trans_date'       => $created_at,
      'trans_expires_at' => $expires_at,
      'trans_gateway'    => sprintf(__('%1$s (%2$s)', 'memberpress'), $pm->label, $pm->name),
      'trans_status'     => ucfirst($txn->status),
      'user_remote_addr' => $_SERVER['REMOTE_ADDR'],
      'payment_amount'   => $payment_amount,
      'blog_name'        => get_bloginfo('name'),
      'payment_subtotal' => $payment_subtotal,
      'tax_rate'         => $txn->tax_rate,
      'tax_amount'       => $tax_amount,
      'tax_desc'         => $txn->tax_desc,
      'business_name'    => $mepr_options->attr('biz_name'),
      'biz_name'         => $mepr_options->attr('biz_name'),
      'biz_address1'     => $mepr_options->attr('biz_address1'),
      'biz_address2'     => $mepr_options->attr('biz_address2'),
      'biz_city'         => $mepr_options->attr('biz_city'),
      'biz_state'        => $mepr_options->attr('biz_state'),
      'biz_postcode'     => $mepr_options->attr('biz_postcode'),
      'biz_country'      => $mepr_options->attr('biz_country'),
      'login_page'       => $mepr_options->login_page_url(),
      'account_url'      => $mepr_options->account_page_url(),
      'login_url'        => $mepr_options->login_page_url()
    );

    // When lifetime, include these subscription vars too
    if( ($sub = $txn->subscription()) ) {
      $sub_params = MeprSubscriptionsHelper::get_email_params($sub);
    }
    else { // Get as much info out there as we can
      $cc = (object)array( 'cc_last4' => '****', 'cc_exp_month' => '', 'cc_exp_year' => '' );

      $sub_params = array(
        'subscr_num'                  => $txn->trans_num,
        'subscr_date'                 => $created_at,
        'subscr_gateway'              => $params['trans_gateway'],
        'subscr_next_billing_at'      => __('Never', 'memberpress'),
        'subscr_expires_at'           => $expires_at,
        'subscr_terms'                => $params['payment_amount'],
        'subscr_next_billing_amount'  => $params['payment_amount'],
        'subscr_cc_num'               => MeprUtils::cc_num($cc->cc_last4),
        'subscr_cc_month_exp'         => $cc->cc_exp_month,
        'subscr_cc_year_exp'          => $cc->cc_exp_year,
        'subscr_renew_url'            => $mepr_options->login_page_url( 'redirect_to=' . urlencode($prd->url()) ),
        'subscr_update_url'           => $mepr_options->account_page_url(),
        'subscr_upgrade_url'          => $mepr_options->login_page_url( 'redirect_to=' . urlencode($prd->group_url()) )
      );
    }

    $params = array_merge($params, $sub_params);
    $ums    = MeprUtils::get_formatted_usermeta($usr->ID);

    if(!empty($ums)) {
      foreach($ums as $umkey => $umval) {
        $params["usermeta:{$umkey}"] = $umval;
      }
    }

    // You know we're just going to lump the user record fields in here no problem
    foreach((array)$usr->rec as $ukey => $uval) {
      $params["usermeta:{$ukey}"] = $uval;
    }

    // This filter is DEPRECATED ... please use mepr-transaction-email-params instead
    $params = MeprHooks::apply_filters( 'mepr_gateway_notification_params', $params, $txn );

    return MeprHooks::apply_filters( 'mepr_transaction_email_params', $params, $txn );
  }

  /** This is what we use to retrieve the invoice string for the transaction */
  public static function get_invoice( $txn ) {
    $prd = $txn->product();

    if( $sub = $txn->subscription() ) {
      if( $sub->trial && $sub->txn_count < 1 ) {
        $desc = __('Initial Payment', 'memberpress');

        $txn = new MeprTransaction();
        $txn->user_id = $sub->user_id;
        $txn->product_id = $sub->product_id;
        $txn->set_subtotal($sub->trial_amount);

        // Must do this *after* apply tax so we don't screw up the invoice
        $txn->subscription_id = $sub->id;
      }
      else if( $sub->txn_count >= 1 ) {
        $desc = __('Subscription Payment', 'memberpress');
      }
      else {
        $desc = __('Initial Payment', 'memberpress');
      }
    }
    else {
      $desc = __('Payment', 'memberpress') . MeprProductsHelper::renewal_str($prd);
    }

    if($coupon = $txn->coupon()) {
      $amount = $prd->price;
      $cpn_id = $coupon->ID;
      $cpn_desc = sprintf(__("Coupon Code '%s'", 'memberpress'), $coupon->post_title);
      $cpn_amount = MeprUtils::format_float((float)$amount - (float)$txn->amount);
    }
    else {
      $amount = $txn->amount;
      $cpn_id = 0;
      $cpn_desc = '';
      $cpn_amount = 0.00;
    }

    $invoice = MeprHooks::apply_filters(
      'mepr-invoice',
      array(
        'items' => array(
          array(
            'description' => $prd->post_title . '&nbsp;&ndash;&nbsp;' . $desc,
            'quantity' => 1,
            'amount' => $amount
          )
        ),
        'coupon' => array(
          'id' => $cpn_id,
          'desc' => $cpn_desc,
          'amount' => $cpn_amount
        ),
        'tax' => array(
          'percent' => $txn->tax_rate,
          'type' => $txn->tax_desc,
          'amount' => $txn->tax_amount
        )
      ),
      $txn
    );

    $show_quantity = MeprHooks::apply_filters('mepr-invoice-show-quantity', false, $txn);

    $quantities = array();
    foreach( $invoice['items'] as $item ) {
      $quantities[] = $item['amount'];
    }

    $subtotal = (float)array_sum( $quantities ) - (float)$invoice['coupon']['amount'];
    $total = $subtotal + $invoice['tax']['amount'];

    ob_start();

    if($sub = $txn->subscription()) {
      $prd = $sub->product();
      if($prd->register_price_action == 'custom' && !empty($prd->register_price) && !$txn->coupon_id && !$txn->prorated) {
        $sub_price_str = stripslashes($prd->register_price);
      }
      elseif($prd->register_price_action != 'hidden') {
        $sub_price_str = MeprSubscriptionsHelper::format_currency($sub);
      }
    }

    MeprView::render('/checkout/invoice', get_defined_vars());

    $invoice = ob_get_clean();
    return MeprHooks::apply_filters('mepr-invoice-html', $invoice, $txn );
  }

  public static function statuses_dropdown($field, $value, $id='', $classes='') {
    if(empty($id)) { $id = $field; }

    ?>
    <select name="<?php echo $field; ?>" id="<?php echo $id; ?>" class="<?php echo $classes; ?>">
      <option value="<?php echo MeprTransaction::$complete_str; ?>" <?php echo selected( $value, MeprTransaction::$complete_str ); ?>><?php _e('Complete', 'memberpress'); ?></option>
      <option value="<?php echo MeprTransaction::$pending_str; ?>" <?php echo selected( $value, MeprTransaction::$pending_str ); ?>><?php _e('Pending', 'memberpress'); ?></option>
      <option value="<?php echo MeprTransaction::$failed_str; ?>" <?php echo selected( $value, MeprTransaction::$failed_str ); ?>><?php _e('Failed', 'memberpress'); ?></option>
      <option value="<?php echo MeprTransaction::$refunded_str; ?>" <?php echo selected( $value, MeprTransaction::$refunded_str ); ?>><?php _e('Refunded', 'memberpress'); ?></option>
    </select>
    <?php
  }

  public static function transaction_membership_field( $field, $value='', $expires_at_field_id='', $id='', $classes='' ) {
    if(empty($id)) { $id = $field; }

    $products = MeprCptModel::all('MeprProduct');

    ?>

    <select
      name="<?php echo $field; ?>"
      id="<?php echo $id; ?>"
      class="mepr-membership-dropdown <?php echo $classes; ?>"
      data-expires_at_field_id="<?php echo $expires_at_field_id; ?>"
      >
      <?php foreach($products as $product): ?>
        <option value="<?php echo $product->ID; ?>" <?php selected( $value, $product->ID ); ?>><?php echo $product->post_title; ?></option>
      <?php endforeach; ?>
    </select>
    <?php
  }

  public static function transaction_created_at_field( $field, $value='', $id='', $classes='' ) {
    if(empty($id)) { $id = $field; }

    ?>
      <div id="<?php echo $id; ?>" class="mepr_transaction_created_field">
        <input
          type="text"
          name="<?php echo $field; ?>"
          value="<?php echo MeprAppHelper::format_date_utc($value, date('Y-m-d H:i:s'), 'Y-m-d H:i:s'); ?>"
          class="regular-text mepr-date-picker mepr-created-at"
        />
        <a href="" class="mepr-today-button button"><?php _e('Now', 'memberpress'); ?></a>
      </div>
    <?php
  }

  public static function transaction_expires_at_field( $field, $membership_field, $created_at_field, $value='', $id='', $classes='' ) {
    if(empty($id)) { $id = $field; }

    ?>
      <div
        id="<?php echo $id; ?>"
        data-membership_field_id="<?php echo $membership_field; ?>"
        data-created_at_field_id="<?php echo $created_at_field; ?>"
        class="mepr_transaction_expires_field">
        <input type="text" name="<?php echo $field; ?>"
          value="<?php echo MeprAppHelper::format_date_utc($value, '', 'Y-m-d H:i:s'); ?>"
          class="regular-text mepr-date-picker mepr-expires-at"
        />
        <a href="" class="mepr-default-expiration-button button"><?php _e('Default', 'memberpress'); ?></a>
        <a href="" class="mepr-lifetime-expiration-button button"><?php _e('Lifetime', 'memberpress'); ?></a>
      </div>
    <?php
  }
}
