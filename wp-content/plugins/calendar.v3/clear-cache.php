<?php
require_once(__DIR__ . '/clear_cache.php');
clearSiteCache();
echo json_encode(['success' => true]);
