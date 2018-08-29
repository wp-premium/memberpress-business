<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprRemindersHelper {
  public static function get_email_vars($reminder=null) {
    $vars = array(
      'reminder_id',
      'reminder_trigger_length',
      'reminder_trigger_interval',
      'reminder_trigger_timing',
      'reminder_trigger_event',
      'reminder_name',
      'reminder_description'
    );

    // DEPRECATED
    $params = MeprHooks::apply_filters( 'mepr_reminder_notification_vars', $vars, $reminder );

    return MeprHooks::apply_filters( 'mepr_reminder_email_vars', $vars, $reminder );
  }

  public static function get_email_params($reminder) {
    $params = array(
      'reminder_id'               => $reminder->ID,
      'reminder_trigger_length'   => $reminder->trigger_length,
      'reminder_trigger_interval' => $reminder->trigger_interval,
      'reminder_trigger_timing'   => $reminder->trigger_timing,
      'reminder_trigger_event'    => $reminder->trigger_event,
      'reminder_name'             => self::get_reminder_info($reminder,'name'),
      'reminder_description'      => self::get_reminder_info($reminder,'description')
    );

    // DEPRECATED
    $params = MeprHooks::apply_filters( 'mepr_reminder_notification_params', $params, $reminder );

    return MeprHooks::apply_filters( 'mepr_reminder_email_params', $params, $reminder );
  }

  public static function get_reminder_info($reminder,$field) {
    if( !in_array( $field, array( 'name', 'description' ) ) ) { return false; }

    $lookup = array(
      'sub-expires' => array(
        'before' => array(
          'name' => __('Subscription Expiring', 'memberpress'),
          'description' => sprintf( __( 'Subscription is expiring in %d %s' , 'memberpress'),
                                    $reminder->trigger_length,
                                    $reminder->get_trigger_interval_str() )
        ),
        'after' => array(
          'name' => __('Subscription Expired', 'memberpress'),
          'description' => sprintf( __( 'Subscription expired %d %s ago' , 'memberpress'),
                                    $reminder->trigger_length,
                                    $reminder->get_trigger_interval_str() )
        )
      ),
      'sub-renews' => array(
        'before' => array(
          'name' => __('Subscription Renewing', 'memberpress'),
          'description' => sprintf( __( 'Subscription is renewing in %d %s' , 'memberpress'),
                                    $reminder->trigger_length,
                                    $reminder->get_trigger_interval_str() )
        ),
        'after' => array(
          'name' => __('Subscription Renewed', 'memberpress'),
          'description' => sprintf( __( 'Subscription renewed %d %s ago' , 'memberpress'),
                                    $reminder->trigger_length,
                                    $reminder->get_trigger_interval_str() )
        )
      ),
      'cc-expires' => array(
        'before' => array(
          'name' => __('Credit Card Expiring', 'memberpress'),
          'description' => sprintf( __( 'Credit Card is Expiring in %d %s' , 'memberpress'),
                                    $reminder->trigger_length,
                                    $reminder->get_trigger_interval_str() )
        ),
        'after' => array(
          'name' => __('Credit Card Expired', 'memberpress'),
          'description' => sprintf( __( 'Credit Card Expired %d %s ago' , 'memberpress'),
                                    $reminder->trigger_length,
                                    $reminder->get_trigger_interval_str() )
        )
      ),
      'member-signup' => array(
        'before' => array( 'name' => '', 'description' => '' ),
        'after' => array(
          'name' => __('Member Signed Up', 'memberpress'),
          'description' => sprintf( __( 'Member Signed Up %d %s ago' , 'memberpress'),
                                    $reminder->trigger_length,
                                    $reminder->get_trigger_interval_str() )
        )
      ),
      'signup-abandoned' => array(
        'before' => array( 'name' => '', 'description' => '' ),
        'after' => array(
          'name' => __('Sign Up Abandoned', 'memberpress'),
          'description' => sprintf( __( 'Sign Up Abandoned %d %s ago' , 'memberpress'),
                                    $reminder->trigger_length,
                                    $reminder->get_trigger_interval_str() )
        )
      )
    );

    return $lookup[ $reminder->trigger_event][$reminder->trigger_timing][$field];
  }

  public static function products_multiselect($field_name, $selected) {
    $formatted = array();

    $all_products = MeprCptModel::all('MeprProduct');

    foreach($all_products as $prd) {
      $formatted[$prd->ID] = $prd->post_title;
    }

    //Empty array means ALL products should be selected for backwards compat
    if(!is_array($selected) || empty($selected)) {
      $selected = array();
    }

    ?>
      <select name="<?php echo $field_name; ?>[]" id="<?php echo $field_name; ?>" class="mepr-multi-select" multiple="true">
      <?php foreach($formatted as $id => $name): ?>
        <option value="<?php echo $id; ?>" <?php selected((empty($selected) || in_array($id, $selected))); ?>><?php echo $name; ?>&nbsp;</option>
      <?php endforeach; ?>
      </select>
      <span class="description">
        <small><?php _e('Hold the Control Key (Command Key on the Mac) in order to select or deselect multiple memberships', 'memberpress'); ?></small>
      </span>
    <?php
  }
} //End Class
