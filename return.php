<?php
// The voter lands here after paying (or cancelling). PayChangu adds ?tx_ref=...&status=...
// We never trust the status in the address: we always verify with PayChangu's API.
require __DIR__ . '/includes/bootstrap.php';

$ref = (string)($_GET['tx_ref'] ?? ($_GET['ref'] ?? ''));
$urlStatus = (string)($_GET['status'] ?? '');
$state = 'unknown';
$picks = [];

if (preg_match('/^IGN-[0-9]{6}-[A-F0-9]{12}$/', $ref)) {
    try {
        $state = settle_payment($ref);
        if ($state === 'paid') {
            $p = sb_select('payments', ['tx_ref' => 'eq.' . $ref, 'select' => 'id']);
            if ($p) {
                $picks = sb_select('vote_items', [
                    'payment_id' => 'eq.' . $p[0]['id'],
                    'select'     => 'categories(name),nominees(name)',
                ]);
            }
        } elseif ($state === 'pending' && $urlStatus === 'failed') {
            // PayChangu sent the voter back after a cancel or repeated failures
            $state = 'failed';
        }
    } catch (Throwable $e) {
        error_log('Return check failed: ' . $e->getMessage());
        $state = 'error';
    }
}

render_header('Payment status', [
    'refresh' => $state === 'pending' ? 6 : 0,
    'noindex' => true,
]);
render_band();
?>
<main class="container status-wrap">
  <div class="status-card">
  <?php if ($state === 'paid'): ?>
    <h1>Your vote is counted</h1>
    <p>Payment confirmed. Thank you for voting.</p>
    <?php if ($picks): ?>
      <h2 class="h6 mt-4">Your ballot</h2>
      <ul class="ballot-list">
        <?php foreach ($picks as $row): ?>
          <li><span><?= e($row['categories']['name'] ?? '') ?></span><strong><?= e($row['nominees']['name'] ?? '') ?></strong></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="text-secondary small mt-3 mb-0">Reference: <?= e($ref) ?></p>

  <?php elseif ($state === 'pending'): ?>
    <h1>Confirming your payment</h1>
    <p>We have not received the payment yet. This page checks again every few seconds. If you are still approving the payment on your phone, finish that first.</p>
    <p class="text-secondary small mb-0">Reference: <?= e($ref) ?></p>

  <?php elseif ($state === 'failed'): ?>
    <h1>The payment was not completed</h1>
    <p>Your vote has not been counted. If money left your wallet, wait a minute and refresh this page before trying again.</p>
    <p class="text-secondary small mb-0">Reference: <?= e($ref) ?></p>

  <?php elseif ($state === 'error'): ?>
    <h1>We could not check your payment</h1>
    <p>If money left your wallet, do not pay again. Refresh this page in a minute and it will update.</p>
    <p class="text-secondary small mb-0">Reference: <?= e($ref) ?></p>

  <?php else: ?>
    <h1>We could not find that payment</h1>
    <p>Check the link, or go back and start a new ballot.</p>
  <?php endif; ?>

  <?php if ($state !== 'paid'): ?>
    <a class="btn btn-outline-dark mt-3" href="<?= e(url('index.php')) ?>">Back to the ballot</a>
  <?php endif; ?>
  </div>
</main>
<?php render_footer(); ?>