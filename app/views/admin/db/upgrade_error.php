<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php echo get_option( 'blog_charset' ); ?>" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?php _e('MemberPress database upgrade error', 'memberpress'); ?></title>
  <script src="https://code.jquery.com/jquery-1.12.4.min.js" integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ=" crossorigin="anonymous"></script>
  <?php
  //wp_admin_css( 'install', true );
  //wp_admin_css( 'ie', true );
  ?>
  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
  <!-- Optional theme -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">
  <!-- Latest compiled and minified JavaScript -->
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
  <style>
    body { background-color: #dedede; }
    p { font-size: 120%; }
  </style>
</head>
<body>
  <div class="container">
    <div class="row">&nbsp;</div>
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <div class="panel panel-primary">
          <div class="panel-heading"><h2><?php _e('MemberPress database upgrade error', 'memberpress'); ?></h2></div>
          <div class="panel-body">
            <p>&nbsp;</p>
            <?php
              $update_ctrl = new MeprUpdateCtrl();
            ?>
            <h3><?php _e('Oops, your MemberPress database upgrade triggered an error...', 'memberpress'); ?></h3>
            <p><?php _e('If this is a production website rollback MemberPress to a previous version and contact our support team.', 'memberpress'); ?></p>
            <p>&nbsp;</p>
            <?php if(!empty($error)): ?>
              <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
              <p>&nbsp;</p>
            <?php endif; ?>
            <p>
              <a class="btn btn-primary" href="<?php echo $update_ctrl->rollback_url(); ?>" target="_blank"><?php _e('Rollback MemberPress', 'memberpress'); ?></a>
              <a class="btn btn-primary" href="https://www.memberpress.com/support/" target="_blank"><?php _e('Contact Support', 'memberpress'); ?></a>
            </p>
            <p>&nbsp;</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

