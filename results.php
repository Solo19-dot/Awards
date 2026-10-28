<?php
require __DIR__ . '/includes/bootstrap.php';

$public = false;
$cats = [];
$error = null;
try {
    $public = setting('results_public') === 'true';
    if ($public) {
        $cats = results_by_category();
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'We could not load the results. Refresh in a moment.';
}

render_header('Results');
render_band();
?>
<main class="container py-4" style="max-width:820px">
  <h1 class="page-title">Results</h1>
  <?php if ($error): ?>
    <div class="notice"><p class="mb-0"><?= e($error) ?></p></div>
  <?php elseif (!$public): ?>
    <div class="notice">
      <h2>Results are not public yet</h2>
      <p class="mb-0">Winners are announced at the Ignite dinner in December.</p>
    </div>
  <?php else: ?>
    <?php render_results_blocks($cats); ?>
  <?php endif; ?>
</main>
<?php render_footer(); ?>
