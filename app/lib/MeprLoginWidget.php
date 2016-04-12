<?php
/**
 * Adds MeprLoginWidget widget.
 */
class MeprLoginWidget extends WP_Widget {
  /**
   * Register widget with WordPress.
   */
  public function __construct() {
    parent::__construct(
      'mepr_login_widget', // Base ID
      'MemberPress Login', // Name
      array('description' => __('Place a MemberPress Login on any page with a sidebar region', 'memberpress')) // Args
    );
  }

  public static function register_widget() {
    $mepr_options = MeprOptions::fetch();

    if( !empty($mepr_options->login_page_id) and
        is_numeric($mepr_options->login_page_id) and
        ($mepr_options->login_page_id > 0) ) {
      register_widget("MeprLoginWidget");
    }
  }

  /**
   * Front-end display of widget.
   *
   * @see WP_Widget::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function widget($args, $instance) {
    extract($args);
    $title = MeprHooks::apply_filters( 'mepr-login-title', $instance['title'] );
    $redirect = (isset($instance['redirect']) && $instance['redirect']);

    echo $before_widget;
    if(!empty($title)) {
      echo $before_title.$title.$after_title;
    }

    try {
      $login_ctrl = MeprCtrlFactory::fetch('login');

      if($redirect) {
        $login_ctrl->display_login_form(false, true);
      }
      else {
        $login_ctrl->display_login_form(true);
      }
    }
    catch(Exception $e) {
      // Nothing for now
    }

    echo $after_widget;
  }

  /**
   * Sanitize widget form values as they are saved.
   *
   * @see WP_Widget::update()
   *
   * @param array $new_instance Values just sent to be saved.
   * @param array $old_instance Previously saved values from database.
   *
   * @return array Updated safe values to be saved.
   */
  public function update($new_instance, $old_instance) {
    $instance = array();
    $instance['title'] = strip_tags($new_instance['title']);
    $instance['redirect'] = isset($new_instance['redirect']);

    return $instance;
  }

  /**
   * Back-end widget form.
   *
   * @see WP_Widget::form()
   *
   * @param array $instance Previously saved values from database.
   */
  public function form($instance) {
    $title = (isset($instance['title'])) ? $instance['title'] : __('Login', 'memberpress');
    $redirect = (isset($instance['redirect']) && $instance['redirect']);

    ?>
    <p>
      <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' , 'memberpress'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
    </p>
    <p>
      <input type="checkbox" id="<?php echo $this->get_field_id('redirect'); ?>" name="<?php echo $this->get_field_name('redirect'); ?>" <?php checked($redirect); ?> />
      <label for="<?php echo $this->get_field_id('redirect'); ?>"><?php _e('Use Login Redirect URL?', 'memberpress'); ?></label>
    </p>
    <?php
  }
} // class MeprLoginWidget
