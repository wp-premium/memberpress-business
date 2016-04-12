<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprCouponsHelper
{
  public static function products_dropdown($field_name, $access = array())
  {
    $access = is_array($access) ? $access : array();
    $contents = array();

    $posts = get_posts(array( 'numberposts' => -1, 'post_type' => 'memberpressproduct', 'post_status' => 'publish'));

    foreach($posts as $post)
      $contents[$post->ID] = $post->post_title;

    ?>
      <select name="<?php echo $field_name; ?>[]" id="<?php echo $field_name; ?>[]" class="mepr-multi-select mepr-coupon-products-select" multiple="true">
      <?php
        foreach($contents as $curr_type => $curr_label)
        {
          ?>
          <option value="<?php echo $curr_type; ?>" <?php echo (in_array($curr_type, $access))?'selected="selected"':''; ?>><?php echo $curr_label; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function months_options($ts)
  {
    if($ts <= 0)
      $ts = time();
    $months = array( __('Jan', 'memberpress'),
                     __('Feb', 'memberpress'),
                     __('Mar', 'memberpress'),
                     __('Apr', 'memberpress'),
                     __('May', 'memberpress'),
                     __('Jun', 'memberpress'),
                     __('Jul', 'memberpress'),
                     __('Aug', 'memberpress'),
                     __('Sept', 'memberpress'),
                     __('Oct', 'memberpress'),
                     __('Nov', 'memberpress'),
                     __('Dec', 'memberpress') );

    foreach( $months as $i => $month ):
      $val = $i + 1;
      ?>
      <option value="<?php echo $val; ?>" <?php echo (MeprUtils::get_date_from_ts($ts, 'n') == $val)?'selected="selected"':''; ?>><?php echo $month ?></option>
      <?php
    endforeach;
  }
}
