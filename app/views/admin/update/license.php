<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mepr-page-title"><?php _e('MemberPress License', 'memberpress'); ?></div>

<?php if( !isset($li) or empty($li) ): ?>
  <p class="description"><?php printf(__('You must have a License Key to enable automatic updates for MemberPress. If you don\'t have a License please go to %1$s to get one. If you do have a license you can login at %2$s to manage your licenses and site activations.', 'memberpress'), '<a href="http://memberpress.com">MemberPress.com</a>', '<a href="http://memberpress.com/login">MemberPress.com/login</a>'); ?></p>
  <form name="activation_form" method="post" action="">
    <?php wp_nonce_field('activation_form'); ?>

    <table class="form-table">
      <tr class="form-field">
        <td valign="top" width="225px"><?php _e('Enter Your MemberPress License Key:', 'memberpress'); ?></td>
        <td>
          <input type="text" name="<?php echo $mepr_options->mothership_license_str; ?>" value="<?php echo (isset($_POST[$mepr_options->mothership_license_str])?$_POST[$mepr_options->mothership_license_str]:$mepr_options->mothership_license); ?>"/>
        </td>
      </tr>
    </table>
    <p class="submit">
      <input type="submit" name="Submit" class="button button-primary" value="<?php printf(__('Activate License Key on %s', 'memberpress'), MeprUtils::site_domain()); ?>" />
    </p>
  </form>
<?php else: ?>
  <div class="mepr-license-active">
    <div><h4><?php _e('Active License Key Information:', 'memberpress'); ?></h4></div>
    <table>
      <tr>
        <td><?php _e('License Key:', 'memberpress'); ?></td>
        <td>********-****-****-****-<?php echo substr($li['license_key']['license'], -12); ?></td>
      </tr>
      <tr>
        <td><?php _e('Status:', 'memberpress'); ?></td>
        <td><?php printf(__('<b>Active on %s</b>', 'memberpress'), MeprUtils::site_domain()); ?></td>
      </tr>
      <tr>
        <td><?php _e('Product:', 'memberpress'); ?></td>
        <td><?php echo $li['product_name']; ?></td>
      </tr>
      <tr>
        <td><?php _e('Activations:', 'memberpress'); ?></td>
        <td><?php printf('<b>%1$d of %2$s</b> sites have been activated with this license key', $li['activation_count'], ucwords($li['max_activations'])); ?></td>
      </tr>
    </table>
    <div class="mepr-deactivate-button"><a href="<?php echo admin_url('admin.php?page=memberpress-updates&action=deactivate&_wpnonce='.wp_create_nonce('memberpress_deactivate')); ?>" class="button button-primary" onclick="return confirm('<?php printf(__("Are you sure? MemberPress will not be functional on %s if this License Key is deactivated.", 'memberpress'), MeprUtils::site_domain()); ?>');"><?php printf(__('Deactivate License Key on %s', 'memberpress'), MeprUtils::site_domain()); ?></a></div>
  </div>
  <?php MeprView::render('/admin/update/edge_updates', get_defined_vars()); ?>
  <br/>
  <div id="mepr-version-string"><?php printf(__("You're currently running version %s of MemberPress", 'memberpress'), '<b>'.MEPR_VERSION.'</b>'); ?></div>
  <br/>
  <?php
  /*
  <div id="mepr-rollback-version" class="button"><a href="<?php echo MeprUpdateCtrl::rollback_url(); ?>" onclick="confirm('<?php _e('Are you sure you want to rollback MemberPress?', 'memberpress'); ?>');"><?php _e('Rollback to Previous Version', 'memberpress'); ?></a></div>
  */
  ?>
<?php endif; ?>
<?php MeprHooks::do_action('mepr_activate_license_page'); ?>
