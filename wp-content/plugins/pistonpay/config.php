<?php
// /wp-content/plugins/pistonpay/config.php

require_once(__DIR__ . '/lib/stripe/init.php');  
require_once(__DIR__ . '/db_connect.php');

\Stripe\Stripe::setApiKey('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');
