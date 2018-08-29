<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mepr-page-title"><?php _e('MemberPress Add-ons', 'memberpress'); ?></div>

<?php $addons = MeprUpdateCtrl::addons(true); ?>

<?php if(empty($addons)): ?>
  <h3><?php _e('There were no Add-ons found for your license or lack thereof...', 'memberpress'); ?></h3>
<?php else: ?>
  <table class="widefat">

    <thead>
      <tr>
        <th><?php _e('Add-on', 'memberpress'); ?></th>
        <th><?php _e('Description', 'memberpress'); ?></th>
        <th><?php _e('Install', 'memberpress'); ?></th>
      </tr>
    </thead>

    <tbody>
      <?php $alternate = true; ?>
      <?php foreach($addons as $slug => $info):
        $info = (object)$info;

        $update_available = false;
        if(($installed = (isset($info->extra_info->directory) && is_dir(WP_PLUGIN_DIR . '/' . $info->extra_info->directory)))) {
          $update_available = MeprAddonsHelper::is_update_available($info->extra_info->main_file, $info->version);
        }

        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $active = (isset($info->extra_info->main_file) && is_plugin_active($info->extra_info->main_file));

        ?>
        <tr class="<?php echo $alternate ? 'alternate' : ''; ?>">
          <td>
            <strong>
            <?php

              echo isset($info->extra_info->list_title) ? $info->extra_info->list_title : $info->product_name;

              if($update_available) {
                echo ' ' . __('(Update Available)', 'memberpress');
              }
            ?>
            </strong>
          </td>
          <td><?php echo $info->extra_info->description; ?></td>
          <td>
            <?php if($installed && $active): ?>
              <a class="button" href="" style="pointer-events: none;" disabled><?php _e('Installed & Active', 'memberpress'); ?></a>
            <?php elseif($installed && !$active): ?>
              <a class="button" href="" style="pointer-events: none;" disabled><?php _e('Installed & Inactive', 'memberpress'); ?></a>
            <?php else: ?>
              <a class="button button-primary" href="<?php echo MeprAddonsHelper::install_url($slug); ?>"><?php _e('Install', 'memberpress'); ?></a>
            <?php endif; ?>
          </td>
        </tr>

        <?php $alternate = !$alternate; ?>
      <?php endforeach; ?>
    </tbody>

  </table>
<?php endif; ?>

