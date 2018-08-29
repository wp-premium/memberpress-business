<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php if(isset($errors) && $errors != null && count($errors) > 0): ?>
<div class="mp_wrapper">
  <div class="mepr_error" id="mepr_jump">
    <ul>
      <?php foreach($errors as $error): ?>
        <li><strong><?php _ex('ERROR', 'ui', 'memberpress'); ?></strong>: <?php print $error; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<?php if( isset($message) and !empty($message) ): ?>
<div class="mp_wrapper">
  <div class="mepr_updated"><?php echo $message; ?></div>
</div>
<?php endif; ?>
