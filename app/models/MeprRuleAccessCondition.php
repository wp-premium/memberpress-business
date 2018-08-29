<?php
if(!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); }

class MeprRuleAccessCondition extends MeprBaseModel {
  public function __construct($obj = null) {
    $this->initialize(
      array(
        'id'               => 0,
        'rule_id'          => 0,
        'access_type'      => '',
        'access_operator'  => '',
        'access_condition' => '',
      ),
      $obj
    );
  }

  public static function get_one($id, $return_type = OBJECT) {
    $mepr_db = new MeprDb();
    $args = compact('id');

    return $mepr_db->get_one_record($mepr_db->rule_access_conditions, $args);
  }

  /** Checks to see if there's already a rule access condition like this.
    * It will return an id if there's one that's been found and '' if not.
    */
  public static function rule_access_condition_exists($rule_access_condition) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT id
          FROM {$mepr_db->rule_access_conditions}
         WHERE rule_id=%d
           AND access_type=%s
           AND access_operator=%s
           AND access_condition=%s
         LIMIT 1
      ",
      $rule_access_condition->rule_id,
      $rule_access_condition->access_type,
      $rule_access_condition->access_operator,
      $rule_access_condition->access_condition
    );

    $id = $wpdb->get_var($q);

    if(empty($id)) {
      MeprUtils::debug_log("Access condition DOESN'T exists: rule:{$rule_access_condition->rule_id} type:{$rule_access_condition->access_type} op:{$rule_access_condition->access_operator} cond:{$rule_access_condition->access_condition}");
    }
    else {
      MeprUtils::debug_log("Access condition exists: {$id}");
    }

    return $id;
  }

  public static function delete_all_by_rule($rule_id) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        DELETE FROM {$mepr_db->rule_access_conditions}
         WHERE rule_id=%d
      ",
      $rule_id
    );

    return $wpdb->query($q);
  }

  public function store() {
    if(isset($this->id) && !is_null($this->id) && (int)$this->id > 0) {
      $this->id = self::update($this);
    }
    else {
      $this->id = self::create($this);
    }

    MeprHooks::do_action('mepr_rule_access_stored', $this);

    return $this->id;
  }

  public static function create($rule_access_condition) {
    // Ensure no duplicate rule access conditions get created
    $id = self::rule_access_condition_exists($rule_access_condition);
    if(!empty($id)) { return $id; }

    $mepr_db = MeprDb::fetch();
    $attributes = $rule_access_condition->get_values();

    return MeprHooks::apply_filters(
      'mepr_create_rule_access',
      $mepr_db->create_record($mepr_db->rule_access_conditions, $attributes, false),
      $attributes,
      $rule_access_condition->rule_id
    );
  }

  public static function update($rule_access_condition) {
    $mepr_db = new MeprDb();
    $attributes = $rule_access_condition->get_values();

    return MeprHooks::apply_filters(
      'mepr_update_rule_access',
      $mepr_db->update_record($mepr_db->rule_access_conditions, $rule_access_condition->id, $attributes),
      $attributes,
      $rule_access_condition->rule_id
    );
  }

  public function destroy() {
    $mepr_db = new MeprDb();
    $args = array('id' => $this->id);

    $res = $mepr_db->delete_records($mepr_db->rule_access_conditions, $args);
    MeprHooks::do_action('mepr_rule_access_deleted', $this);

    return $res;
  }
}
