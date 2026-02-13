<?php
// ✅ Full WP environment
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// ✅ Secret check
if (!isset($_GET['secret']) || $_GET['secret'] !== 'aabbcc112233ddee') {
  echo "❌ Unauthorized.";
  exit;
}

// ✅ SiteGround Optimizer cache purge
if (function_exists('sg_cachepress_purge_cache')) {
  do_action('sg_cachepress_purge_cache');
  echo "✅ SiteGround cache cleared.";
} else {
  echo "❌ SG Optimizer not active or not detected.";
}
