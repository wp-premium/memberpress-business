<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

if(!class_exists('WP_List_Table'))
  require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');

class MeprTransactionsTable extends WP_List_Table
{
  public $_screen;
  public $_columns;
  public $_sortable;

  public $_searchable;
  public $db_search_cols;

  public $totalitems;

  public function __construct($screen, $columns=array()) {
    if ( is_string( $screen ) )
      $screen = convert_to_screen( $screen );

    $this->_screen = $screen;

    if ( !empty( $columns ) ) {
      $this->_columns = $columns;
    }

    $this->_searchable = array(
      'txn' => __('Transaction', 'memberpress'),
      'sub' => __('Subscription', 'memberpress'),
      'user' => __('Username', 'memberpress'),
      'email' => __('User Email', 'memberpress'),
      'id' => __('Id', 'memberpress'),
    );

    $this->db_search_cols = array(
      'txn' => 'tr.trans_num',
      'sub' => 'sub.subscr_id',
      'user' => 'm.user_login',
      'email' => 'm.user_email',
      'id' => 'tr.id',
    );

    parent::__construct(
      array(
        'singular'=> 'wp_list_mepr_transaction', //Singular label
        'plural' => 'wp_list_mepr_transactions', //plural label, also this will be one of the table css class
        'ajax'  => true // false //We won't support Ajax for this table
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

  public function extra_tablenav($which)
  {
    if($which == "top") {
      $search_cols = $this->_searchable;

      MeprView::render("/admin/table_controls", get_defined_vars());
    }

    if($which == "bottom") {
      $action = 'mepr_transactions';
      $totalitems = $this->totalitems;
      $itemcount = count($this->items);
      MeprView::render("/admin/table_footer", compact('action','totalitems','itemcount'));
    }
  }

  public function get_columns() {
    return $this->_columns;
  }

  public function get_sortable_columns()
  {
    return $sortable= array(
      'col_id' => array('ID', true),
      'col_trans_num' => array('trans_num', true),
      'col_subscr_id' => array('subscr_id', true),
      'col_product' => array('product_name', true),
      'col_net' => array('amount', true),
      'col_tax' => array('tax_amount', true),
      'col_total' => array('total', true),
      'col_propername' => array('lname', true),
      'col_user_login' => array('user_login', true),
      'col_status' => array('status', true),
      'col_payment_system' => array('gateway', true),
      'col_created_at' => array('created_at', true),
      'col_expires_at' => array('expires_at', true)
    );
  }

  public function prepare_items()
  {
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

    $orderby = !empty($_GET["orderby"]) ? esc_sql($_GET["orderby"]) : 'created_at';
    $order   = !empty($_GET["order"])   ? esc_sql($_GET["order"])   : 'DESC';
    $paged   = !empty($_GET["paged"])   ? esc_sql($_GET["paged"])   : 1;
    $search  = !empty($_GET["search"])  ? esc_sql($_GET["search"])  : '';
    $search_field = !empty($_GET["search-field"])  ? esc_sql($_GET["search-field"])  : 'any';
    $search_field = isset($this->db_search_cols[$search_field]) ? $this->db_search_cols[$search_field] : 'any';

    $list_table = MeprTransaction::list_table($orderby, $order, $paged, $search, $search_field, $perpage);
    $totalitems = $list_table['count'];

    //How many pages do we have in total?
    $totalpages = ceil($totalitems/$perpage);

    /* -- Register the pagination -- */
    $this->set_pagination_args(
      array(
        'total_items' => $totalitems,
        'total_pages' => $totalpages,
        'per_page' => $perpage
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

  public function display_rows()
  {
    //Get the records registered in the prepare_items method
    $records = $this->items;

    //Get the columns registered in the get_columns and get_sortable_columns methods
    list( $columns, $hidden ) = $this->get_column_info();

    MeprView::render('/admin/transactions/row', get_defined_vars());
  }

  public function get_items() {
    return $this->items;
  }
}

