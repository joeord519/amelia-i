<?php
// /wp-content/plugins/cfipanel/logout.php
session_start();
session_destroy();
echo 'logged out';
