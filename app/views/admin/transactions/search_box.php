<span class="filter-by">
  <label><?php _e('Filter by', 'memberpress'); ?></label>

  <select class="mepr_filter_field" id="membership">
    <option value="all" <?php selected($membership, false); ?>><?php _e('All Memberships', 'memberpress'); ?></option>
    <?php foreach($prds as $p): ?>
      <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $membership); ?>><?php echo $p->post_title; ?></option>
    <?php endforeach; ?>
  </select>

  <select class="mepr_filter_field" id="status">
    <option value="all" <?php selected($status, false); ?>><?php _e('All Statuses', 'memberpress'); ?></option>
    <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'memberpress'); ?></option>
    <option value="complete" <?php selected($status, 'complete'); ?>><?php _e('Complete', 'memberpress'); ?></option>
    <option value="refunded" <?php selected($status, 'refunded'); ?>><?php _e('Refunded', 'memberpress'); ?></option>
    <option value="failed" <?php selected($status, 'failed'); ?>><?php _e('Failed', 'memberpress'); ?></option>
  </select>

  <select class="mepr_filter_field" id="gateway">
    <option value="all" <?php selected($gateway, false); ?>><?php _e('All Gateways', 'memberpress'); ?></option>
    <?php foreach($gateways as $gid => $g): ?>
      <option value="<?php echo $gid; ?>" <?php selected($gid, $gateway); ?>><?php printf(__('%1$s (%2$s)', 'memberpress'), $g->label, $g->name); ?></option>
    <?php endforeach; ?>
  </select>

  <input type="submit" id="mepr_search_filter" class="button" value="<?php _e('Go', 'memberpress'); ?>" />

  <?php
    if(isset($_REQUEST['status']) || isset($_REQUEST['membership'])) {
      $uri = $_SERVER['REQUEST_URI'];
      $uri = preg_replace('/[\?&]status=[^&]*/','',$uri);
      $uri = preg_replace('/[\?&]membership=[^&]*/','',$uri);
      $uri = preg_replace('/[\?&]gateway=[^&]*/','',$uri);
      ?>
      <a href="<?php echo $uri; ?>">[x]</a>
      <?php
    }
  ?>
</span>

<?php /* _e('or', 'memberpress'); */ ?>

