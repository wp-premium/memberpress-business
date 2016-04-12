<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/** Specific base class for CPT Style models */
abstract class MeprCptModel extends MeprBaseModel
{
  //All inheriting classes should set -- public static $cpt (custom post type)
  public static $cpt = '';

  /** This should only be used if the model is using a custom post type **/
  protected function initialize_new_cpt()
  {
    $whos_calling = get_class($this);

    if(!isset($this->attrs) or !is_array($this->attrs))
      $this->attrs = array();

    $r = array( "ID"            => null,
                "post_content"  => '',
                "post_title"    => null,
                "post_excerpt"  => '',
                "post_name"     => null,
                "post_date"     => null,
                "post_status"   => 'publish', // We'll assume this is published if not coming through the post editor
                "post_parent"   => 0,
                "menu_order"    => 0,
                "post_type"     => MeprUtils::get_property($whos_calling, 'cpt') );

    // Initialize postmeta variables
    // Backwards compatible in case attrs has no default values
    if( MeprUtils::is_associative_array($this->attrs) ) {
      foreach($this->attrs as $var => $default)
        $r[$var] = $default;
    }
    else {
      foreach($this->attrs as $var)
        $r[$var] = null;
    }

    $this->rec = (object)$r;

    return $this->rec;
  }

  /** Requires defaults to be set */
  protected function load_cpt($id, $cpt, $attrs) {
    $this->attrs = $attrs;

    if(null === ($this->rec = get_post($id)))
      $this->initialize_new_cpt();
    elseif($this->post_type != $cpt)
      $this->initialize_new_cpt();
    else
      $this->load_meta($id);
  }

  /** Requires defaults to be set */
  protected function load_meta($id) {
    $metas = get_post_custom($id);

    $rec = array();

    // Unserialize and set appropriately
    foreach( $this->attrs as $akey => $aval ) {
      $rclass = new ReflectionClass($this);
      // This requires that the static variable have the same name
      // as the attribute key with "_str" appended
      $rkey = $rclass->getStaticPropertyValue("{$akey}_str");
      if(isset($metas[$rkey])) {
        if(count($metas[$rkey]) > 1) {
          $rec[$akey] = array();
          foreach($metas[$rkey] as $skey => $sval)
            $rec[$akey][$skey] = maybe_unserialize($sval);
        }
        else {
          $mval = $metas[$rkey][0];
          if($mval==='' and is_bool($this->attrs[$akey]))
            $rec[$akey] = false;
          else
            $rec[$akey] = maybe_unserialize($mval);
        }
      }
    }

    $this->rec = (object)array_merge((array)$this->rec,$this->attrs,$rec);
  }

  public function store()
  {
    if(isset($this->ID) and !is_null($this->ID))
      $id = wp_update_post((array)$this->rec);
    else
      $id = wp_insert_post((array)$this->rec);

    if(empty($id) or is_wp_error($id))
      throw new MeprCreateException(sprintf(__( 'This was unable to be saved.', 'memberpress')));
    else
      $this->ID = $id;

    $this->store_meta();

    return $id;
  }

  abstract public function store_meta();

  public function save_meta()
  {
    return $this->store_meta();
  }

  public function destroy() {
    $res = wp_delete_post($this->ID, true);

    if(false===$res) {
      throw new MeprCreateException(sprintf(__( 'This was unable to be deleted.', 'memberpress')));
    }

    return $res;
  }

  //Should probabaly add a delim char check to add before the args
  //similar to how I did it in MeprOptions
  public function url($args = '') {
    $link = MeprUtils::get_permalink($this->ID);
    return MeprHooks::apply_filters('mepr_cpt_model_url', "{$link}{$args}", $this);
  }

  public static function get_all_data( $class, // get_class relies on $this so we have to pass the name in
                                       $type=OBJECT,
                                       $orderby='ID',
                                       $order='ASC',
                                       $limit=100,
                                       $offset=0,
                                       $selects=array(),
                                       $joins=array(),
                                       $wheres=array() ) {
    global $wpdb;

    $rc = new ReflectionClass($class);
    $obj = $rc->newInstance();

    // Account for associative or numeric arrays
    if(MeprUtils::is_associative_array($obj->attrs)) {
      $meta_keys = array_keys($obj->attrs);
    }
    else {
      $meta_keys = $obj->attrs;
    }

    array_unshift( $wheres,
      $wpdb->prepare( 'p.post_type=%s', $rc->getStaticPropertyValue('cpt') ),
      $wpdb->prepare( 'p.post_status=%s', 'publish' ) );

    if(empty($selects)) {
      $selects = array('p.*');
      $fill_selects = true;
    }
    else {
      $fill_selects = false;
    }

    foreach($meta_keys as $meta_key) {
      // Static var for every attr convention
      $meta_key_str = $rc->getStaticPropertyValue("{$meta_key}_str");

      if($fill_selects) {
        $selects[] = "pm_{$meta_key}.meta_value AS {$meta_key}";
      }
      $joins[] = $wpdb->prepare(
        "LEFT JOIN {$wpdb->postmeta} AS pm_{$meta_key} " .
          "ON pm_{$meta_key}.post_id=p.ID " .
         "AND pm_{$meta_key}.meta_key=%s", $meta_key_str);
    }

    $selects_str = join(', ', $selects);
    $joins_str = join(' ', $joins);
    $wheres_str = join( ' AND ', $wheres );

    $q = "SELECT {$selects_str} " .
           "FROM {$wpdb->posts} AS p {$joins_str} " .
          "WHERE {$wheres_str} " .
          "ORDER BY {$orderby} {$order} " .
          "LIMIT {$limit} " .
         "OFFSET {$offset}";

    $res = $wpdb->get_results($q,$type);

    // two layer maybe_unserialize
    for( $i=0; $i<count($res); $i++ ) {
      foreach( $res[$i] as $k => $val ) {
        $res[$i][$k] = maybe_unserialize($val);
      }
    }

    return $res;
  }
}

