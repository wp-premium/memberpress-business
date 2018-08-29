<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="wrap">
  <div class="icon32"></div>
  <h2><?php _e('New Transaction', 'memberpress'); ?></h2>

  <?php MeprView::render("/admin/errors", get_defined_vars()); ?>

  <div class="form-wrap">
    <form action="" method="post">
      <table class="form-table">
        <tbody>
          <?php MeprView::render("/admin/transactions/trans_form", get_defined_vars()); ?>
        </tbody>
      </table>
      <p class="submit">
        <input type="submit" id="submit" class="button button-primary" value="<?php _e('Create', 'memberpress'); ?>" />
      </p>
    </form>
  </div>
</div>
