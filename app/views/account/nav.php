<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mp_wrapper">
  <div id="mepr-account-nav">
    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav('home'); ?>">
      <a href="<?php echo MeprHooks::apply_filters('mepr-account-nav-home-link',$account_url.$delim.'action=home'); ?>" id="mepr-account-home"><?php _ex('Home', 'ui', 'memberpress'); ?></a>
    </span>
    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav('subscriptions'); ?>">
      <a href="<?php echo MeprHooks::apply_filters('mepr-account-nav-subscriptions-link',$account_url.$delim.'action=subscriptions'); ?>" id="mepr-account-subscriptions"><?php _ex('Subscriptions', 'ui', 'memberpress'); ?></a></span>
    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav('payments'); ?>">
      <a href="<?php echo MeprHooks::apply_filters('mepr_account-nav-payments-link',$account_url.$delim.'action=payments'); ?>" id="mepr-account-payments"><?php _ex('Payments', 'ui', 'memberpress'); ?></a>
    </span>
    <?php MeprHooks::do_action('mepr_account_nav', $mepr_current_user); ?>
    <span class="mepr-nav-item"><a href="<?php echo MeprUtils::logout_url(); ?>" id="mepr-account-logout"><?php _ex('Logout', 'ui', 'memberpress'); ?></a></span>
  </div>
</div>

<?php
if(isset($expired_subs) and !empty($expired_subs)) {
  $account_url = MeprUtils::get_permalink(); // $mepr_options->account_page_url();
  $delim = preg_match('#\?#',$account_url) ? '&' : '?';
  $errors = array(sprintf(_x('You have a problem with one or more of your subscriptions. To prevent any lapses in your subscriptions please visit your %sSubscriptions%s page to update them.', 'ui', 'memberpress'),'<a href="'.$account_url.$delim.'action=subscriptions">','</a>'));
  MeprView::render('/shared/errors', get_defined_vars());
}
