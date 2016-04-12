<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

if(!empty($records))
{
  $row_index = 0;
  foreach($records as $rec)
  {
    $alternate = ( $row_index++ % 2 ? '' : 'alternate' );

    //Open the line
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
      switch($column_name) {
        case 'col_id':
          ?>
          <td <?php echo $attributes; ?>><?php echo $rec->id; ?></td>
          <?php
          break;
        case 'col_created_at':
          ?>
          <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_date($rec->created_at); ?></td>
          <?php
          break;
        case 'col_expires_at':
          ?>
          <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_date($rec->expires_at, __('Never','memberpress')); ?></td>
          <?php
          break;
        case 'col_user_login':
          ?>
          <td <?php echo $attributes; ?>><a href="<?php echo $editlink; ?>" title="<?php _e("View member's profile", 'memberpress'); ?>"><?php echo ((int)$rec->user_id)?stripslashes($rec->user_login):__('Deleted', 'memberpress'); ?></a></td>
          <?php
          break;
        case 'col_product':
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
        case 'col_payment_system':
          ?>
          <td <?php echo $attributes; ?>><?php echo stripslashes($rec->gateway); ?></td>
          <?php
          break;
        case 'col_trans_num':
          ?>
          <td <?php echo $attributes; ?>>
            <a href="<?php echo admin_url('admin.php?page=memberpress-trans&action=edit&id='.$rec->id); ?>" title="<?php _e('Edit transaction', 'memberpress'); ?>"><b><?php echo $rec->trans_num; ?></b></a> <img src="<?php echo MEPR_IMAGES_URL . '/square-loader.gif'; ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" class="mepr_loader" />
            <div class="mepr-row-actions">
              <a href="<?php echo admin_url('admin.php?page=memberpress-trans&action=edit&id='.$rec->id); ?>" title="<?php _e('Edit transaction', 'memberpress'); ?>"><?php _e('Edit','memberpress'); ?></a> |
              <a href="#" class="mepr_resend_txn_email" data-value="<?php echo $rec->id; ?>"><?php _e('Send Receipt', 'memberpress'); ?></a> |
              <?php
              $txn = new MeprTransaction($rec->id);
              if($txn->can('process-refunds')):
              ?>
                <span class="mepr-refund-txn-action">
                  <a href="#" class="mepr-refund-txn" title="<?php _e('Refund Transaction', 'memberpress'); ?>" data-value="<?php echo $rec->id; ?>"><?php _e('Refund', 'memberpress'); ?></a> |
                </span>
              <?php
              endif;
              ?>
                <a href="#" class="remove-txn-row" title="<?php _e('Delete Transaction', 'memberpress'); ?>" data-value="<?php echo $rec->id; ?>"><?php _e('Delete', 'memberpress'); ?></a>
            </div>
          </td>
          <?php
          break;
        case 'col_subscr_id':
          if(!empty($rec->sub_id)):
            ?>
            <td <?php echo $attributes; ?>><a href="<?php echo admin_url('admin.php?page=memberpress-subscriptions&subscription='.$rec->sub_id); ?>" title="<?php _e('View Subscription', 'memberpress'); ?>"><?php echo $rec->subscr_id; ?></a></td>
            <?php
          else:
            ?>
            <td <?php echo $attributes; ?>><a href="<?php echo admin_url('admin.php?page=memberpress-subscriptions&lifetime=1&subscription='.$rec->id); ?>" title="<?php _e('View Subscription', 'memberpress'); ?>"><?php _e('None','memberpress'); ?></a></td>
            <?php
          endif;
          break;
        case 'col_net':
          ?>
          <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_currency($rec->amount, true, false); ?></td>
          <?php
          break;
        case 'col_tax':
          ?>
          <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_currency($rec->tax_amount, true, false); ?></td>
          <?php
          break;
        case 'col_total':
          if( $rec->total == 0.00 && $rec->amount > 0.00 ): ?>
            <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_currency($rec->amount, true, false); ?></td>
          <?php else: ?>
            <td <?php echo $attributes; ?>><?php echo MeprAppHelper::format_currency($rec->total, true, false); ?></td>
          <?php endif;

          break;
        case 'col_status':
          ?>
          <td <?php echo $attributes; ?>>
            <div class="status_initial status_initial_<?php echo $rec->id; ?>" data-value="<?php echo $rec->id; ?>">
              <a href="#" title="<?php _e("Change transaction's status", 'memberpress'); ?>"><?php echo stripslashes(MeprAppHelper::human_readable_status($rec->status)); ?></a>
            </div>
            <div class="status_editable status_editable_<?php echo $rec->id; ?>">
              <?php
                MeprAppHelper::info_tooltip('mepr-transactions-status-'.$rec->id,
                                            __('Editing Transaction Status', 'memberpress'),
                                            __("Changing the status here will ONLY change the status on your site, not at the Gateway itself. To cancel a Transaction either you or the member should click the Cancel link next to the Subscription. You must use your Payment Gateway's web interface to refund a transaction.", 'memberpress'));
              ?>
              <select class="status_edit status_edit_<?php echo $rec->id; ?>" data-value="<?php echo $rec->id; ?>">
                <option value="pending" <?php echo (stripslashes($rec->status) == 'pending')?'selected="selected"':''; ?>><?php _e('Pending', 'memberpress'); ?></option>
                <option value="failed" <?php echo (stripslashes($rec->status) == 'failed')?'selected="selected"':''; ?>><?php _e('Failed', 'memberpress'); ?></option>
                <option value="refunded" <?php echo (stripslashes($rec->status) == 'refunded')?'selected="selected"':''; ?>><?php _e('Refunded', 'memberpress'); ?></option>
                <option value="complete" <?php echo (stripslashes($rec->status) == 'complete')?'selected="selected"':''; ?>><?php _e('Complete', 'memberpress'); ?></option>
              </select><br/>
              <a href="#" class="button status_save" data-value="<?php echo $rec->id; ?>"><?php _e('Save', 'memberpress'); ?></a>
              <a href="#" class="button cancel_change" data-value="<?php echo $rec->id; ?>"><?php _e('Cancel', 'memberpress'); ?></a>
            </div>
            <div class="status_saving status_saving_<?php echo $rec->id; ?>">
              <?php _e('Saving ...', 'memberpress'); ?>
            </div>
          </td>
          <?php
          break;
        case 'col_propername':
          if(empty($rec->lname))
            $rec->lname = __('Empty', 'memberpress');
          if(empty($rec->fname))
            $rec->fname = __('Empty', 'memberpress');
          ?>
          <td <?php echo $attributes; ?>>
            <?php echo stripslashes($rec->lname).', '.stripslashes($rec->fname); ?>
          </td>
          <?php
          break;
      }
    }
    ?>
    </tr>
    <?php
  } //End foreach
} //End if
