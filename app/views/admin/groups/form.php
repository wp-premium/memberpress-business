<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div id="mepr-groups-form">
  <input type="checkbox" id="<?php echo MeprGroup::$is_upgrade_path_str; ?>" name="<?php echo MeprGroup::$is_upgrade_path_str; ?>" <?php checked($group->is_upgrade_path); ?> />
  <label for="<?php echo MeprGroup::$is_upgrade_path_str; ?>"><?php _e('This group is a membership upgrade path', 'memberpress'); ?></label>
  <?php
    MeprAppHelper::info_tooltip('mepr-group-page-upgrade-path',
                                __('Upgrade Path', 'memberpress'),
                                __('Enabling this will mean that users can only be subscribed to one of these memberships at a time. If un-checked, users may be subscribed to each of these memberships simultaneously.', 'memberpress'));
  ?>

  <h4><strong><?php _e('Memberships:', 'memberpress'); ?></strong>
  <?php
    MeprAppHelper::info_tooltip('mepr-group-products-list',
                              __('Memberships', 'memberpress'),
                              __('Here you can add/remove memberships from this group pricing page.', 'memberpress'));
  ?>
  </h4>
  <ol id="sortable-products" class="mepr-sortable">
    <?php MeprGroupsHelper::get_existing_products_list($group); ?>
  </ol>
  <a href="" id="add-new-product" title="<?php _e('Add Membership', 'memberpress'); ?>"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
  <div id="hidden-line-item">
    <li class="product-item">
      <?php MeprGroupsHelper::get_products_dropdown(); ?>
      <span class="remove-span">
        <a href="" class="remove-product-item" title="Remove Membership"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
      </span>
    </li>
  </div>

  <br/><br/>
  <input type="checkbox" id="<?php echo MeprGroup::$pricing_page_disabled_str; ?>" name="<?php echo MeprGroup::$pricing_page_disabled_str; ?>" <?php checked($group->pricing_page_disabled); ?> />
  <label for="<?php echo MeprGroup::$pricing_page_disabled_str; ?>"><?php _e('Disable Pricing Page', 'memberpress'); ?></label>
  <?php
    MeprAppHelper::info_tooltip('mepr-group-disable-pricing-page',
                                __('Disable Pricing Page', 'memberpress'),
                                __('This will disable the pricing page from being accessed on the front end of your site. It will return a 404 (not found) page if a user attempts to access it.<br/><br/>You can optionally provide an alterante URL to take the member to if they try to visit this page.', 'memberpress'));
  ?>
  <br/>
  <div id="mepr_hidden_pricing_page_theme">
    <label for="<?php echo MeprGroup::$group_theme_str; ?>">
      <?php _e('Pricing Page Theme:', 'memberpress') . ' ' . $group->group_theme; ?>
    </label>
    <?php echo MeprGroupsHelper::theme_dropdown($group->group_theme); ?>
    <br/><br/>

    <label><?php _e('Custom Button CSS classes (optional):', 'memberpress'); ?></label><br/>
    <input type="text" class="mepr-group-button-css" name="<?php echo MeprGroup::$page_button_class_str; ?>" id="<?php echo MeprGroup::$page_button_class_str; ?>" value="<?php echo $group->page_button_class; ?>" />
    <br/><br/>

    <label><?php _e('Custom Highlighted Button CSS classes (optional):', 'memberpress'); ?></label><br/>
    <input type="text" class="mepr-group-button-css" name="<?php echo MeprGroup::$page_button_highlighted_class_str; ?>" id="<?php echo MeprGroup::$page_button_highlighted_class_str; ?>" value="<?php echo $group->page_button_highlighted_class; ?>" />
    <br/><br/>

    <label><?php _e('Custom Disabled Button CSS classes (optional):', 'memberpress'); ?></label><br/>
    <input type="text" class="mepr-group-button-css" name="<?php echo MeprGroup::$page_button_disabled_class_str; ?>" id="<?php echo MeprGroup::$page_button_disabled_class_str; ?>" value="<?php echo $group->page_button_disabled_class; ?>" />
  </div>
  <div id="mepr_hidden_alternate_group_url">
    <label><?php _e('Alternate Group URL:', 'memberpress'); ?></label><br/>
    <input type="text" class="mepr-group-button-css" name="<?php echo MeprGroup::$alternate_group_url_str; ?>" id="<?php echo MeprGroup::$alternate_group_url_str; ?>" value="<?php echo $group->alternate_group_url; ?>" />
  </div>
  <br/>
  <div id="mepr-manually-place-group-boxes">
    <a href="#" data-target="#mepr-group-price-box-shortcodes" class="mepr-slide-toggle"><?php _e('Group Price Boxes Shortcodes', 'memberpress'); ?></a>
    <?php
      MeprAppHelper::info_tooltip('mepr-group-manual-place-pricing-boxes',
                                  __('Manually place group price boxes', 'memberpress'),
                                  __('By default MemberPress will append the pricing boxes to the end of the Group page.<br/><br/>If you\'d like to show them in a different place on the group page or some other page on this site just copy and paste a shortcode where you\'d like the price boxes for this group to appear.', 'memberpress'));
    ?>
    <div id="mepr-group-price-box-shortcodes" class="mepr-radius-border">
      <p class="description"><?php _e('Shortcode to be used on this group page.', 'memberpress'); ?></p>
      [mepr-group-price-boxes]<br/><br/>
      <p class="description"><?php _e('Shortcode which can be used on any other WordPress page, post or custom post type.', 'memberpress'); ?></p>
      [mepr-group-price-boxes group_id="<?php echo $group->ID; ?>"]
    </div>
  </div>

  <!-- The NONCE below prevents post meta from being blanked on move to trash -->
  <input type="hidden" name="<?php echo MeprGroup::$nonce_str; ?>" value="<?php echo wp_create_nonce(MeprGroup::$nonce_str.wp_salt()); ?>" />
</div>
