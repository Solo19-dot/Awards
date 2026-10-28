<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

// Basic throttle: one checkout attempt every few seconds per visitor
$now = time();
if (($now - (int)($_SESSION['last_checkout'] ?? 0)) < 4) {
    flash('error', 'Please wait a few seconds before trying again.');
    redirect('index.php');
}
$_SESSION['last_checkout'] = $now;

$paymentId = null;
try {
    if (setting('voting_open') !== 'true') {
        flash('error', 'Voting is closed.');
        redirect('index.php');
    }

    // Clean the picks: category id => nominee id
    $picks = $_POST['pick'] ?? [];
    $clean = [];
    if (is_array($picks)) {
        foreach ($picks as $cid => $nid) {
            $cid = (int)$cid;
            $nid = (int)$nid;
            if ($cid > 0 && $nid > 0) {
                $clean[$cid] = $nid;
            }
        }
    }
    if (!$clean) {
        flash('error', 'Pick at least one nominee before paying.');
        redirect('index.php');
    }

    // Every pick must be an active category and a nominee that belongs to it
    $activeCats = array_map('intval', array_column(
        sb_select('categories', ['is_active' => 'eq.true', 'select' => 'id']), 'id'));
    $noms = sb_select('nominees', [
        'id' => 'in.(' . implode(',', array_values($clean)) . ')',
        'select' => 'id,category_id',
    ]);
    $nomCat = [];
    foreach ($noms as $n) {
        $nomCat[(int)$n['id']] = (int)$n['category_id'];
    }
    foreach ($clean as $cid => $nid) {
        if (!in_array($cid, $activeCats, true) || ($nomCat[$nid] ?? 0) !== $cid) {
            flash('error', 'Your ballot had an invalid pick. Please choose again.');
            redirect('index.php');
        }
    }

    // Create the pending ballot. The amount always comes from the server config.
    $fee = (int)cfg('vote_fee');
    $txRef = 'IGN-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
    $payment = sb_insert('payments', [
        'tx_ref'   => $txRef,
        'amount'   => $fee,
        'currency' => cfg('currency'),
        'status'   => 'pending',
    ]);
    $paymentId = (int)$payment[0]['id'];

    $items = [];
    foreach ($clean as $cid => $nid) {
        $items[] = ['payment_id' => $paymentId, 'category_id' => $cid, 'nominee_id' => $nid];
    }
    sb_insert('vote_items', $items);

    // PayChangu sends the voter back to these addresses and adds ?tx_ref=...&status=...
    // itself, so they must NOT contain a query string. Both go to the same status page.
    // (The webhook address is set separately in the PayChangu dashboard.)
    $checkoutUrl = pc_create_checkout(
        $txRef,
        $fee,
        url('return.php'),
        url('return.php')
    );
    header('Location: ' . $checkoutUrl);
    exit;
} catch (Throwable $e) {
    error_log('Checkout failed: ' . $e->getMessage());
    if ($paymentId) {
        try {
            sb_update('payments', ['id' => 'eq.' . $paymentId], ['status' => 'failed']);
        } catch (Throwable $e2) {
            error_log($e2->getMessage());
        }
    }
    flash('error', 'We could not start your payment. Nothing was charged. Please try again.');
    redirect('index.php');
}