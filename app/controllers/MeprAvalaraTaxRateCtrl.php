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
    $apikey   = urlencode($mepr_options->attr('tax_avalara_key'));
    $street   = urlencode($street);
    $city     = urlencode($city);
    $postcode = urlencode($postcode);
    $state    = urlencode($state);

    if( strtoupper($country) == 'US' ) {
      $res = wp_remote_get(
        "https://taxrates.api.avalara.com:443/address?country=usa&street={$street}&city={$city}&state={$state}&postal={$postcode}&apikey={$apikey}",
        array('sslverify' => false, 'headers' => array(), 'body' => array())
      );

      if(!is_wp_error($res)) {
        $obj = json_decode($res['body']);

        if(isset($obj->totalRate)) {
          $tax_rate->tax_rate = $obj->totalRate;
          $tax_rate->tax_desc = __('Tax', 'memberpress');
        }
      }
    }

    return $tax_rate;
  }
}

