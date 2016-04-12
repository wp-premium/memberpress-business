<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprOptionsHelper {
  public static function wp_pages_dropdown($field_name, $page_id = 0, $auto_page = '', $blank_page = false) {
    $pages = MeprUtils::get_pages();
    $selected_page_id = (isset($_POST[$field_name])?$_POST[$field_name]:$page_id);

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-pages-dropdown">
      <?php if($blank_page): ?>
        <option value=""><?php _e('None', 'memberpress'); ?></option>
      <?php endif ?>
      <?php if(!empty($auto_page)) { ?>
        <option value="__auto_page:<?php echo $auto_page; ?>"><?php _e('- Auto Create New Page -', 'memberpress'); ?>&nbsp;</option>
      <?php } else { ?>
        <option>&nbsp;</option>
      <?php
        }
        foreach($pages as $page) {
          $selected = (((isset($_POST[$field_name]) and $_POST[$field_name] == $page->ID) or (!isset($_POST[$field_name]) and $page_id == $page->ID))?' selected="selected"':'');
          ?>
          <option value="<?php echo $page->ID; ?>" <?php echo $selected; ?>><?php echo $page->post_title; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php

    if($selected_page_id) {
        $permalink = MeprUtils::get_permalink($selected_page_id);
    ?>
&nbsp;<a href="<?php echo admin_url("post.php?post={$selected_page_id}&action=edit"); ?>" target="_blank" class="button"><?php _e('Edit', 'memberpress'); ?></a>
      <a href="<?php echo $permalink; ?>" target="_blank" class="button"><?php _e('View', 'memberpress'); ?></a>
    <?php
    }
  }

  public static function payment_types_dropdown($field_name, $payment_type) {
    $payment_types = array( 'paypal' => __('PayPal', 'memberpress'),
                            'manual' => __('Manual', 'memberpress') );

    $field_value = $_POST[$field_name];

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-payment-types-dropdown">
      <?php
        foreach($payment_types as $curr_type => $curr_label) {
          ?>
          <option value="<?php echo $curr_type; ?>" <?php echo (((isset($_POST[$field_name]) and $_POST[$field_name] == $curr_type) or (!isset($_POST[$field_name]) and $payment_type == $curr_type))?' selected="selected"':''); ?>><?php echo $curr_label; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function payment_currencies_dropdown($field_name, $payment_currency) {
    $payment_currencies = MeprHooks::apply_filters('mepr-currency-symbols', array('$', '£', '€', '¥', 'kr', 'R$', '฿', '₹', 'zł', ' лв', ' Ft', 'Rp', 'R', '₪', '﷼', 'CHF', 'KSh', 'RM', 'руб', 'NT$', 'Mex$', 'P', 'lei', 'JOD', '₺', 'S/.'));
    $field_value = isset($_POST[$field_name])?$_POST[$field_name]:null;

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-payment-currencies-dropdown">
      <?php
        foreach($payment_currencies as $curr_currency) {
          ?>
          <option value="<?php echo $curr_currency; ?>" <?php echo (((isset($_POST[$field_name]) and $_POST[$field_name] == $curr_currency) or (!isset($_POST[$field_name]) and $payment_currency == $curr_currency))?' selected="selected"':''); ?>><?php echo $curr_currency; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function payment_currency_code_dropdown($field_name, $code) {
    $codes = MeprHooks::apply_filters('mepr-currency-codes', array('USD', 'GBP', 'EUR', 'JPY', 'AUD', 'BRL', 'CAD', 'HKD', 'NZD', 'SGD', 'SEK', 'THB', 'INR', 'PLN', 'BGN', 'HUN', 'HUF', 'IDR', 'ZAR', 'NOK', 'DKK', 'ILS', 'SAR', 'CHF', 'KES', 'MYR', 'RUB', 'TWN', 'MXN', 'BWP', 'RON', 'JOD', 'TRY', 'PEN'));
    $field_value = isset($_POST[$field_name])?$_POST[$field_name]:null;

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-payment-formats-dropdown">
      <?php
        foreach($codes as $curr_code) {
          ?>
          <option value="<?php echo $curr_code; ?>" <?php echo (((isset($_POST[$field_name]) and $_POST[$field_name] == $curr_code) or (!isset($_POST[$field_name]) and $code == $curr_code))?' selected="selected"':''); ?>><?php echo $curr_code; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function payment_language_code_dropdown($field_name, $code) {
    $codes = MeprHooks::apply_filters('mepr-language-codes', array('US', 'AU', 'CN', 'EN', 'FR', 'BR', 'DE', 'IT', 'JP', 'ES', 'GB', 'SE', 'TH', 'PL', 'BG', 'HU', 'ID', 'PT', 'ZA', 'NO', 'DK', 'HE', 'FI', 'SR', 'CH', 'SW', 'NL', 'MS', 'RU', 'TW', 'MX', 'TN', 'RO', 'AR', 'SK', 'TR', 'PE'));
    $field_value = isset($_POST[$field_name])?$_POST[$field_name]:null;

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-language-code-dropdown">
      <?php
        foreach($codes as $curr_code) {
          ?>
          <option value="<?php echo $curr_code; ?>" <?php echo (((isset($_POST[$field_name]) and $_POST[$field_name] == $curr_code) or (!isset($_POST[$field_name]) and $code == $curr_code))?' selected="selected"':''); ?>><?php echo $curr_code; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function gateways_dropdown($field_name, $curr_gateway, $obj_id) {
    $gateways = MeprGatewayFactory::all();
    $field_value = isset($_POST[$field_name])?$_POST[$field_name]:'';
    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" data-id="<?php echo $obj_id; ?>" class="mepr-dropdown mepr-gateways-dropdown">
      <?php
        foreach($gateways as $gateway => $gateway_name) {
          $obj = MeprGatewayFactory::fetch($gateway);

          ?>
          <option value="<?php echo $gateway; ?>" <?php echo (((isset($_POST[$field_name]) and $_POST[$field_name] == $gateway) or (!isset($_POST[$field_name]) and $curr_gateway == $gateway))?' selected="selected"':''); ?>><?php echo $gateway_name; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function show_existing_custom_fields() {
    $mepr_options = MeprOptions::fetch();
    $blank_line = array((object)array('field_key'       => '',
                                      'field_name'      => '',
                                      'field_type'      => 'text',
                                      'default_value'   => '',
                                      'show_on_signup'  => false,
                                      'required'        => false,
                                      'options'         => array()));

    if(empty($mepr_options->custom_fields))
      $mepr_options->custom_fields = $blank_line;

    foreach($mepr_options->custom_fields as $line) {
      $random_id = rand(1, 100000000);

      if( in_array(
            $line->field_type,
            array('dropdown','multiselect','radios','checkboxes')
          ) ) {
        $hide = '';
      }
      else {
        $hide = 'style="display:none;"';
      }

      MeprView::render('/admin/options/custom_fields_row', get_defined_vars());
    }
  }

  public static function payment_methods_dropdown($field_name, $pms = false) {
    $mepr_options = MeprOptions::fetch();

    $pms = $pms ? $pms : array_keys($mepr_options->integrations);

    if(count($pms) == 0):
      return false;
    elseif(count($pms) == 1):
      $pm_id = array_shift($pms);
      $obj = $mepr_options->payment_method($pm_id);

      $label = esc_html(trim($obj->label));
      $desc = esc_html(trim(stripslashes($obj->desc)));

      if($obj->use_icon && !empty($obj->icon)) {
        $icon = '<span class="mepr-payment-method-icon"><img src="'.$obj->icon.'" height="32px" /></span>';
      }
      else {
        $icon = '';
      }

      if($obj->use_label && !empty($obj->label)) {
        $label = '<span class="mepr-payment-method-label-text">'.$label.'</span>';
      }
      else {
        $label = '';
      }

      if($obj->use_desc && !empty($desc)) {
        $desc = '<div id="mp-pm-desc-'.$obj->id.'" class="mepr-payment-method-desc-text">'.$desc.'</div>';
      }
      else {
        $desc = '';
      }

      if($obj instanceof MeprBaseRealGateway):
        ?>
        <div id="<?php echo $field_name; ?>" class="mp-form-row">
          <div id="<?php echo "{$field_name}-{$obj->id}"; ?>" class="mepr-payment-method">
            <input type="hidden" name="<?php echo $field_name; ?>" value="<?php echo $obj->id; ?>" />
            <div class="mepr-payment-method-label"><?php echo $label.$icon; ?></div>
            <?php echo $desc; ?>
          </div>
        </div>
        <?php
      else:
        return false;
      endif;
    elseif(count($pms) > 1):
    ?><div id="<?php echo $field_name; ?>" class="mp-form-row"><?php
        $first = true;
        foreach($pms as $pm_id):
          $obj = $mepr_options->payment_method($pm_id);
          $label = esc_html(trim($obj->label));
          $desc = esc_html(trim(stripslashes($obj->desc)));

          // This will ensure that the first pm is checked by default
          if( $first ) {
            $first = false;
            if(!isset($_POST[$field_name])) {
              $_POST[$field_name] = $obj->id;
            }
          }

          if($obj->use_icon && !empty($obj->icon)) {
            $icon = '<span class="mepr-payment-method-icon"><img src="'.$obj->icon.'" height="32px" /></span>';
          }
          else {
            $icon = '';
          }

          if($obj->use_label && !empty($label)) {
            $label = '<span class="mepr-payment-method-label-text">'.$label.'</span>';
          }
          else {
            $label = '';
          }

          if($obj->use_desc && !empty($desc)) {
            $desc_hidden = ($_POST[$field_name]==$obj->id ? '' : ' mepr-hidden');
            $desc = '<div id="mp-pm-desc-'.$obj->id.'" class="mepr-payment-method-desc-text' . $desc_hidden . '">'.$desc.'</div>';
          }
          else {
            $desc = '';
          }

          if( $obj instanceof MeprBaseRealGateway ):
            ?>
            <div id="<?php echo "{$field_name}-{$obj->id}"; ?>" class="mepr-payment-method">
              <div class="mepr-payment-method-label">
                <label class="mp-checkbox-field">
                  <input
                    type="radio"
                    name="<?php echo $field_name; ?>"
                    class="mepr-form-radio"
                    value="<?php echo $obj->id; ?>"
                    <?php checked($_POST[$field_name],$obj->id) ?> />
                  <?php echo $label.$icon; ?>
                </label>
                <?php echo $desc; ?>
              </div>
            </div>
            <?php
          endif;
        endforeach;
    ?></div><?php
    endif;
  }

  public static function format_plaintext_email($text) {
    // Don't be alarmed, inline styles are required in emails
    return '<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">' . wpautop(make_clickable($text), true) . '</div>';
  }

  public static function display_show_excerpts_dropdown($excerpt_type_str, $excerpt_type, $excerpt_size_str, $excerpt_size, $global=false) {
    ?>
    <div>
      <?php if($global): ?>
        <span><?php _e('Choose the excerpt type:', 'memberpress'); ?></span>
      <?php else: ?>
        <p><strong><?php _e('Excerpts:', 'memberpress'); ?></strong></p>
      <?php endif; ?>

      <?php if(!$global): ?>
        <div class="mepr-sub-pane">
      <?php endif; ?>

        <select id="<?php echo $excerpt_type_str; ?>" name="<?php echo $excerpt_type_str; ?>">
          <?php if(!$global): ?>
            <option value="default"<?php selected('default',$excerpt_type); ?>><?php _e('Default', 'memberpress'); ?></option>
            <option value="hide"<?php selected('hide',$excerpt_type); ?>><?php _e('Hide', 'memberpress'); ?></option>
          <?php endif; ?>
          <option value="more"<?php selected('more',$excerpt_type); ?>><?php _e('More Tag', 'memberpress'); ?></option>
          <option value="excerpt"<?php selected('excerpt',$excerpt_type); ?>><?php _e('Post Excerpt', 'memberpress'); ?></option>
          <option value="custom"<?php selected('custom',$excerpt_type); ?>><?php _e('Custom', 'memberpress'); ?></option>
        </select>

        <div id="<?php echo $excerpt_type_str; ?>-size" class="mepr-hidden">
          <div>&nbsp;</div>
          <i class="mp-icon-level-down"></i>
          <?php
            ob_start();
            ?>
            <input type="text" name="<?php echo $excerpt_size_str; ?>" id="<?php echo $excerpt_size_str; ?>" size="5" value="<?php echo $excerpt_size; ?>" />
            <?php
            $excerpt_size_input = ob_get_clean();
          ?>
          <span><?php printf(__('Show the first %s characters of your content', 'memberpress'), $excerpt_size_input); ?></span>
        </div>

      <?php if(!$global): ?>
        </div>
      <?php endif; ?>

    </div>
    <?php
  }

  public static function display_unauth_message_dropdown($message_type_str, $message_type, $message_str, $message) {
    ?>
    <div>
      <p><strong><?php _e('Unauthorized Message:', 'memberpress'); ?></strong></p>
      <div class="mepr-sub-pane">
        <select id="<?php echo $message_type_str; ?>" name="<?php echo $message_type_str; ?>">
          <option value="default"<?php selected('default',$message_type); ?>><?php _e('Default', 'memberpress'); ?></option>
          <option value="hide"<?php selected('hide',$message_type); ?>><?php _e('Hide', 'memberpress'); ?></option>
          <option value="custom"<?php selected('custom',$message_type); ?>><?php _e('Custom', 'memberpress'); ?></option>
        </select>
        <div id="<?php echo $message_type_str; ?>-editor" class="mepr-hidden mepr-sub-pane">
          <br/>
          <p class="description"><?php _e('Enter your custom unauthorized message here:', 'memberpress'); ?></p>
          <?php wp_editor($message, $message_str); ?>
        </div>
      </div>
    </div>
    <?php
  }

  public static function display_unauth_login_dropdown($login_str, $login) {
    ?>
    <div>
      <p><strong><?php _e('Login Form:', 'memberpress'); ?></strong></p>
      <div class="mepr-sub-pane">
        <select id="<?php echo $login_str; ?>" name="<?php echo $login_str; ?>">
          <option value="default"<?php selected('default',$login); ?>><?php _e('Default', 'memberpress'); ?></option>
          <option value="show"<?php selected('show',$login); ?>><?php _e('Show', 'memberpress'); ?></option>
          <option value="hide"<?php selected('hide',$login); ?>><?php _e('Hide', 'memberpress'); ?></option>
        </select>
      </div>
    </div>
    <?php
  }
} //End class

