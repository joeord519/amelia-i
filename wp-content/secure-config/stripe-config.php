<?php
// Stripe Live Mode
define('STRIPE_SECRET_KEY_LIVE', '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');
define('STRIPE_PUBLISHABLE_KEY_LIVE', 'pk_live_51LB5ypGfQu79Z5mPGVg9gidJ9Vi4SP6zCh1IIB197XlnU5JHIbujZO20jbRLZ2wy5gWRiqpIKfaJFBK0HoqkJR9P004fqixJej');
define('STRIPE_WEBHOOK_SECRET_LIVE', 'whsec_LXT3NfZ3DUJNIQ2vqlpd0Bmrc3lKNtD4');

// Stripe Sandbox/Test Mode
define('STRIPE_SECRET_KEY_TEST', 'sk_test_51SUMeWK4TsyJVjkCQLv6giPqp1Z1OVbVXuOLa0Lem09L82uKl8N5Zz0y9OGPSo51jmlglHyTgLEY2qGHZrjnisF700Id3UFoZP');
define('STRIPE_PUBLISHABLE_KEY_TEST', 'pk_test_51SUMeWK4TsyJVjkChy1sUeWUjE0wYHtMiKl9YqXceWWMlInOaXZbT0RY5b1m8v9yrJBAGJU7dEFQOd6bsqNNWTnf00ln832c9k');
define('STRIPE_WEBHOOK_SECRET_TEST', 'whsec_6nBwpHVq9UTUXLR35Co3aeTany2Q2FjE');

// Which mode are we running?
define('STRIPE_MODE', 'live');  // change to 'live' when ready
