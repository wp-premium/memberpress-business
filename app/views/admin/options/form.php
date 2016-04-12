<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="wrap">
  <div class="icon32"></div>
  <h2><?php _e('Options', 'memberpress'); ?><a href="http://www.memberpress.com/user-manual/" class="add-new-h2" target="_blank"><?php _e('User Manual', 'memberpress'); ?></a></h2>

  <?php MeprView::render('/admin/errors', get_defined_vars()); ?>

  <form name="mepr_options_form" id="mepr_options_form" class="mepr-form" method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="action" value="process-form">
    <?php wp_nonce_field('update-options'); ?>

    <h2 id="mepr-reports-column-selector" class="nav-tab-wrapper">
      <a class="nav-tab nav-tab-active" id="pages" href="#"><?php _e('Pages', 'memberpress'); ?></a>
      <a class="nav-tab" id="accounts" href="#"><?php _e('Account', 'memberpress'); ?></a>
      <a class="nav-tab" id="fields" href="#"><?php _e('Fields', 'memberpress'); ?></a>
      <a class="nav-tab" id="integration" href="#"><?php _e('Payments', 'memberpress'); ?></a>
      <a class="nav-tab" id="emails" href="#"><?php _e('Emails', 'memberpress'); ?></a>
      <a class="nav-tab" id="marketing" href="#"><?php _e('Marketing', 'memberpress'); ?></a>
      <a class="nav-tab" id="info" href="#"><?php _e('Info', 'memberpress'); ?></a>
      <a class="nav-tab" id="general" href="#"><?php _e('General', 'memberpress'); ?></a>
      <?php MeprHooks::do_action('mepr_display_options_tabs'); ?>
    </h2>

    <div id="pages" class="mepr-options-hidden-pane">
      <h3><?php _e('Reserved Pages', 'memberpress'); ?></h3>
      <table class="mepr-options-pane">
        <tr>
          <td><?php _e('MemberPress Thank You Page', 'memberpress'); ?>*:</td>
          <td><?php MeprOptionsHelper::wp_pages_dropdown($mepr_options->thankyou_page_id_str, $mepr_options->thankyou_page_id, __('Thank You', 'memberpress')); ?></td>
        </tr>
        <tr>
          <td><?php _e('MemberPress Account Page', 'memberpress'); ?>*:</td>
          <td><?php MeprOptionsHelper::wp_pages_dropdown($mepr_options->account_page_id_str, $mepr_options->account_page_id, __('Account', 'memberpress')); ?></td>
        </tr>
        <tr>
          <td><?php _e('MemberPress Login Page', 'memberpress'); ?>*:</td>
          <td><?php MeprOptionsHelper::wp_pages_dropdown($mepr_options->login_page_id_str, $mepr_options->login_page_id, __('Login', 'memberpress')); ?></td>
        </tr>
      </table>

      <h3 class="mepr-field-label">
        <?php _e('Group and Membership Pages Slugs:', 'memberpress'); ?>
        <?php MeprAppHelper::info_tooltip( 'mepr-group-and-product-pages-slugs',
                                           __('Group and Membership Pages Slugs', 'memberpress'),
                                           __('Use these fields to customize the base slug of urls for your groups and memberships.', 'memberpress') . "<br/><br/>" .
                                           __('Note: It isn\'t recommended that you change these values if you already have existing groups and membership pages on a production membership site because all your urls for them will change (WordPress will attempt to redirect from old urls to new urls).', 'memberpress') ); ?>
      </h3>
      <table class="mepr-options-pane">
        <tbody>
          <tr valign="top">
            <td><label for="<?php echo $mepr_options->group_pages_slug_str; ?>"><?php _e("Group Pages Slug:", 'memberpress'); ?></td>
            <td>
              <input type="text" id="<?php echo $mepr_options->group_pages_slug_str; ?>" name="<?php echo $mepr_options->group_pages_slug_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->group_pages_slug); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <td><label for="<?php echo $mepr_options->product_pages_slug_str; ?>"><?php _e("Membership Pages Slug:", 'memberpress'); ?></td>
            <td>
              <input type="text" id="<?php echo $mepr_options->product_pages_slug_str; ?>" name="<?php echo $mepr_options->product_pages_slug_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->product_pages_slug); ?>" />
            </td>
          </tr>
        </tbody>
      </table>

      <h3 class="mepr-field-label"><?php _e('Unauthorized Access', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <input type="checkbox" name="<?php echo $mepr_options->redirect_on_unauthorized_str; ?>" id="<?php echo $mepr_options->redirect_on_unauthorized_str; ?>" <?php checked($mepr_options->redirect_on_unauthorized); ?> />
        <label for="<?php echo $mepr_options->redirect_on_unauthorized_str; ?>"><?php _e('Redirect unauthorized visitors to a specific URL', 'memberpress'); ?></label>
        <?php MeprAppHelper::info_tooltip( 'mepr-unauthorized-handling',
                                           __('Redirect Unauthorized Access', 'memberpress'),
                                           __("MemberPress allows you to handle unauthorized access by replacing the content on page or via a redirection to a specific url.<br/><br/>When this is checked, unauthorized visits will be redirected to a url otherwise the unauthorized message will appear on page.", 'memberpress') ); ?>

        <div id="mepr-unauthorized-redirect" class="mepr_hidden mepr-options-sub-pane">
          <label for="<?php echo $mepr_options->redirect_method_str; ?>"><?php _e('Redirect method:', 'memberpress'); ?></label>
          <?php MeprAppHelper::info_tooltip( 'mepr-unauthorized-redirect-method',
                                             __('Redirect Method', 'memberpress'),
                                             __('It is highly recommended that "template_redirect" should be your default redirect method.<br/><br/>However, this does not work with all WordPress themes. If you find that the unauthorized redirection is not happening when enabled try switching this to "init" instead.', 'memberpress') ); ?>
          <select name="<?php echo $mepr_options->redirect_method_str; ?>" id="<?php echo $mepr_options->redirect_method_str; ?>">
            <option value="template_redirect" <?php selected($mepr_options->redirect_method, 'template_redirect'); ?>><?php _e('template_redirect (recommended)', 'memberpress'); ?></option>
            <option value="init" <?php selected($mepr_options->redirect_method, 'init'); ?>><?php _e('init', 'memberpress'); ?></option>
          </select>
          <br/><br/>
          <input type="checkbox" name="<?php echo $mepr_options->redirect_non_singular_str; ?>" id="<?php echo $mepr_options->redirect_non_singular_str; ?>" <?php checked($mepr_options->redirect_non_singular); ?> />
          <label for="<?php echo $mepr_options->redirect_non_singular_str; ?>"><?php _e('Redirect non-singular views:', 'memberpress'); ?></label>
          <?php MeprAppHelper::info_tooltip( 'mepr-unauthorized-redirect-non-singular',
                                             __('Redirect Non-Singular Views', 'memberpress'),
                                             __('If any post in a non-singular view (EX: Blog page, category pages, archive pages etc) is protected, then do not allow the unauthorized members to see this non-singular view at all.', 'memberpress') ); ?>
          <br/><br/>
          <label for="<?php echo $mepr_options->unauthorized_redirect_url_str; ?>"><?php _e('URL to direct unauthorized visitors to:', 'memberpress'); ?></label>
          <?php MeprAppHelper::info_tooltip( 'mepr-unauthorized-redirect-url',
                                             __('Unauthorized Redirection URL', 'memberpress'),
                                             __('This is the URL that visitors will be redirected to when trying to access unauthorized content.', 'memberpress') ); ?>
          <input type="text" id="<?php echo $mepr_options->unauthorized_redirect_url_str; ?>" name="<?php echo $mepr_options->unauthorized_redirect_url_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->unauthorized_redirect_url); ?>" />
          <br/>
          <span class="description"><?php _e('You can use the <strong>[mepr-unauthorized-message]</strong> shortcode on this unauthorized page (assuming this url points to a page on this site).', 'memberpress'); ?></span>
        </div>
        <div>&nbsp;</div>
        <div id="mepr-unauthorized-show-excerpts">
          <input type="checkbox" name="<?php echo $mepr_options->unauth_show_excerpts_str; ?>" id="<?php echo $mepr_options->unauth_show_excerpts_str; ?>" <?php checked($mepr_options->unauth_show_excerpts); ?> />
          <label for="<?php echo $mepr_options->unauth_show_excerpts_str; ?>"><?php _e('Show an excerpt to unauthorized visitors', 'memberpress'); ?></label>
        </div>

        <div id="mepr-unauthorized-show-excerpts-type" class="mepr-options-sub-pane mepr-hidden">
          <?php
            MeprOptionsHelper::display_show_excerpts_dropdown( $mepr_options->unauth_excerpt_type_str,
                                                               $mepr_options->unauth_excerpt_type,
                                                               $mepr_options->unauth_excerpt_size_str,
                                                               $mepr_options->unauth_excerpt_size,
                                                               true
                                                             );
          ?>
        </div>

        <div>&nbsp;</div>
        <div id="mepr-unauthorized-show-login">
          <input type="checkbox" name="<?php echo $mepr_options->unauth_show_login_str; ?>" id="<?php echo $mepr_options->unauth_show_login_str; ?>" <?php checked($mepr_options->unauth_show_login); ?> />
          <label for="<?php echo $mepr_options->unauth_show_login_str; ?>"><?php _e('Show a login form on pages containing unauthorized content', 'memberpress'); ?></label>
        </div>

        <br/>
        <div class="mepr-field-label">
          <a href="" class="mp-toggle-unauthorized-message"><?php _e('Default Unauthorized Message:', 'memberpress'); ?></a>
            <?php MeprAppHelper::info_tooltip( 'mepr-default-unauthorized-message',
                                               __('Default Unauthorized Message', 'memberpress'),
                                               __('This is the default message that will show up when a user is not allowed to access the content on a page.', 'memberpress') ); ?>
        </div>
        <div class="mepr-hidden mepr-options-sub-pane mp-unauthorized-message">
          <?php wp_editor($mepr_options->unauthorized_message, $mepr_options->unauthorized_message_str); ?>
        </div>
      </div>
    </div>

    <div id="accounts" class="mepr-options-hidden-pane">
      <h3><?php _e('Permissions:', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <div class="mp-row">
          <div class="mp-col-4">
            <label for="<?php echo $mepr_options->disable_wp_admin_bar_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->disable_wp_admin_bar_str; ?>" id="<?php echo $mepr_options->disable_wp_admin_bar_str; ?>" <?php checked($mepr_options->disable_wp_admin_bar); ?> />
              <span><?php _e('Disable the WordPress admin bar for members', 'memberpress'); ?></span>
            </label>
          </div>
          <div class="mp-col-5">
            <label for="<?php echo $mepr_options->lock_wp_admin_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->lock_wp_admin_str; ?>" id="<?php echo $mepr_options->lock_wp_admin_str; ?>" <?php checked($mepr_options->lock_wp_admin); ?> />
              <span><?php _e('Keep members out of the WordPress Dashboard', 'memberpress'); ?></span>
            </label>
          </div>
        </div>
        <div class="mp-row">
          <div class="mp-col-4">
            <label for="<?php echo $mepr_options->allow_cancel_subs_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->allow_cancel_subs_str; ?>" id="<?php echo $mepr_options->allow_cancel_subs_str; ?>" <?php checked($mepr_options->allow_cancel_subs); ?> />
              <span><?php _e('Allow Members to Cancel their own subscriptions', 'memberpress'); ?></span>
            </label>
          </div>
          <div class="mp-col-5">
            <label for="<?php echo $mepr_options->allow_suspend_subs_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->allow_suspend_subs_str; ?>" id="<?php echo $mepr_options->allow_suspend_subs_str; ?>" <?php checked($mepr_options->allow_suspend_subs); ?> />
              <span>
                <?php _e('Allow Members to Pause &amp; Resume their own subscriptions', 'memberpress'); ?>
                <?php MeprAppHelper::info_tooltip( 'mepr-suspend-resume',
                                                   __('Pausing &amp; Resuming Subscriptions', 'memberpress'),
                                                   __('This option will only be available if this is enabled and the user purchased their subsciption using PayPal or Stripe.', 'memberpress') ); ?>
              </span>
            </label>
          </div>
        </div>
      </div>

      <h3><?php _e('Registration:', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <div class="mp-row">
          <div class="mp-col-9">
            <label for="<?php echo $mepr_options->enforce_strong_password_str; ?>"><?php _e('Password Strength Meter:', 'memberpress'); ?></label>
            <select name="<?php echo $mepr_options->enforce_strong_password_str; ?>" id="<?php echo $mepr_options->enforce_strong_password_str; ?>">
              <option value="0" <?php selected($mepr_options->enforce_strong_password, 0); ?>><?php _e('Hide', 'memberpress'); ?></option>
              <option value="show" <?php selected($mepr_options->enforce_strong_password, 'show'); ?>><?php _e('Show', 'memberpress'); ?></option>
              <option value="weak" <?php selected($mepr_options->enforce_strong_password, 'weak'); ?>><?php _e('Show &amp; Require Weak Password or Stronger', 'memberpress'); ?></option>
              <option value="medium" <?php selected($mepr_options->enforce_strong_password, 'medium'); ?>><?php _e('Show &amp; Require Medium Password or Stronger (Recommended)', 'memberpress'); ?></option>
              <option value="strong" <?php selected($mepr_options->enforce_strong_password, 'strong'); ?>><?php _e('Show &amp; Require Strong Password', 'memberpress'); ?></option>
            </select>
          </div>
        </div>
        <div class="mp-row">
          <div class="mp-col-4">
            <label for="<?php echo $mepr_options->disable_wp_registration_form_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->disable_wp_registration_form_str; ?>" id="<?php echo $mepr_options->disable_wp_registration_form_str; ?>" <?php checked($mepr_options->disable_wp_registration_form); ?> />
              <span><?php _e('Disable the standard WordPress registration form', 'memberpress'); ?></span>
            </label>
          </div>
          <div class="mp-col-5">
            <label for="<?php echo $mepr_options->coupon_field_enabled_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->coupon_field_enabled_str; ?>" id="<?php echo $mepr_options->coupon_field_enabled_str; ?>" <?php checked($mepr_options->coupon_field_enabled); ?> />
              <span><?php _e('Enable Coupon Field on membership registration forms', 'memberpress'); ?></span>
            </label>
          </div>
        </div>
        <div class="mp-row">
          <div class="mp-col-4">
            <label for="<?php echo $mepr_options->username_is_email_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->username_is_email_str; ?>" id="<?php echo $mepr_options->username_is_email_str; ?>" <?php checked($mepr_options->username_is_email); ?> />
              <span><?php _e('Members must use their email address for their Username', 'memberpress'); ?></span>
            </label>
          </div>
          <div class="mp-col-5">
            <label for="<?php echo $mepr_options->pro_rated_upgrades_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->pro_rated_upgrades_str; ?>" id="<?php echo $mepr_options->pro_rated_upgrades_str; ?>" <?php checked($mepr_options->pro_rated_upgrades); ?> />
              <span><?php _e('Pro-rate subscription prices when a member upgrades', 'memberpress'); ?></span>
            </label>
          </div>
        </div>
        <div class="mp-row">
          <div class="mp-col-4">
            <label for="<?php echo $mepr_options->disable_grace_init_days_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->disable_grace_init_days_str; ?>" id="<?php echo $mepr_options->disable_grace_init_days_str; ?>" <?php checked($mepr_options->disable_grace_init_days); ?> />
              <span><?php _e('Disable the 1 day grace period after signup', 'memberpress'); ?></span>
            </label>
            <?php MeprAppHelper::info_tooltip( 'mepr-disable-init-grace-days',
                                               __('1 Day Grace Period', 'memberpress'),
                                               __('PayPal, Stripe, and Authorize.net can sometimes take up to 24 hours to process the first payment on a members recurring subscription. By default MemberPress allows a 1 day grace period after a member signs up, so they can access the site immediately rather than wait for their payment to clear.', 'memberpress') . '<br/><br/>' . __('If you would like to make them wait for the payment to clear before they are allowed to access the site, then enable this option.', 'memberpress') ); ?>
          </div>
          <div class="mp-col-5">
            <?php //Nothing here for now ?>
          </div>
        </div>
        <div class="mp-row">
          <div class="mp-col-9">
            <label for="<?php echo $mepr_options->require_tos_str; ?>">
              <input type="checkbox" name="<?php echo $mepr_options->require_tos_str; ?>" id="<?php echo $mepr_options->require_tos_str; ?>" <?php checked($mepr_options->require_tos); ?> />
              <span><?php _e('Require Terms of Service on membership registration forms', 'memberpress'); ?></span>
            </label>
          </div>
        </div>
        <div id="mepr_tos_hidden" class="mepr-options-sub-pane mepr-hidden">
          <div class="mp-row">
            <div class="mp-col-3">
              <label for="<?php echo $mepr_options->tos_url_str; ?>"><?php _e('URL to your Terms of Service page:', 'memberpress'); ?></label>
            </div>
            <div class="mp-col-4">
              <input type="text" id="<?php echo $mepr_options->tos_url_str; ?>" name="<?php echo $mepr_options->tos_url_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->tos_url); ?>" />
            </div>
          </div>
          <div class="mp-row">
            <div class="mp-col-3">
              <label for="<?php echo $mepr_options->tos_title_str; ?>"><?php _e('Terms of Service Checkbox Title:', 'memberpress'); ?></label>
            </div>
            <div class="mp-col-4">
              <input type="text" id="<?php echo $mepr_options->tos_title_str; ?>" name="<?php echo $mepr_options->tos_title_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->tos_title); ?>" />
            </div>
          </div>
        </div>
      </div>

      <h3><?php _e('Login & Logout:', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <label for="<?php echo $mepr_options->force_login_page_url_str; ?>">
          <input type="checkbox" name="<?php echo $mepr_options->force_login_page_url_str; ?>" id="<?php echo $mepr_options->force_login_page_url_str; ?>" <?php checked($mepr_options->force_login_page_url); ?> />
          <span><?php _e('Force WordPress to use the MemberPress login page', 'memberpress'); ?></span>
        </label>
        <?php MeprAppHelper::info_tooltip( 'mepr-force-login-page-url',
                                           __('Force Login Page URL', 'memberpress'),
                                           __('Use this option to override WordPress links to /wp-login.php and instead use the Login page you have specified for MemberPress. If you have other plugins that use their own Login pages too you may want to leave this option disabled.', 'memberpress') ); ?>
        <br/><br/>
        <label for="<?php echo $mepr_options->login_redirect_url_str; ?>"><?php _e('URL to direct member to after login:', 'memberpress'); ?></label>&nbsp;&nbsp;&nbsp;
        <?php MeprAppHelper::info_tooltip( 'mepr-login-redirect-message',
                                           __('Login Redirect URL', 'memberpress'),
                                           __('For this to work you must have the Login page set in the MemberPress options. You can also override this option on a per-membership basis in the Advanced box when creating/editing a membership.', 'memberpress') ); ?>
        <input type="text" id="<?php echo $mepr_options->login_redirect_url_str; ?>" name="<?php echo $mepr_options->login_redirect_url_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->login_redirect_url); ?>" /><br/><br/>
        <label for="<?php echo $mepr_options->logout_redirect_url_str; ?>"><?php _e('URL to direct member to after logout:', 'memberpress'); ?></label>
        <?php MeprAppHelper::info_tooltip( 'mepr-logout-redirect-message',
                                           __('Logout Redirect URL', 'memberpress'),
                                           __('Set what URL you want the member to be taken to when they logout on your site. This setting applies to Administrators as well.', 'memberpress') ); ?>
        <input type="text" id="<?php echo $mepr_options->logout_redirect_url_str; ?>" name="<?php echo $mepr_options->logout_redirect_url_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->logout_redirect_url); ?>" /><br/><br/>
      </div>

      <h3 class="mepr-field-label"><?php _e('Account Page Welcome Message', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <?php wp_editor($mepr_options->custom_message, $mepr_options->custom_message_str); ?>
        <p class="description"><?php _e('This text will appear below the navigation on the Account Page.', 'memberpress'); ?></p>
      </div>
    </div>

    <div id="fields" class="mepr-options-hidden-pane">
    <h3><?php _e('Logged In Purchases:', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <label for="<?php echo $mepr_options->show_fields_logged_in_purchases_str; ?>">
          <input type="checkbox" name="<?php echo $mepr_options->show_fields_logged_in_purchases_str; ?>" id="<?php echo $mepr_options->show_fields_logged_in_purchases_str; ?>" <?php checked($mepr_options->show_fields_logged_in_purchases); ?>>
          <span><?php _e('Show The Fields Below For Logged-in Purchases', 'memberpress'); ?></span>
        </label>
      </div>
      <h3><?php _e('Extended User Information Fields:', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <div class="mp-row">
          <div class="mp-col-4">
            <strong><?php _e('Name Fields:', 'memberpress'); ?></strong>
          </div>
          <div class="mp-col-2">
            <label>
              <input type="checkbox" name="<?php echo $mepr_options->show_fname_lname_str; ?>" id="<?php echo $mepr_options->show_fname_lname_str; ?>" <?php checked($mepr_options->show_fname_lname); ?> />
              <span>&nbsp;<?php _e('Show', 'memberpress'); ?></span>
            </label>
          </div>
          <div class="mp-col-2">
            <label>
              <input type="checkbox" name="<?php echo $mepr_options->require_fname_lname_str; ?>" id="<?php echo $mepr_options->require_fname_lname_str; ?>" <?php checked($mepr_options->require_fname_lname); ?> />
              <span>&nbsp;<?php _e('Require', 'memberpress'); ?></span>
            </label>
          </div>
        </div>
        <div class="mp-row">
          <div class="mp-col-4">
            <strong><?php _e('Address Fields:', 'memberpress'); ?></strong>
            <span id="address-tax-info" class="mepr-hidden"><?php _e('(Required when taxes are enabled)', 'memberpress'); ?></span>
          </div>
          <div class="mp-col-2">
            <label>
              <input type="checkbox" name="<?php echo $mepr_options->show_address_fields_str; ?>" id="<?php echo $mepr_options->show_address_fields_str; ?>" <?php checked($mepr_options->show_address_fields); ?> />
              <span>&nbsp;<?php _e('Show', 'memberpress'); ?></span>
            </label>
          </div>
          <div class="mp-col-2">
            <label>
              <input type="checkbox" name="<?php echo $mepr_options->require_address_fields_str; ?>" id="<?php echo $mepr_options->require_address_fields_str; ?>" <?php checked($mepr_options->require_address_fields); ?> />
              <span>&nbsp;<?php _e('Require', 'memberpress'); ?></span>
            </label>
          </div>
        </div>
      </div>
      <h3>
        <?php _e('Custom User Information Fields:', 'memberpress'); ?>
        <?php MeprAppHelper::info_tooltip( 'mepr-custom-fields',
                                           __('Custom User Information Fields', 'memberpress'),
                                           __('You can specify custom fields to be used with your users\' account. Just click the \'plus\' button below to add your first field.', 'memberpress') ); ?>
      </h3>
      <ol id="custom_profile_fields" class="mepr-sortable">
        <?php MeprOptionsHelper::show_existing_custom_fields(); ?>
        <a href="" id="mepr-add-new-custom-field" title="<?php _e('Add new Custom Field', 'memberpress'); ?>"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
      </ol>
      <br/>
    </div>

    <div id="integration" class="mepr-options-hidden-pane">
      <h3><?php _e('Payment Methods', 'memberpress'); ?></h3>
      <div id="integrations-list">
        <?php
        $objs = $mepr_options->payment_methods();
        foreach( $objs as $pm_id => $obj ) {
          if( $obj instanceof MeprBaseRealGateway )
            MeprView::render("/admin/options/gateway", get_defined_vars());
        }
        ?>
      </div>
      <a href="" id="mepr-add-integration" title="<?php _e('Add a Payment Method', 'memberpress'); ?>"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
      <?php //MeprHooks::do_action('mepr-integration-options'); ?>
      <?php //MeprOptionsHelper::gateways_dropdown('gateway[' . time() . ']', ''); ?>
      <?php unset($objs['free']); unset($objs['manual']); ?>
      <div id="no_saved_pms" data-value="<?php echo (empty($objs))?'true':'false'; ?>"></div>
    </div>

    <div id="emails" class="mepr-options-hidden-pane">
      <h3><?php _e('Send Mail From', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <label for="<?php echo $mepr_options->mail_send_from_name_str; ?>"><?php _e('From Name:', 'memberpress'); ?></label>
        <input type="text" id="<?php echo $mepr_options->mail_send_from_name_str; ?>" name="<?php echo $mepr_options->mail_send_from_name_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->mail_send_from_name); ?>" />
        <br/>
        <label for="<?php echo $mepr_options->mail_send_from_email_str; ?>"><?php _e('From Email:', 'memberpress'); ?>&nbsp;</label>
        <input type="text" id="<?php echo $mepr_options->mail_send_from_email_str; ?>" name="<?php echo $mepr_options->mail_send_from_email_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->mail_send_from_email); ?>" />
      </div>
      <h3><?php _e('Member Notices', 'memberpress'); ?>
          <?php MeprAppHelper::info_tooltip( 'mepr-member-notices',
                                             __('Member Notices', 'memberpress'),
                                             __('These are notices that will be sent to your members when events happen in MemberPress.', 'memberpress') ); ?>
      </h3>
      <div class="mepr-options-pane">
        <?php MeprAppHelper::display_emails('MeprBaseOptionsUserEmail'); ?>
      </div>
      <h3><?php _e('Admin Emails &amp; Notices', 'memberpress'); ?>
          <?php MeprAppHelper::info_tooltip( 'mepr-member-notices',
                                             __('Admin Notices', 'memberpress'),
                                             __('These are notices that will be sent to the addresses you\'ve set below when events happen in MemberPress.', 'memberpress') ); ?>
      </h3>
      <div class="mepr-options-pane">
        <label for="<?php echo $mepr_options->admin_email_addresses_str; ?>"><?php _e('Admin Email Addresses:', 'memberpress'); ?>
          <?php MeprAppHelper::info_tooltip( 'mepr-admin-email-addresses',
                                             __('Notification Email Addresses', 'memberpress'),
                                             __('This is a comma separated list of email addresses that will recieve admin notifications. This defaults to your admin email set in "Settings" -> "General" -> "E-mail Address"', 'memberpress') ); ?>
        </label>
        <input type="text" id="<?php echo $mepr_options->admin_email_addresses_str; ?>" name="<?php echo $mepr_options->admin_email_addresses_str; ?>" class="regular-text" value="<?php echo stripslashes($mepr_options->admin_email_addresses); ?>" />
      </div>
      <div class="mepr-options-pane">
        <?php MeprAppHelper::display_emails('MeprBaseOptionsAdminEmail'); ?>
      </div>
    </div>

    <div id="marketing" class="mepr-options-hidden-pane">
      <h3><?php _e('Settings', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <input type="checkbox" name="<?php echo $mepr_options->disable_global_autoresponder_list_str; ?>" id="<?php echo $mepr_options->disable_global_autoresponder_list_str; ?>" <?php checked($mepr_options->disable_global_autoresponder_list); ?> />
        <label for="<?php echo $mepr_options->disable_global_autoresponder_list_str; ?>"><?php _e('Disable global auto-responder lists', 'memberpress'); ?></label>
        <?php MeprAppHelper::info_tooltip( 'mepr-disable-global-autoresponder-list',
                                           __('Disable global auto-responder lists', 'memberpress'),
                                           __("When this option is checked, and a membership has its own auto-responder list set under its Advanced tab. The global list you set on this page will be disabled on that membership page. The member will only be added to the membership's list, and not the global list in this case.", 'memberpress') ); ?>
        <br/><br/>
        <input type="checkbox" name="<?php echo $mepr_options->opt_in_checked_by_default_str; ?>" id="<?php echo $mepr_options->opt_in_checked_by_default_str; ?>" <?php checked($mepr_options->opt_in_checked_by_default); ?> />
        <label for="<?php echo $mepr_options->opt_in_checked_by_default_str; ?>"><?php _e("Opt-in box checked by default", 'memberpress'); ?></label>
        <?php MeprAppHelper::info_tooltip( 'mepr-opt-in-checked-by-default',
                                           __('Opt-in checkbox initial value', 'memberpress'),
                                           __("Use this option to set whether the opt-in checkbox shown at signup should be checked or unchecked by default. When enabled, the checkbox will be checked by default.", 'memberpress') ); ?>
      </div>
      <h3><?php _e('Enable/Disable Integrations', 'memberpress'); ?></h3>
      <div class="mepr-options-pane">
        <?php MeprHooks::do_action('mepr_display_autoresponders'); ?>
      </div>
    </div>

    <div id="info" class="mepr-options-hidden-pane">
      <h3>
        <?php _e('Merchant Business Address', 'memberpress'); ?>
        <?php MeprAppHelper::info_tooltip( 'mepr-business-address',
          __('Merchant Business Address', 'memberpress'),
          __('Enter your business\'s name & address. Used to figure out tax rates and to include in email receipts.', 'memberpress') ); ?>
      </h3>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('biz_name'); ?>"><?php _e('Business Name*', 'memberpress'); ?></label>
            </th>
            <td>
              <input type="text" class="regular-text" name="<?php echo $mepr_options->attr_slug('biz_name'); ?>" value="<?php echo $mepr_options->attr('biz_name'); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('biz_address1'); ?>"><?php _e('Address Line 1*', 'memberpress'); ?></label>
            </th>
            <td>
              <input type="text" class="regular-text" name="<?php echo $mepr_options->attr_slug('biz_address1'); ?>" value="<?php echo $mepr_options->attr('biz_address1'); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('biz_address2'); ?>"><?php _e('Address Line 2', 'memberpress'); ?></label>
            </th>
            <td>
              <input type="text" class="regular-text" name="<?php echo $mepr_options->attr_slug('biz_address2'); ?>" value="<?php echo $mepr_options->attr('biz_address2'); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('biz_city'); ?>"><?php _e('City*', 'memberpress'); ?></label>
            </th>
            <td>
              <input type="text" class="regular-text" name="<?php echo $mepr_options->attr_slug('biz_city'); ?>" value="<?php echo $mepr_options->attr('biz_city'); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('biz_country'); ?>"><?php _e('Country*', 'memberpress'); ?></label>
            </th>
            <td>
              <?php echo MeprAppHelper::countries_dropdown($mepr_options->attr_slug('biz_country'), $mepr_options->attr('biz_country'), 'regular-text', '', false); ?>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('biz_state'); ?>"><?php _e('State*', 'memberpress'); ?></label>
            </th>
            <td>
              <input type="text" class="regular-text" name="<?php echo $mepr_options->attr_slug('biz_state'); ?>" value="<?php echo $mepr_options->attr('biz_state'); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->attr_slug('biz_postcode'); ?>"><?php _e('Postcode*', 'memberpress'); ?></label>
            </th>
            <td>
              <input type="text" class="regular-text" name="<?php echo $mepr_options->attr_slug('biz_postcode'); ?>" value="<?php echo $mepr_options->attr('biz_postcode'); ?>" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div id="general" class="mepr-options-hidden-pane">
      <h3><?php _e('Internationalization', 'memberpress'); ?></h3>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->language_code_str; ?>"><?php _e('Language Code:', 'memberpress'); ?></label>
            </th>
            <td>
              <?php MeprOptionsHelper::payment_language_code_dropdown($mepr_options->language_code_str, $mepr_options->language_code); ?>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->currency_code_str; ?>"><?php _e('Currency Code:', 'memberpress'); ?></label>
            </th>
            <td>
              <?php MeprOptionsHelper::payment_currency_code_dropdown($mepr_options->currency_code_str, $mepr_options->currency_code); ?>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->currency_symbol_str; ?>"><?php _e('Currency Symbol:', 'memberpress'); ?></label>
            </th>
            <td>
              <?php MeprOptionsHelper::payment_currencies_dropdown($mepr_options->currency_symbol_str, $mepr_options->currency_symbol); ?>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->currency_symbol_after_str; ?>"><?php _e('Symbol After Amount', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip( 'mepr-currency-symbol-after',
                                                 __('Display Currency Symbol After Amount', 'memberpress'),
                                                 __('Will display the currency symbol after the price of the item. For example: 5.00$ instead of $5.00. Unless you know your countries currency symbol should be listed after, leave this unchecked.', 'memberpress') . '</strong>' ); ?>
            </th>
            <td>
              <input type="checkbox" name="<?php echo $mepr_options->currency_symbol_after_str; ?>" id="<?php echo $mepr_options->currency_symbol_after_str; ?>" <?php checked($mepr_options->currency_symbol_after); ?> />
            </td>
          </tr>
        </tbody>
      </table>

      <br/>
      <h3><?php _e('Theme Compatibility', 'memberpress'); ?></h3>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->global_styles_str; ?>"><?php _e('Global CSS Styles', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip( 'mepr-global-styles',
                                                 __('Load all of MemberPress\'s CSS Styles on Each Page', 'memberpress'),
                                                 __('When this option is enabled, all of MemberPress\'s CSS Styles will be loaded on every front end page of your website.', 'memberpress') . '<br/><br/><strong>' . __('Note: This option should only be enabled if MemberPress compatibility with your theme requires its usage.', 'memberpress') . '</strong>' ); ?>
            </th>
            <td>
              <input type="checkbox" name="<?php echo $mepr_options->global_styles_str; ?>" id="<?php echo $mepr_options->global_styles_str; ?>" <?php checked($mepr_options->global_styles); ?> />
            </td>
          </tr>
        </tbody>
      </table>

      <br/>
      <h3><?php _e('SEO & PayWall', 'memberpress'); ?></h3>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->authorize_seo_views_str; ?>"><?php _e('Authorize Search Engines', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip( 'mepr-authorize-seo-views',
                                                 __('Treat popular search engines as authorized members?', 'memberpress'),
                                                 __("When this option is enabled -- Google, Yahoo, Ask, and Bing/MSN are allowed to view protected content on your site as if they were authorized members. This can help search engines index your content.", 'memberpress') . "<br/><br/>" . __("Note: This does not work with caching enabled on your site. So avoid enabling this when using WP Super Cache, W3TC, WP Engine hosting, GoDaddy's Managed WordPress -- or any other plugin/webhost that caches your pages.", 'memberpress') ); ?>
            </th>
            <td>
              <input type="checkbox" name="<?php echo $mepr_options->authorize_seo_views_str; ?>" id="<?php echo $mepr_options->authorize_seo_views_str; ?>" <?php checked($mepr_options->authorize_seo_views); ?> />
            </td>
          </tr>
        </tbody>
      </table>

      <div id="mepr-seo-noindex-area" class="mepr-hidden">
        <table class="form-table">
          <tbody>
            <tr valign="top">
              <th scope="row">
                <label for="<?php echo $mepr_options->seo_unauthorized_noindex_str; ?>"><?php _e('Block Search Engines', 'memberpress'); ?></label>
                <?php MeprAppHelper::info_tooltip( 'mepr-seo-unauthorized-noindex',
                                                   __('Ask search engines to not index protected content?', 'memberpress'),
                                                   __("Automatically ask search engines not to index protected content on your site. If the 'Treat popular search engines as authorized members?' option is enabled, this setting has no effect.", 'memberpress') ); ?>
              </th>
              <td>
                <input type="checkbox" name="<?php echo $mepr_options->seo_unauthorized_noindex_str; ?>" id="<?php echo $mepr_options->seo_unauthorized_noindex_str; ?>" <?php checked($mepr_options->seo_unauthorized_noindex); ?> />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->paywall_enabled_str; ?>"><?php _e('Enable PayWall', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip( 'mepr-paywall-enabled',
                                                 __('Enable PayWall', 'memberpress'),
                                                 __("When enabled, you can specify how many free page views an unauthorized guest will have before being shown the unauthorized messages. We strongly encourage you to leave this option disabled during your initial setup and testing of MemberPress.", 'memberpress') ); ?>
            </th>
            <td>
              <input type="checkbox" name="<?php echo $mepr_options->paywall_enabled_str; ?>" id="<?php echo $mepr_options->paywall_enabled_str; ?>" <?php checked($mepr_options->paywall_enabled); ?> />
            </td>
          </tr>
        </tbody>
      </table>

      <div id="mepr-paywall-options-area" class="mepr-sub-box-white mepr-hidden">
        <div class="mepr-arrow mepr-white mepr-up mepr-sub-box-arrow"> </div>
        <table class="form-table">
          <tbody>
            <tr valign="top">
              <th scope="row">
                <label for="<?php echo $mepr_options->paywall_num_free_views_str; ?>"><?php _e('# Free Views', 'memberpress'); ?></label>
                <?php MeprAppHelper::info_tooltip( 'mepr-num-unauthorized-views',
                                                   __('Number of Free Views per Guest', 'memberpress'),
                                                   __('The number of free views unauthorized users can have before the paywall kicks in to prevent access to protected content.', 'memberpress') ); ?>
              </th>
              <td>
                <input type="text" size="2" name="<?php echo $mepr_options->paywall_num_free_views_str; ?>" id="<?php echo $mepr_options->paywall_num_free_views_str; ?>" value="<?php echo (int)$mepr_options->paywall_num_free_views; ?>" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <br/>
      <h3><?php _e('Rewrite Rules', 'memberpress'); ?></h3>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $mepr_options->disable_mod_rewrite_str; ?>"><?php _e('Disable Rewrite Rules', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip( 'mepr-disable-mod-rewrite',
                                                 __('Disable mod_rewrite (.htaccess) Rules', 'memberpress'),
                                                 __("If you are having problems getting other 3rd party applications such as phpBB or phpList to work along side MemberPress, you may need to check this option. Disabling mod_rewrite will mean that individual files cannot be protected with the Custom URI Rules.", 'memberpress') ); ?>
            </th>
            <td>
              <input type="checkbox" name="<?php echo $mepr_options->disable_mod_rewrite_str; ?>" id="<?php echo $mepr_options->disable_mod_rewrite_str; ?>" <?php checked($mepr_options->disable_mod_rewrite); ?> />
            </td>
          </tr>
        </tbody>
      </table>

      <?php MeprHooks::do_action('mepr_display_general_options'); ?>
    </div>

    <?php MeprHooks::do_action('mepr_display_options'); ?>

    <p class="submit">
      <input type="submit" class="button button-primary" name="Submit" value="<?php _e('Update Options', 'memberpress') ?>" />
    </p>

  </form>
</div>
