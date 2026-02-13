<?php
function clearSiteCache() {
  if (function_exists('sg_cachepress_purge_cache')) {
    sg_cachepress_purge_cache();
  } else {
    error_log("⚠️ SiteGround cache function not available.");
  }
}
