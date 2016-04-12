<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php /*
<div class="wrap">
*/ ?>

  <h1><?php _e('Automatic Updates & Add-ons', 'memberpress'); ?></h1>
  <?php MeprView::render('/admin/errors', get_defined_vars()); ?>

  <div class="mepr_spacer"></div>
  <table class="mepr-settings-table">
    <tr class="mepr-mobile-nav">
      <td colspan="2">
        <a href="" class="mepr-toggle-nav"><i class="mp-icon-menu"> </i></a>
      </td>
    </tr>
    <tr>
      <td class="mepr-settings-table-nav">
        <ul class="mepr-sidebar-nav">
          <li><a data-id="license"><?php _e('License', 'memberpress'); ?></a></li>
          <li><a data-id="addons"><?php _e('Add-Ons', 'memberpress'); ?></a></li>
          <?php MeprHooks::do_action('mepr_updates_nav_items'); ?>
        </ul>
      </td>
      <td class="mepr-settings-table-pages">
        <div class="mepr-page" id="license">
          <?php MeprView::render('/admin/update/license', get_defined_vars()); ?>
        </div>
        <div class="mepr-page" id="addons">
          <?php MeprView::render('/admin/update/addons', get_defined_vars()); ?>
        </div>
        <?php MeprHooks::do_action('mepr_updates_pages'); ?>
      </td>
    </tr>
  </table>

<?php /*
</div>
*/ ?>

