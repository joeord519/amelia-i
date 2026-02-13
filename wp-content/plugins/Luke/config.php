<?php
// config.php for Luke
define('OPENAI_API_KEY', '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');

// TODO: put your real Stripe secret key here:
define('STRIPE_SECRET_KEY', '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');

// Map Luke program slugs → Stripe Price IDs
// Replace these with your actual Stripe price IDs from the dashboard.
const LUKE_STRIPE_PRICES = [
    'ppl_50x50'      => 'price_1234567890abcd', // New Student 50x50 Pack
    'ppl_full'       => 'price_2345678901bcde', // Private Pilot Full Program
    'discovery_flight' => 'price_3456789012cdef', // Discovery flight
];
