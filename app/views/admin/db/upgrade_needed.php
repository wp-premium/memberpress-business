<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php echo get_option( 'blog_charset' ); ?>" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?php _e('Memberpress needs to upgrade your database', 'memberpress'); ?></title>
  <script src="https://code.jquery.com/jquery-1.12.4.min.js" integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ=" crossorigin="anonymous"></script>
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
  <script>
    $(document).ready(function() {
      var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

      var upgrade_db_success = function() {
        window.location.href = '<?php
          echo MeprUtils::admin_url(
            'admin-ajax.php',
            array('db_upgrade_success', 'mepr_db_upgrade_nonce'),
            array('action' => 'mepr_db_upgrade_success')
          ); ?>';
      };

      var upgrade_db_not_needed = function() {
        window.location.href = '<?php
          echo MeprUtils::admin_url(
            'admin-ajax.php',
            array('db_upgrade_not_needed', 'mepr_db_upgrade_nonce'),
            array('action' => 'mepr_db_upgrade_not_needed')
          ); ?>';
      };

      var error_count = 0;
      var upgrade_db_error = function(retries) {
        error_count = error_count + 1;

        // Let's only error out if we get at least 3 errors in a row
        if(error_count >= retries) {
          window.location.href = '<?php
            echo MeprUtils::admin_url(
              'admin-ajax.php',
              array('db_upgrade_error', 'mepr_db_upgrade_nonce'),
              array('action' => 'mepr_db_upgrade_error')
            ); ?>';
        }
        else {
          console.info('An error occurred (' + error_count + '), retrying')
        }
      };

      var last_progress = 0;
      var working_notice = function(message, progress, completed, total) {
        var wn = $('.progress-bar').text();

        if(wn=='' || wn==' ') {
          $('.progress-bar').text('<?php _e('Upgrading...', 'memberpress'); ?>');
        }
        else {
          $('.progress-bar').text(' ');
        }

        $('.progress-bar-status').html('<em>'+message+' ...</em> <strong>('+completed+'/'+total+')</strong>');

        var pg = parseFloat($('.progress-bar').attr('aria-valuenow'));
        progress = parseFloat(progress);

        // Let's at least throw a bone if they're checking
        if(progress==last_progress) {
          last_progress = progress;
          progress = (pg + 0.3);
        }

        $('.progress-bar').css('width', progress + "%").attr('aria-valuenow', progress);
      }

      var upgrade_db = function(already_upgrading) {
        $('#upgrade_db_trigger').attr('disabled',true);

        var args = {
          'action': 'mepr_db_upgrade',
          'mepr_db_upgrade_nonce': '<?php echo wp_create_nonce('db_upgrade'); ?>'
        };

        $('#upgrade_db').modal({
          backdrop: 'static',
          keyboard: false
        });

        if(!already_upgrading) {
          $.post(ajaxurl, args, function(data) {
            // Do nothing until failure
            if(data['status']=='complete') {
              upgrade_db_success();
            }
            else if(data['status']=='already_migrated') {
              upgrade_db_not_needed();
            }
          },'json')
          .fail(function() {
            upgrade_db_error(0);
          });
        }

        setInterval(
          function() {
            $.get(ajaxurl, {
              'action': 'mepr_db_upgrade_in_progress',
              'mepr_db_upgrade_nonce': '<?php echo wp_create_nonce('db_upgrade_in_progress'); ?>'
            },
            function(data) {
              // Do nothing until failure
              if(data['status']=='not_in_progress') {
                upgrade_db_success();
              }
              else if(data['status']=='in_progress') {
                error_count = 0; // reset error count to zero
                // Nothing ... notice & loop
                working_notice(data['message'],data['progress'],data['completed'],data['total']);
              }
            },'json')
            .fail(function() {
              upgrade_db_error(3);
            });
          },
          5000
        );
      };

      $.get(ajaxurl, {
        'action': 'mepr_db_upgrade_in_progress',
        'mepr_db_upgrade_nonce': '<?php echo wp_create_nonce('db_upgrade_in_progress'); ?>'
      },
      function(data) {
        // Do nothing until failure
        if(data['status']=='not_in_progress') {
          //upgrade_db_success();
        }
        else if(data['status']=='in_progress') {
          working_notice(data['message'],data['progress'],data['completed'],data['total']);
          upgrade_db(true);
        }
      },'json')
      .fail(function() {
        upgrade_db_error(0);
      });

      $('#upgrade_db_trigger').on('click',function() {
        upgrade_db(false);
      });
    });
  </script>
</head>
<body>
  <div class="container">
    <div class="row">&nbsp;</div>
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <div class="panel panel-primary">
          <div class="panel-heading"><h2><?php _e('Memberpress needs to upgrade your database', 'memberpress'); ?></h2></div>
          <div class="panel-body">
            <p>&nbsp;</p>
            <p><?php _e('Before starting the upgrade process <strong>make sure your <em>database is backed up</em></strong>.', 'memberpress'); ?></p>
            <p><?php _e('And please be patient, the upgrade process <em>may take a few minutes</em>.', 'memberpress'); ?></p>
            <p>&nbsp;</p>
            <?php
              $update_ctrl = new MeprUpdateCtrl();
            ?>
            <!-- <p><a class="btn btn-primary" href="<?php
              echo MeprUtils::admin_url(
                'admin-ajax.php',
                array('db_upgrade', 'mepr_db_upgrade_nonce'),
                array('action' => 'mepr_db_upgrade')
              );
            ?>"><?php _e('Upgrade', 'memberpress'); ?></a></p> -->
            <!-- Button trigger modal -->
            <p>
              <button type="button" class="btn btn-primary btn-lg" id="upgrade_db_trigger"><?php _e('Upgrade', 'memberpress'); ?></button> or
              <a href="<?php echo $update_ctrl->rollback_url(); ?>" onclick="return confirm('<?php _e('Are you sure? This will cancel the upgrade and roll Memberpress back to the previous version.', 'memberpress'); ?>');" target="_blank"><?php _e('Cancel', 'memberpress'); ?></a>
            </p>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="upgrade_db" tabindex="-1" role="dialog" aria-labelledby="upgrade_db_label">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-body">
            <h2><?php _e('Your database is being upgraded', 'memberpress'); ?></h2>
            <p><?php _e('Please be patient this could take a few minutes.', 'memberpress'); ?></p>
            <p>&nbsp;</p>
            <div class="progress">
              <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"> </div>
            </div>
            <p class="progress-bar-status"> </p>
            <br/><br/>
            <a href="<?php echo $update_ctrl->rollback_url(); ?>" onclick="return confirm('<?php _e('Are you sure? This will abort the upgrade and roll Memberpress back to the previous version.', 'memberpress'); ?>');" target="_blank"><?php _e('Cancel', 'memberpress'); ?></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

