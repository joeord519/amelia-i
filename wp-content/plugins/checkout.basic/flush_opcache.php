<?php
header('Content-Type: text/plain');

if (function_exists('opcache_reset')) {
  opcache_reset();
  echo "✅ OPcache has been cleared.";
} else {
  echo "❌ OPcache is not enabled on this server.";
}
