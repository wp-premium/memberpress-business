<?php
/**
 * Adds MeprSubscriptionsWidget widget.
 */
class MeprSubscriptionsWidget extends WP_Widget {
  public function __construct() {
    parent::__construct(
      'mepr_subscriptions_widget', // Base ID
      'MemberPress Subscriptions', // Name
      array('description' => __('Show a list of the current member\'s Subscriptions with optional links to each Membership\'s "Membership Access URL" setting.', 'memberpress')) // Args
    );
  }

  public static function register_widget() {
    register_widget('MeprSubscriptionsWidget');
  }

  public function widget($args, $instance) {
    extract($args);

    $title              = $instance['title'];
    $top_desc           = $instance['top_desc'];
    $bottom_desc        = $instance['bottom_desc'];
    $not_logged_in_msg  = $instance['not_logged_in_msg'];
    $member_no_subs_msg = $instance['member_no_subs_msg'];
    $show_link          = (isset($instance['show_link']) && $instance['show_link']);
    $user = MeprUtils::get_currentuserinfo();

    echo $before_widget;

    if(!empty($title)) {
      echo $before_title.$title.$after_title;
    }

    if($user !== false && !empty($top_desc)) {
      echo '<div><span class="mepr-subscriptions-widget-top-description">' . $top_desc . '</span></div>';
    }

    echo "<ul>";

    if($user !== false) {
      $subs = $user->active_product_subscriptions('products');

      if(empty($subs)) {
        echo '<li class="mepr-subscriptions-widget-row mepr-widget-error">'.$member_no_subs_msg.'</li>';
      }
      else {
        $prev_dups = array();

        foreach($subs as $prd) {
          if(empty($prev_dups) || !in_array($prd->ID, $prev_dups, false)) {
            $prev_dups[] = $prd->ID;

            if($show_link && !empty($prd->access_url)) {
              echo '<li class="mepr-subscriptions-widget-row mepr-widget-link"><a href="'.stripslashes($prd->access_url).'">'.$prd->post_title.'</a></li>';
            }
            else {
              echo '<li class="mepr-subscriptions-widget-row mepr-widget-text">'.$prd->post_title.'</li>';
            }
          }
        }
      }
    }
    else {
      echo '<li class="mepr-subscriptions-widget-row mepr-widget-error">'.$not_logged_in_msg.'</li>';
    }

    echo "</ul>";

    if($user !== false && !empty($bottom_desc)) {
      echo '<div><span class="mepr-subscriptions-widget-bottom-description">' . $bottom_desc . '</span></div>';
    }

    echo $after_widget;
  }

  public function update($new_instance, $old_instance) {
    $instance = array();
    $instance['title']              = stripslashes(strip_tags($new_instance['title']));
    $instance['top_desc']           = stripslashes($new_instance['top_desc']);
    $instance['bottom_desc']        = stripslashes($new_instance['bottom_desc']);
    $instance['not_logged_in_msg']  = stripslashes($new_instance['not_logged_in_msg']);
    $instance['member_no_subs_msg'] = stripslashes($new_instance['member_no_subs_msg']);
    $instance['show_link']          = isset($new_instance['show_link']);

    return $instance;
  }

  public function form($instance) {
    $title              = (isset($instance['title']))?$instance['title']:__('Your Subscriptions', 'memberpress');
    $top_desc           = (isset($instance['top_desc']))?$instance['top_desc']:'';
    $bottom_desc        = (isset($instance['bottom_desc']))?$instance['bottom_desc']:'';
    $not_logged_in_msg  = (isset($instance['not_logged_in_msg']))?$instance['not_logged_in_msg']:'You are not logged in.';
    $member_no_subs_msg = (isset($instance['member_no_subs_msg']))?$instance['member_no_subs_msg']:'You have no Subscriptions yet.';
    $show_link          = (isset($instance['show_link']) && $instance['show_link']);

    ?>
    <p>
      <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:' , 'memberpress'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
    </p>
    <p>
      <label for="<?php echo $this->get_field_id('not_logged_in_msg'); ?>"><?php _e('Not Logged In Message:' , 'memberpress'); ?></label>
      <textarea class="widefat" id="<?php echo $this->get_field_id('not_logged_in_msg'); ?>" name="<?php echo $this->get_field_name('not_logged_in_msg'); ?>"><?php echo esc_attr(stripslashes($not_logged_in_msg)); ?></textarea>
    </p>
    <p>
      <label for="<?php echo $this->get_field_id('member_no_subs_msg'); ?>"><?php _e('No Subscriptions Message:' , 'memberpress'); ?></label>
      <textarea class="widefat" id="<?php echo $this->get_field_id('member_no_subs_msg'); ?>" name="<?php echo $this->get_field_name('member_no_subs_msg'); ?>"><?php echo esc_attr(stripslashes($member_no_subs_msg)); ?></textarea>
    </p>
    <p>
      <label for="<?php echo $this->get_field_id('top_desc'); ?>"><?php _e('Top Description (optional):' , 'memberpress'); ?></label>
      <textarea class="widefat" id="<?php echo $this->get_field_id('top_desc'); ?>" name="<?php echo $this->get_field_name('top_desc'); ?>"><?php echo esc_attr(stripslashes($top_desc)); ?></textarea>
    </p>
    <p>
      <label for="<?php echo $this->get_field_id('bottom_desc'); ?>"><?php _e('Bottom Description (optional):' , 'memberpress'); ?></label>
      <textarea class="widefat" id="<?php echo $this->get_field_id('bottom_desc'); ?>" name="<?php echo $this->get_field_name('bottom_desc'); ?>"><?php echo esc_attr(stripslashes($bottom_desc)); ?></textarea>
    </p>
    <p>
      <input type="checkbox" id="<?php echo $this->get_field_id('show_link'); ?>" name="<?php echo $this->get_field_name('show_link'); ?>" <?php checked($show_link); ?> />
      <label for="<?php echo $this->get_field_id('show_link'); ?>"><?php _e('Use Membership Access URLs?', 'memberpress'); ?></label><br/>
      <small style="display:block;padding-left:24px;"><?php _e('Makes the Subscription name clickable, pointing to the Membership Access URL you have set in the Membership settings (Advanced tab).', 'memberpress'); ?></small>
    </p>
    <?php
  }
} // class MeprSubscriptionsWidget
