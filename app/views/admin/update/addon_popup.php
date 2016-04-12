<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mepr-admin-notice mepr-auto-open mfp-hide mepr-white-popup mepr-popup-leftalign">
  <h2 class="mepr_error" style="text-align: center !important; padding-bottom: 15px !important; padding-top: 15px !important"><i class="mp-icon-attention"> </i> <?php _e('ACTION REQUIRED', 'memberpress'); ?></h2>
  <p><?php printf(__('The %s features in your MemberPress options have been moved from MemberPress into a separate plugin.', 'memberpress'), $a->name); ?></p>
  <p><?php __('Why you ask? Well, it\'s to streamline and increase the performance of MemberPress for you.', 'memberpress'); ?></p>

  <?php if($installed): ?>
    <p><?php printf(__('And good for you, it looks like you\'ve already got the %1$s Add-on installed. Just click the "Activate %2$s Add-on" button below and you\'ll get all these features back now.', 'memberpress'), $a->name, $a->name); ?></p>
  <?php else: ?>
    <p><?php printf(__('Luckily it\'s easy to get these features back now. Just click the "Install %s Add-on" button below.', 'memberpress'), $a->name); ?></p>
    <p><?php printf(__('If you have problems with the auto-install please refer to %1$sthe user manual%2$s for manual install instructions.', 'memberpress'), '<a href="https://www.memberpress.com/marketing/" target="_blank">', '</a>'); ?></p>
  <?php endif; ?>
  <br/>
  <center>
    <div data-addon="<?php echo $k; ?>">
      <?php if($installed): ?>
        <button data-href="<?php echo MeprAddonsHelper::activate_url("memberpress-{$k}/main.php"); ?>" class="mepr-btn mepr-left-margin mepr-addon-activate"><?php printf(__('Activate %s Add-on', 'memberpress'), $a->name); ?></button>
      <?php else: ?>
        <button data-href="<?php echo MeprAddonsHelper::install_url('memberpress-'.$k); ?>" class="mepr-btn mepr-left-margin mepr-addon-install"><?php printf(__('Install %s Add-on', 'memberpress'), $a->name); ?></button>
      <?php endif; ?>
      <button class="mepr-btn mepr-left-margin mepr-addon-stop-notices"><?php _e('Don\'t Show This Message Again', 'memberpress'); ?></button>
    </div>
  </center>
  <br/>
</div>

