<?php
// /wp-content/plugins/accelerated/config.php

require_once(__DIR__ . '/lib/stripe/init.php');  // Stripe autoloader
require_once(__DIR__ . '/db_connect.php');       // PDO database connector

\Stripe\Stripe::setApiKey('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'); // Use your correct secret key
