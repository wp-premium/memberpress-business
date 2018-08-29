<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php if(isset($errors) && is_array($errors) && !empty($errors)): ?>
  <div class="error notice is-dismissible below-h2">
    <ul>
      <?php foreach($errors as $error): ?>
        <li><strong><?php _e('ERROR:', 'memberpress'); ?></strong> <?php print $error; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php if(isset($message) && !empty($message)): ?>
  <div id="message" class="updated notice notice-success is-dismissible below-h2">
    <p><?php echo $message; ?></p>
  </div>
<?php endif; ?>
