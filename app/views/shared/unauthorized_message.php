<div class="mp_wrapper">
<?php if(!empty($unauth->excerpt)): ?>
  <div class="mepr-unauthorized-excerpt">
    <?php echo $unauth->excerpt; ?>
  </div>
<?php endif; ?>
<?php if(!empty($unauth->message)): ?>
  <div class="mepr-unauthorized-message">
    <?php echo $unauth->message; ?>
  </div>
<?php endif; ?>
<?php if(!MeprUtils::is_user_logged_in()): ?>
  <div class="mepr-login-form-wrap">
  <?php if($show_login): ?>
    <?php echo $form; ?>
  <?php elseif(is_singular()): //Let's not show the annoying login link on non singular pages ?>
    <span class="mepr-login-link"><a href="<?php echo $mepr_options->login_page_url(); ?>"><?php echo MeprHooks::apply_filters('mepr-unauthorized-login-link-text',_x('Login', 'ui', 'memberpress')); ?></a></span>
  <?php endif; ?>
  </div>
<?php endif; ?>
</div>

