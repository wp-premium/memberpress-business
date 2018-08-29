<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAvalaraTaxRateCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    // Filter for MP Options page (field to enable VAT and collect VAT country & VAT ID)
    add_action('mepr_tax_rate_options', array($this,'options'));
    add_action('mepr-process-options', array($this,'store_options'));

    $calculate_taxes = get_option('mepr_calculate_taxes');
    $tax_avalara_enabled = get_option('mepr_tax_avalara_enabled');

    if($calculate_taxes && $tax_avalara_enabled) {
      // Filter for tax calculation
      add_filter('mepr_found_tax_rate', array($this,'find_rate'), 10, 6);
    }
  }

  public function options() {
    $mepr_options = MeprOptions::fetch();

    if(isset($_POST['mepr_tax_avalara_enabled']) and !empty($_POST['mepr_tax_avalara_enabled'])) {
      $tax_avalara_enabled = isset($_POST['mepr_tax_avalara_enabled']);
    }
    else {
      $tax_avalara_enabled = get_option('mepr_tax_avalara_enabled');
    }

    MeprView::render('/admin/taxes/avalara_options', get_defined_vars());
  }

  public function store_options() {
    update_option('mepr_tax_avalara_enabled', isset($_POST['mepr_tax_avalara_enabled']));
  }

  public function find_rate($tax_rate, $country, $state, $postcode, $city, $street) {
    $mepr_options = MeprOptions::fetch();
    $apikey   = $mepr_options->attr('tax_avalara_key');
    $accountID = $mepr_options->attr('tax_avalara_account_id');
    $auth      = base64_encode($accountID . ':' . $apikey);
    $street   = urlencode($street);
    $city     = urlencode($city);
    $postcode = urlencode($postcode);
    $state    = urlencode($state);

    if( strtoupper($country) == 'US' ) {
      $response = wp_remote_get(
        "https://rest.avatax.com/api/v2/taxrates/byaddress?line1={$street}&country={$country}&city={$city}&region={$state}&postalCode={$postcode}",
        array('headers' => array('Authorization' => "Basic {$auth}"))
      );

      if(is_wp_error($response)) {
        MeprUtils::debug_log(print_r($response, true));
      }
      else {
        $response_body = json_decode($response['body']);

        if(isset($response_body->totalRate)) {
          $tax_rate->tax_rate = $response_body->totalRate * 100; // MP expects a percent
          $tax_rate->tax_desc = __('Tax', 'memberpress');
        }
      }
    }

    return $tax_rate;
  }
}
