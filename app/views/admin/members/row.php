<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

if(!empty($records)) {
  $row_index = 0;
  foreach($records as $rec) {
    $alternate = ( $row_index++ % 2 ? '' : 'alternate' );

    //Open the line
    ?>
    <tr id="record_<?php echo $rec->ID; ?>" class="<?php echo $alternate; ?>">
    <?php
    foreach($columns as $column_name => $column_display_name) {
      //Style attributes for each col
      $class = "class=\"{$column_name} column-{$column_name}\"";
      $style = "";
      if(in_array($column_name, $hidden)) {
        $style = ' style="display:none;"';
      }
      $attributes = $class.$style;

      //$editlink = admin_url('user-edit.php?user_id='.(int)$rec->ID);
      //$deletelink = admin_url('user-edit.php?user_id='.(int)$rec->ID);
      $deletelink = wp_nonce_url( "users.php?action=delete&amp;user={$rec->ID}", 'bulk-users' );
      $editlink = esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), get_edit_user_link( $rec->ID ) ) );

      //Display the cell
      switch($column_name) {
        case 'col_id':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->ID; ?></td>
          <?php
          break;
        case 'col_photo':
          ?>
          <td <?php echo $attributes; ?>><?php echo get_avatar($rec->email, 32); ?></td>
          <?php
          break;
        case 'col_name':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->name; ?></td>
          <?php
          break;
        case 'col_username':
          ?>
          <td <?php echo $attributes; ?>>
            <span class="mepr-member-avatar"><?php echo get_avatar($rec->email, 32); ?></span>
            <span class="mepr-member-username-and-actions">
              <div class="mepr-member-username">
                <a href="<?php echo $editlink; ?>" title="<?php _e("View member's profile", 'memberpress'); ?>"><?php echo ((int)$rec->ID)?stripslashes($rec->username):__('Deleted', 'memberpress'); ?></a>
              </div>
              <div class="mepr-member-actions mepr-hidden">
                <a href="<?php echo $editlink; ?>" title="<?php _e("Edit member's profile", 'memberpress'); ?>"><?php _e('Edit', 'memberpress'); ?></a>
                |
                <a href="<?php echo $deletelink; ?>" title="<?php _e("Delete member", 'memberpress'); ?>"><?php _e('Delete', 'memberpress'); ?></a>
              </div>
            </span>
          </td>
          <?php
          break;
        case 'col_email':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->email; ?></td>
          <?php
          break;
        case 'col_status':
          $mepr_user = new MeprUser($rec->ID);
          if($mepr_user->is_active()) {
            $status = '<span class="mepr-active">' . __('Active', 'memberpress') . '</span>';
          }
          else if($mepr_user->has_expired()) {
            $status = '<span class="mepr-inactive">' . __('Inactive', 'memberpress') . '</span>';
          }
          else {
            $status = '<span>' . __('None', 'memberpress') . '</span>';
          }

          ?>
          <td <?php echo $attributes; ?>><?php echo $status; ?></td>
          <?php
          break;
        case 'col_txn_count':
          ?>
          <td <?php echo $attributes; ?>><a href="<?php echo admin_url('admin.php?page=memberpress-trans&member=' . urlencode($rec->username)); ?>"><?php echo $rec->txn_count; ?></a></td>
          <?php
          break;
        case 'col_expired_txn_count':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->expired_txn_count; ?></td>
          <?php
          break;
        case 'col_active_txn_count':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->active_txn_count; ?></td>
          <?php
          break;
        case 'col_sub_count':
          ?>
          <td <?php echo $attributes; ?>><a href="<?php echo admin_url('admin.php?page=memberpress-subscriptions&member=' . urlencode($rec->username)); ?>"><?php echo $rec->sub_count; ?></a></td>
          <?php
          break;
        case 'col_sub_info':
          $admin_sub_url = admin_url('admin.php?page=memberpress-subscriptions&member=' . urlencode($rec->username));
          $sub_counts = array(
            __('Enabled', 'memberpress') => 'active',
            __('Stopped', 'memberpress') => 'cancelled',
            __('Pending', 'memberpress') => 'pending',
            __('Paused', 'memberpress')  => 'suspended',
          );
          ?>
          <td <?php echo $attributes; ?>>
          <?php
          foreach($sub_counts as $label => $status) {
            $status_count = "{$status}_sub_count";
            if($rec->$status_count > 0) {
              ?>
                <div><a href="<?php echo $admin_sub_url . '&status=' . $status; ?>"><?php echo "{$rec->$status_count} {$label}"; ?></a></div>
              <?php
            }
          }
          ?>
          </td>
          <?php
          break;
        case 'col_txn_info':
          $admin_txn_url = admin_url('admin.php?page=memberpress-trans&member=' . urlencode($rec->username));
          $other_count = $rec->txn_count;
          ?>
          <td <?php echo $attributes; ?>>
          <?php
          if($rec->active_txn_count > 0) {
            $other_count = $other_count - $rec->active_txn_count;
            ?>
              <div><a href="<?php echo $admin_txn_url . '&status=complete'; ?>"><?php printf("%d %s", $rec->active_txn_count, __('Complete', 'memberpress')); ?></a></div>
            <?php
          }
          if($rec->expired_txn_count > 0) {
            $other_count = $other_count - $rec->expired_txn_count;
            ?>
              <div><a href="<?php echo $admin_txn_url; ?>"><?php printf("%d %s", $rec->expired_txn_count, __('Expired', 'memberpress')); ?></a></div>
            <?php
          }
          if($rec->trial_txn_count > 0) {
            ?>
              <div><?php printf("%d %s", $rec->trial_txn_count, __('Trial', 'memberpress')); ?></div>
            <?php
          }
          if($other_count > 0) {
            ?>
              <div><a href="<?php echo $admin_txn_url; ?>"><?php printf("%d %s", $other_count, __('Other', 'memberpress')); ?></a></div>
            <?php
          }
          ?>
          </td>
          <?php
          break;
        case 'col_info':
          ?>
          <td <?php echo $attributes; ?>>
            <div><a href="<?php echo admin_url('admin.php?page=memberpress-subscriptions&member=' . urlencode($rec->username)); ?>"><?php printf('%d Subscriptions', $rec->sub_count); ?></a></div>
            <div><a href="<?php echo admin_url('admin.php?page=memberpress-trans&member=' . urlencode($rec->username)); ?>"><?php printf('%d Transactions', $rec->txn_count); ?></a></div>
          </td>
          <?php
          break;
        case 'col_pending_sub_count':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->pending_sub_count; ?></td>
          <?php
          break;
        case 'col_active_sub_count':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->active_sub_count; ?></td>
          <?php
          break;
        case 'col_suspended_sub_count':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->suspended_sub_count; ?></td>
          <?php
          break;
        case 'col_cancelled_sub_count':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->cancelled_sub_count; ?></td>
          <?php
          break;
        case 'col_memberships':
          $titles = array();
          if(!empty($rec->memberships)) {
            $ids = explode(',',$rec->memberships);
            foreach($ids as $id) {
              $membership = new MeprProduct($id);
              $titles[] = $membership->post_title;
            }
          }
          ?>
          <td <?php echo $attributes; ?>><?php echo implode(', ', $titles); ?></td>
          <?php
          break;
        case 'col_total_spent':
          ?>
          <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_currency($rec->total_spent,true,false); ?></td>
          <?php
          break;
        case 'col_last_login_date':
          ?>
          <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_date($rec->last_login_date, __('Never', 'memberpress')); ?></td>
          <?php
          break;
        case 'col_login_count':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->login_count; ?></td>
          <?php
          break;
        case 'col_registered':
          ?>
          <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_date($rec->registered); ?></td>
          <?php
          break;
        default:
          MeprHooks::do_action('mepr_members_list_table_row', $attributes, $rec, $column_name, $column_display_name);
      }
    }
    ?>
    </tr>
    <?php
  } //End foreach
} //End if
