<a id="mepr_post_rules"></a>
<table class="mepr-rules-table">
  <tr>
    <td class="mepr-rules-col">
      <h4><?php _e('Access Rules Protected by:', 'memberpress'); ?></h4>
      <p class="description"><?php _e('These rules protect the current content.', 'memberpress'); ?></p>
      <table>
        <?php
        foreach( $rules as $rule ):
        ?>
          <tr>
            <td><strong><?php echo $rule->post_title; ?></strong></td>
            <td><a href="<?php echo admin_url("post.php?post={$rule->ID}&action=edit"); ?>" class="button"><?php _e("Edit", 'memberpress'); ?></a></td>
          </tr>
        <?php
        endforeach;
        ?>
      </table>
    </td>
    <td class="mepr-products-col">
      <h4><?php _e('Memberships with Access:', 'memberpress'); ?></h4>
      <p class="description"><?php _e('Members who are active on any of these memberships will be able to access this content. This list is calculated from the Rules that protect this content.', 'memberpress'); ?></p>
      <table>
        <?php
        foreach( $product_ids as $product_id ):
          $product = new MeprProduct($product_id);
          ?>
            <tr>
              <td><strong><?php echo $product->post_title; ?></strong></td>
              <td>&nbsp;&nbsp;<a href="<?php echo admin_url("post.php?post={$product->ID}&action=edit"); ?>" class="button"><?php _e("Edit", 'memberpress'); ?></a></td>
            </tr>
          <?php
        endforeach;
        ?>
      </table>
    </td>
    <td class="mepr-products-col">
      <h4><?php _e('Members with Access:', 'memberpress'); ?></h4>
      <p class="description"><?php _e('Members below will be able to access this content. This list is calculated from the Rules that protect this content.', 'memberpress'); ?></p>
      <table>
        <?php
        foreach( $members as $member ):
          ?>
            <tr>
              <td><strong><?php echo $member; ?></strong></td>
              <td><a href="<?php echo admin_url("post.php?post={$rule->ID}&action=edit"); ?>" class="button"><?php _e("Edit", 'memberpress'); ?></a></td>
            </tr>
          <?php
        endforeach;
        ?>
      </table>
    </td>
  </tr>
</table>
