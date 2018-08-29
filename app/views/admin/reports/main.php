<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="wrap">
  <div class="icon32"></div>
  <h2><?php _e('Reports', 'memberpress'); ?></h2>

  <div id="overall_info_blocks">
    <div class="info_block">
      <span class="info_block_title"><?php _e('Active Members', 'memberpress'); ?></span>
      <h3><?php echo MeprReports::get_active_members_count(); ?></h3>
    </div>

    <div class="info_block">
      <span class="info_block_title"><?php _e('Inactive Members', 'memberpress'); ?></span>
      <h3><?php echo MeprReports::get_inactive_members_count(); ?></h3>
    </div>

    <div class="info_block">
      <span class="info_block_title"><?php _e('Total Members', 'memberpress'); ?></span>
      <h3><?php echo MeprReports::get_total_members_count(); ?></h3>
    </div>

    <div class="info_block">
      <span class="info_block_title"><?php _e('Total WP Users', 'memberpress'); ?></span>
      <h3><?php echo MeprReports::get_total_wp_users_count(); ?></h3>
    </div>

    <div class="info_block">
      <span class="info_block_title"><?php _e('Active Free Members', 'memberpress'); ?></span>
      <h3><?php echo MeprReports::get_free_active_members_count(); ?></h3>
    </div>

    <div class="info_block">
      <span class="info_block_title"><?php _e('Active Paid Members', 'memberpress'); ?></span>
      <h3><?php echo MeprReports::get_paid_active_members_count(); ?></h3>
    </div>
    <div class="info_block">
      <span class="info_block_title"><?php _e('Avg Mbr Lifetime Val', 'memberpress'); ?></span>
      <h3><?php echo MeprAppHelper::format_currency(MeprReports::get_average_lifetime_value(), true, false); ?></h3>
    </div>
<?php /** These are slowing things down too much for now
    <div class="info_block">
      <span class="info_block_title"><?php _e('Avg Num Mbr Pmts', 'memberpress'); ?></span>
      <h3><?php echo MeprUtils::format_float(MeprReports::get_average_payments_per_member()); ?></h3>
    </div>

    <div class="info_block">
      <span class="info_block_title"><?php _e('% Members Rebill', 'memberpress'); ?></span>
      <h3><?php echo MeprUtils::format_float(MeprReports::get_percentage_members_who_rebill()) . '%'; ?></h3>
    </div>
*/ ?>
  </div>

  <h2 class="nav-tab-wrapper">
    <a class="nav-tab main-nav-tab" href="#" id="monthly"><?php _e('Monthly', 'memberpress'); ?></a>
    <a class="nav-tab main-nav-tab" href="#" id="yearly"><?php _e('Yearly', 'memberpress'); ?></a>
    <a class="nav-tab main-nav-tab" href="#" id="all-time"><?php _e('All-Time', 'memberpress'); ?></a>
  </h2>

<!-- MONTHLY AREA STUFF HERE -->
  <div id="monthly-reports-area" class="monthly mepr_reports_area">
    <div id="monthly-dropdowns-form" class="dropdown_form">
      <?php MeprReportsHelper::get_monthly_dropdowns_form(); ?>
    </div>

    <?php if($curr_product == 'all'): ?>
      <div id="monthly-pie-chart-area" class="pie_chart_area">
        <img src="<?php echo admin_url('images/wpspin_light-2x.gif'); ?>" width="16px" height="16px" />
      </div>
    <?php endif; ?>

    <div id="monthly-info-blocks" class="info_blocks_area">
      <?php MeprView::render('/admin/reports/month_info_blocks', get_defined_vars()); ?>
    </div>

    <div class="float_block_separator"></div>

    <div id="monthly-area-graphs" class="area_graphs">
      <h2 class="nav-tab-wrapper">
        <a class="nav-tab monthly-nav-tab nav-tab-active" href="#" id="mamounts"><?php _e('Amounts', 'memberpress'); ?></a>
        <a class="nav-tab monthly-nav-tab" href="#" id="mtransactions"><?php _e('Transactions', 'memberpress'); ?></a>
      </h2>

      <div id="monthly-amounts-area-graph" class="mamounts monthly_graph_area">
        <img src="<?php echo admin_url('images/wpspin_light-2x.gif'); ?>" width="16px" height="16px" />
      </div>

      <div id="monthly-transactions-area-graph" class="mtransactions monthly_graph_area">
        <img src="<?php echo admin_url('images/wpspin_light-2x.gif'); ?>" width="16px" height="16px" />
      </div>
    </div>

    <div id="monthly-data-table">
      <?php MeprView::render('/admin/reports/month_table', get_defined_vars()); ?>
    </div>
  </div>

