<?php
// Copy this file to config.php and fill in your own values.
// Never share config.php or commit it to GitHub.
return [
    'site_name'    => 'Ignite Blantyre Vote',
    // Full address of the site, no trailing slash. Subfolders are fine.
    'site_url'     => 'http://localhost/awards',

    // Flat fee for one ballot (one vote per category)
    'vote_fee'     => 500,
    'currency'     => 'MWK',

    // Supabase: Project Settings > API
    'supabase_url'         => 'https://kjtkszotopwafyexynab.supabase.co',
    'supabase_service_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtqdGtzem90b3B3YWZ5ZXh5bmFiIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc5MTgzMTkwNiwiZXhwIjoyMTA3NDA3OTA2fQ.N1_OgySrDKmSlF-Kn---aVM6W__ytkKxbyTpVtfc3k0',   // secret, server only

    // PayChangu dashboard: API & Webhooks
    'paychangu_secret_key'     => 'SEC-TEST-loZJJ9QQf01eqX5LQGBYa17l58ONO9jt',
    'paychangu_webhook_secret' => 'YOUR-WEBHOOK-SECRET',

    // Make a hash by running:
    // php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT);"
     'admin_password_hash' => '$2y$10$PT4vfTAU1C.p1wP0MeUA/.8YVe5W.VqeIgttVu6O2lPu60NXZVDHK',
    

    // Nominee photo upload limit in bytes (2 MB)
    'upload_max_bytes' => 2 * 1024 * 1024,
];
