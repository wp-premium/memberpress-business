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
        'trans_num',
        'trans_date',
        'trans_expires_at',
        'trans_gateway',
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
    $usr = $txn->user();
    $prd = $txn->product();
    $pm = $txn->payment_method();

    $created_at = MeprAppHelper::format_date($txn->created_at, '');

    if(!isset($txn->expires_at) || empty($txn->expires_at)) {
      $expires_at = __('Unknown', 'memberpress');
    }
    else if($txn->expires_at==MeprUtils::mysql_lifetime()) {
      $expires_at = __('Never', 'memberpress');
    }
    else {
      $expires_at = MeprAppHelper::format_date($txn->expires_at, '');
    }

    if($mepr_options->currency_symbol_after) {
      $payment_amount = preg_replace('~\$~', '\\\$', sprintf(MeprUtils::format_float($txn->total).'%s', stripslashes($mepr_options->currency_symbol)));
      $payment_subtotal = preg_replace('~\$~', '\\\$', sprintf(MeprUtils::format_float($txn->amount).'%s', stripslashes($mepr_options->currency_symbol)));
    }
    else {
      $payment_amount = preg_replace('~\$~', '\\\$', sprintf('%s'.MeprUtils::format_float($txn->total), stripslashes($mepr_options->currency_symbol)));
      $payment_subtotal = preg_replace('~\$~', '\\\$', sprintf('%s'.MeprUtils::format_float($txn->amount), stripslashes($mepr_options->currency_symbol)));
    }

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
      'invoice_num'      => $txn->id,
      'trans_num'        => $txn->trans_num,
      'trans_date'       => $created_at,
      'trans_expires_at' => $expires_at,
      'trans_gateway'    => sprintf(__('%1$s (%2$s)', 'memberpress'), $pm->label, $pm->name),
      'user_remote_addr' => $_SERVER['REMOTE_ADDR'],
      'payment_amount'   => $payment_amount,
      'blog_name'        => get_bloginfo('name'),
      'payment_subtotal' => $payment_subtotal,
      'tax_rate'         => $txn->tax_rate,
      'tax_amount'       => $txn->tax_amount,
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
      $cc = (object)array( 'cc_last4' => '', 'cc_exp_month' => '', 'cc_exp_year' => '' );

      if( !empty($txn->response) &&
          $res = json_decode($txn->response) &&
          !empty($res->cc_last4) &&
          !empty($res->cc_exp_month) &&
          !empty($res->cc_exp_year) ) {
        $cc = $res;
      }

      $sub_params = array(
        'subscr_num'             => $txn->trans_num,
        'subscr_date'            => $created_at,
        'subscr_gateway'         => $params['trans_gateway'],
        'subscr_next_billing_at' => __('Never', 'memberpress'),
        'subscr_expires_at'      => $expires_at,
        'subscr_terms'           => $params['payment_amount'],
        'subscr_cc_last4'        => $cc->cc_last4,
        'subscr_cc_month_exp'    => $cc->cc_exp_month,
        'subscr_cc_year_exp'     => $cc->cc_exp_year,
        'subscr_renew_url'       => $mepr_options->login_page_url( 'redirect_to=' . urlencode($prd->url()) ),
        'subscr_update_url'      => $mepr_options->account_page_url(),
        'subscr_upgrade_url'     => $mepr_options->login_page_url( 'redirect_to=' . urlencode($prd->group_url()) )
      );
    }

    $params = array_merge( $params, $sub_params );

    $ums = get_user_meta( $usr->ID );
    if(isset($ums) and is_array($ums)) {
      foreach( $ums as $umkey => $um ) {
        // Only support first val for now and yes some of these will be serialized values
        $params["usermeta:{$umkey}"] = $um[0];
      }
    }

    // You know we're just going to lump the user record fields in here no problem
    foreach( (array)$usr->rec as $ukey => $uval ) {
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
        $desc = __('Trial Payment', 'memberpress');

        $txn = new MeprTransaction();
        $txn->user_id = $sub->user_id;
        $txn->product_id = $sub->product_id;
        $txn->set_subtotal($sub->trial_amount);

        // Must do this *after* apply tax so we don't screw up the invoice
        $txn->subscription_id = $sub->ID;
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

    $subtotal = (float)array_sum( $quantities ) - (float)$cpn_amount;
    $total = $subtotal + $invoice['tax']['amount'];

    ob_start();

    if( $sub = $txn->subscription() ) {
      $sub_price_str = MeprSubscriptionsHelper::format_currency($sub);
    }

    MeprView::render('/checkout/invoice', get_defined_vars());

    $invoice = ob_get_clean();
    return MeprHooks::apply_filters('mepr-invoice-html', $invoice, $txn );
  }
}
