<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

$products = MeprCptModel::all('MeprProduct');
?>

<?php if(!empty($products)): ?>
  <div id="mepr-rules-form">
    <div class="mepr-main-pane">
      <h3 class="mepr-page-title">
        <?php _e('Protected Content', 'memberpress') ?>
        <?php MeprAppHelper::info_tooltip( 'mepr-rule-protected-content',
                                           __('Protected Content', 'memberpress'),
                                           __('This selects the content on your site that will be protected by this rule. If a piece of content is selected by this rule it will be protected from non-logged in visitors and from logged-in users who don\'t meet the conditions you specify in the \'Access Rules\' section below.', 'memberpress') ); ?>
      </h3>
      <?php if($server == 'apache' and !$htaccess_writable): ?>
        <div class="error"><ul><li><strong><?php _e('Note:', 'memberpress'); ?></strong> <?php printf(__('%1$s isn\'t writable so <strong>Custom URIs</strong> won\'t be able to be locked until you add some %2$scustom rules%3$s to your .htaccess file. If you\'ve already installed these rules then you can ignore this message. <strong>All other rules should work normally.</strong>', 'memberpress'), $htaccess, '<a href="http://memberpress.helpscoutdocs.com/article/179-understanding-rewrite-rules" target="_blank">', '</a>'); ?></li></ul></div>
      <?php elseif($server == 'nginx'): ?>
        <div class="error"><ul><li><strong><?php _e('Note:', 'memberpress'); ?></strong> <?php printf(__('It appears that your website is running Nginx as it\'s webserver. In order for Custom URI rules to work you\'ll need to add some %1$scustom rules%2$s to your Nginx configuration. If you\'ve already installed these rules then you can ignore this message. <strong>All other rules should work normally.</strong>', 'memberpress'), '<a href="http://memberpress.helpscoutdocs.com/article/179-understanding-rewrite-rules" target="_blank">', '</a>'); ?></li></ul></div>
      <?php elseif($server == 'unknown'): ?>
        <div class="error"><ul><li><strong><?php _e('Note:', 'memberpress'); ?></strong> <?php printf(__('MemberPress doesn\'t recognize the webserver you\'re using and until you add additional %1$scustom rules%2$s to your webserver\'s configuration Custom URI rules won\'t work properly. If you\'ve already installed these rules then you can ignore this message. <strong>All other rules should work normally.</strong>', 'memberpress'), '<a href="http://memberpress.helpscoutdocs.com/article/179-understanding-rewrite-rules" target="_blank">', '</a>'); ?></li></ul></div>
      <?php elseif($server == 'whocares'): ?>
        <div class="error"><ul><li><strong><?php _e('Note:', 'memberpress'); ?></strong> <?php _e('You have disabled the .htaccess rewrite rules in your MemberPress Options. This means you will not be able to protect static files from your local filesystem (PDF, ZIP, DOCX etc) using Custom URI Rules. This only affects Custom URI Rules which are protecting static files on your filesystem, and will not affect any of your other Rules.', 'memberpress'); ?></li></ul></div>
      <?php endif; ?>
      <?php MeprRulesHelper::type_dropdown(MeprRule::$mepr_type_str, $rule->mepr_type, 'mepr_show_content_dropdown( \''.MeprRule::$mepr_content_str.'\', this.value )'); ?>:&nbsp;
      <img src="<?php echo admin_url('images/wpspin_light.gif'); ?>" id="mepr-rule-loading-icon" class="mepr_hidden" />
      <?php MeprRulesHelper::content_dropdown(MeprRule::$mepr_content_str, $rule->mepr_content, $rule->mepr_type, array(MeprRule::$is_mepr_content_regexp_str => $rule->is_mepr_content_regexp)); ?>
    </div>

    <div class="mepr-main-pane">
      <h3 class="mepr-page-title">
        <?php _e('Access Conditions', 'memberpress') ?>
        <?php MeprAppHelper::info_tooltip(
          'mepr-rule-access-conditions',
          __('Access Conditions', 'memberpress'),
          sprintf(__('If %1$sany%2$s of these conditions match for a logged-in user then he / she will be granted access to the protected content for this rule -- otherwise he / she will be denied.', 'memberpress'), '<strong>','</strong>')); ?>
      </h3>
      <h4><?php _e('Grant access to the protected content above if a logged-in user matches any of the following condtions:', 'memberpress') ?></h4>
      <div id="mepr-access-rows">
        <?php
          if(empty($rule_access_conditions)) {
            MeprRulesHelper::access_row();
          }
          else {
            foreach($rule_access_conditions as $ac_index => $access_condition) {
              MeprRulesHelper::access_row($access_condition, $ac_index);
            }
          }
        ?>
      </div>
      <div>&nbsp;</div>
      <img src="<?php echo admin_url('images/wpspin_light.gif'); ?>" id="mepr-condition-loading-icon" class="mepr_hidden" />
      <a href="" id="add-new-rule-condition" title="<?php _e('Add Access Rule', 'memberpress'); ?>"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
    </div>

    <div class="mepr-main-pane">
      <h3 class="mepr-page-title"><a href="" class="mepr-toggle-link" data-box="mepr-partial-codes"><?php _e('Partial Content Codes', 'memberpress'); ?></a></h3>
      <div class="mepr-sub-box mepr-partial-codes mepr-hidden">
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
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
<?php else: ?>
  <div id="mepr-rules-form">
    <strong><?php _e('You cannot create rules until you have added at least 1 Membership.', 'memberpress'); ?></strong>
    <!-- jQuery i18n data -->
    <div id="save-rule-helper" style="display:none;" data-value="<?php _e('Save Rule', 'memberpress'); ?>"></div>
    <div id="rule-message-helper" style="display:none;" data-value="<?php _e('Rule Saved', 'memberpress'); ?>"></div>
  </div>
<?php endif;
