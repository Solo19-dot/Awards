<?php
// PayChangu: hosted checkout + server-side verification.
// Docs: https://developer.paychangu.com

function pc_request(string $method, string $path, ?array $body = null): array
{
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . cfg('paychangu_secret_key'),
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $ch = curl_init('https://api.paychangu.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Could not reach PayChangu: ' . $err);
    }
    curl_close($ch);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Unexpected response from PayChangu: ' . substr($raw, 0, 200));
    }
    return $data;
}

// Returns the PayChangu checkout URL to send the voter to.
function pc_create_checkout(string $txRef, int $amount, string $returnUrl, string $callbackUrl): string
{
    $res = pc_request('POST', '/payment', [
        'amount'       => (string)$amount,
        'currency'     => cfg('currency'),
        'tx_ref'       => $txRef,
        'callback_url' => $callbackUrl,
        'return_url'   => $returnUrl,
        'customization' => [
            'title'       => 'Ignite Vote',
            'description' => 'One ballot, one vote per category',
        ],
    ]);
    $checkout = $res['data']['checkout_url'] ?? null;
    if (!$checkout) {
        throw new RuntimeException('PayChangu did not return a checkout link: ' . json_encode($res));
    }
    return $checkout;
}

function pc_verify(string $txRef): array
{
    return pc_request('GET', '/verify-payment/' . rawurlencode($txRef));
}

// Checks the things PayChangu says to check before giving the customer value.
function pc_payment_is_valid(array $res, string $txRef, float $expectedAmount, string $currency): bool
{
    if (($res['status'] ?? '') !== 'success') return false;
    $d = $res['data'] ?? [];
    if (($d['status'] ?? '') !== 'success') return false;
    if ((string)($d['tx_ref'] ?? '') !== $txRef) return false;
    if (strtoupper((string)($d['currency'] ?? '')) !== strtoupper($currency)) return false;
    return (float)($d['amount'] ?? 0) >= $expectedAmount;
}

// Verifies with PayChangu and marks the ballot paid. Safe to call many times
// (webhook + return page can both call it): the update only touches pending rows.
// Returns: 'paid' | 'pending' | 'unknown'
function settle_payment(string $txRef): string
{
    $rows = sb_select('payments', ['tx_ref' => 'eq.' . $txRef, 'select' => '*']);
    if (!$rows) return 'unknown';
    $p = $rows[0];
    if ($p['status'] === 'paid') return 'paid';
    if ($p['status'] === 'failed') return 'unknown';

    $res = pc_verify($txRef);
    if (pc_payment_is_valid($res, $txRef, (float)$p['amount'], (string)$p['currency'])) {
        sb_update('payments',
            ['tx_ref' => 'eq.' . $txRef, 'status' => 'eq.pending'],
            ['status' => 'paid', 'paid_at' => gmdate('c')]);
        return 'paid';
    }
    return 'pending';
}
