<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="product-options-panel">
  <div id="mepr-product-access-url">
    <label for="<?php echo MeprProduct::$access_url_str; ?>"><?php _e('Membership Access URL', 'memberpress'); ?></label>
    <?php MeprAppHelper::info_tooltip('mepr-product-access-url',
                                      __('Membership Access URL', 'memberpress'),
                                      __('This is the URL where those who purchase this membership can get the content they now have access to.', 'memberpress').'<br/><br/>'.__('When set, the Membership Access URL will make this membership name clickable when a member views their Subscriptions/Payments tabs on the Account page.', 'memberpress'));
    ?>
    &nbsp;&nbsp;
    <input type="text" name="<?php echo MeprProduct::$access_url_str; ?>" id="<?php echo MeprProduct::$access_url_str; ?>" value="<?php echo stripslashes($product->access_url); ?>" size="50" />
  </div>

  <div id="mepr-register-price" class="mepr-product-adv-item">
    <label for="<?php echo MeprProduct::$register_price_action_str; ?>">
      <span><?php _e('Membership Pricing Terms:', 'memberpress'); ?></span>
      <select name="<?php echo MeprProduct::$register_price_action_str; ?>" id="<?php echo MeprProduct::$register_price_action_str; ?>">
        <option value="default"<?php selected( $product->register_price_action, 'default' ); ?>><?php _e('Default', 'memberpress'); ?></option>
        <option value="custom"<?php selected( $product->register_price_action, 'custom' ); ?>><?php _e('Custom', 'memberpress'); ?></option>
        <option value="hidden"<?php selected( $product->register_price_action, 'hidden' ); ?>><?php _e('Hidden', 'memberpress'); ?></option>
      </select>
      <?php
        MeprAppHelper::info_tooltip( 'mepr-register-price',
                                     __('Membership Pricing Terms', 'memberpress'),
                                     __('By default MemberPress will automatically generate the pricing terms for you on the membership registration page. But in some cases you\'ll want to either present custom text for the terms ... or hide it alltogether.', 'memberpress') . '<br/><br/>' .
                                     __('This will allow you to customize the pricing terms on the <b>Registration, Payment, and Account Subscription\'s pages</b> for this membership. Here\'s a description of these options:', 'memberpress') . '<br/><br/>' .
                                     __('<b>Default:</b> This will enable the system-generated membership terms.', 'memberpress') . '<br/><br/>' .
                                     __('<b>Custom:</b> This will enable you to enter your own description for your membership terms.', 'memberpress') . '<br/><br/>' .
                                     __('<b>Hidden:</b> This will hide the membership terms.', 'memberpress') );
      ?>
    </label>

    <div class="product-options-panel mepr-hidden">
      <span><?php _e('Custom Registration Pricing Terms:', 'memberpress'); ?></span>
      <input type="text" name="<?php echo MeprProduct::$register_price_str; ?>" id="<?php echo MeprProduct::$register_price_str; ?>" value="<?php echo stripslashes($product->register_price); ?>" size="50" />
    </div>
  </div>

  <div id="mepr-custom-login-redirect-urls">
    <input type="checkbox" name="<?php echo MeprProduct::$custom_login_urls_enabled_str; ?>" id="<?php echo MeprProduct::$custom_login_urls_enabled_str; ?>" <?php checked($product->custom_login_urls_enabled); ?> />
    <label for="<?php echo MeprProduct::$custom_login_urls_enabled_str; ?>"><?php _e('Custom Login Redirect URLs', 'memberpress'); ?>
      <?php
        MeprAppHelper::info_tooltip('mepr-customize-login-redirections',
                                    __('Custom Login Redirect URLs', 'memberpress'),
                                    __('By default MemberPress will redirect members to the page setup in options. This will allow you to override that default behavior and customize it any way you want.', 'memberpress'));
      ?>
    </label>

    <div id="mepr-custom-login-urls-area">
      <label><?php _e('Default Login Redirect URL', 'memberpress'); ?></label>
      <input type="text" name="<?php echo MeprProduct::$custom_login_urls_default_str; ?>" value="<?php echo $product->custom_login_urls_default; ?>" size="50" />
      <?php
        MeprAppHelper::info_tooltip('mepr-custom-login-urls-default',
                                    __('Default Login Redirect URL', 'memberpress'),
                                    __('If the member logging in has an active Subscription to this membership and none of the custom URLs below apply, they will be redirected to this URL after logging in.', 'memberpress') . '<br/><br/>' . __('Note: This overrides the Login Redirect URL setting in the MemberPress Options. Leave this option blank to use the Login Redirect URL in the MemberPress Options instead.', 'memberpress'));
      ?>
      <div id="custom-login-urls-list-area">
        <label><?php _e('Add Custom Login Redirect URLs', 'memberpress'); ?>:</label>
        <?php
          MeprAppHelper::info_tooltip('mepr-add-custom-login-urls',
                                      __('Add Custom Login Redirect URLs', 'memberpress'),
                                      __('This allows you to redirect a user to different URLs based on how many times they have already logged in. So for example, if you wanted to show upsell pages to members on their 3rd and 6th logins, you can do that by setting the URL to send the member to when they login for the 3rd time and again on the 6th time.', 'memberpress'));
        ?>
        <ul id="custom-login-urls-list">
          <?php if(!empty($product->custom_login_urls)): ?>
            <?php foreach($product->custom_login_urls as $url): ?>
              <li>
                <span class="custom_login_urls_item">
                  <label><?php _e('URL', 'memberpress'); ?></label>
                  <input type="text" name="<?php echo MeprProduct::$custom_login_urls_str; ?>[]" value="<?php echo htmlentities(stripslashes($url->url), ENT_QUOTES); ?>" size="50" />
                  &nbsp;
                  <label><?php _e('Login #', 'memberpress'); ?></label>
                  <input type="text" name="_mepr_custom_login_urls_count[]" value="<?php echo $url->count; ?>" size="5" />

                  <span class="remove-span">
                    <a href="" class="custom_login_urls_remove_url" title="<?php _e('Remove URL', 'memberpress'); ?>"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
                  </span>
                </span>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
              <li>
                <span class="custom_login_urls_item">
                  <label><?php _e('URL', 'memberpress'); ?></label>
                  <input type="text" name="<?php echo MeprProduct::$custom_login_urls_str; ?>[]" value="" size="50" />
                  &nbsp;
                  <label><?php _e('Login #', 'memberpress'); ?></label>
                  <input type="text" name="_mepr_custom_login_urls_count[]" value="1" size="3" />

                  <span class="remove-span">
                    <a href="" class="custom_login_urls_remove_url" title="<?php _e('Remove URL', 'memberpress'); ?>"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
                  </span>
                </span>
              </li>
          <?php endif; ?>
        </ul>

        <span>
          <a href="" class="custom-login-urls-new-url" title="<?php _e('Add URL', 'memberpress'); ?>"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
        </span>
      </div>
    </div>
  </div>

  <?php MeprHooks::do_action('mepr-product-advanced-metabox', $product); ?>
</div>
