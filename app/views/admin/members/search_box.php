<span class="filter-by">
  <label><?php _e('Filter by', 'memberpress'); ?></label>

  <select class="mepr_filter_field" id="membership">
    <option value="all" <?php selected($membership, false); ?>><?php _e('All Memberships', 'memberpress'); ?></option>
    <?php foreach($prds as $p): ?>
      <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $membership); ?>><?php echo $p->post_title; ?></option>
    <?php endforeach; ?>
  </select>

  <select class="mepr_filter_field" id="status">
    <option value="all" <?php selected($status, false); ?>><?php _e('All Members', 'memberpress'); ?></option>
    <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active Members', 'memberpress'); ?></option>
    <option value="expired" <?php selected($status, 'expired'); ?>><?php _e('Expired Members', 'memberpress'); ?></option>
    <option value="none" <?php selected($status, 'none'); ?>><?php _e('Inactive Members', 'memberpress'); ?></option>
  </select>

  <input type="submit" id="mepr_search_filter" class="button" value="<?php _e('Go', 'memberpress'); ?>" />

  <?php
    if(isset($_REQUEST['status']) || isset($_REQUEST['membership'])) {
      $uri = $_SERVER['REQUEST_URI'];
      $uri = preg_replace('/[\?&]status=[^&]*/','',$uri);
      $uri = preg_replace('/[\?&]membership=[^&]*/','',$uri);
      ?>
      <a href="<?php echo $uri; ?>">[x]</a>
      <?php
    }
  ?>
</span>

<?php /* _e('or', 'memberpress'); */ ?>
