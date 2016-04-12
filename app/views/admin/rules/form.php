<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

$products = get_posts(array('numberposts' => -1, 'post_type' => 'memberpressproduct', 'post_status' => 'publish'));
if($products != null)
{
?>
  <div id="mepr-rules-form">
    <div class="mepr-main-pane">
      <?php if($server == 'apache' and !$htaccess_writable): ?>
        <div class="error"><ul><li><strong><?php _e('Note:', 'memberpress'); ?></strong> <?php printf(__('%1$s isn\'t writable so <strong>Custom URIs</strong> won\'t be able to be locked until you add some %2$scustom rules%3$s to your .htaccess file. If you\'ve already installed these rules then you can ignore this message. <strong>All other rules should work normally.</strong>', 'memberpress'), $htaccess, '<a href="http://memberpress.com/user-manual/rewrite" target="_blank">', '</a>'); ?></li></ul></div>
      <?php elseif($server == 'nginx'): ?>
        <div class="error"><ul><li><strong><?php _e('Note:', 'memberpress'); ?></strong> <?php printf(__('It appears that your website is running Nginx as it\'s webserver. In order for Custom URI rules to work you\'ll need to add some %1$scustom rules%2$s to your Nginx configuration. If you\'ve already installed these rules then you can ignore this message. <strong>All other rules should work normally.</strong>', 'memberpress'), '<a href="http://memberpress.com/user-manual/rewrite" target="_blank">', '</a>'); ?></li></ul></div>
      <?php elseif($server == 'unknown'): ?>
        <div class="error"><ul><li><strong><?php _e('Note:', 'memberpress'); ?></strong> <?php printf(__('MemberPress doesn\'t recognize the webserver you\'re using and until you add additional %1$scustom rules%2$s to your webserver\'s configuration Custom URI rules won\'t work properly. If you\'ve already installed these rules then you can ignore this message. <strong>All other rules should work normally.</strong>', 'memberpress'), '<a href="http://memberpress.com/user-manual/rewrite" target="_blank">', '</a>'); ?></li></ul></div>
      <?php elseif($server == 'whocares'): ?>
        <div class="error"><ul><li><strong><?php _e('Note:', 'memberpress'); ?></strong> <?php _e('You have disabled the .htaccess rewrite rules in your MemberPress Options. This means you will not be able to protect static files from your local filesystem (PDF, ZIP, DOCX etc) using Custom URI Rules. This only affects Custom URI Rules which are protecting static files on your filesystem, and will not affect any of your other Rules.', 'memberpress'); ?></li></ul></div>
      <?php endif; ?>
      <?php _e('Rule:', 'memberpress'); ?>&nbsp;<?php MeprRulesHelper::type_dropdown(MeprRule::$mepr_type_str, $rule->mepr_type, 'mepr_show_content_dropdown( \''.MeprRule::$mepr_content_str.'\', this.value )'); ?>:&nbsp;<img src="<?php echo admin_url('images/wpspin_light.gif'); ?>" id="mepr-rule-loading-icon" class="mepr_hidden" /><?php MeprRulesHelper::content_dropdown(MeprRule::$mepr_content_str, $rule->mepr_content, $rule->mepr_type, array(MeprRule::$is_mepr_content_regexp_str => $rule->is_mepr_content_regexp)); ?>
    </div>
    <div class="mepr-main-pane">
      <?php _e('Give Access to Members who have purchased:', 'memberpress'); ?><br/>
      <?php MeprRulesHelper::access_dropdown(MeprRule::$mepr_access_str, $rule->mepr_access); ?><br/>
      <span class="description"><small><?php _e('Hold the Control Key (Command Key on the Mac) in order to select or deselect multiple memberships', 'memberpress'); ?></small></span>
    </div>
    <div class="mepr-main-pane">
      <a href="#" id="partial_content_codes" class="button"><?php _e('Partial Content Codes', 'memberpress'); ?></a>
      <div id="partial_content_codes_hidden">
        <?php _e('Shortcode:', 'memberpress'); ?> <strong>[mepr-show if="rule: <?php echo $rule->ID; ?>"] </strong><?php _e('This content is shown only to authorized members. It is hidden from everyone else.', 'memberpress'); ?><strong> [/mepr-show]</strong>
        <br/><br/>
        <?php _e('Shortcode:', 'memberpress'); ?> <strong>[mepr-hide if="rule: <?php echo $rule->ID; ?>"] </strong><?php _e('This content is shown to everyone except authorized members.', 'memberpress'); ?><strong> [/mepr-hide]</strong>
        <br/><br/>
        <?php _e('PHP Snippet:', 'memberpress'); ?>
        <strong><?php echo htmlentities("<?php if(current_user_can('mepr-active','rule: {$rule->ID}')): ?>"); ?></strong>
          <?php _e('Content to protect goes inbetween.', 'memberpress'); ?>
        <strong><?php echo htmlentities("<?php endif; ?>"); ?></strong>
        <?php MeprHooks::do_action('mepr-partial-content-codes', $rule); ?>
      </div>
    </div>
    <input type="hidden" name="<?php echo MeprRule::$auto_gen_title_str; ?>" id="<?php echo MeprRule::$auto_gen_title_str ?>" value="<?php echo ($rule->auto_gen_title)?'true':'false'; ?>" />
    <!-- The NONCE below prevents post meta from being blanked on move to trash -->
    <input type="hidden" name="<?php echo MeprRule::$mepr_nonce_str; ?>" value="<?php echo wp_create_nonce(MeprRule::$mepr_nonce_str.wp_salt()); ?>" />
    <!-- jQuery i18n data -->
    <div id="save-rule-helper" style="display:none;" data-value="<?php _e('Save Rule', 'memberpress'); ?>"></div>
    <div id="rule-message-helper" style="display:none;" data-value="<?php _e('Rule Saved', 'memberpress'); ?>"></div>
  </div>
<?php
}
else
{
?>
  <div id="mepr-rules-form">
    <strong><?php _e('You cannot create rules until you have added at least 1 Membership.', 'memberpress'); ?></strong>
    <!-- jQuery i18n data -->
    <div id="save-rule-helper" style="display:none;" data-value="<?php _e('Save Rule', 'memberpress'); ?>"></div>
    <div id="rule-message-helper" style="display:none;" data-value="<?php _e('Rule Saved', 'memberpress'); ?>"></div>
  </div>
<?php
}
