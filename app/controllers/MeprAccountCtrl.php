<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAccountCtrl extends MeprBaseCtrl {
  //Prevent a silly error with BuddyPress and our account links widget
  //We should eventually change our account links widget to properly inherit the WP_Widget class
  public $id_base = 'mepr_account_links_widget';

  public function load_hooks() {
    add_action('wp_enqueue_scripts', array($this,'enqueue_scripts'));
    add_action('init', array($this, 'maybe_update_username')); //Need to use init for cookie stuff and to get old and new emails

    //Shortcodes
    add_shortcode('mepr-account-form', array($this,'account_form_shortcode'));
    add_shortcode('mepr-account-link', array($this,'get_account_links'));
    add_shortcode('mepr_account_link', array($this,'get_account_links')); // Deprecated
    add_shortcode('mepr-account-info', array($this,'output_account_meta'));
  }

  //Update username if username is email address and email address is changing
  public function maybe_update_username() {
    if(!isset($_POST['mepr-process-account']) || $_POST['mepr-process-account'] != 'Y') { return; }

    global $wpdb;
    $mepr_options = MeprOptions::fetch();

    $mepr_user = MeprUtils::get_currentuserinfo();
    $old_email = $mepr_user->user_email;
    $new_email = stripslashes($_POST['user_email']);

    if( $mepr_user !== false &&
        $mepr_options->username_is_email &&
        is_email($new_email) && //make sure this isn't sql injected or something
        is_email($mepr_user->user_login) && //make sure we're not overriding a non-email username
        $old_email == $mepr_user->user_login && //Make sure old email and old username match up
        $old_email != $new_email ) {
      //Some trickery here to keep the user logged in
      $wpdb->query($wpdb->prepare("UPDATE {$wpdb->users} SET user_login = %s WHERE ID = %d", $new_email, $mepr_user->ID));
      clean_user_cache($mepr_user->ID); //Get rid of the user cache
      wp_clear_auth_cookie(); //Clear their old cookie
      wp_set_current_user($mepr_user->ID); //Set the current user again
      wp_set_auth_cookie($mepr_user->ID, true, false); //Log the user back in w/out knowing their password
      update_user_caches(new WP_User($mepr_user->ID));
    }
  }

  public function enqueue_scripts() {
    global $post;
    $mepr_options = MeprOptions::fetch();

    if(MeprUser::is_account_page($post)) {
      wp_enqueue_style('jquery-magnific-popup', MEPR_CSS_URL.'/vendor/magnific-popup.css');
      wp_register_script('jquery-clippy', MEPR_JS_URL.'/jquery.clippy.js', array('jquery'));
      wp_register_script('jquery-magnific-popup', MEPR_JS_URL.'/vendor/jquery.magnific-popup.js', array('jquery'));
      wp_enqueue_script('mp-account', MEPR_JS_URL.'/account.js', array('jquery','jquery-clippy','jquery-magnific-popup'), MEPR_VERSION);
      wp_localize_script('mp-account', 'clippy', array( 'url' => MEPR_JS_URL.'/clippy.swf' ));

      $pms = $mepr_options->payment_methods();

      if($pms) {
        foreach($pms as $pm) {
          if($pm instanceof MeprBaseRealGateway) {
            $pm->enqueue_user_account_scripts();
          }
        }
      }
    }
  }

  public function render() {
    global $post;

    if(!isset($post) or !($post instanceof WP_Post)) { return; }

    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $expired_subs = $mepr_current_user->subscription_expirations('expired',true);
    $mepr_options = MeprOptions::fetch();
    $account_url = MeprUtils::get_permalink($post->ID); //$mepr_options->account_page_url();
    $delim = MeprAppCtrl::get_param_delimiter_char($account_url);

    MeprView::render('/account/nav', get_defined_vars());

    $action = (isset($_REQUEST['action']))?$_REQUEST['action']:false;

    switch($action) {
      case 'payments':
        $this->payments();
        break;
      case 'subscriptions':
        $this->subscriptions();
        break;
      case 'newpassword':
        $this->password();
        break;
      case 'cancel':
        $this->cancel();
        break;
      case 'suspend':
        $this->suspend();
        break;
      case 'resume':
        $this->resume();
        break;
      case 'update':
        $this->update();
        break;
      case 'upgrade':
        $this->upgrade();
        break;
      default:
        // Allows you to override the content for a nav tab
        ob_start();
        MeprHooks::do_action( 'mepr_account_nav_content', $action );
        $custom_content = ob_get_clean();

        if(empty($custom_content)) {
          $this->home();
        }
        else {
          echo $custom_content;
        }
    }
  }

  public function home() {
    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $mepr_options = MeprOptions::fetch();
    $account_url = $mepr_options->account_page_url();
    $delim = MeprAppCtrl::get_param_delimiter_char($account_url);
    $errors = array();
    $saved = false;
    $welcome_message = wpautop(stripslashes($mepr_options->custom_message));

    if(isset($_POST['mepr-process-account']) && $_POST['mepr-process-account'] == 'Y') {
      $errors = MeprUsersCtrl::validate_extra_profile_fields(null, null, $mepr_current_user);
      $errors = MeprUser::validate_account($_POST, $errors);
      $errors = MeprHooks::apply_filters('mepr-validate-account', $errors, $mepr_current_user);

      if(empty($errors)) {
        //Need to find a better way to do this eventually but for now update the user's email
        $new_email = stripslashes($_POST['user_email']);

        if($mepr_current_user->user_email != $new_email) {
          $mepr_current_user->user_email = $new_email;
          $mepr_current_user->store();
        }

        //Save the usermeta
        if($saved = MeprUsersCtrl::save_extra_profile_fields($mepr_current_user->ID, true)) {
          $message = __('Your account has been saved.', 'memberpress');
        }

        MeprHooks::do_action('mepr-save-account', $mepr_current_user);
      }
    }
    elseif(isset($_REQUEST['message']) && $_REQUEST['message']=='password_updated') {
      $message = __('Your password was successfully updated.', 'memberpress');
    }

    //Load user last in case we saved above, we want the saved info to show up
    $mepr_current_user = new MeprUser($mepr_current_user->ID);

    MeprView::render('/account/home', get_defined_vars());
  }

  public function password() {
    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $mepr_options = MeprOptions::fetch();
    $account_url = $mepr_options->account_page_url();
    $delim = MeprAppCtrl::get_param_delimiter_char($account_url);

    if(isset($_REQUEST['error'])) {
      $errors = array(__('Password update failed, please be sure your passwords match and try again.', 'memberpress'));
    }

    MeprView::render('/account/password', get_defined_vars());
  }

  public function payments() {
    global $wpdb;
    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $mepr_options = MeprOptions::fetch();
    $account_url = $mepr_options->account_page_url();
    $delim = MeprAppCtrl::get_param_delimiter_char($account_url);
    $perpage = 10;
    $curr_page = (isset($_GET['currpage']) && is_numeric($_GET['currpage']))?$_GET['currpage']:1;
    $start = ($curr_page - 1) * $perpage;
    $end = $start + $perpage;
    $list_table = MeprTransaction::list_table( 'created_at', 'DESC',
                                               $curr_page, '', $perpage,
                                               array( 'member' => $mepr_current_user->user_login,
                                                      'statuses' => array( MeprTransaction::$complete_str ) ) );
    $payments = $list_table['results'];
    $all = $list_table['count'];
    $next_page = (($curr_page * $perpage) >= $all)?false:$curr_page+1;
    $prev_page = ($curr_page > 1)?$curr_page - 1:false;

    MeprView::render('/account/payments', get_defined_vars());
  }

  public function subscriptions($message='',$errors=array()) {
    global $wpdb;
    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $mepr_options = MeprOptions::fetch();
    $account_url = $mepr_options->account_page_url();
    $delim = MeprAppCtrl::get_param_delimiter_char($account_url);
    $perpage = 10;
    $curr_page = (isset($_GET['currpage']) && is_numeric($_GET['currpage']))?$_GET['currpage']:1;
    $start = ($curr_page - 1) * $perpage;
    $end = $start + $perpage;

    // This is necessary to optimize the queries ... only query what we need
    $sub_cols = array('ID','user_id','product_id','subscr_id','status','created_at','expires_at','active');

    $table = MeprSubscription::account_subscr_table(
      'created_at', 'DESC',
      $curr_page, '', $perpage, false,
      array(
        'member' => $mepr_current_user->user_login,
        'statuses' => array(
          MeprSubscription::$active_str,
          MeprSubscription::$suspended_str,
          MeprSubscription::$cancelled_str
        )
      ),
      $sub_cols
    );

    $subscriptions = $table['results'];
    $all = $table['count'];
    $next_page = (($curr_page * $perpage) >= $all)?false:$curr_page + 1;
    $prev_page = ($curr_page > 1)?$curr_page - 1:false;

    MeprView::render('/shared/errors', get_defined_vars());
    MeprView::render('/account/subscriptions', get_defined_vars());
  }

  public function suspend() {
    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $sub = new MeprSubscription($_GET['sub']);
    $errors = array();
    $message = '';

    if($sub->user_id == $mepr_current_user->ID) {
      $pm = $sub->payment_method();

      if($pm->can('suspend-subscriptions')) {
        try {
          $pm->process_suspend_subscription($sub->ID);
          $message = __('Your subscription was successfully paused.', 'memberpress');
        }
        catch( Exception $e ) {
          $errors[] = $e->getMessage();
        }
      }
    }

    $this->subscriptions($message, $errors);
  }

  public function resume() {
    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $sub = new MeprSubscription($_GET['sub']);
    $errors = array();
    $message = '';

    if($sub->user_id == $mepr_current_user->ID) {
      $pm = $sub->payment_method();

      if($pm->can('suspend-subscriptions')) {
        try {
          $pm->process_resume_subscription($sub->ID);
          $message = __('You successfully resumed your subscription.', 'memberpress');
        }
        catch(Exception $e) {
          $errors[] = $e->getMessage();
        }
      }
    }

    $this->subscriptions($message, $errors);
  }

  public function cancel() {
    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $sub = new MeprSubscription($_GET['sub']);
    $errors = array();
    $message = '';

    if($sub->user_id == $mepr_current_user->ID)
    {
      $pm = $sub->payment_method();

      if($pm->can('cancel-subscriptions')) {
        try {
          $pm->process_cancel_subscription($sub->ID);
          $message = __('Your subscription was successfully cancelled.', 'memberpress');
        }
        catch(Exception $e) {
          $errors[] = $e->getMessage();
        }
      }
    }

    $this->subscriptions($message, $errors);
  }

  public function update() {
    $mepr_current_user = MeprUtils::get_currentuserinfo();
    $sub = new MeprSubscription($_REQUEST['sub']);

    if($sub->user_id == $mepr_current_user->ID) {
      $pm = $sub->payment_method();

      if(strtoupper($_SERVER['REQUEST_METHOD'] == 'GET')) // DISPLAY FORM
        $pm->display_update_account_form($sub->ID, array());
      elseif(strtoupper($_SERVER['REQUEST_METHOD'] == 'POST')) { // PROCESS FORM
        $errors = $pm->validate_update_account_form(array());
        $message='';

        if(empty($errors)) {
          try {
            $pm->process_update_account_form($sub->ID);
            $message = __('Your account information was successfully updated.', 'memberpress');
          }
          catch(Exception $e) {
            $errors[] = $e->getMessage();
          }
        }

        $pm->display_update_account_form($sub->ID, $errors, $message);
      }
    }
  }

  public function upgrade() {
    $sub = new MeprSubscription($_GET['sub']);
    $prd = $sub->product();
    $grp = $prd->group();

    // TODO: Uyeah, we may want to come up with a more elegant solution here
    //       for now we have to do a js redirect because we're in mid-page render
    ?>
    <script>
      top.window.location = '<?php echo $grp->url(); ?>';
    </script>
    <?php
  }

  public function account_form_shortcode($atts, $content = '') {
    //No need to validate anything as the below function already
    //does all the validations. This is just a wrapper
    return $this->display_account_form($content);
  }

  public function display_account_form($content='') {
    global $post;

    if(MeprUtils::is_user_logged_in()) {
      ob_start();
      MeprAccountCtrl::render();
      $content .= ob_get_clean();
    }
    else {
      $content = do_shortcode(MeprRulesCtrl::unauthorized_message($post));
    }

    return $content;
  }

  public function get_account_links() {
    $mepr_options = MeprOptions::fetch();
    ob_start();

    if(MeprUtils::is_user_logged_in()) {
      $account_url = $mepr_options->account_page_url();
      $logout_url = MeprUtils::logout_url();
      MeprView::render('/account/logged_in_template', get_defined_vars());
    }
    else {
      $login_url = MeprUtils::login_url();
      MeprView::render('/account/logged_out_template', get_defined_vars());
    }

    return ob_get_clean();
  }

  public function account_links_widget($args) {
    $mepr_options = MeprOptions::fetch();

    extract($args);

    echo $before_widget;
    echo $before_title.__('Account', 'memberpress').$after_title;

    if(MeprUtils::is_user_logged_in()) {
      $account_url = $mepr_options->account_page_url();
      $logout_url = MeprUtils::logout_url();
      MeprView::render('/account/logged_in_widget', get_defined_vars());
    }
    else {
      $login_url = MeprUtils::login_url();
      MeprView::render('/account/logged_out_widget', get_defined_vars());
    }

    echo $after_widget;
  }

  public function output_account_meta($atts=array(), $content='') {
    global $mepr_options, $user_ID;

    if((int)$user_ID < 1 || !isset($atts['field'])) {
      return '';
    }

    static $usermeta;

    if(!isset($usermeta) || !empty($usermeta)) {
      $userdata = get_userdata($user_ID);
      $usermeta = get_user_meta($user_ID);
    }

    foreach($userdata->data as $key => $value) {
      $usermeta[$key] = array($value);
    }

    //We can begin to define more custom return cases in here...
    switch($atts['field']) {
      case 'full_name':
        return ucfirst($usermeta['first_name'][0]) . ' ' . ucfirst($usermeta['last_name'][0]);
        break;
      case 'full_name_last_first':
        return ucfirst($usermeta['last_name'][0]) . ', ' . ucfirst($usermeta['first_name'][0]);
        break;
      case 'first_name_last_initial':
        return ucfirst($usermeta['first_name'][0]) . ' ' . ucfirst($usermeta['last_name'][0][0]) . '.';
        break;
      case 'last_name_first_initial':
        return ucfirst($usermeta['last_name'][0]) . ', ' . ucfirst($usermeta['first_name'][0][0]) . '.';
        break;
      default:
        return $usermeta[$atts['field']][0];
        break;
    }
  }

  public function save_new_password($user_id, $new_pass, $new_pass_confirm) {
    $mepr_options = MeprOptions::fetch();
    $account_url = $mepr_options->account_page_url();
    $delim = MeprAppCtrl::get_param_delimiter_char($account_url);

    $user = MeprUtils::get_currentuserinfo();

    if($user_id && $user && ($user->ID==$user_id)) {
      if(($new_pass == $new_pass_confirm) && !empty($new_pass)) {
        $user->rec->user_pass = $new_pass;
        $user->store();
        MeprUtils::wp_redirect($account_url.$delim.'action=home&message=password_updated');
      }
    }

    MeprUtils::wp_redirect($account_url.$delim.'action=newpassword&error=failed');
  }
}

