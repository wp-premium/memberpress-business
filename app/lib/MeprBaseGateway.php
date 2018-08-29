<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/***** Define Exceptions *****/
class MeprGatewayException extends Exception { }
class MeprHttpException extends Exception { }
class MeprRemoteException extends Exception { }

/** Lays down the interface for Gateways in MemberPress **/
abstract class MeprBaseGateway {

  /** Used in the view to identify the payment method */
  public $name;

  /** Used in the view to label the payment method */
  public $label;
  public $use_label;

  /** Used in the view to render an icon for each payment method */
  public $icon;
  public $use_icon;

  /** Used in the view to render a description for each payment method */
  public $desc;
  public $use_desc;

  /** The public id of the payment method **/
  public $id;

  /** The recurrence type of the payment method 'manual' or 'automatic' */
  //public $recurrence_type;

  /** This will be where the gateway interface will store its settings */
  public $settings;

  /** Important to determine what this gateway is capable of **/
  public $capabilities;

  /** An array of callbacks to be called on 'init' ... these will be used to
    * impelement listeners for notifiers like PayPal IPN and/or Authorize.net Silent Posts.
    *
    * This should be an array in this format:
    *
    *  array( 'action' => 'callback', 'action2' => 'callback2' ... )
    *
    * An example of this is:
    *
    *  array( 'ipn' => 'listener' )
    */
  protected $notifiers;

  /** This works just like the notifiers but is rendered as a page for the end user.
    * This can be used to render cancellation, error and any other kind of page the
    * specific gateway we're working with at the time requires.
    */
  protected $message_pages;

  /** This generates a unique id for the gateway integration to use */
  public function generate_id() {
    $mepr_options = MeprOptions::fetch();
    $ids = array_keys($mepr_options->integrations);

    $num = mt_rand(1,9999);
    $id = MeprUtils::base36_encode(time()) . '-' . MeprUtils::base36_encode($num);

    return $id;
  }

  /** The system uses this to load this if there's a payment option configured for this */
  abstract public function load($settings);

  /** Sets the defaults for the settings, etc */
  abstract protected function set_defaults();

  /** Returns true if a capability exists ... false if not */
  public function can($cap) {
    return in_array(trim($cap),$this->capabilities);
  }

  /** Returns the array of notifiers for the specific gateway */
  public function notifiers() {
    return $this->notifiers;
  }

  public function notifier($action) {
    if( isset($this->notifiers[$action]) )
      return $this->notifiers[$action];

    return false;
  }

  /** Returns the array of notifiers for the specific gateway */
  public function message_pages() {
    return $this->message_pages;
  }

  public function message_page($action) {
    if( isset($this->message_pages[$action]) )
      return $this->message_pages[$action];

    return false;
  }

  /** Returns the url of a given notifier for the current gateway */
  public function notify_url($action, $force_ssl=false) {
    if(isset($this->notifiers[$action])) {
      $permalink_structure = get_option('permalink_structure');
      $force_ugly_urls = get_option('mepr_force_ugly_gateway_notify_urls');

      if($force_ugly_urls || empty($permalink_structure)) {
        $url = MEPR_SCRIPT_URL."&pmt={$this->id}&action={$action}";
      }
      else {
        $notify_url = preg_replace('!%gatewayid%!', $this->id, MeprUtils::gateway_notify_url_structure());
        $notify_url = preg_replace('!%action%!', $action, $notify_url);

        $url = site_url($notify_url);
      }

      if($force_ssl) {
        $url = preg_replace('/^http:/','https:',$url);
      }

      return $url;
    }

    return false;
  }

