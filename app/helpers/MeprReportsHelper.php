<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprReportsHelper
{
  //Show a memberships dropdown
  public static function display_products_dropdown()
  {
    global $wpdb;
    $product = (isset($_GET['product']) && $_GET['product'] != 'all')?$_GET['product']:'all';
    $contents = array();

    $posts = MeprCptModel::all('MeprProduct');

    foreach($posts as $post)
      $contents[$post->ID] = $post->post_title;

    ?>
      <select name="product">
      <option value="all" <?php echo ($product == 'all')?'selected="selected"':''; ?>><?php _e('All', 'memberpress'); ?>&nbsp;</option>
      <?php
        foreach($contents as $curr_type => $curr_label)
        {
          ?>
          <option value="<?php echo $curr_type; ?>" <?php echo ($product == $curr_type)?'selected="selected"':''; ?>><?php echo $curr_label; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

//MONTHLY HELPERS
  public static function get_monthly_dropdowns_form()
  {
    ?>
      <form method="get" action="admin.php">
        <input type="hidden" name="page" value="memberpress-reports" />
        <?php wp_nonce_field('mepr_customize_report', 'mepr_reports_nonce'); ?>
        <label><?php _e('Month:', 'memberpress'); ?></label>
        <?php self::display_monthly_month_dropdown(); ?>
        <label>&nbsp;&nbsp;<?php _e('Year:', 'memberpress'); ?></label>
        <?php self::display_year_dropdown(); ?>
        <label>&nbsp;&nbsp;<?php _e('Membership:', 'memberpress'); ?></label>
        <?php self::display_products_dropdown(); ?>
        <input type="hidden" name="main-view" value="monthly" />
        &nbsp;&nbsp;<input type="submit" class="button button-primary" value="<?php _e('Go', 'memberpress'); ?>" />
      </form>
    <?php
  }

  public static function display_monthly_month_dropdown()
  {
    $month = (isset($_GET['month']) && !empty($_GET['month']) && is_numeric($_GET['month']))?$_GET['month']:date('n');
    $months = array( __('January', 'memberpress'),
                     __('February', 'memberpress'),
                     __('March', 'memberpress'),
                     __('April', 'memberpress'),
                     __('May', 'memberpress'),
                     __('June', 'memberpress'),
                     __('July', 'memberpress'),
                     __('August', 'memberpress'),
                     __('September', 'memberpress'),
                     __('October', 'memberpress'),
                     __('November', 'memberpress'),
                     __('December', 'memberpress') );

    echo '<select name="month">';
    foreach($months as $i => $m)
    {
      $val = $i + 1;
      $selected = ($month == $val)?'selected="selected"':'';
      echo '<option value="'.$val.'" '.$selected.'>'.$m.'</option>';
    }
    echo '</select>';
  }

//YEARLY HELPERS
  public static function get_yearly_dropdowns_form()
  {
    $month = (isset($_GET['month']))?$_GET['month']:date('n', time());
    ?>
      <form method="get" action="admin.php">
        <input type="hidden" name="page" value="memberpress-reports" />
        <input type="hidden" name="month" value="<?php echo $month; ?>" />
        <?php wp_nonce_field('mepr_customize_report', 'mepr_reports_nonce'); ?>
        <label><?php _e('Year:', 'memberpress'); ?></label>
        <?php self::display_year_dropdown(); ?>
        <label>&nbsp;&nbsp;<?php _e('Membership:', 'memberpress'); ?></label>
        <?php self::display_products_dropdown(); ?>
        <input type="hidden" name="main-view" value="yearly" />
        &nbsp;&nbsp;<input type="submit" class="button button-primary" value="<?php _e('Go', 'memberpress'); ?>" />
      </form>
    <?php
  }

  public static function display_year_dropdown()
  {
    $year = (isset($_GET['year']) && !empty($_GET['year']) && is_numeric($_GET['year']))?$_GET['year']:date('Y');
    $first = MeprReports::get_first_year();
    $last = MeprReports::get_last_year();

    echo '<select name="year">';

    if(($last - $first) >= 0)
      for($i = 0; $i <= ($last - $first); $i++)
      {
        $selected = ($year == ($first + $i))?'selected="selected"':'';
        echo '<option value="'.($first + $i).'" '.$selected.'>'.($first + $i).'</option>';
      }

    echo '</select>';
  }

//ALL-TIME HELPERS
  public static function get_all_time_dropdowns_form()
  {
    $month = (isset($_GET['month']))?$_GET['month']:date('n', time());
    $year = (isset($_GET['year']))?$_GET['year']:date('Y', time());
    ?>
      <form method="get" action="admin.php">
        <input type="hidden" name="page" value="memberpress-reports" />
        <input type="hidden" name="month" value="<?php echo $month; ?>" />
        <input type="hidden" name="year" value="<?php echo $year; ?>" />
        <?php wp_nonce_field('mepr_customize_report', 'mepr_reports_nonce'); ?>
        <label><?php _e('Membership:', 'memberpress'); ?></label>
        <?php self::display_products_dropdown(); ?>
        <input type="hidden" name="main-view" value="all-time" />
        &nbsp;&nbsp;<input type="submit" class="button button-primary" value="<?php _e('Go', 'memberpress'); ?>" />
      </form>
    <?php
  }
} //End class
