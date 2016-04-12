<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<tr>
  <th>
    <label for="mepr-vat-customer-type"><?php _e('VAT Customer Type', 'memberpress'); ?></label>
  </th>
  <td>
    <?php
      if(!empty($ctype)) {
        if($ctype=='consumer') {
          _e('Consumer', 'memberpress');
        }
        else {
          _e('Business', 'memberpress');
        }
      }
      else {
        _e('Unknown', 'memberpress');
      }
    ?>
  </td>
</tr>
<tr>
  <th>
    <label for="mepr-vat-number"><?php _e('VAT Number', 'memberpress'); ?></label>
  </th>
  <td>
    <?php
      if(!empty($vnum)) {
        echo $vnum;
      }
      else {
        if($ctype=='consumer') {
          _e('Not Applicable', 'memberpress');
        }
        else {
          _e('Not Set', 'memberpress');
        }
      }
    ?>
  </td>
</tr>
