<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<table class="widefat" style="margin-top:25px;">
  <thead>
    <tr>
      <th width="15%"><?php _e('Date', 'memberpress'); ?></th>
      <th width="12%"><?php _e('Pending', 'memberpress'); ?></th>
      <th width="12%"><?php _e('Failed', 'memberpress'); ?></th>
      <th width="12%"><?php _e('Complete', 'memberpress'); ?></th>
      <th width="12%"><?php _e('Refunded', 'memberpress'); ?></th>
      <th width="12%"><?php _e('Collected', 'memberpress'); ?></th>
      <th width="12%"><?php _e('Refunded', 'memberpress'); ?></th>
      <th width="12%"><?php _e('Tax', 'memberpress'); ?></th>
      <th width="12%"><?php _e('Net Total', 'memberpress'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php
    $records = MeprReports::get_monthly_data('transactions', $curr_month, $curr_year, $curr_product);
    $pTotal = $fTotal = $cTotal = $rTotal = $revTotal = $refTotal = $taxTotal = 0;
    $row_index = 0;

    foreach($records as $r) {
      $revenue = (float)MeprReports::get_revenue($curr_month, $r->day, $curr_year, $curr_product);
      $taxes = (float)MeprReports::get_taxes($curr_month, $r->day, $curr_year, $curr_product);
      $refunds = (float)MeprReports::get_refunds($curr_month, $r->day, $curr_year, $curr_product);
      $alternate = ( $row_index++ % 2 ? '' : 'alternate' );
    ?>
      <tr class="<?php echo $alternate; ?>">
        <td>
          <a href="<?php echo admin_url('admin.php?page=memberpress-trans&product='.$curr_product.'&month='.$curr_month.'&day='.$r->day.'&year='.$curr_year); ?>">
            <?php echo MeprReports::make_table_date($curr_month, $r->day, $curr_year); ?>
          </a>
        </td>
        <td>
          <a href="<?php echo admin_url('admin.php?page=memberpress-trans&product='.$curr_product.'&month='.$curr_month.'&day='.$r->day.'&year='.$curr_year.'&search=pending'); ?>">
            <?php echo $r->p; $pTotal += $r->p; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo admin_url('admin.php?page=memberpress-trans&product='.$curr_product.'&month='.$curr_month.'&day='.$r->day.'&year='.$curr_year.'&search=failed'); ?>">
            <?php echo $r->f; $fTotal += $r->f; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo admin_url('admin.php?page=memberpress-trans&product='.$curr_product.'&month='.$curr_month.'&day='.$r->day.'&year='.$curr_year.'&search=complete'); ?>">
            <?php echo $r->c; $cTotal += $r->c; ?>
          </a>
        </td>
        <td>
          <a href="<?php echo admin_url('admin.php?page=memberpress-trans&product='.$curr_product.'&month='.$curr_month.'&day='.$r->day.'&year='.$curr_year.'&search=refunded'); ?>">
            <?php echo $r->r; $rTotal += $r->r; ?>
          </a>
        </td>
        <td style="color:green;"><?php echo MeprAppHelper::format_currency(($revenue + $refunds + $taxes),true,false); $revTotal += $revenue; ?></td>
        <td style="color:red;"><?php echo MeprAppHelper::format_currency($refunds,true,false); $refTotal += $refunds; ?></td>
        <td style="color:orange;"><?php echo MeprAppHelper::format_currency($taxes,true,false); $taxTotal += $taxes; ?></td>
        <td style="color:navy;"><?php echo MeprAppHelper::format_currency($revenue,true,false); ?></td>
      </tr>
    <?php
    }
    ?>
    </tbody>
    <tfoot>
      <tr>
        <th><?php _e('Totals', 'memberpress'); ?></th>
        <th><?php echo $pTotal; ?></th>
        <th><?php echo $fTotal; ?></th>
        <th><?php echo $cTotal; ?></th>
        <th><?php echo $rTotal; ?></th>
        <th style="color:green;"><?php echo MeprAppHelper::format_currency(($revTotal + $refTotal + $taxTotal),true,false); ?></th>
        <th style="color:red;"><?php echo MeprAppHelper::format_currency($refTotal,true,false); ?></th>
        <th style="color:orange;"><?php echo MeprAppHelper::format_currency($taxTotal,true,false); ?></th>
        <th style="color:navy;"><?php echo MeprAppHelper::format_currency($revTotal,true,false); ?></th>
      </tr>
  </tfoot>
</table>
<div>&nbsp;</div>
<div>
  <a class="button" href="<?php echo admin_url( "admin-ajax.php?action=mepr_export_report&export=monthly&{$_SERVER['QUERY_STRING']}" ); ?>"><?php _e('Export as CSV', 'memberpress'); ?></a>
  <?php MeprHooks::do_action('mepr-report-footer','monthly'); ?>
</div>

