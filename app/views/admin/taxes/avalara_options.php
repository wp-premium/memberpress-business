<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<tr valign="top">
  <th scope="row">
    <label for="mepr_tax_avalara_enabled"><a href="http://taxratesapi.avalara.com/" target="_blank"><?php _e('Enable Avalara TaxRates', 'memberpress'); ?></a></label>
    <?php MeprAppHelper::info_tooltip( 'mepr-enable-tax-avalara',
      __('Get US Tax Rates from Avalara', 'memberpress'),
      __('Avalara has a great Tax Rate API for automatically finding the correct tax rate for your customers in the US. Check this option to pull in rates automatically from Avalara on each transaction.<br/><br/>NOTE: This will override any tax rates for the US you\'ve imported via CSV.', 'memberpress'));
    ?>
  </th>
  <td>
    <input type="checkbox" id="mepr_tax_avalara_enabled" name="mepr_tax_avalara_enabled" class="mepr-toggle-checkbox" data-box="mepr_tax_avalara_box" value="mepr_tax_avalara_enabled" <?php checked($tax_avalara_enabled); ?> />
  </td>
</tr>
<tr valign="top">
  <td colspan="2" class="mepr-sub-box-wrapper">
    <div id="mepr_tax_avalara_box" class="mepr-sub-box mepr_tax_avalara_box">
      <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('tax_avalara_account_id'); ?>"><?php _e('Avalara Account ID', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                'mepr-merchant-tax-avalara-account-id',
                __('Avalara Account ID', 'memberpress'),
                __('You can create a free account by registering for Avalara\'s Tax Rate API at taxratesapi.avalara.com.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input id="<?php echo $mepr_options->attr_slug('tax_avalara_account_id'); ?>" name="<?php echo $mepr_options->attr_slug('tax_avalara_account_id'); ?>" class="regular-text" value="<?php echo $mepr_options->attr('tax_avalara_account_id'); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('tax_avalara_key'); ?>"><?php _e('Avalara License Key', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                'mepr-merchant-tax-avalara-key',
                __('Avalara License Key', 'memberpress'),
                __('You can get a free License Key by registering for Avalara\'s Tax Rate API at taxratesapi.avalara.com.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input id="<?php echo $mepr_options->attr_slug('tax_avalara_key'); ?>" name="<?php echo $mepr_options->attr_slug('tax_avalara_key'); ?>" class="regular-text" value="<?php echo $mepr_options->attr('tax_avalara_key'); ?>" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </td>
</tr>
