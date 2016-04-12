<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

if(!class_exists('WP_List_Table'))
  require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');

class MeprSubscriptionsTable extends WP_List_Table
{
  public $lifetime;
  public $periodic_count;
  public $lifetime_count;

  public $_screen;
  public $_columns;
  public $_sortable;

  public function __construct($screen, $columns, $lifetime=false)
  {
    if ( is_string( $screen ) ) {
      $screen = convert_to_screen( $screen );
    }

    $this->_screen = $screen;

    if ( !empty( $columns ) ) {
      $this->_columns = $columns;
    }

    $this->lifetime = $lifetime;

    if($lifetime) {
      $label = 'wp_list_mepr_lifetime_subscription';
    }
    else {
      $label = 'wp_list_mepr_subscription';
    }

    parent::__construct(
      array(
        'singular'=> $label, //Singular label
        'plural' => "{$label}s", //plural label, also this well be one of the table css class
        'ajax'  => true //false //We won't support Ajax for this table
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
      $member = (isset($_GET['member']) && !empty($_GET['member']))?'&member='.stripslashes($_GET['member']):'';
      $search = (isset($_GET['search']) && !empty($_GET['search']))?'&search='.stripslashes($_GET['search']):'';
      $perpage = (isset($_GET['perpage']) && !empty($_GET['perpage']))?'&perpage='.stripslashes($_GET['perpage']):'';

      $table = $this;
      MeprView::render("/admin/subscriptions/tabs", get_defined_vars());
      MeprView::render("/admin/table_controls", get_defined_vars());
    }

    if($which == "bottom") {
      if($this->lifetime) {
        $action = "mepr_lifetime_subscriptions";
      }
      else {
        $action = "mepr_subscriptions";
      }

      MeprView::render("/admin/table_footer", get_defined_vars());
    }
  }

  public function get_columns()
  {
    return $this->_columns;
  }

  public function get_sortable_columns()
  {
    $prefix = $this->lifetime ? 'col_txn_' : 'col_';
    $cols = array( $prefix.'created_at' => array('created_at', true),
                   $prefix.'id' => array('ID', true),
                   $prefix.'member' => array('member', true),
                   $prefix.'propername' => array('lname', true),
                   $prefix.'product' => array('product_name', true),
                   $prefix.'gateway' => array('gateway', true),
                   $prefix.'subscr_id' => array('subscr_id', true),
                   $prefix.'txn_count' => array('txn_count', true),
                   $prefix.'expires_at' => array('expires_at', true),
                   $prefix.'ip_addr' => array('ip_addr', true),
                   $prefix.'status' => array('status', true),
                   $prefix.'active' => array('active', true)
                 );

    if($this->lifetime) {
      unset($cols[$prefix.'txn_count']);
      unset($cols[$prefix.'status']);
    }

    return MeprHooks::apply_filters('mepr-admin-subscriptions-sortable-cols', $cols, $prefix, $this->lifetime);
  }

  public function prepare_items()
  {
    $user_id = get_current_user_id();
    $screen = get_current_screen();

    if(isset($screen) && is_object($screen)) {
      $option = $screen->get_option('per_page', 'option');

      $perpage = get_user_meta($user_id, $option, true);
      $perpage = !empty($perpage) && !is_array($perpage) ? $perpage : 10;

      // Specifically for the CSV export to work properly
      $_SERVER['QUERY_STRING'] = ( empty( $_SERVER['QUERY_STRING'] ) ? "?" : "{$_SERVER['QUERY_STRING']}&" ) . "perpage={$perpage}";
    }
    else {
      $perpage = !empty($_GET["perpage"]) ? esc_sql($_GET["perpage"]) : 10;
    }

    $orderby = !empty($_GET["orderby"]) ? esc_sql($_GET["orderby"]) : 'ID';
    $order   = !empty($_GET["order"])   ? esc_sql($_GET["order"])   : 'DESC';
    $paged   = !empty($_GET["paged"])   ? esc_sql($_GET["paged"])   : 1;
    $search  = !empty($_GET["search"])  ? esc_sql($_GET["search"])  : '';

    $lifetime_table = MeprSubscription::lifetime_subscr_table($orderby, $order, $paged, $search, $perpage, (!$this->lifetime));
    $periodic_table = MeprSubscription::subscr_table($orderby, $order, $paged, $search, $perpage, ($this->lifetime));

    $list_table = $this->lifetime ? $lifetime_table : $periodic_table;

    $this->periodic_count = $periodic_table['count'];
    $this->lifetime_count = $lifetime_table['count'];

    $totalitems = $list_table['count'];

    //How many pages do we have in total?
    $totalpages = ceil($totalitems/$perpage);

    /* -- Register the pagination -- */
    $this->set_pagination_args( array( "total_items" => $totalitems,
                                       "total_pages" => $totalpages,
                                       "per_page" => $perpage ) );

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

    /* -- Fetch the items -- */
    $this->items = $list_table['results'];
  }

  public function display_rows()
  {
    $mepr_options = MeprOptions::fetch();

    //Get the records registered in the prepare_items method
    $records = $this->items;

    //Get the columns registered in the get_columns and get_sortable_columns methods
    list($columns, $hidden) = $this->get_column_info();

    $table = $this;
    MeprView::render('/admin/subscriptions/row', get_defined_vars());
  }

  public function get_items() {
    return $this->items;
  }
} //End class