<!-- YEARLY AREA STUFF HERE -->
  <div id="yearly-reports-area" class="yearly mepr_reports_area">
    <div id="yearly-dropdowns-form" class="dropdown_form">
      <?php MeprReportsHelper::get_yearly_dropdowns_form(); ?>
    </div>

    <?php if($curr_product == 'all'): ?>
      <div id="yearly-pie-chart-area" class="pie_chart_area">
        <img src="<?php echo admin_url('images/wpspin_light-2x.gif'); ?>" width="16px" height="16px" />
      </div>
    <?php endif; ?>

    <div id="yearly-info-blocks" class="info_blocks_area">
      <?php MeprView::render('/admin/reports/year_info_blocks', get_defined_vars()); ?>
    </div>

    <div class="float_block_separator"></div>

    <div id="yearly-area-graphs" class="area_graphs">
      <h2 class="nav-tab-wrapper">
        <a class="nav-tab yearly-nav-tab nav-tab-active" href="#" id="yamounts"><?php _e('Amounts', 'memberpress'); ?></a>
        <a class="nav-tab yearly-nav-tab" href="#" id="ytransactions"><?php _e('Transactions', 'memberpress'); ?></a>
      </h2>

      <div id="yearly-amounts-area-graph" class="yamounts yearly_graph_area">
        <img src="<?php echo admin_url('images/wpspin_light-2x.gif'); ?>" width="16px" height="16px" />
      </div>

      <div id="yearly-transactions-area-graph" class="ytransactions yearly_graph_area">
        <img src="<?php echo admin_url('images/wpspin_light-2x.gif'); ?>" width="16px" height="16px" />
      </div>
    </div>

    <div id="yearly-data-table">
      <?php MeprView::render('/admin/reports/year_table', get_defined_vars()); ?>
    </div>
  </div>

<!-- ALL-TIME AREA STUFF HERE -->
  <div id="all-time-reports-area" class="all-time mepr_reports_area">
    <div id="all-time-dropdowns-form" class="dropdown_form">
      <?php MeprReportsHelper::get_all_time_dropdowns_form(); ?>
    </div>

    <?php if($curr_product == 'all'): ?>
      <div id="all-time-pie-chart-area" class="pie_chart_area">
        <img src="<?php echo admin_url('images/wpspin_light-2x.gif'); ?>" width="16px" height="16px" />
      </div>
    <?php endif; ?>

    <div id="all-time-info-blocks" class="info_blocks_area">
      <?php MeprView::render('/admin/reports/all_time_info_blocks', get_defined_vars()); ?>
    </div>

    <div class="float_block_separator"></div>
  </div>

<!-- HIDDEN STUFF FOR JS HERE -->
  <div id="mepr-reports-hidden-stuff">
    <div id="mepr-reports-main-view" data-value="<?php echo (isset($_GET['main-view']) && !empty($_GET['main-view']))?$_GET['main-view']:'monthly'; ?>"></div>
    <div id="mepr-reports-pie-title" data-value="<?php _e('Total Transactions By Membership', 'memberpress'); ?>"></div>
    <div id="mepr-reports-monthly-areas-title" data-value="<?php _e('Amounts By Day Of Month', 'memberpress'); ?>"></div>
    <div id="mepr-reports-monthly-transactions-title" data-value="<?php _e('Transactions By Day Of Month', 'memberpress'); ?>"></div>
    <div id="mepr-reports-monthly-htitle" data-value="<?php _e('Day Of Month', 'memberpress'); ?>"></div>
    <div id="mepr-reports-yearly-areas-title" data-value="<?php _e('Amounts By Month Of Year', 'memberpress'); ?>"></div>
    <div id="mepr-reports-yearly-transactions-title" data-value="<?php _e('Transactions By Month Of Year', 'memberpress'); ?>"></div>
    <div id="mepr-reports-yearly-htitle" data-value="<?php _e('Month Of Year', 'memberpress'); ?>"></div>
    <div id="mepr-reports-currency-symbol" data-value="<?php echo $mepr_options->currency_symbol; ?>"></div>
  </div>
</div>
