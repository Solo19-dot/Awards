<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_auth.php';

// Open/close voting and publish results
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $key = (string)($_POST['setting'] ?? '');
    if (in_array($key, ['voting_open', 'results_public'], true)) {
        $val = (($_POST['value'] ?? '') === 'true') ? 'true' : 'false';
        try {
            set_setting($key, $val);
            $labels = ['voting_open' => 'Voting', 'results_public' => 'Public results'];
            flash('success', $labels[$key] . ($val === 'true' ? ' is now on.' : ' is now off.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect('admin/dashboard.php');
}

$error = null;
$votingOpen = $resultsPublic = false;
$paid = $pending = $catCount = $nomCount = 0;
$cats = [];
$recent = [];

try {
    $votingOpen = setting('voting_open') === 'true';
    $resultsPublic = setting('results_public') === 'true';
    $paid = sb_count('payments', ['status' => 'eq.paid']);
    $pending = sb_count('payments', ['status' => 'eq.pending']);
    $catCount = sb_count('categories');
    $nomCount = sb_count('nominees');
    $cats = results_by_category();
    $recent = sb_select('payments', ['select' => 'tx_ref,status,created_at,paid_at', 'order' => 'created_at.desc', 'limit' => 15]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = $e->getMessage();
}

render_header('Dashboard', ['noindex' => true]);
render_admin_nav('dashboard');
?>
<main class="container pb-5">
  <h1 class="admin-title">Dashboard</h1>
  <?php render_flash(); ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="admin-card"><div class="stat"><?= (int)$paid ?></div><div class="stat-label">Paid ballots</div></div></div>
    <div class="col-6 col-md-3"><div class="admin-card"><div class="stat"><?= (int)$pending ?></div><div class="stat-label">Waiting for payment</div></div></div>
    <div class="col-6 col-md-3"><div class="admin-card"><div class="stat"><?= (int)$catCount ?></div><div class="stat-label">Categories</div></div></div>
    <div class="col-6 col-md-3"><div class="admin-card"><div class="stat"><?= (int)$nomCount ?></div><div class="stat-label">Nominees</div></div></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="admin-card h-100">
        <h2 class="h6 mb-1">Voting</h2>
        <p class="text-secondary small">Currently <strong><?= $votingOpen ? 'open' : 'closed' ?></strong>. Voters can only pay and vote while it is open.</p>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="setting" value="voting_open">
          <input type="hidden" name="value" value="<?= $votingOpen ? 'false' : 'true' ?>">
          <button class="btn <?= $votingOpen ? 'btn-outline-dark' : 'btn-flame' ?>" type="submit"><?= $votingOpen ? 'Close voting' : 'Open voting' ?></button>
        </form>
      </div>
    </div>
    <div class="col-md-6">
      <div class="admin-card h-100">
        <h2 class="h6 mb-1">Public results</h2>
        <p class="text-secondary small">Currently <strong><?= $resultsPublic ? 'visible to everyone' : 'hidden' ?></strong> on the results page. Keep them hidden until the dinner.</p>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="setting" value="results_public">
          <input type="hidden" name="value" value="<?= $resultsPublic ? 'false' : 'true' ?>">
          <button class="btn <?= $resultsPublic ? 'btn-outline-dark' : 'btn-flame' ?>" type="submit"><?= $resultsPublic ? 'Hide results' : 'Publish results' ?></button>
          <a class="btn btn-link" href="<?= e(url('results.php')) ?>" target="_blank" rel="noopener">Open results page</a>
        </form>
      </div>
    </div>
  </div>

  <h2 class="h4 mb-3">Live results <span class="text-secondary fs-6 fw-normal">(only you can see this)</span></h2>
  <div class="admin-card mb-4">
    <?php if ($cats): render_results_blocks($cats); else: ?>
      <p class="mb-0 text-secondary">No categories yet. <a href="<?= e(url('admin/manage.php')) ?>">Add your first category</a>.</p>
    <?php endif; ?>
  </div>

  <h2 class="h4 mb-3">Recent ballots</h2>
  <div class="admin-card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Reference</th><th>Status</th><th>Started</th><th>Paid</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $p):
            $cls = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'secondary'][$p['status']] ?? 'secondary'; ?>
          <tr>
            <td><code><?= e($p['tx_ref']) ?></code></td>
            <td><span class="badge text-bg-<?= $cls ?>"><?= e($p['status']) ?></span></td>
            <td><?= e(fmt_time($p['created_at'])) ?></td>
            <td><?= e(fmt_time($p['paid_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="4" class="text-secondary">No ballots yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php render_footer(); ?>
