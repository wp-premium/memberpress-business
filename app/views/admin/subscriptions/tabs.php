<h2 class="nav-tab-wrapper">
  <a class="nav-tab main-nav-tab <?php if(!$table->lifetime) { echo 'nav-tab-active'; } ?>" href="<?php echo admin_url('admin.php?page=memberpress-subscriptions'.$member.$search.$search_field.$perpage); ?>" id="mepr-subscriptions"><?php printf(__('Recurring (%d)','memberpress'), $table->periodic_count); ?></a>
  <a class="nav-tab main-nav-tab <?php if($table->lifetime) { echo 'nav-tab-active'; } ?>" href="<?php echo admin_url('admin.php?page=memberpress-lifetimes'.$member.$search.$search_field.$perpage); ?>" id="mepr-lifetime-subscriptions"><?php printf(__('Non-Recurring (%d)','memberpress'), $table->lifetime_count); ?></a>
</h2>
<div>&nbsp;</div>
