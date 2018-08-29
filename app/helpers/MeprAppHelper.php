<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAppHelper {
  public static function info_tooltip($id, $title, $info) {
    ?>
    <span id="mepr-tooltip-<?php echo $id; ?>" class="mepr-tooltip">
      <span><i class="mp-icon mp-icon-info-circled mp-16"></i></span>
      <span class="mepr-data-title mepr-hidden"><?php echo $title; ?></span>
      <span class="mepr-data-info mepr-hidden"><?php echo $info; ?></span>
    </span>
    <?php
  }

  public static function format_currency($number, $show_symbol = true, $free_str = true, $truncate_zeroes = false) {
    global $wp_locale;
    $mepr_options = MeprOptions::fetch();
    $dp = $wp_locale->number_format['decimal_point'];

    if((float)$number > 0.00 || !$free_str) {
      //Do decimal and 0's handling before adding symbol
      if(MeprUtils::is_zero_decimal_currency()) {
        $rstr = (string)MeprUtils::format_currency_float((float)$number, 0); //Zero decimal, means no decimals yo
      }
      else {
        $rstr = (string)MeprUtils::format_currency_float((float)$number, 2);

        if($truncate_zeroes) {
          $rstr = preg_replace('/' . preg_quote($dp) . '00$/', '', $rstr);
        }
      }

      if($show_symbol) {
        if(!$mepr_options->currency_symbol_after) {
          $rstr = $mepr_options->currency_symbol . $rstr;
        }
        else {
          $rstr = $rstr . $mepr_options->currency_symbol;
        }
      }
    }
    else {
      $rstr = __('Free','memberpress');
    }

    return $rstr;
  }

  public static function auto_add_page($page_name) {
    return wp_insert_post(array('post_title' => $page_name, 'post_type' => 'page', 'post_status' => 'publish', 'comment_status' => 'closed'));
  }

  public static function format_number($number, $show_decimals = false, $truncate_zeroes = false) {
    global $wp_locale;

    $decimal_point = $wp_locale->number_format['decimal_point'];
    $thousands_sep = $wp_locale->number_format['thousands_sep'];

    $rstr = 0;

    if((float)$number > 0.00) {
      if($show_decimals) {
        $rstr = (string)number_format((float)$number, 2, $decimal_point, $thousands_sep);
      }
      else {
        $rstr = (string)number_format((float)$number, 0, $decimal_point, $thousands_sep);
      }

      if($show_decimals && $truncate_zeroes) {
        $rstr = preg_replace('/' . preg_quote($decimal_point) . '00$/', '', $rstr);
      }
    }

    return $rstr;
  }

  // NOTE - This should only be used in views/emails as it modifies UTC
  // timestamps to show in the users WP locale settings instead of in UTC
  public static function format_date($datetime, $default = null, $format = null) {
    if(is_null($default)) { $default = __('Unknown','memberpress'); }
    if(is_null($format)) { $format = get_option('date_format'); } //Gets WP date format option
    if(empty($datetime) or preg_match('#^0000-00-00#', $datetime)) { return $default; }

    $ts = strtotime($datetime);
    $offset = get_option('gmt_offset'); //Gets WP timezone offset option

    return date_i18n($format, ($ts + MeprUtils::hours($offset)), false); // return a translatable date in the WP locale options
  }

  //Right now - just used on the new/edit txn pages
  public static function format_date_utc($utc_datetime, $default = null, $format = null) {
    if(is_null($default)) { $default = __('Unknown','memberpress'); }
    if(is_null($format)) { $format = get_option('date_format'); } //Gets WP date format option
    if(empty($utc_datetime) or preg_match('#^0000-00-00#', $utc_datetime)) { return $default; }

    $ts = strtotime($utc_datetime);

    return date_i18n($format, $ts, true); // return a translatable date in the WP locale options
  }

  public static function page_template_dropdown($field_name, $field_value=null) {
    $templates = get_page_templates();
    //$field_value = isset($_POST[$field_name])?$_POST[$field_name]:null;
    ?>
    <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-page-templates-dropdown">
    <?php
      foreach($templates as $template_name => $template_filename) {
        ?>
        <option value="<?php echo $template_filename; ?>" <?php selected($template_filename,$field_value); ?>><?php echo $template_name; ?>&nbsp;</option>
        <?php
      }
    ?>
    </select>
    <?php
  }

  public static function human_readable_status( $status, $type='transaction' ) {
    if( $type == 'transaction' ) {
      switch( $status ) {
        case MeprTransaction::$pending_str:
          return __('Pending','memberpress');
        case MeprTransaction::$failed_str:
          return __('Failed','memberpress');
        case MeprTransaction::$complete_str:
          return __('Complete','memberpress');
        case MeprTransaction::$refunded_str:
          return __('Refunded','memberpress');
        default:
          return __('Unknown','memberpress');
      }
    }
    elseif( $type == 'subscription' ) {
      switch( $status ) {
        case MeprSubscription::$pending_str:
          return __('Pending','memberpress');
        case MeprSubscription::$active_str:
          return __('Enabled','memberpress');
        case MeprSubscription::$cancelled_str:
          return __('Stopped','memberpress');
        case MeprSubscription::$suspended_str:
          return __('Paused','memberpress');
        default:
          return __('Unknown','memberpress');
      }
    }
  }

  public static function format_price_string($obj, $price = 0.00, $show_symbol = true, $coupon_code = null, $show_prorated = true) {
    global $wp_locale;

    $user = MeprUtils::get_currentuserinfo();
    $regex_dp = preg_quote($wp_locale->number_format['decimal_point'], '#');
    $mepr_options = MeprOptions::fetch();
    $coupon = false;

    if(empty($coupon_code)) {
      $coupon_code = null;
    }
    else {
      $coupon = MeprCoupon::get_one_from_code($coupon_code, true);
    }

    if($obj instanceof MeprTransaction || $obj instanceof MeprSubscription) {
      $product = $obj->product();
    }
    elseif($obj instanceof MeprProduct) {
      $product = $obj;
    }
    else {
      $product = false;
    }

    $tax_str = '';

    if(!empty($obj->tax_rate) && $obj->tax_rate > 0.00) {
      // $tax_rate = $obj->tax_rate;
      $tax_rate = preg_replace("#([{$regex_dp}]000?)([^0-9]*)$#", '$2', MeprUtils::format_tax_percent_for_display($obj->tax_rate));
      $tax_desc = $obj->tax_desc;
      // $tax_str = ' +'.MeprUtils::format_float($tax_rate).'% '.$tax_desc;
      $tax_str = ' +'.$tax_rate.'% '.$tax_desc;
    }

    // Just truncate the zeros if it's an even dollar amount
    $fprice = MeprAppHelper::format_currency($price, $show_symbol);
    $fprice = preg_replace("#([{$regex_dp}]000?)([^0-9]*)$#", '$2', (string)$fprice);
    $fprice = $fprice . $tax_str;

    $period = (int)$obj->period;
    $period_type = $obj->period_type;
    $period_type_str = strtolower( MeprUtils::period_type_name($period_type,$period) );

    if((float)$price <= 0.00) {
      if( $period_type != 'lifetime' && !empty($coupon) &&
          $coupon->discount_type == 'percent' && $coupon->discount_amount == 100 ) {
        $price_str = __('Free forever', 'memberpress');
      }
      elseif($period_type == 'lifetime') {
        $price_str = __('Free', 'memberpress');
      }
      elseif($period==1) {
        $price_str = sprintf(__('Free for a %1$s', 'memberpress'), $period_type_str);
      }
      else {
        $price_str = sprintf(__('Free for %1$d %2$s', 'memberpress'), $period, $period_type_str);
      }
    }
    elseif($period_type == 'lifetime') {
      $price_str = $fprice;
      if( $show_prorated && $obj instanceof MeprProduct &&
          $mepr_options->pro_rated_upgrades && $obj->is_upgrade_or_downgrade() ) {
        $group        = $obj->group();
        $lt           = false;
        $old_subscr   = $user->subscription_in_group($group->ID);
        $old_lifetime = $user->lifetime_subscription_in_group($group->ID);

        if($old_subscr !== false) {
          $lt = $old_subscr->latest_txn();
        }

        if($old_lifetime !== false) {
          $lt = $old_lifetime;
        }

        //Don't show prorated if the old amount is 0.00
        if($lt === false || MeprUtils::format_float($lt->amount) > 0.00) {
          $price_str .= __(' (prorated)', 'memberpress');
        }
      }
    }
    else {
      if( $obj->trial ) {
        if( $obj->trial_amount > 0.00 ) {
          $trial_str = MeprAppHelper::format_currency($obj->trial_amount, $show_symbol);
          $trial_str = preg_replace("#([{$regex_dp}]000?)([^0-9]*)$#", '$2', (string)$trial_str);
        }
        else
          $trial_str = __('free', 'memberpress');

        if( ( $obj instanceof MeprSubscription and $obj->prorated_trial ) or
            ( $obj instanceof MeprProduct and $mepr_options->pro_rated_upgrades and $obj->is_upgrade_or_downgrade() ) ) {
          if( $obj instanceof MeprProduct ) {
            $usr = MeprUtils::get_currentuserinfo();
            $grp = $obj->group();

            if( $show_prorated && ($old_sub = $usr->subscription_in_group($grp->ID)) ) {
              $upgrade_str = __(' (proration)','memberpress');
            }
            else {
              $upgrade_str = '';
            }
          }
          else if( $show_prorated ) {
            $upgrade_str = __(' (proration)','memberpress');
          }
          else {
            $upgrade_str = '';
          }
        }
        else {
          $upgrade_str = '';
        }

        if($obj->trial_days > 0) {
          list($conv_trial_type, $conv_trial_count) = MeprUtils::period_type_from_days($obj->trial_days);

          $conv_trial_type_str = MeprUtils::period_type_name($conv_trial_type, $conv_trial_count);
          $sub_str = __( '%1$s %2$s for %3$s%4$s then ', 'memberpress' );
          $price_str = sprintf( $sub_str, $conv_trial_count, strtolower($conv_trial_type_str), $trial_str, $upgrade_str );
        }
        else {
          $sub_str = __( '%1$s%2$s once and ', 'memberpress' );
          $price_str = sprintf( $sub_str, $trial_str, $upgrade_str );
        }
      }
      else {
        $price_str = '';
      }

      if( $obj->limit_cycles and $obj->limit_cycles_num==1 ) {
        $price_str .= $fprice;
        if( $obj->limit_cycles_action=='expire' ) {
          $price_str .= sprintf( __( ' for %1$d %2$s', 'memberpress' ), $period, $period_type_str );
        }
      }
      elseif( $obj->limit_cycles ) { // Prefix with payments count
        $price_str .= sprintf( _n( '%1$d payment of ', '%1$d payments of ',
                                   $obj->limit_cycles_num, 'memberpress' ),
                               $obj->limit_cycles_num );
      }

      if( !$obj->limit_cycles or ( $obj->limit_cycles and $obj->limit_cycles_num > 1 ) ) {
        if( $period == 1 ) {
          $price_str .= sprintf(__('%1$s / %2$s', 'memberpress'), $fprice, $period_type_str);
        }
        else {
          $price_str .= sprintf(__('%1$s / %2$d %3$s', 'memberpress'), $fprice, $period, $period_type_str);
        }
      }
    }

    if($period_type == 'lifetime') {
      if($obj->expire_type=='delay') {
        $expire_str = strtolower( MeprUtils::period_type_name($obj->expire_unit,$obj->expire_after) );
        $price_str .= sprintf( __( ' for %1$d %2$s', 'memberpress' ), $obj->expire_after, $expire_str );
      }
      else if($obj->expire_type == 'fixed') {
        $expire_ts = strtotime( $obj->expire_fixed );
        $now = time();

        //Make sure we adjust the year if the membership is a renewable type and the user forgot to bump up the year
        if($product->allow_renewal) {
          while($now > $expire_ts) { //Add a year until $now < expiration date
            $expire_ts += MeprUtils::years(1);
          }
        }

        $expire_str = date_i18n( 'D, M j, Y', $expire_ts, true );

        if(!$product->is_renewal()) { //Just hide this if it's a renewal
          $price_str .= sprintf( __( ' for access until %s', 'memberpress' ), $expire_str );
        }
      }
    }

    if(!empty($coupon)) { $price_str .= sprintf(__(' with coupon %s','memberpress'), $coupon_code); }

    return MeprHooks::apply_filters('mepr-price-string', $price_str, $obj, $show_symbol);
  }

  public static function display_emails($etype='MeprBaseEmail',$args=array()) {
    ?><div class="mepr-emails-wrap"><?php

    $emails = MeprEmailFactory::all($etype,$args);

    foreach( $emails as $email ) {
      if($email->show_form) { $email->display_form(); }
    }

    ?></div><?php
  }

  public static function render_csv($rows,$header=array(),$filename=null) {
    $filename = (is_null($filename)?uniqid().'.csv':$filename);

    // output headers so that the file is downloaded rather than displayed
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$filename}");

    // create a file pointer connected to the output stream
    $output = fopen('php://output','w');

    // output the column headings
    fputcsv($output,$header);

    // loop over the rows, outputting them
    foreach($rows as $row) {
      fputcsv($output, (array)$row);
    }

    // close the file and exit
    fclose($output);
    exit;
  }

  public static function countries_dropdown($field_key, $value=null, $classes='', $required_attr='', $geolocate=true) {
    $value = (($geolocate && empty($value)) ? '' : $value);

    ob_start();
    ?>
    <select name="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" class="<?php echo $classes; ?> mepr-countries-dropdown mepr-form-input mepr-select-field" <?php echo $required_attr; ?>>
      <option value=""><?php _e('-- Select Country --', 'memberpress'); ?></option>
      <?php
        foreach(MeprUtils::countries() as $opt_key => $opt_val) {
        ?>
          <option value="<?php echo $opt_key; ?>" <?php selected(esc_attr($opt_key), esc_attr($value)); ?>><?php echo stripslashes($opt_val); ?></option>
        <?php
        }
      ?>
    </select>
    <?php
    return ob_get_clean();
  }

  /** NOTE: In order to use this method you must also enqueue the i18n.js along with the localize script array. */
  public static function states_dropdown($field_key, $value=null, $classes='', $required_attr='', $geolocate=true) {
    $value = (($geolocate && empty($value)) ? '' : $value);

    ob_start();
    ?>
    <select name="" data-fieldname="<?php echo $field_key; ?>" data-value="<?php echo esc_attr($value); ?>" id="<?php echo $field_key; ?>-dropdown" class="<?php echo $classes; ?> mepr-hidden mepr-states-dropdown mepr-form-input mepr-select-field" style="display: none;" <?php echo $required_attr; ?>>
      <?php /* The js populates this part */ ?>
    </select>
    <?php /* Make sure the text box isn't hidden ... at the very least we need to see something! */ ?>
    <input type="text" name="<?php echo $field_key; ?>" data-fieldname="<?php echo $field_key; ?>" data-value="<?php echo esc_attr($value); ?>" id="<?php echo $field_key; ?>-text" class="<?php echo $classes; ?> mepr-states-text mepr-form-input" value="<?php echo esc_attr($value); ?>" <?php echo $required_attr; ?>/>
    <?php
    return ob_get_clean();
  }

  public static function memberships_dropdown($field_name, $memberships = array(), $classes = '') {
    $memberships = is_array($memberships) ? $memberships : array();
    $contents = array();

    $posts = MeprCptModel::all('MeprProduct');

    foreach($posts as $post) {
      $contents[$post->ID] = $post->post_title;
    }

    ?>
      <select name="<?php echo $field_name; ?>" class="<?php echo $classes; ?>">
      <?php
        foreach($contents as $curr_type => $curr_label) {
          ?><option value="<?php echo $curr_type; ?>" <?php selected(in_array($curr_type, $memberships)); ?>><?php echo $curr_label; ?>&nbsp;</option><?php
        }
      ?>
      </select>
    <?php
  }

  public static function export_table_link($action, $nonce_action, $nonce_name, $itemcount, $all=false) {
    $params = array('action' => $action);

    if($all) {
      $params['all'] = 1;
      $label = __('Export table as CSV (%s records)', 'memberpress');
    }
    else {
      $label = __('Export all as CSV (%s records)', 'memberpress');
    }

    ?>
      <a href="<?php
        echo MeprUtils::admin_url(
          'admin-ajax.php',
          array($nonce_action, $nonce_name),
          $params,
          true
        ); ?>"><?php printf($label, MeprAppHelper::format_number($itemcount)); ?></a>
    <?php
  }

  public static function privacy_policy_page_link() {
    $privacy_policy_page_id = get_option('wp_page_for_privacy_policy', false);
    if($privacy_policy_page_id !== false) {
      return get_permalink($privacy_policy_page_id);
    }

    return false;
  }
} //End class
