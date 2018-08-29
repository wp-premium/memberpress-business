<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<br/>
<h3><?php _e('Usage', 'memberpress'); ?></h3>
<table class="form-table">
  <tbody>
    <tr valign="top">
      <th scope="row">
        <label for="mepr_disable_senddata"><?php _e('Disable Anonymous Usage Reporting', 'memberpress'); ?></label>
        <?php MeprAppHelper::info_tooltip(
          'mepr-senddata',
          __('Disable Anonymous Usage Reporting', 'memberpress'),
          __('In order to help us improve MemberPress you can allow MemberPress to send anonymous usage data back to our developers. We respect your privacy so any data that is sent to us can\'t be traced back to you -- but it will help us to fix issues, identify new features and generally improve MemberPress.', 'memberpress') ); ?>
      </th>
      <td>
        <input type="checkbox" name="mepr_disable_senddata" id="mepr_disable_senddata" <?php checked($disable_senddata); ?> />
      </td>
    </tr>
  </tbody>
</table>

