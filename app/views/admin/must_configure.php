<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="error" style="padding: 10px;"><?php printf(__('<b>MemberPress hasn\'t been configured yet.</b> Go to the MemberPress %1$soptions page%2$s to get it setup.', 'memberpress'), '<a href="'.admin_url('admin.php?page=memberpress-options').'">','</a>'); ?></div>
