<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUsersHelper {
  public static function get_email_vars() {
    return MeprHooks::apply_filters(
      'mepr_user_email_vars',
      array(
        'user_id',
        'user_login',
        'username',
        'user_email',
        'user_first_name',
        'user_last_name',
        'user_full_name',
        'user_address',
        'user_register_date',
        'blog_name',
        'business_name',
        'biz_name',
        'biz_address1',
        'biz_address2',
        'biz_city',
        'biz_state',
        'biz_postcode',
        'biz_country',
        'login_page',
        'account_url',
        'login_url',
        'usermeta:*'
      )
    );
  }

  public static function get_email_params($usr) {
    $mepr_options = MeprOptions::fetch();
    $ts = MeprUtils::db_date_to_ts($usr->user_registered);
    $usr_date = date_i18n(__("F j, Y, g:i a", 'memberpress'), $ts, true);

    $params = array(
      'user_id'            => $usr->ID,
      'user_login'         => $usr->user_login,
      'username'           => $usr->user_login,
      'user_email'         => $usr->user_email,
      'user_first_name'    => $usr->first_name,
      'user_last_name'     => $usr->last_name,
      'user_full_name'     => $usr->full_name(),
      'user_address'       => $usr->formatted_address(),
      'user_register_date' => $usr_date,
      'blog_name'          => get_bloginfo('name'),
      'business_name'      => $mepr_options->attr('biz_name'),
      'biz_name'           => $mepr_options->attr('biz_name'),
      'biz_address1'       => $mepr_options->attr('biz_address1'),
      'biz_address2'       => $mepr_options->attr('biz_address2'),
      'biz_city'           => $mepr_options->attr('biz_city'),
      'biz_state'          => $mepr_options->attr('biz_state'),
      'biz_postcode'       => $mepr_options->attr('biz_postcode'),
      'biz_country'        => $mepr_options->attr('biz_country'),
      'login_page'         => $mepr_options->login_page_url(),
      'account_url'        => $mepr_options->account_page_url(),
      'login_url'          => $mepr_options->login_page_url()
    );

    $ums = MeprUtils::get_formatted_usermeta($usr->ID);

    if(!empty($ums)) {
      foreach($ums as $umkey => $umval) {
        $params["usermeta:{$umkey}"] = $umval;
      }
    }

    // You know we're just going to lump the user record fields in here no problem
    foreach((array)$usr->rec as $ukey => $uval) {
      $params["usermeta:{$ukey}"] = $uval;
    }

    $params = MeprHooks::apply_filters( 'mepr_user_notification_params', $params, $usr ); // DEPRECATED

    return MeprHooks::apply_filters( 'mepr_user_email_params', $params, $usr );
  }

  public static function render_custom_field($line,$value='',$classes=array()) {
    $required_attr = $line->required ? 'required' : '';
    $array_types = array( 'multiselect', 'checkboxes' ); //If we update this, we need make sure it doesn't break the {$usermeta:slug} stuff in MeprTransactionsHelper
    $bool_types  = array( 'checkbox' );

    // Figure out what type we have here
    $is_array  = in_array( $line->field_type, $array_types );
    $is_bool   = in_array( $line->field_type, $bool_types );
    $is_string = ( !$is_array && !$is_bool );

    if(isset($_REQUEST[$line->field_key])) {
      if( $is_array ) { $value = $_REQUEST[$line->field_key]; }
      else if( $is_bool ) { $value = true; }
      else { $value = stripslashes($_REQUEST[$line->field_key]); }
    }
    else if( $value==='' ) {
      if( $is_array && $line->field_type==='multiselect' ) {
        $value = explode(',', preg_replace('/\s*,\s*/', ',', trim($line->default_value)));
      }
      else if( $is_array && $line->field_type==='checkboxes' ) {
        $vals = explode(',', preg_replace('/\s*,\s*/', ',', trim($line->default_value)));

        $value = array();
        for( $i = 0; $i < count($vals); $i++ ) {
          $value[$vals[$i]] = 'on';
        }
      }
      else if( $is_bool ) {
        $current_user = MeprUtils::get_currentuserinfo();

        // We have to account for the possibility that the checkbox has been saved
        // with a value of '' instead of false so we have to formally check if the
        // value has been saved at some point in the past otherwise set as default
        if($current_user !== false) {
          if(MeprUtils::user_meta_exists($current_user->ID, $line->field_key)) {
            $value = !empty($value);
          }
          else { //User may have unchecked the box during signup
            $value = false;
          }
        }
        else {
          $value = !empty($line->default_value);
        }
      }
      else { $value = stripslashes($line->default_value); }
    }
    else if( $is_bool ) {
      $value = !empty($value);
    }

    $class = isset($classes[$line->field_type]) ? $classes[$line->field_type] : '';

    ob_start();

    switch($line->field_type) {
      case 'text':
      case 'email':
        ?><input type="<?php echo $line->field_type; ?>" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key; ?>" class="mepr-form-input <?php echo $class; ?>" value="<?php echo esc_attr($value); ?>" <?php echo $required_attr; ?> /><?php
        break;

      case 'url':
        ?><input type="<?php echo $line->field_type; ?>" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key; ?>" class="mepr-form-input <?php echo $class; ?>" value="<?php echo esc_attr($value); ?>" title="<?php _e('A URL must be prefixed with a protocol (eg. http://)', 'memberpress'); ?>" <?php echo $required_attr; ?> /><?php
        break;

      case 'textarea':
        ?><textarea name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key; ?>" class="mepr-form-textarea mepr-form-input <?php echo $class; ?>" <?php echo $required_attr; ?>><?php echo esc_textarea($value); ?></textarea><?php
        break;

      case 'checkbox':
        $required = $line->required ? '*' : '';
        ?>
        <label for="<?php echo $line->field_key; ?>" class="mepr-checkbox-field mepr-form-input <?php echo $class; ?>" <?php echo $required_attr; ?>>
          <input type="checkbox" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key; ?>" <?php checked($value); ?> />
          <?php printf( '%1$s%2$s', __(stripslashes($line->field_name), 'memberpress'), $required ); ?>
        </label>
        <?php
        break;

      case 'date':
        ?><input type="text" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key; ?>" value="<?php echo esc_attr(stripslashes($value)); ?>" class="mepr-date-picker mepr-form-input <?php echo $class; ?>" <?php echo $required_attr; ?> /><?php
        break;

      case 'dropdown':
      case 'multiselect':
        $is_multi = $line->field_type==='multiselect';
        $multiselect = $is_multi ? 'multiple="true"' : '';
        $ms_class = $is_multi ? 'mepr-multi-select-field' : '';
        $select_name = $is_multi ? "{$line->field_key}[]" : $line->field_key;

        ?>
        <select name="<?php echo $select_name; ?>" id="<?php echo $line->field_key; ?>" class="mepr-form-input mepr-select-field <?php echo $ms_class; ?> <?php echo $class; ?>" <?php echo $multiselect; ?> <?php echo $required_attr; ?>>
          <?php
            foreach( $line->options as $o ) {
              if( $is_multi ) {
                ?><option value="<?php echo $o->option_value; ?>" <?php selected(in_array($o->option_value, $value), true); ?>><?php echo stripslashes($o->option_name); ?></option><?php
              }
              else {
                ?><option value="<?php echo $o->option_value; ?>" <?php selected(esc_attr($o->option_value), esc_attr($value)); ?>><?php echo stripslashes($o->option_name); ?></option><?php
              }
            }
          ?>
        </select>
        <?php
        break;

      case 'radios':
      case 'checkboxes':
        ?>
        <div id="<?php echo $line->field_key; ?>" class="mepr-<?php echo $line->field_type; ?>-field mepr-form-input" <?php echo $required_attr; ?>>
        <?php
          foreach($line->options as $o) {
            $field_id = "{$line->field_key}-{$o->option_value}";
            if($line->field_type==='radios') {
              ?>
              <span class="mepr-radios-field-row">
                <input type="radio" name="<?php echo $line->field_key; ?>" id="<?php echo $field_id; ?>" value="<?php echo $o->option_value; ?>" class="mepr-form-radios-input <?php echo $class; ?>" <?php checked(esc_attr($o->option_value), esc_attr($value)); ?>>
                <label for="<?php echo $field_id; ?>" class="mepr-form-radios-label"><?php
                  _e(stripslashes($o->option_name), 'memberpress');
              ?></label>
              </span>
              <?php
            }
            else {
              if(!is_array($value)) { $value = array(); } //Suppress some errors here

              $value[$o->option_value] = isset($value[$o->option_value]) ? true : false;

              ?>
              <span class="mepr-checkboxes-field-row">
                <input type="checkbox" name="<?php echo $line->field_key; ?>[<?php echo $o->option_value; ?>]" id="<?php echo $field_id; ?>" class="mepr-form-checkboxes-input <?php echo $class; ?>" <?php checked($value[$o->option_value]); ?>>
                <label for="<?php echo $field_id; ?>" class="mepr-form-checkboxes-label"><?php
                  _e(stripslashes($o->option_name), 'memberpress');
              ?></label>
              </span>
              <?php
            }
          }
        ?>
        </div>
        <?php
        break;
      case 'countries': // for now only geolocate if the user isn't logged in
        echo MeprAppHelper::countries_dropdown($line->field_key, $value, '', $required_attr, !MeprUtils::is_user_logged_in());
        break;
      case 'states': // for now only geolocate if the user isn't logged in
        echo MeprAppHelper::states_dropdown($line->field_key, $value, $class, $required_attr, !MeprUtils::is_user_logged_in());
        break;
    }

    return MeprHooks::apply_filters( 'mepr_custom_field_html', ob_get_clean(), $line, $value );
  }

  public static function render_address_fields() {
    $mepr_options = MeprOptions::fetch();

    if($logged_in = MeprUtils::is_user_logged_in()) {
      $user = MeprUtils::get_currentuserinfo();
    }

    foreach($mepr_options->address_fields as $line) {
      $required = $line->required ? '*' : '';
      $value = $logged_in ? get_user_meta($user->ID, $line->field_key, true) : '';
      MeprView::render('checkout/signup_row', get_defined_vars());
    }
  }

  public static function render_custom_fields( $product=null, $from_page=null ) {
    $mepr_options = MeprOptions::fetch();

    if($logged_in = MeprUtils::is_user_logged_in()) {
      $user = MeprUtils::get_currentuserinfo();
    }

    //Get the right custom fields
    if($logged_in && is_admin() && MeprUtils::is_mepr_admin()) {
      //An admin is view the user's profile, so let's view all fields
      $custom_fields = $mepr_options->custom_fields;
    }
    elseif(!is_null($product) && $product instanceof MeprProduct) {
      if($product->customize_profile_fields) {
        $custom_fields = $product->custom_profile_fields();
      }
      else {
        $custom_fields = $mepr_options->custom_fields;
      }
    }
    elseif($logged_in) {
      $custom_fields = $user->custom_profile_fields();
    }
    else {
      $custom_fields = array();
    }

    //Maybe show the address fields too
    if($mepr_options->show_address_fields) {
      if(is_null($product)) {
        // Check if any memberships require address fields
        if($user->show_address_fields()) {
          $custom_fields = array_merge($mepr_options->address_fields, $custom_fields);
        }
      }
      else {
        if(!$product->disable_address_fields) {
          $custom_fields = array_merge($mepr_options->address_fields, $custom_fields);
        }
      }
    }

    //Give devs a chance to re-order these if they so wish
    $custom_fields = MeprHooks::apply_filters('mepr_render_custom_fields', $custom_fields);

    foreach($custom_fields as $line) {
      if('signup' == $from_page && !$line->show_on_signup) { continue; }
      if('account' == $from_page && isset($line->show_in_account) && !$line->show_in_account) { continue; }

      $required = ($line->required?'*':'');
      $value    = ($logged_in) ? get_user_meta($user->ID, $line->field_key, true) : '';

      MeprView::render('checkout/signup_row', get_defined_vars());
    }
  }

  // Renders the actual custom fields setup by the admin user. The fields rendered here are
  // to allow admins and the users themselves to display and edit values for the custom fields.
  public static function render_editable_custom_fields($user=null) {
    $mepr_options = MeprOptions::fetch();

    if(MeprUtils::is_mepr_admin()) { //Let admins see all fields
      $custom_fields = $mepr_options->custom_fields;
    }
    else if(!is_null($user)) {
      $custom_fields = $user->custom_profile_fields();
    }
    else {
      return; // if we aren't an admin and don't have a user we have no business being here
    }

    if($mepr_options->show_address_fields) {
      $custom_fields = array_merge($custom_fields, $mepr_options->address_fields); //Genius
    }

    if(!empty($custom_fields)) {
      foreach($custom_fields as $line) {
        $value = '';
        if(!is_null($user)) {
          $value = get_user_meta($user->ID, $line->field_key, true);
        }

        $required = ($line->required)?'<span class="description">'.__('(required)', 'memberpress').'</span>':'';

        ?>
        <tr>
          <th>
            <label for="<?php echo $line->field_key; ?>"><?php printf( __('%1$s:%2$s', 'memberpress'), stripslashes($line->field_name), $required ); ?></label>
          </th>
          <td>
            <?php
              echo self::render_custom_field($line, $value, array(
                'text' => 'regular-text',
                'email' => 'regular-text',
                'url' => 'regular-text',
                'textarea' => 'regular-text',
                'date' => 'regular-text',
                'states' => 'regular-text'
              ));
            ?>
          </td>
        </tr>
        <?php
      }
    }
  }
}
