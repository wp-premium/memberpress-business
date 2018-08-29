<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprSubscriptionsHelper {
  public static function get_email_vars() {
    return MeprHooks::apply_filters(
      'mepr_subscription_email_vars',
      array(
        'user_id',
        'user_login',
        'username',
        'user_email',
        'user_first_name',
        'user_last_name',
        'user_full_name',
        'user_address',
        'user_remote_addr',
        'usermeta:*',
        'membership_type',
        'product_name',
        'coupon_code',
        'signup_url',
        'subscr_num',
        'subscr_date',
        'subscr_gateway',
        'subscr_next_billing_at',
        'subscr_expires_at',
        'subscr_terms',
        'subscr_next_billing_amount',
        'subscr_cc_num',
        'subscr_cc_month_exp',
        'subscr_cc_year_exp',
        'subscr_renew_url',
        'subscr_update_url',
        'subscr_upgrade_url',
        'blog_name',
        'business_name',
        'biz_name',
        'biz_address1',
        'biz_address2',
        'biz_city',
        'biz_state',
        'biz_postcode',
        'biz_country',
        'login_url',
        'account_url',
        'login_page'
      )
    );
  }

  public static function get_email_params($sub) {
    $mepr_options = MeprOptions::fetch();
    $usr          = $sub->user();
    $prd          = $sub->product();
    $pm           = $sub->payment_method();
    $cpn          = $sub->coupon();
    $sub_date     = MeprAppHelper::format_date($sub->created_at, '');

    if($sub->expires_at == MeprUtils::db_lifetime()) {
      $expires_at = __('Never', 'memberpress');
    }
    else {
      $expires_at = MeprAppHelper::format_date($sub->expires_at, '');
    }

    $next_bill_amt = ($sub->total >= 0.00)?$sub->total:$sub->price;

    //Coupon title
    $cpn = ($cpn !== false)?$cpn->post_title:'';

    $params = array(
      'user_id'                     => $usr->ID,
      'user_login'                  => $usr->user_login,
      'username'                    => $usr->user_login,
      'user_email'                  => $usr->user_email,
      'user_first_name'             => $usr->first_name,
      'user_last_name'              => $usr->last_name,
      'user_full_name'              => $usr->full_name(),
      'user_address'                => $usr->formatted_address(),
      'user_remote_addr'            => $_SERVER['REMOTE_ADDR'],
      'membership_type'             => preg_replace('~\$~', '\\\$', $prd->post_title),
      'product_name'                => preg_replace('~\$~', '\\\$', $prd->post_title),
      'coupon_code'                 => $cpn,
      'signup_url'                  => $prd->url(),
      'subscr_num'                  => $sub->subscr_id,
      'subscr_date'                 => $sub_date,
      'subscr_gateway'              => sprintf(__('%1$s (%2$s)', 'memberpress'), $pm->label, $pm->name),
      'subscr_next_billing_at'      => MeprAppHelper::format_date($sub->next_billing_at, ''),
      'subscr_expires_at'           => $expires_at,
      'subscr_terms'                => preg_replace('~\$~', '\\\$', MeprSubscriptionsHelper::format_currency($sub)),
      'subscr_next_billing_amount'  => preg_replace('~\$~', '\\\$', MeprAppHelper::format_currency($next_bill_amt, true, false, true)),
      'subscr_cc_num'               => $sub->cc_num(),
      'subscr_cc_month_exp'         => sprintf( '%02d', $sub->cc_exp_month ),
      'subscr_cc_year_exp'          => $sub->cc_exp_year,
      'subscr_renew_url'            => $mepr_options->login_page_url( 'redirect_to=' . urlencode($prd->url()) ),
      'subscr_update_url'           => $mepr_options->login_page_url( 'redirect_to=' . urlencode($sub->update_url()) ),
      'subscr_upgrade_url'          => $mepr_options->login_page_url( 'redirect_to=' . urlencode($sub->upgrade_url()) ),
      'blog_name'                   => get_bloginfo('name'),
      'business_name'               => $mepr_options->attr('biz_name'),
      'biz_name'                    => $mepr_options->attr('biz_name'),
      'biz_address1'                => $mepr_options->attr('biz_address1'),
      'biz_address2'                => $mepr_options->attr('biz_address2'),
      'biz_city'                    => $mepr_options->attr('biz_city'),
      'biz_state'                   => $mepr_options->attr('biz_state'),
      'biz_postcode'                => $mepr_options->attr('biz_postcode'),
      'biz_country'                 => $mepr_options->attr('biz_country'),
      'login_page'                  => $mepr_options->login_page_url(),
      'account_url'                 => $mepr_options->account_page_url(),
      'login_url'                   => $mepr_options->login_page_url()
    );

    $ums = MeprUtils::get_formatted_usermeta($usr->ID);

    if(!empty($ums)) {
      foreach($ums as $umkey => $umval) {
        $params["usermeta:{$umkey}"] = $umval;
      }
    }

    // You know we're just going to lump the user record fields in here no problem
    foreach((array)$usr->rec as $ukey => $uval) {
      $params["usermeta:{$ukey}"] = $uval;
    }

    return MeprHooks::apply_filters( 'mepr_subscription_email_params', $params, $sub );
  }

  /** Especially for formatting a subscription's price */
  public static function format_currency($sub, $show_symbol=true, $show_prorated=true) {
    $coupon_code = null;
    if($coupon = $sub->coupon()) { $coupon_code = $coupon->post_title; }

    $tax_info = array(
      'rate' => $sub->tax_rate,
      'desc' => $sub->tax_desc
    );

    return MeprAppHelper::format_price_string( $sub, $sub->price, $show_symbol, $coupon_code, $show_prorated, $tax_info );
  }

  // For use in the new/edit subscriptions form
  public static function payment_methods_dropdown($field_name, $value = '') {
    $mepr_options = MeprOptions::fetch();

    $pms = array_keys($mepr_options->integrations);

    $value = isset($_POST[$field_name]) ? $_POST[$field_name] : $value;

    ?>
    <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-multi-select mepr-payment-methods-select">
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
}
