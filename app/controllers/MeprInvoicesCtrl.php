<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprInvoicesCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    if(/*MeprUpdateCtrl::is_activated() && */get_option('mepr_enable_invoice_downloads')) { //MeprUpdateCtrl isn't always defined this early
      add_action('mepr_account_payments_table_header', array($this,'table_header'));
      add_action('mepr_account_payments_table_row', array($this,'table_row'));
      add_action('wp_ajax_mepr_download_invoice', array($this,'ajax_download_invoice'));
    }
  }

  public function table_header() {
    ?><th><?php _ex('Download', 'ui', 'memberpress'); ?></th><?php
  }

  public function table_row($payment) {
    ?>
    <td data-label="<?php _ex('Download', 'ui', 'memberpress'); ?>">
      <a href="<?php
        echo MeprUtils::admin_url(
          'admin-ajax.php',
          array('download_invoice', 'mepr_invoices_nonce'),
          array(
            'action' => 'mepr_download_invoice',
            't' => $payment->id
          )
        ); ?>"><?php _ex('PDF', 'ui', 'memberpress'); ?></a>
    </td>
    <?php
  }

  private function get_invoice_locale() {
    $locale_map = array(
      'de_DE' => 'de',
      'de_CH' => 'de',
      'en_US' => 'en',
      'en_AU' => 'en',
      'en_CA' => 'en',
      'en_NZ' => 'en',
      'en_ZA' => 'en',
      'es_AR' => 'es',
      'es_CL' => 'es',
      'es_CO' => 'es',
      'es_GT' => 'es',
      'es_MX' => 'es',
      'es_PE' => 'es',
      'es_PR' => 'es',
      'es_ES' => 'es',
      'es_VE' => 'es',
      'fr_BE' => 'fr',
      'fr_CA' => 'fr',
      'fr_FR' => 'fr',
      'nl_NL' => 'nl',
      'nl_BE' => 'nl',
      //'zh_CN' => 'zh-CN', // We've apparently got to figure out fonts first
    );

    $locale = get_locale();

    return (isset($locale_map[$locale]) ? $locale_map[$locale] : 'en');
  }

  public function ajax_download_invoice() {
    check_ajax_referer('download_invoice', 'mepr_invoices_nonce');

    $url = 'https://mp-invoices.herokuapp.com/invoice/generate';

    if(!MeprUtils::is_user_logged_in()) { MeprUtils::exit_with_status(403,__('Forbidden', 'memberpress')); }

    if(!isset($_REQUEST['t'])) { MeprUtils::exit_with_status(400,__('No transaction specified', 'memberpress')); }

    if(!MeprUpdateCtrl::is_activated()) { MeprUtils::exit_with_status(403,__('MemberPress is unlicensed, please contact your system adminisrator.', 'memberpress')); }

    $txn = new MeprTransaction($_REQUEST['t']);
    if($txn->id <= 0) { MeprUtils::exit_with_status(400,__('Invalid Transaction', 'memberpress')); }

    $current_user = MeprUtils::get_currentuserinfo();
    if(!MeprUtils::is_mepr_admin() && $txn->user_id != $current_user->ID) { MeprUtils::exit_with_status(403,__('Forbidden Transaction', 'memberpress')); }

    $mepr_options = MeprOptions::fetch();
    $addr2 = $mepr_options->attr('biz_address2');

    $company_details =
      $mepr_options->attr('biz_address1') . "\n" .
      (empty($addr2) ? '' : "{$addr2}\n") .
      $mepr_options->attr('biz_city')     . ', ' .
      $mepr_options->attr('biz_state')    . ' ' .
      $mepr_options->attr('biz_postcode') . "\n" .
      MeprUtils::country_name($mepr_options->attr('biz_country'));

    $name = trim($current_user->full_name());
    if(!($company_name = get_user_meta($current_user->ID, 'mepr_company', true)) &&
       !($company_name = get_user_meta($current_user->ID, 'company', true)) &&
       !($company_name = get_user_meta($current_user->ID, 'mepr-company', true))) {
      $company_name = $name;
      $name = '';
    }

    $bill_to =
      (empty($company_name) ? '' : $company_name . "\n") .
      (empty($name) ? '' : $name . "\n") .
      $current_user->user_email . "\n";

    if($current_user->address_is_set()) {
      $addr = $current_user->full_address(false);

      $bill_to .=
        $addr['mepr-address-one'] . "\n" .
        (empty($addr['mepr-address-two']) ? '' : $addr['mepr-address-two'] . "\n") .
        $addr['mepr-address-city'] . ", " .
        $addr['mepr-address-state'] . " " .
        $addr['mepr-address-zip'] . "\n" .
        MeprUtils::country_name($addr['mepr-address-country']) . "\n";
    }

    $prd = $txn->product();

    $mepr_options = MeprOptions::fetch();
    $site_domain  = MeprUtils::site_domain();
    $blog_name    = get_option('blogname');
    $created_ts   = strtotime($txn->created_at);

    $json = json_encode(array(
      'locale' => $this->get_invoice_locale(),
      'invoice_number' => $txn->trans_num,
      'company_name' => $mepr_options->attr('biz_name'),
      'company_details' => $company_details,
      'bill_to' => $bill_to,
      'notes' => sprintf(__('For your purchase on %s','memberpress'), $blog_name),
      //'shipping_description' => "Well Not sure what youre shipping ... but here it is.",
      'tax_rate' => $txn->tax_rate,
      'tax_description' => $txn->tax_desc,
      'paid_at' => strftime('%D',$created_ts),
      'invoice_date' => strftime('%D',$created_ts),
      'items' => array(
        array(
          'amount' => $txn->amount,
          'description' => $prd->post_title,
        ),
      )
    ));

    $headers = array(
      'accept'              => 'application/pdf',
      'content-type'        => 'application/json',
      'X-Mothership-Auth'   => $mepr_options->mothership_license,
      'X-Mothership-Domain' => $site_domain
    );

    $headers_json = json_encode($headers);

    $response = wp_remote_post(
      $url,
      array(
        'method'      => 'POST',
        'timeout'     => 45,
        'redirection' => 5,
        'httpversion' => '1.1',
        'blocking'    => true,
        'headers'     => $headers,
        'body'        => $json,
        'cookies'     => array()
      )
    );

    header('Content-Type: application/pdf');
    header('Content-disposition: attachment; filename="'.$txn->trans_num.'_invoice.pdf"');

    MeprUtils::exit_with_status(200,$response['body']);
  }

} //End class
