<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<script type="text/javascript">
  var _gaq = _gaq || [];
  _gaq.push(["_setAccount", "<?php echo $ga_account; ?>"]);
  _gaq.push(["_trackPageview"]);
  _gaq.push(["_addTrans",
    "<?php echo $_GET['trans_num']; ?>",     // transaction ID - required
    "<?php echo get_option('blogname'); ?>", // affiliation or store name
    "<?php echo $txn->total; ?>"            // total - required
  ]);

  _gaq.push(["_addItem",
    "<?php echo $_GET['trans_num']; ?>",   // transaction ID - required
    "<?php echo $product->ID; ?>",         // SKU/code - required
    "<?php echo $product->post_title; ?>", // product name
    "<?php echo $product->price; ?>",      // unit price - required
    "1"                         // quantity - required
  ]);
  _gaq.push(["_trackTrans"]); //submits transaction to the Analytics servers

  (function() {
    var ga = document.createElement("script"); ga.type = "text/javascript"; ga.async = true;
    ga.src = ("https:" == document.location.protocol ? "https://ssl" : "http://www") + ".google-analytics.com/ga.js";
    var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ga, s);
  })();
</script>
