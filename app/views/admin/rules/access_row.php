<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mepr-access-row grid mepr-left-aligned-grid">
  <div class="col-1-12">
    <?php if($index > 0): ?>
      <span class="mepr-rule-access-and-or"><strong><?php _e('OR', 'memberpress'); ?></strong></span>
    <?php else: ?>
      <span class="mepr-rule-access-and-or">&nbsp;</span>
    <?php endif; ?>
  </div>
  <div class="col-2-12">
    <?php
      if(isset($access_condition) && !empty($access_condition)):
        ?><input name="mepr_access_row[rule_access_condition_id][]" type="hidden" value="<?php echo $access_condition->id; ?>"><?php
        MeprRulesHelper::access_types_dropdown($access_condition->access_type);
      else:
        ?><input name="mepr_access_row[rule_access_condition_id][]" type="hidden" value=""><?php
        MeprRulesHelper::access_types_dropdown();
      endif;
    ?>
  </div>
  <div class="col-1-12">
    <?php
      if(isset($access_condition) && !empty($access_condition)):
        MeprRulesHelper::access_operators_dropdown($access_condition->access_type, $access_condition->access_operator);
      else:
        MeprRulesHelper::access_operators_dropdown();
      endif;
    ?>
  </div>
  <div class="col-3-12">
    <?php
      if(isset($access_condition) && !empty($access_condition)):
        MeprRulesHelper::access_conditions_dropdown($access_condition->access_type, $access_condition->access_condition);
      else:
        MeprRulesHelper::access_conditions_dropdown();
      endif;
    ?>
  </div>
  <div class="col-1-12">
    <?php if($index > 0): ?>
      <a href="" class="remove-rule-condition" title="<?php _e('Remove Access Rule', 'memberpress'); ?>"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
    <?php endif; ?>
  </div>
</div>
