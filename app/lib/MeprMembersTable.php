<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

if(!class_exists('WP_List_Table')) {
  require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
}

class MeprMembersTable extends WP_List_Table {
  public $_screen;
  public $_columns;
  public $_sortable;

  public $_searchable;
  public $db_search_cols;
  public $totalitems;

  public function __construct($screen, $columns=array()) {
    if(is_string($screen)) {
      $screen = convert_to_screen( $screen );
    }

    $this->_screen = $screen;

    if(!empty($columns)) {
      $this->_columns = $columns;
    }

    $this->_searchable = array(
      'username' => __('Username', 'memberpress'),
      'email' => __('Email', 'memberpress'),
      'first_name' => __('First Name', 'memberpress'),
      'last_name' => __('Last Name', 'memberpress'),
      'id' => __('Id', 'memberpress'),
    );

    $this->db_search_cols = array(
      'username' => 'u.user_login',
      'email' => 'u.user_email',
      'last_name' => 'pm_last_name.meta_value',
      'first_name' => 'pm_first_name.meta_value',
      'id' => 'u.ID',
    );

    parent::__construct(
      array(
        'singular'=> 'wp_list_mepr_members', //Singular label
        'plural'  => 'wp_list_mepr_members', //plural label, also this will be one of the table css class
        'ajax'    => true // false //We won't support Ajax for this table
      )
    );
  }

  public function get_column_info() {
    $columns = get_column_headers( $this->_screen );
    $hidden = get_hidden_columns( $this->_screen );

    // Bypass MeprHooks to call built-in filter
    $sortable = apply_filters( "manage_{$this->_screen->id}_sortable_columns", $this->get_sortable_columns() );

    $primary = 'col_id';
    return array( $columns, $hidden, $sortable, $primary );
  }

  public function extra_tablenav($which) {
    if($which == 'top') {
      $search_cols = $this->_searchable;
      MeprView::render("/admin/table_controls", compact('search_cols'));
    }

    if($which == 'bottom') {
      $action = 'mepr_members';
      $totalitems = $this->totalitems;
      $itemcount = count($this->items);
      MeprView::render("/admin/table_footer", compact('action','totalitems','itemcount'));
    }
  }

  public function get_columns() {
    return $this->_columns;
  }

  public function get_sortable_columns() {
    return $sortable= array(
      'col_id'        => array('u.ID',true),
      'col_username'  => array('u.user_login',true),
      'col_email'     => array('u.user_email',true),
      'col_status'    => array('status',true),
      'col_name'      => array('name',true),
      //'col_txn_count' => array('m.txn_count',true),
      //'col_expired_txn_count' => array('m.expired_txn_count',true),
      //'col_active_txn_count'  => array('m.active_txn_count',true),
      //'col_sub_count' => array('m.sub_count',true),
      'col_last_login_date'   => array('last_login.created_at',true),
      'col_login_count' => array('m.login_count',true),
      'col_total_spent' => array('m.total_spent',true),
      'col_registered'  => array('u.user_registered',true),
    );
  }

  public function prepare_items() {
    $user_id = get_current_user_id();
    $screen = get_current_screen();

    if(isset($screen) && is_object($screen)) {
      $option = $screen->get_option('per_page', 'option');

      $perpage = !empty($option) ? get_user_meta($user_id, $option, true) : 10;
      $perpage = !empty($perpage) && !is_array($perpage) ? $perpage : 10;

      // Specifically for the CSV export to work properly
      $_SERVER['QUERY_STRING'] = ( empty( $_SERVER['QUERY_STRING'] ) ? "?" : "{$_SERVER['QUERY_STRING']}&" ) . "perpage={$perpage}";
    }
    else {
      $perpage = !empty($_GET["perpage"]) ? esc_sql($_GET["perpage"]) : 10;
    }

    $orderby = !empty($_GET["orderby"]) ? esc_sql($_GET["orderby"]) : 'registered';
    $order   = !empty($_GET["order"])   ? esc_sql($_GET["order"])   : 'DESC';
    $paged   = !empty($_GET["paged"])   ? esc_sql($_GET["paged"])   : 1;
    $search  = !empty($_GET["search"])  ? esc_sql($_GET["search"])  : '';
    $search_field = !empty($_GET["search-field"])  ? esc_sql($_GET["search-field"])  : 'any';
    $search_field = isset($this->db_search_cols[$search_field]) ? $this->db_search_cols[$search_field] : 'any';

    $list_table = MeprUser::list_table($orderby, $order, $paged, $search, $search_field, $perpage);
    $totalitems = $list_table['count'];

    //How many pages do we have in total?
    $totalpages = ceil($totalitems/$perpage);

    /* -- Register the pagination -- */
    $this->set_pagination_args(
      array(
        'total_items' => $totalitems,
        'total_pages' => $totalpages,
        'per_page' => $perpage,
      )
    );

    /* -- Register the Columns -- */
    if(isset($screen) && is_object($screen)) {
      $this->_column_headers = $this->get_column_info();
    }
    // For CSV to work properly
    else {
      $this->_column_headers = array(
        $this->get_columns(),
        array(),
        $this->get_sortable_columns()
      );
    }

    $this->totalitems = $totalitems;

    /* -- Fetch the items -- */
    $this->items = $list_table['results'];
  }

  public function display_rows() {
    //Get the records registered in the prepare_items method
    $records = $this->items;

    //Get the columns registered in the get_columns and get_sortable_columns methods
    list( $columns, $hidden ) = $this->get_column_info();

    MeprView::render('/admin/members/row', get_defined_vars());
  }

  public function get_items() {
    return $this->items;
  }
}

