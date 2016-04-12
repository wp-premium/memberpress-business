<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<h3><?php _e('Membership Information', 'memberpress'); ?></h3>

<table class="form-table mepr-form">
  <tbody>
    <tr>
      <th>
        <label for="mepr-geo-country"><?php _e('Signup Location', 'memberpress'); ?></label>
      </th>
      <td>
        <?php
          if($geo_country = get_user_meta($user->ID, 'mepr-geo-country', true)) {
            $countries = require(MEPR_I18N_PATH.'/countries.php');
            printf($countries[$geo_country]);
          }
          else {
            _e('Unknown', 'memberpress');
          }
        ?>
        <p class="description"><?php _e('Detected on user\'s initial signup', 'memberpress'); ?></p>
      </td>
    </tr>
  <?php
    if(MeprUtils::is_mepr_admin()) { //Let admins see all fields
      $custom_fields = $mepr_options->custom_fields;
    }
    else {
      $custom_fields = $user->custom_profile_fields();
    }

    if($mepr_options->show_address_fields) {
      $custom_fields = array_merge($custom_fields, $mepr_options->address_fields); //Genius
    }

    if(!empty($custom_fields)) {
      foreach($custom_fields as $line) {
        $value = get_user_meta($user->ID, $line->field_key, true);
        $required = ($line->required)?'<span class="description">'.__('(required)', 'memberpress').'</span>':'';

        ?>
        <tr>
          <th>
            <label for="<?php echo $line->field_key; ?>"><?php printf( __('%1$s:%2$s', 'memberpress'), stripslashes($line->field_name), $required ); ?></label>
          </th>
          <td>
            <?php
              echo MeprUsersHelper::render_custom_field($line, $value, array(
                'text' => 'regular-text',
                'email' => 'regular-text',
                'textarea' => 'regular-text',
                'date' => 'regular-text',
                'states' => 'regular-text'
              ));
            ?>
          </td>
        </tr>
        <?php
      }
    }

    if(MeprUtils::is_mepr_admin()) { //Allow admins to see
    ?>
      <tr>
        <td colspan="2">
          <a href="<?php echo admin_url('admin.php?page=memberpress-trans&member='.$user->user_login); ?>" class="button"><?php _e("View Member's Transactions", "memberpress");?></a>
        </td>
      </tr>
      <tr>
        <td>
          <a href="<?php echo admin_url('admin.php?page=memberpress-subscriptions&member='.$user->user_login); ?>" class="button"><?php _e("View Member's Subscriptions", "memberpress");?></a>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <a class="button mepr-resend-welcome-email" href="#" user-id="<?php echo $user->ID; ?>" mepr-nonce="<?php echo wp_create_nonce('mepr-resend-welcome-email'); ?>"><?php _e('Resend MemberPress Welcome Email', 'memberpress'); ?></a>&nbsp;&nbsp;<img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" class="mepr-resend-welcome-email-loader" />&nbsp;&nbsp;<span class="mepr-resend-welcome-email-message">&nbsp;</span>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <h4><?php _e('Custom MemberPress Account Message', 'memberpress'); ?></h4>
          <?php wp_editor($user->user_message, MeprUser::$user_message_str); ?>
        </td>
      </tr>
    <?php
    }

    MeprHooks::do_action('mepr_extra_profile_fields', $user);
  ?>
  </tbody>
</table>

