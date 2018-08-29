<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="taxes" class="mepr-options-hidden-pane">
  <h3><?php _e('Taxes', 'memberpress'); ?></h3>
  <table class="form-table">
    <tbody>
      <tr valign="top">
        <th scope="row">
          <label for="mepr_calculate_taxes"><?php _e('Enable Tax Calculations', 'memberpress'); ?></label>
          <?php MeprAppHelper::info_tooltip('mepr-options-calculate-taxes',
             __('Enable Tax Calculations', 'memberpress'),
             __('Enable tax calculations on transactions on all memberships other than those where tax calculations have been specifically disabled.', 'memberpress'));
          ?>
        </th>
        <td>
          <input type="checkbox" id="mepr_calculate_taxes" class="mepr-toggle-checkbox" data-box="mepr_tax_options_box" name="mepr_calculate_taxes" <?php checked($calculate_taxes != 0); ?>/>
        </td>
      </tr>
    </tbody>
  </table>
  <div id="mepr_tax_options_box" class="mepr-sub-box-white mepr_tax_options_box">
    <div class="mepr-arrow mepr-white mepr-up mepr-sub-box-arrow"> </div>
    <h3 class="mepr-page-heading"><?php _e('Tax Options', 'memberpress'); ?></h3>
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">
            <label for="<?php echo $mepr_options->attr_slug('tax_calc_type'); ?>"><?php _e('Prices Entered With Tax', 'memberpress'); ?></label>
            <?php MeprAppHelper::info_tooltip('mepr-tax-calc-type',
              __('Are membership prices exclusive or inclusive tax?', 'memberpress'),
              __('<strong>Exclusive:</strong> The customer will pay the price of the membership plus the cost of the tax.</br></br><strong>Inclusive:</strong> The customer will pay just the price of the membership but will include the tax.', 'memberpress')); ?>
          </th>
          <td>
            <select name="<?php echo $mepr_options->attr_slug('tax_calc_type'); ?>">
              <option value="exclusive" <?php selected('exclusive', $mepr_options->attr('tax_calc_type')); ?>><?php _e('Prices entered are exclusive of tax', 'memberpress'); ?></option>
              <option value="inclusive" <?php selected('inclusive', $mepr_options->attr('tax_calc_type')); ?>><?php _e('Prices entered are inclusive of tax', 'memberpress'); ?></option>
            </select>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="<?php echo $mepr_options->attr_slug('tax_calc_location'); ?>"><?php _e('Calculate Tax Based On', 'memberpress'); ?></label>
            <?php MeprAppHelper::info_tooltip('mepr-tax-calc-location',
              __('What location should taxes be based on?', 'memberpress'),
              __('This option will determine whether tax should be calculated based on the address that the customer enters or the address you entered on the "Info" tab here in MemberPress Options.', 'memberpress')); ?>
          </th>
          <td>
            <select name="<?php echo $mepr_options->attr_slug('tax_calc_location'); ?>">
              <option value="customer" <?php selected('customer', $mepr_options->attr('tax_calc_location')); ?>><?php _e('Customer Address', 'memberpress'); ?></option>
              <option value="merchant" <?php selected('merchant', $mepr_options->attr('tax_calc_location')); ?>><?php _e('Merchant Address', 'memberpress'); ?></option>
            </select>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label for="<?php echo $mepr_options->attr_slug('tax_default_address'); ?>"><?php _e('Default Address', 'memberpress'); ?></label>
            <?php MeprAppHelper::info_tooltip('mepr-tax-default-address',
              __('What default customer address should be used?', 'memberpress'),
              __('If there is no address set for the customer then MemberPress can either use the merchant address to calculate the tax or use no address at all which would result in no tax being applied to the sale.', 'memberpress')); ?>
          </th>
          <td>
            <select name="<?php echo $mepr_options->attr_slug('tax_default_address'); ?>">
              <option value="none" <?php selected('none', $mepr_options->attr('tax_default_address')); ?>><?php _e('No Address', 'memberpress'); ?></option>
              <option value="merchant" <?php selected('merchant', $mepr_options->attr('tax_default_address')); ?>><?php _e('Merchant Address', 'memberpress'); ?></option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
    <div>&nbsp;</div>

    <h3 class="mepr-page-heading"><?php _e('Tax Rates', 'memberpress'); ?></h3>
    <table class="form-table">
      <tbody>
        <?php MeprHooks::do_action('mepr_tax_rate_options'); ?>
      </tbody>
    </table>
    <div>&nbsp;</div>

    <h3 class="mepr-page-heading"><?php _e('Custom Tax Rates', 'memberpress'); ?></h3>
    <?php if(isset( $tax_rates ) && !empty( $tax_rates )): ?>
      <div id="mepr_tax_rates_box">
        <table id="mepr_custom_tax_rates" class="wp-list-table widefat">
          <thead>
            <tr>
              <th scope="col" class="manage-column"><?php _e('Country', 'memberpress'); ?></th>
              <th scope="col" class="manage-column"><?php _e('State', 'memberpress'); ?></th>
              <th scope="col" class="manage-column"><?php _e('Postcode', 'memberpress'); ?></th>
              <th scope="col" class="manage-column"><?php _e('City', 'memberpress'); ?></th>
              <th scope="col" class="manage-column"><?php _e('Rate %', 'memberpress'); ?></th>
              <th scope="col" class="manage-column"><?php _e('Desc', 'memberpress'); ?></th>
              <th scope="col" class="manage-column"><?php _e('Priority', 'memberpress'); ?></th>
              <th scope="col" class="manage-column">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach( $tax_rates as $i => $tax_rate ): ?>
              <tr id="mepr_tax_rate_row_<?php echo $tax_rate->id; ?>" <?php echo ( ( ($i % 2) == 0 ) ? 'class="alternate"' : '' ); ?>>
                <td><?php echo empty($tax_rate->tax_country)?'*':$tax_rate->tax_country; ?></td>
                <td><?php echo empty($tax_rate->tax_state)?'*':$tax_rate->tax_state; ?></td>
                <td><?php echo empty($tax_rate->postcodes)?'*':$tax_rate->postcodes; ?></td>
                <td><?php echo empty($tax_rate->cities)?'*':$tax_rate->cities; ?></td>
                <td><?php echo MeprUtils::format_float($tax_rate->tax_rate, 3); ?>%</td>
                <td><?php echo $tax_rate->tax_desc; ?></td>
                <td><?php echo $tax_rate->tax_priority; ?></td>
                <td width="25px"><a href="" class="mepr-tax-rate-remove alignright" data-id="<?php echo $tax_rate->id; ?>"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a></td>

              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div>&nbsp;</div>
      <div style="float: right;">
        <strong><a href="<?php
          echo MeprUtils::admin_url(
            'admin-ajax.php',
            array('export_tax_rates', 'mepr_taxes_nonce'),
            array(
              'action' => 'mepr_export_tax_rates'
            )
          );
        ?>" class="button"><?php _e('Export Tax Rates', 'memberpress'); ?></a></strong>
        <strong><a href="<?php
          echo MeprUtils::admin_url(
            'admin.php',
            array('clear_tax_rates', 'mepr_taxes_nonce'),
            array(
              'page' => 'memberpress-options',
              'action' => 'clear_tax_rates'
            )
          );
        ?>" class="button" onclick="if(!confirm('<?php echo 'Are you sure? This will delete all tax rates from the database'; ?>')){return false;}"><?php _e('Clear Tax Rates', 'memberpress'); ?></a></strong>
      </div>
      <br/>
    <?php else: ?>
      <div><strong><?php _e('No custom tax rates have been set. To add some, upload a csv file.', 'memberpress'); ?></strong></div>
    <?php endif; ?>

    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">
            <label for="mepr_tax_rates_csv"><?php _e('Upload Tax Rates', 'memberpress'); ?></label>
            <?php MeprAppHelper::info_tooltip('mepr-options-tax-rates',
              __('Upload Tax Rates', 'memberpress'),
              __('Upload Tax Rates via a CSV file. Use this to select a csv file ... then to upload, just hit the "Update Options" button.', 'memberpress'));
            ?>
          </th>
          <td>
            <input type="file" name="mepr_tax_rates_csv" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <?php MeprHooks::do_action('mepr_tax_options'); ?>
</div>
