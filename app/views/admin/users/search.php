<?php /* This must be on a single line or extra lines are added to the output*/ ?>
<?php /* <div class="suggest-result" style="padding: 5px 0; width: 300px;"><span class="suggest-result-img" style="float: left; padding-right: 5px;"><?php echo get_avatar( $user->user_email, 30 ) ?></span><span class="suggest-result-name" style="display: block;"><?php echo $user->user_login ?></span><span class="suggest-result-email" style="display: block;"><?php echo $user->user_email ?></span><div class="clear"></div></div> */ ?>
<?php foreach( $users as $user ) : ?>
   <?php echo $user->user_login, "\n"; ?>
<?php endforeach; ?>
