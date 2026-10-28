<?php
// PayChangu calls this URL when a payment changes state (set it in the PayChangu dashboard).
// 1) Check the signature, 2) verify the payment with PayChangu's API, 3) mark the ballot paid.
require __DIR__ . '/includes/bootstrap.php';

$raw = file_get_contents('php://input') ?: '';
$sig = (string)($_SERVER['HTTP_SIGNATURE'] ?? '');
$expected = hash_hmac('sha256', $raw, (string)cfg('paychangu_webhook_secret'));

if ($sig === '' || !hash_equals($expected, $sig)) {
    http_response_code(401);
    exit('Invalid signature');
}

$payload = json_decode($raw, true);
$candidates = [];
if (is_array($payload)) {
    foreach ([
        $payload['tx_ref'] ?? null,
        $payload['reference'] ?? null,
        $payload['data']['tx_ref'] ?? null,
        $payload['data']['reference'] ?? null,
    ] as $c) {
        // Only act on references this site created
        if (is_string($c) && preg_match('/^IGN-[0-9]{6}-[A-F0-9]{12}$/', $c)) {
            $candidates[$c] = true;
        }
    }
}

foreach (array_keys($candidates) as $txRef) {
    try {
        settle_payment($txRef);
    } catch (Throwable $e) {
        error_log('Webhook settle failed: ' . $e->getMessage());
        http_response_code(500); // PayChangu retries on anything other than 200
        exit('Error');
    }
}

http_response_code(200);
echo 'ok';