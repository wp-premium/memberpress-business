<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php $mepr_options = MeprOptions::fetch(); ?>
<p><?php printf(_x('You\'re unauthorized to view this page. Why don\'t you %s and try again.', 'ui', 'memberpress'), "<a href=\"" . $mepr_options->login_page_url() . "\">" . _x('Login', 'ui', 'memberpress') . "</a>"); ?></p>
