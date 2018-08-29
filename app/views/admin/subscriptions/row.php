<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

//Loop for each record
if(!empty($records))
{
  $row_index = 0;
  foreach($records as $rec)
  {
    $alternate = ( $row_index++ % 2 ? '' : 'alternate' );

    ?>
    <tr id="record_<?php echo $rec->id; ?>" class="<?php echo $alternate; ?>">
    <?php
    foreach($columns as $column_name => $column_display_name)
    {
      //Style attributes for each col
      $class = "class=\"{$column_name} column-{$column_name}\"";
      $style = "";
      if(in_array($column_name, $hidden))
        $style = ' style="display:none;"';
      $attributes = $class.$style;

      $editlink = admin_url('user-edit.php?user_id='.(int)$rec->user_id);

      //Display the cell
      switch($column_name)
      {
        case 'col_id':
        case 'col_txn_id':
          ?>
          <td <?php echo $attributes; ?>><?php echo ($rec->id); ?></td>
          <?php
          break;
        case 'col_created_at':
        case 'col_txn_created_at':
          ?>
          <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_date($rec->created_at); ?></td>
          <?php
          break;
        case 'col_subscr_id':
          $view_url = admin_url("admin.php?page=memberpress-trans&subscription={$rec->id}");
          $add_url = admin_url("admin.php?page=memberpress-trans&action=new&subscription={$rec->id}");
          ?>
          <td <?php echo $attributes; ?>><b><?php echo $rec->subscr_id; ?></b> <img src="<?php echo MEPR_IMAGES_URL . '/square-loader.gif'; ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" class="mepr_loader" />
            <div class="mepr-row-actions">
              <a href="<?php echo $add_url; ?>" title="<?php _e('Manually add a transaction to this subscription', 'memberpress'); ?>"><?php _e('Add Txn', 'memberpress'); ?></a> |
              <a href="<?php echo $view_url; ?>" title="<?php _e('View related transactions', 'memberpress'); ?>"><?php _e('View Txns', 'memberpress'); ?></a> |
              <?php
              $sub = new MeprSubscription($rec->id);
              if($sub->can('suspend-subscriptions')):
                if($sub->status==MeprSubscription::$active_str)
                  { $hide_suspend = ''; $hide_resume = ' mepr-hidden'; }
                else if($sub->status==MeprSubscription::$suspended_str)
                  { $hide_suspend = ' mepr-hidden'; $hide_resume = ''; }
                else { $hide_suspend = $hide_resume = ' mepr-hidden'; }

                ?>
                <span class="mepr-suspend-sub-action<?php echo $hide_suspend; ?>">
                  <a href="" class="mepr-suspend-sub" title="<?php _e('Pause Subscription', 'memberpress'); ?>" data-value="<?php echo $rec->id; ?>"><?php _e('Pause', 'memberpress'); ?></a> |
                </span>
                <span class="mepr-resume-sub-action<?php echo $hide_resume; ?>">
                  <a href="" class="mepr-resume-sub" title="<?php _e('Resume Subscription', 'memberpress'); ?>" data-value="<?php echo $rec->id; ?>"><?php _e('Resume', 'memberpress'); ?></a> |
                </span>
                <?php
              endif;

              ?>
              <span class="mepr-edit-sub-action">
                <a href="<?php echo admin_url('admin.php?page=memberpress-subscriptions&action=edit&id='.$rec->id); ?>" title="<?php _e('Edit Subscription', 'memberpress'); ?>"><?php _e('Edit', 'memberpress'); ?></a> |
              </span>
              <?php
              if($sub->status==MeprSubscription::$active_str and $sub->can('cancel-subscriptions')):
              ?>
                <span class="mepr-cancel-sub-action">
                  <a href="" class="mepr-cancel-sub" title="<?php _e('Cancel Subscription', 'memberpress'); ?>" data-value="<?php echo $rec->id; ?>"><?php _e('Cancel', 'memberpress'); ?></a> |
                </span>
              <?php
              endif;
              ?>
              <a href="" class="remove-sub-row" title="<?php _e('Delete Subscription', 'memberpress'); ?>" data-value="<?php echo $rec->id; ?>"><?php _e('Delete', 'memberpress'); ?></a>
            </div>
          </td>
          <?php
          break;
        case 'col_txn_subscr_id':
          ?>
          <td <?php echo $attributes; ?>>
            <a href="<?php echo admin_url('admin.php?page=memberpress-trans&transaction=').$rec->id; ?>" title="<?php _e('Show related transaction', 'memberpress'); ?>"><b><?php echo $rec->subscr_id; ?></b></a>
          </td>
          <?php
          break;
        case 'col_txn_count':
        case 'col_txn_txn_count':
          $view_url = admin_url("admin.php?page=memberpress-trans&subscription={$rec->id}");
          ?>
          <td <?php echo $attributes; ?>>
            <a href="<?php echo $view_url; ?>" title="<?php _e('Show related transactions', 'memberpress'); ?>"><?php echo $rec->txn_count; ?></a>
          </td>
          <?php
          break;
        case 'col_member':
        case 'col_txn_member':
          ?>
          <td <?php echo $attributes; ?>>
            <?php if(!empty($rec->user_id)): ?>
              <a href="<?php echo $editlink; ?>" title="<?php _e("View member's profile", 'memberpress'); ?>"><?php echo stripslashes($rec->member); ?></a>
            <?php else: ?>
              <?php echo __('Deleted', 'memberpress'); ?>
            <?php endif; ?>
          </td>
          <?php
          break;
        case 'col_gateway':
        case 'col_txn_gateway':
          $pm = $mepr_options->payment_method($rec->gateway);
          if($pm) {
            $pm_str = "{$pm->label} ({$pm->name})";
          }
          else {
            $pm_str = ucwords($rec->gateway);
          }
          ?>
          <td <?php echo $attributes; ?>><?php echo $pm_str; ?></td>
          <?php
          break;
        case 'col_product':
        case 'col_txn_product':
          if($rec->product_id) {
            $prd = new MeprProduct($rec->product_id);
            $product_link = '<a href="'.$prd->edit_url().'">'.$rec->product_name.'</a>';
          }
          else {
            $product_link = __('Unknown', 'memberpress');
          }

          ?>
          <td <?php echo $attributes; ?>><?php echo $product_link; ?></td>
          <?php
          break;
        case 'col_product_meta':
        case 'col_txn_product_meta':
          ?>
          <td <?php echo $attributes; ?>>
          <?php
            if($table->lifetime) {
              $txn = new MeprTransaction($rec->id);
              echo MeprAppHelper::format_currency($txn->amount) . ($txn->prorated?__(' (prorated)','memberpress'):'');
            }
            elseif($rec->status == MeprSubscription::$pending_str) {
              $prd = new MeprProduct($rec->product_id);
              $sub = new MeprSubscription();
              $sub->load_product_vars($prd);
              $sub->coupon_id = $rec->coupon_id;
              echo MeprSubscriptionsHelper::format_currency($sub,true,false);
            }
            else {
              $sub = new MeprSubscription($rec->id);
              $txn = $sub->latest_txn();

              if(false != $txn && $txn instanceof MeprTransaction) {
                echo MeprTransactionsHelper::format_currency($txn);
              }
              else {
                echo MeprSubscriptionsHelper::format_currency($sub);
              }
            }
          ?>
          </td>
          <?php
          break;
        case 'col_active':
        case 'col_txn_active':
          ?>
          <td <?php echo $attributes; ?>><?php echo ($rec->active); ?></td>
          <?php
          break;
        case 'col_expires_at':
        case 'col_txn_expires_at':
          $expire_ts = strtotime($rec->expires_at);
          $lifetime = (MeprAppHelper::format_date($rec->expires_at, 0) == 0);

          $expired_class = '';
          if(!$lifetime and $expire_ts < current_time('timestamp')) {
            $expired_class = 'class="mepr-inactive"';
          }

          if($table->lifetime) {
            $default = __('Never','memberpress');
          }
          else {
            $sub = new MeprSubscription($rec->id);
            $txn = $sub->latest_txn();

            if($txn == false || !($txn instanceof MeprTransaction) || $txn->id <= 0) {
              $default = __('Unknown','memberpress');
            }
            else if(trim($txn->expires_at) == MeprUtils::db_lifetime() || empty($txn->expires_at)) {
              $default = __('Never','memberpress');
            }
            else {
              $default = __('Unknown','memberpress');
            }
          }

          ?>
          <td <?php echo $attributes; ?>><span <?php echo $expired_class; ?>><?php echo MeprAppHelper::format_date($rec->expires_at, $default); ?></span></td>
          <?php
          break;
        case 'col_status':
          ?>
          <td <?php echo $attributes; ?>>
            <a href="" id="status-row-<?php echo $rec->id; ?>" class="status_editable" data-value="<?php echo $rec->id; ?>"><?php echo MeprAppHelper::human_readable_status($rec->status,'subscription'); ?></a>
            <div id="status-hidden-<?php echo $rec->id; ?>" class="status_hidden">
              <?php
                MeprAppHelper::info_tooltip('mepr-subscriptions-status-'.$rec->id,
                                            __('Editing Subscription Status', 'memberpress'),
                                            __("Modifying the Auto Rebill status here will change the status of the Subscription ONLY on your site, not at the Gateway itself. To cancel a Subscription, either you or the member must click on Cancel.", 'memberpress'));
              ?>
              <select id="status-select-<?php echo $rec->id; ?>" class="status_select" data-value="<?php echo $rec->id; ?>">
                <option value="<?php echo MeprSubscription::$pending_str; ?>"><?php _e('Pending', 'memberpress'); ?></option>
                <option value="<?php echo MeprSubscription::$active_str; ?>"><?php _e('Enabled', 'memberpress'); ?></option>
                <option value="<?php echo MeprSubscription::$suspended_str; ?>"><?php _e('Paused', 'memberpress'); ?></option>
                <option value="<?php echo MeprSubscription::$cancelled_str; ?>"><?php _e('Cancelled', 'memberpress'); ?></option>
              </select><br/>
              <a href="" class="button status_save" data-value="<?php echo $rec->id; ?>"><?php _e('Save', 'memberpress'); ?></a>
              <a href="" class="button cancel_change" data-value="<?php echo $rec->id; ?>"><?php _e('Cancel', 'memberpress'); ?></a>
            </div>

            <div id="status-saving-<?php echo $rec->id; ?>" class="status_saving">
              <?php _e('Saving...', 'memberpress'); ?>
            </div>
          </td>
          <?php
          break;
        case 'col_txn_status':
          ?>
          <td <?php echo $attributes; ?>>
            <?php echo $rec->status; ?>
          </td>
          <?php
          break;
        case 'col_propername':
        case 'col_txn_propername':
          if(empty($rec->first_name) && empty($rec->last_name)) {
            $full_name = __('Unknown', 'memberpress');
          }
          else if(empty($rec->first_name) && !empty($rec->last_name)) {
            $full_name = stripslashes($rec->last_name);
          }
          else if(!empty($rec->first_name) && empty($rec->last_name)) {
            $full_name = stripslashes($rec->first_name);
          }
          else {
            $full_name = stripslashes($rec->last_name).', '.stripslashes($rec->first_name);
          }
          ?>
          <td <?php echo $attributes; ?>>
            <?php echo $full_name; ?>
          </td>
          <?php
          break;
        default:
          MeprHooks::do_action('mepr-admin-subscriptions-cell', $column_name, $rec, $table, $attributes);
          break;
      }
    }

    ?>
    </tr>
    <?php
  } //End foreach
} //End if
