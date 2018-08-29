<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php
  $products       = $group->products();
  $group_theme    = preg_replace('~\.css$~', '', (is_null($theme) ? $group->group_theme : $theme));
  $group_template = $group->group_template();
?>
<div class="mepr-price-menu <?php echo $group_theme; ?> <?php echo $group_template; ?>">
  <div class="mepr-price-boxes mepr-<?php echo count($products); ?>-col">
  <?php
    if(!empty($products)) {
      foreach($products as $product) {
        MeprGroupsHelper::group_page_item($product, $group);
      }
    }
  ?>
  </div>
</div>