  /** Returns the url of a given message page for the current membership & gateway */
  public function message_page_url($product, $action) {
    if(isset($this->message_pages[$action]))
      return $product->url("?pmt={$this->id}&action={$action}");
    return false;
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
    * before this step is necessary this method should just be left blank.
    */
  abstract public function process_payment($transaction);

  /** Used to record a successful payment by the given gateway. It should have
    * the ability to record a successful payment or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  abstract public function record_payment();

  /** This method should be used by the class to push a request to to the gateway.
    */
  abstract public function process_refund(MeprTransaction $txn);

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  abstract public function record_refund();

  /** Used to record a successful recurring payment by the given gateway. It
    * should have the ability to record a successful payment or a failure. It is
    * this method that should be used when receiving an IPN from PayPal or a
    * Silent Post from Authorize.net.
    */
  abstract public function record_subscription_payment();

  /** Used to record a declined payment. */
  abstract public function record_payment_failure();

  /** Used to process a one-off payment for a trial period.
    * Should be used for gateways that don't support trial periods
    * on recurring subscriptions, or for gateways that don't
    * support flexible trial periods/amounts but do support
    * setting the subscription start date to some time in the future.
    * Authorize.net and Stripe.com currently use this method.
    */
  abstract public function process_trial_payment($transaction);

  /** See above -- process_trial_payment() method
    */
  abstract public function record_trial_payment($transaction);

  /** Used to send subscription data to a given payment gateway. In gateways
    * which redirect before this step is necessary this method should just be
    * left blank.
    */
  abstract public function process_create_subscription($transaction);

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  abstract public function record_create_subscription();

  abstract public function process_update_subscription($subscription_id);

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  abstract public function record_update_subscription();

  /** Used to suspend a subscription by the given gateway.
    */
  abstract public function process_suspend_subscription($subscription_id);

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  abstract public function record_suspend_subscription();

  /** Used to suspend a subscription by the given gateway.
    */
  abstract public function process_resume_subscription($subscription_id);

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  abstract public function record_resume_subscription();

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  abstract public function process_cancel_subscription($subscription_id);

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  abstract public function record_cancel_subscription();

  /** Gets called when the signup form is posted used for running any payment
    * method specific actions when processing the customer signup form.
    */
  abstract public function process_signup_form($txn);

  /** Gets called on the 'init' action after before the payment page is
    * displayed. If we're using an offsite payment solution like PayPal
    * then this method will just redirect to it.
    */
  abstract public function display_payment_page($txn);

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  abstract public function enqueue_payment_form_scripts();

  /** This spits out html for the payment form on the registration / payment
    * page for the user to fill out for payment.
    */
  abstract public function display_payment_form($amount, $user, $product_id, $transaction_id);

  /** Validates the payment form before a payment is processed */
  abstract public function validate_payment_form($errors);

  /** This method can be overridden if necessary */
  public function process_payment_form($txn) {
    $mepr_options = MeprOptions::fetch();

    //Back button fix for IE and Edge
    //Make sure they haven't just completed the subscription signup and clicked the back button
    if($txn->status != MeprTransaction::$pending_str) {
      throw new Exception(sprintf(_x('You already completed your payment to this subscription. %1$sClick here to view your subscriptions%2$s.', 'ui', 'memberpress'), '<a href="'.$mepr_options->account_page_url("action=subscriptions").'">', '</a>'));
    }

    $error_str = __('Sorry but we can\'t process your payment at this time. Try back later.', 'memberpress');

    if(isset($txn) && $txn instanceof MeprTransaction) {
      $usr = $txn->user();
      $prd = $txn->product();
    }
    else {
      throw new Exception($error_str.' [PPF01]');
    }

    if($txn->amount <= 0.00) {
      MeprTransaction::create_free_transaction($txn);
      return;
    }

    if($txn->gateway == $this->id) {
      if(!$prd->is_one_time_payment()) {
        if($usr->is_logged_in_and_current_user() &&
           ($usr->is_already_subscribed_to($prd->ID) &&
            !$prd->simultaneous_subscriptions)) {
          // Do nothing they're already subscribed
          $sub = $txn->subscription();
          // Blow these away ... who knows how we even got here
          $sub->destroy();
          $txn->destroy();

          throw new Exception($error_str.' [PPF02]');
        }
        else {
         //Trial pmt is included in the Subscription profile at gateway (PayPal mostly)
          if(!$this->can('subscription-trial-payment') &&
             ($sub = $txn->subscription()) !== false &&
             $sub->trial && $sub->trial_amount > 0.00) {
            $txn->set_subtotal($sub->trial_amount);
            $this->email_status( "Calling process_trial_payment ...\n\n" . MeprUtils::object_to_string($txn) . "\n\n" . MeprUtils::object_to_string($sub), $this->settings->debug );
            $this->process_trial_payment($txn);
          }

          $this->process_create_subscription($txn);
        }
      }
      else {
        $this->process_payment($txn);
      }
    }
    else {
      throw new Exception($error_str.' [PPF03]');
    }
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  abstract public function display_options_form();

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  abstract public function validate_options_form($errors);

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the front end user account page.
    * Can be overridden if custom scripts are necessary.
    */
  public function enqueue_user_account_scripts() {
  }

  /** This displays the subscription row buttons on the user account page. Can be overridden if necessary.
    */
  public function print_user_account_subscription_row_actions($subscription) {
    global $post;

    $mepr_options = MeprOptions::fetch();
    // $subscription = new MeprSubscription($sub_id);
    $product = new MeprProduct($subscription->product_id);

    // Assume we're either on the account page or some
    // page that is using the [mepr-account-form] shortcode
    $account_url   = MeprUtils::get_permalink($post->ID);
    $account_delim = ( preg_match( '~\?~', $account_url ) ? '&' : '?' );
    $user = $subscription->user();

    ?>
    <?php /* <div class="mepr-account-row-actions"> */ ?>
      <?php if( $subscription->status != MeprSubscription::$pending_str ): ?>
        <?php if($subscription->status != MeprSubscription::$cancelled_str): ?>
          <a href="<?php echo $this->https_url("{$account_url}{$account_delim}action=update&sub={$subscription->id}"); ?>" class="mepr-account-row-action mepr-account-update"><?php _e('Update', 'memberpress'); ?></a>
        <?php endif; ?>

        <?php if(($grp = $product->group()) && count($grp->products('ids')) > 1): //Can't upgrade to no other options ?>
          <div id="mepr-upgrade-sub-<?php echo $subscription->id; ?>" class="mepr-white-popup mfp-hide">
            <center>
              <div class="mepr-upgrade-sub-text">
                <?php _e('Please select a new plan', 'memberpress'); ?>
              </div>
              <br/>
              <div>
                <select id="mepr-upgrade-dropdown-<?php echo $subscription->id; ?>" class="mepr-upgrade-dropdown">
                  <?php foreach($grp->products() as $p): ?>
                    <?php if($p->can_you_buy_me()): ?>
                      <option value="<?php echo $p->url(); ?>"><?php printf('%1$s (%2$s)', $p->post_title, MeprProductsHelper::product_terms($p, $user)); ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
              <br/>
              <div class="mepr-cancel-sub-buttons">
                <button class="mepr-btn mepr-upgrade-buy-now" data-id="<?php echo $subscription->id; ?>"><?php _e('Select Plan', 'memberpress'); ?></button>
                <button class="mepr-btn mepr-upgrade-cancel"><?php _e('Cancel', 'memberpress'); ?></button>
              </div>
            </center>
          </div>
          <?php ob_start(); ?>
          <?php
            if($product->simultaneous_subscriptions && $subscription->is_active() && $subscription->is_cancelled()) {
              MeprAccountHelper::purchase_link($product, _x('Re-Subscribe', 'ui', 'memberpress'));
            }
          ?>
          <?php $upgrade_label = ($grp->is_upgrade_path ? __('Change Plan', 'memberpress') : __('Other Memberships', 'memberpress')); ?>
          <?php if(!$grp->disable_change_plan_popup): ?>
            <a href="#mepr-upgrade-sub-<?php echo $subscription->id; ?>" class="mepr-open-upgrade-popup mepr-account-row-action mepr-account-upgrade"><?php echo $upgrade_label; ?></a>
          <?php else: ?>
            <a href="<?php echo $grp->url(); ?>" class="mepr-account-row-action mepr-account-upgrade"><?php echo $upgrade_label; ?></a>
          <?php endif; ?>
          <?php echo MeprHooks::apply_filters('mepr_custom_upgrade_link', ob_get_clean(), $subscription); ?>
        <?php endif; ?>

        <?php if( $mepr_options->allow_suspend_subs and
                  $this->can('suspend-subscriptions') and
                  $subscription->status == MeprSubscription::$active_str ): ?>
          <?php ob_start(); ?>
            <a href="<?php echo "{$account_url}{$account_delim}action=suspend&sub={$subscription->id}"; ?>" class="mepr-account-row-action mepr-account-suspend" onclick="return confirm('<?php _e('Are you sure you want to pause this subscription?', 'memberpress'); ?>');"><?php _e('Pause', 'memberpress'); ?></a>
          <?php echo MeprHooks::apply_filters('mepr_custom_pause_link', ob_get_clean(), $subscription); ?>
        <?php elseif( $mepr_options->allow_suspend_subs and
                      $this->can('suspend-subscriptions') and
                      $subscription->status==MeprSubscription::$suspended_str ): ?>
          <?php ob_start(); ?>
            <a href="<?php echo "{$account_url}{$account_delim}action=resume&sub={$subscription->id}"; ?>" class="mepr-account-row-action mepr-account-resume"><?php _e('Resume', 'memberpress'); ?></a>
          <?php echo MeprHooks::apply_filters('mepr_custom_resume_link', ob_get_clean(), $subscription); ?>
        <?php endif; ?>

        <?php if($mepr_options->allow_cancel_subs and $this->can('cancel-subscriptions') && $subscription->status == MeprSubscription::$active_str): ?>
          <div id="mepr-cancel-sub-<?php echo $subscription->id; ?>" class="mepr-white-popup mfp-hide">
            <div class="mepr-cancel-sub-text">
              <?php _e('Are you sure you want to cancel this subscription?', 'memberpress'); ?>
            </div>
            <div class="mepr-cancel-sub-buttons">
              <button class="mepr-btn mepr-left-margin mepr-confirm-yes" data-url="<?php echo "{$account_url}{$account_delim}action=cancel&sub={$subscription->id}"; ?>"><?php _e('Yes', 'memberpress'); ?></button>
              <button class="mepr-btn mepr-confirm-no"><?php _e('No', 'memberpress'); ?></button>
            </div>
          </div>
          <?php ob_start(); ?>
            <a href="#mepr-cancel-sub-<?php echo $subscription->id; ?>" class="mepr-open-cancel-confirm mepr-account-row-action mepr-account-cancel"><?php _e('Cancel', 'memberpress'); ?></a>
          <?php echo MeprHooks::apply_filters('mepr_custom_cancel_link', ob_get_clean(), $subscription); ?>
        <?php endif; ?>
      <?php endif; ?>
    <?php /* </div> */ ?>
    <?php
  }

  protected function https_url($url) {
    return $this->force_ssl() ? preg_replace('!^https?:!','https:',$url) : $url;
  }

  /** Displays the update account form on the subscription account page **/
  abstract public function display_update_account_form($subscription_id, $errors=array(), $message="");

  /** Validates the payment form before a payment is processed */
  abstract public function validate_update_account_form($errors=array());

  /** Actually pushes the account update to the payment processor */
  abstract public function process_update_account_form($subscription_id);

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  abstract public function is_test_mode();

  /** Returns boolean ... whether or not we should be forcing ssl */
  abstract public function force_ssl();

  protected function email_status($message, $debug)
  {
    if($debug) {
      // Send notification email to admin user (to and from the admin user)
      /* translators: In this string, %1$s is the Blog Name/Title and %2$s is the Name of the Payment Method */
      $subject = sprintf(__('[%1$s] %2$s Debug Email', 'memberpress'), get_option('blogname'), $this->name );
      MeprUtils::wp_mail_to_admin($subject, $message);

      //error_log('************* MEMBERPRESS ERROR: '.$message);
    }
  }

  /** Luhn Check Credit card algorithm */
  protected function is_credit_card_valid($number) {
    // short circuit if the cc# doesn't match any of the credit card types
    // if( $this->credit_card_type($number) ) //Need to add discover first
      // return false;

    // Strip any non-digits (useful for credit card numbers with spaces and hyphens)
    $number=preg_replace('/\D/', '', $number);

    // Set the string length and parity
    $number_length=strlen($number);
    $parity=$number_length % 2;

    // Loop through each digit and do the maths
    $total=0;
    for ($i=0; $i<$number_length; $i++) {
      $digit=$number[$i];
      // Multiply alternate digits by two
      if ($i % 2 == $parity) {
        $digit*=2;
        // If the sum is two digits, add them together (in effect)
        if ($digit > 9) {
          $digit-=9;
        }
      }
      // Total up the digits
      $total+=$digit;
    }

    // If the total mod 10 equals 0, the number is valid
    return ( ( $total % 10 ) == 0 );
  }

  protected function credit_card_type($number) {
    $cards = array(
        "visa" => "(4\d{12}(?:\d{3})?)",
        "amex" => "(3[47]\d{13})",
        "jcb" => "(35[2-8][89]\d\d\d{10})",
        "maestro" => "((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)",
        "solo" => "((?:6334|6767)\d{12}(?:\d\d)?\d?)",
        "mastercard" => "(5[1-5]\d{14})",
        "switch" => "(?:(?:(?:4903|4905|4911|4936|6333|6759)\d{12})|(?:(?:564182|633110)\d{10})(\d\d)?\d?)",
    );
    $names = array("Visa", "American Express", "JCB", "Maestro", "Solo", "Mastercard", "Switch");
    $matches = array();
    $pattern = "#^(?:".implode("|", $cards).")$#";
    $result = preg_match($pattern, str_replace(" ", "", $number), $matches);
    return ($result>0)?$names[sizeof($matches)-2]:false;
  }

  public function months_dropdown($name,$class,$selected='',$pad_zeros=false) {
    ?>
    <select <?php echo empty($name)?'':"name=\"{$name}\" "; ?>class="mepr-payment-form-select <?php echo empty($class)?'':$class; ?>">
    <?php
    for($i=1;$i<=12;$i++) {
      $i_str = $pad_zeros?sprintf("%02d",$i):$i;
      $selected_str = $selected==$i_str?' selected="selected"':''
      ?>
      <option value="<?php echo $i_str; ?>"<?php echo $selected_str; ?>><?php echo $i_str; ?></option>
      <?php
    }

    ?>
    </select>
    <?php
  }

  public function years_dropdown($name,$class,$selected='') {
    $year = gmdate('Y', time());
    ?>
    <select <?php echo empty($name)?'':"name=\"{$name}\" "; ?>class="mepr-payment-form-select <?php echo empty($class)?'':$class; ?>">
    <?php
    for($i=$year;$i<=($year+9);$i++) {
      $selected_str = $selected==$i?' selected="selected"':''
      ?>
      <option value="<?php echo $i; ?>"<?php echo $selected_str; ?>><?php echo $i; ?></option>
      <?php
    }

    ?>
    </select>
    <?php
  }

  public function txn_count($sub=null) {
    if(!is_null($sub)) {
      return $sub->txn_count;
    }
    else {
      return false;
    }
  }

  //Currently used for both PayPal gateways
  //Determines if the payment being recorded should be a paid trial period payment
  //If so, it should be a confirmation txn that we can convert to a payment txn
  protected function is_subscr_trial_payment($sub) {
    global $wpdb;
    $mepr_db = new MeprDb();

    //If no trial period, or trial period is free, then we don't want to record the first txn as a regular payment
    if(!$sub->trial || ($sub->trial && $sub->trial_amount <= 0.00)) {
      return false;
    }

    //Let's also make sure the first txn is still a confirmation type
    $first_txn = $sub->first_txn();
    if($first_txn == false || !($first_txn instanceof MeprTransaction) || $first_txn->txn_type != MeprTransaction::$subscription_confirmation_str) {
      return false;
    }

    //Making sure this is in fact the first real payment
    $q = $wpdb->prepare("
        SELECT COUNT(*)
          FROM {$mepr_db->transactions}
         WHERE subscription_id = %d
           AND txn_type = %s
           AND status <> %s
      ",
      $sub->id,
      MeprTransaction::$payment_str,
      MeprTransaction::$pending_str
    );

    $count = $wpdb->get_var($q);

    return ((int)$count == 0);
  }

  /** Get the renewal base date for a given subscription. This is the date MemberPress will use to calculate expiration dates.
    * Of course this method is meant to be overridden when a gateway requires it.
    */
  public function get_renewal_base_date(MeprSubscription $sub) {
    return $sub->created_at;
  }

  public function upgraded_sub($sub, $event_txn) {
    $type = MeprUtils::get_sub_type($sub);
    if( $type !== false ) {
      MeprHooks::do_action("mepr-upgraded-{$type}-sub", $sub);
      MeprHooks::do_action("mepr-upgraded-sub", $type, $sub);
      MeprHooks::do_action("mepr-sub-created", $type, $sub, 'upgraded');
      MeprUtils::send_upgraded_sub_notices($sub);
      MeprUtils::record_upgraded_sub_events($sub, $event_txn);
    }
  }

  public function downgraded_sub($sub, $event_txn) {
    $type = MeprUtils::get_sub_type($sub);
    if( $type !== false ) {
      MeprHooks::do_action("mepr-downgraded-{$type}-sub", $sub);
      MeprHooks::do_action("mepr-downgraded-sub", $type, $sub);
      MeprHooks::do_action("mepr-sub-created", $type, $sub, 'downgraded');
      MeprUtils::send_downgraded_sub_notices($sub);
      MeprUtils::record_downgraded_sub_events($sub, $event_txn);
    }
  }

  public function new_sub($sub, $send_notices = false) {
    $type = MeprUtils::get_sub_type($sub);
    if( $type !== false ) {
      MeprHooks::do_action("mepr-new-{$type}-sub", $sub);
      MeprHooks::do_action("mepr-new-sub", $type, $sub);
      MeprHooks::do_action("mepr-sub-created", $type, $sub, 'new');
      if($send_notices === true) {
        MeprUtils::send_new_sub_notices($sub);
      }
    }
  }
}
