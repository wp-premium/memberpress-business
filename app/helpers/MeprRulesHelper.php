<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprRulesHelper
{
  public static function type_dropdown($field_name, $type, $onchange='')
  {
    $field_value = (isset($_POST[$field_name]))?$_POST[$field_name]:'';
    $types = MeprRule::get_types();
    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-rule-types-dropdown" onchange="<?php echo $onchange; ?>">
      <?php
        foreach($types as $curr_type => $curr_label)
        {
          ?>
          <option value="<?php echo $curr_type; ?>" <?php echo (((isset($_POST[$field_name]) and $_POST[$field_name] == $curr_type) or (!isset($_POST[$field_name]) and $type == $curr_type))?' selected="selected"':''); ?>><?php echo $curr_label; ?>&nbsp;&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function get_page_title($type, $content)
  {
    $contents = MeprRule::get_contents_array($type);
    $content_values = array_values((!$contents)?array(''):$contents);
    $types = MeprRule::get_types();

    $type_label = $types[(!empty($type)?$type:'all')];
    if($type == "custom")
      $content_label = $content;
    else
      $content_label = (!empty($contents[$content])?$contents[$content]:$content_values[0]);
  }

  public static function content_dropdown($field_name, $content, $type = 'all', $options = array())
  {
    $types = MeprRule::get_types();

    if(!isset($type) or empty($type) or !array_key_exists($type, $types)) { $type = 'all'; }

    if($type == 'custom')
    {
      $is_regexp = false;

      if(!isset($_POST["_is{$field_name}_regexp"]) and isset($options["_is{$field_name}_regexp"]))
        $is_regexp = $options["_is{$field_name}_regexp"];

      if(isset($_POST["_is{$field_name}_regexp"]))
        $is_regexp = true;

      ?>
      <span id="<?php echo $field_name; ?>-wrap">
        <?php self::get_page_title($type, $content); ?>
        <input type="text" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-input" value="<?php echo isset($_POST[$field_name]) ? $_POST[$field_name] : $content; ?>" onblur="mepr_update_rule_post_title( jQuery('#_mepr_rules_type').val(), this.value )" />
        <input type="checkbox" name="_is<?php echo $field_name; ?>_regexp" id="_is<?php echo $field_name; ?>_regexp" <?php checked($is_regexp); ?> />&nbsp;<?php _e('Regular Expression', 'memberpress'); ?>
      </span>
      <?php
      return;
    }

    //Show a text field for collecting comma separated list of ID's to exclude
    if($type == 'all' or (strstr($type, 'all_') !== false and !preg_match('#^all_tax_#',$type))) {
      ?>
      <span id="<?php echo $field_name; ?>-wrap">
        <?php self::get_page_title($type, $content); ?>
        <label for="<?php echo $field_name; ?>"><?php _e('Except', 'memberpress'); ?></label>
        <?php MeprAppHelper::info_tooltip( 'mepr-rules-all-except',
                                           __('All Except IDs', 'memberpress'),
                                           __('If you want to exclude all except some Pages or Posts, list their IDs here in a comma separated list. Example: 102, 32, 546', 'memberpress') ); ?>
        <input type="text" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-input" value="<?php echo isset($_POST[$field_name]) ? $_POST[$field_name] : $content; ?>" onblur="mepr_update_rule_post_title( jQuery('#_mepr_rules_type').val(), this.value )" />
      </span>
      <?php
      return;
    }

    if(!MeprRule::type_has_contents($type)) {
      ?>
      <span id="<?php echo $field_name; ?>-wrap">
        <?php
          self::get_page_title($type, $content);
          if( $type!='partial' )
            _e('There is not yet any content to select for this rule type.', 'memberpress');
        ?>
      </span>
      <?php
      return;
    }

    $field_value = ( isset($_POST[$field_name]) ? $_POST[$field_name] : $content );
    if(!empty($field_value)) { $obj = MeprRule::get_content($type,$content); }
    if(!isset($obj) or empty($obj)) { $obj = (object)array('id'=>'','label'=>'','slug'=>'','desc'=>''); }

    ?>
    <span id="<?php echo $field_name; ?>-wrap">
      <?php self::get_page_title($type, $content); ?>
      <input type="text" id="rule-content-text" class="mepr-rule-types-autocomplete" value="<?php echo $obj->label; ?>" placeholder="<?php _e('Begin Typing Title', 'memberpress'); ?>" />
      <input type="hidden" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-rule-types-autocomplete" value="<?php echo $obj->id; ?>" />
      <span id="rule-content-info"><?php echo $obj->desc; ?></span>
    </span>
    <?php
  }

  public static function access_dropdown($field_name, $access)
  {
    $contents = array();

    $post_contents = get_posts(array('numberposts' => -1, 'post_type' => 'memberpressproduct', 'post_status' => 'publish'));

    foreach($post_contents as $post)
      $contents[$post->ID] = $post->post_title;

    //Don't think this is used, blair feel free to kill this if I'm right
    $field_value = (isset($_POST[$field_name]))?$_POST[$field_name]:'';

    if(!is_array($access))
      $access = array($access);

    ?>
      <select name="<?php echo $field_name; ?>[]" id="<?php echo $field_name; ?>[]" class="mepr-multi-select mepr-rule-access-select" multiple="true">
      <?php
        foreach($contents as $curr_type => $curr_label)
        {
          ?>
          <option value="<?php echo $curr_type; ?>" <?php echo (((isset($_POST[$field_name]) and in_array($curr_type,$_POST[$field_name])) or (!isset($_POST[$field_name]) and in_array($curr_type,$access)))?' selected="selected"':''); ?>><?php echo $curr_label; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function time_units_dropdown($rule, $type)
  {
    $values = array(__('day(s)', 'memberpress') => 'days',
                    __('week(s)', 'memberpress') => 'weeks',
                    __('month(s)', 'memberpress') => 'months',
                    __('year(s)', 'memberpress') => 'years');
    ?>
    <select name="<?php echo $type; ?>">
      <?php foreach($values as $name => $value): ?>
        <?php if($type == MeprRule::$drip_unit_str): ?>
        <option value="<?php echo $value; ?>" <?php selected($value, $rule->drip_unit); ?>><?php echo $name; ?></option>
        <?php endif; ?>

        <?php if($type == MeprRule::$expires_unit_str): ?>
        <option value="<?php echo $value; ?>" <?php selected($value, $rule->expires_unit); ?>><?php echo $name; ?></option>
        <?php endif; ?>
      <?php endforeach; ?>
    </select>
    <?php
  }

  public static function drip_expires_after_dropdown($rule, $type)
  {
    $products = get_posts(array('post_type' => MeprProduct::$cpt, 'post_status' => 'publish', 'numberposts' => -1));
    ?>
    <select name="<?php echo $type; ?>" id="<?php echo $type; ?>">
      <option value="registers" <?php selected((($type == MeprRule::$drip_after_str && $rule->drip_after == 'registers') || ($type == MeprRule::$expires_after_str && $rule->expires_after == 'registers'))); ?>><?php _e('member registers', 'memberpress'); ?></option>
      <option value="fixed" <?php selected((($type == MeprRule::$drip_after_str && $rule->drip_after == 'fixed') || ($type == MeprRule::$expires_after_str && $rule->expires_after == 'fixed'))); ?>><?php _e('fixed date', 'memberpress'); ?></option>
      <option value="rule-products" <?php selected((($type == MeprRule::$drip_after_str && $rule->drip_after == 'rule-products') || ($type == MeprRule::$expires_after_str && $rule->expires_after == 'rule-products'))); ?>><?php _e('member purchases any product for this rule', 'memberpress'); ?></option>
      <?php foreach($products as $p): ?>
        <?php if($type == MeprRule::$drip_after_str): ?>
        <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $rule->drip_after); ?>><?php echo __('member purchases', 'memberpress').' '.$p->post_title; ?></option>
        <?php endif; ?>

        <?php if($type == MeprRule::$expires_after_str): ?>
        <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $rule->expires_after); ?>><?php echo __('member purchases', 'memberpress').' '.$p->post_title; ?></option>
        <?php endif; ?>
      <?php endforeach; ?>
    </select>
    <?php
  }
} //End class
