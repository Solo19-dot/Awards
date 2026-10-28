<?php
require __DIR__ . '/includes/bootstrap.php';

// Picks one of five avatar colours from the nominee's name (used when there is no photo)
function avatar_class(string $name): string
{
    return 'av-' . (abs(crc32($name)) % 5);
}

$fee = (int)cfg('vote_fee');
$feeLabel = cfg('currency') . ' ' . number_format($fee);
$open = false;
$resultsPublic = false;
$loadError = null;
$cats = [];
$nomsByCat = [];

// Optional: shows a "closes on" line and countdown. Voting itself is still closed from the admin.
$closesRaw = trim((string)cfg('voting_closes'));
$closesTs = $closesRaw !== '' ? strtotime($closesRaw) : false;

try {
    $open = setting('voting_open') === 'true';
    $resultsPublic = setting('results_public') === 'true';
    if ($open) {
        $cats = sb_select('categories', ['is_active' => 'eq.true', 'select' => '*', 'order' => 'sort_order.asc,id.asc']);
        $noms = sb_select('nominees', ['select' => '*', 'order' => 'id.asc']);
        foreach ($noms as $n) {
            $nomsByCat[$n['category_id']][] = $n;
        }
        // Only show categories that have nominees
        $cats = array_values(array_filter($cats, fn($c) => !empty($nomsByCat[$c['id']])));
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $loadError = 'We could not load the ballot. Refresh the page in a moment.';
}

$showBallot = $open && !$loadError && count($cats) > 0;
$showCloses = $showBallot && $closesTs && $closesTs > time();

render_header('Vote', ['body_class' => $showBallot ? 'ballot' : '']);
?>
<header class="hero">
  <div class="hero-bars" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
  <div class="container hero-inner">
    <p class="hero-org">Word Alive Ministries International, Ignite Blantyre</p>
    <h1 class="stack" aria-label="Built for more">
      <span class="brick brick-1" aria-hidden="true">Built</span>
      <span class="brick brick-2" aria-hidden="true">for</span>
      <span class="brick brick-3" aria-hidden="true">more</span>
    </h1>
    <p class="hero-lead">Vote for this year's Ignite nominees. Winners are announced at the Ignite dinner in December.</p>
    <?php if ($showBallot): ?>
    <div class="hero-actions">
      <a class="btn btn-flame btn-lg" href="#ballot">Start voting</a>
      <?php if ($showCloses): ?>
        <p class="closes" data-closes="<?= e(date('c', $closesTs)) ?>">
          <span>Voting closes <?= e(date('j F, H:i', $closesTs)) ?></span>
          <span class="closes-left" hidden></span>
        </p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</header>

<?php if ($showBallot): ?>
<div class="container steps-wrap">
  <ol class="steps">
    <li>
      <span class="step-n">1</span>
      <div><strong>Pick your nominees</strong><span class="sub">One per category. Skip any you like.</span></div>
    </li>
    <li>
      <span class="step-n">2</span>
      <div><strong>Review your ballot</strong><span class="sub">Check your picks before you pay.</span></div>
    </li>
    <li>
      <span class="step-n">3</span>
      <div><strong>Pay <?= e($feeLabel) ?> once</strong><span class="sub">Airtel Money or Mpamba. It covers every pick.</span></div>
    </li>
  </ol>
</div>
<?php endif; ?>

<main id="ballot">
  <div class="container">
    <?php render_flash(); ?>

    <?php if ($loadError): ?>
      <div class="closed-card"><h2>Something went wrong</h2><p><?= e($loadError) ?></p></div>

    <?php elseif (!$open): ?>
      <div class="closed-card">
        <h2>Voting is not open right now</h2>
        <p>Check back soon. Voting opens before the Ignite dinner.</p>
        <?php if ($resultsPublic): ?><a class="btn btn-flame" href="<?= e(url('results.php')) ?>">See the results</a><?php endif; ?>
      </div>

    <?php elseif (!$cats): ?>
      <div class="closed-card">
        <h2>The ballot is being set up</h2>
        <p>Categories and nominees will appear here shortly.</p>
      </div>
    <?php endif; ?>
  </div>

<?php if ($showBallot): ?>
  <form id="ballot-form" method="post" action="<?= e(url('checkout.php')) ?>" data-total="<?= count($cats) ?>" data-label="Pay <?= e($feeLabel) ?> and vote">
    <?= csrf_field() ?>

    <nav class="cat-nav" aria-label="Categories">
      <div class="container">
        <div class="chips" id="chips">
          <?php foreach ($cats as $c): ?>
            <a class="chip" href="#category-<?= (int)$c['id'] ?>" data-cat="<?= (int)$c['id'] ?>">
              <svg class="tick" width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3.2 3L13 4.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span><?= e($c['name']) ?></span>
              <span class="visually-hidden chip-done-label"></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </nav>

    <div class="container">
      <?php foreach ($cats as $c): ?>
      <section class="category" id="category-<?= (int)$c['id'] ?>" data-cat="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>">
        <div class="category-side">
          <h2><?= e($c['name']) ?></h2>
          <?php if (!empty($c['description'])): ?><p><?= e($c['description']) ?></p><?php endif; ?>
          <p class="category-status" aria-live="polite">
            <span class="status-dot" aria-hidden="true"></span>
            <span class="status-text">No pick yet</span>
          </p>
        </div>

        <div class="nominee-grid" role="radiogroup" aria-label="<?= e($c['name']) ?>">
          <?php foreach ($nomsByCat[$c['id']] as $n): ?>
          <label class="nominee">
            <input class="nominee-input visually-hidden" type="radio"
                   name="pick[<?= (int)$c['id'] ?>]" value="<?= (int)$n['id'] ?>"
                   data-cat="<?= (int)$c['id'] ?>" data-name="<?= e($n['name']) ?>">
            <span class="nominee-card">
              <span class="nominee-media">
                <?php if (!empty($n['photo_path'])): ?>
                  <img class="nominee-photo" src="<?= e(url($n['photo_path'])) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="nominee-avatar <?= e(avatar_class($n['name'])) ?>" aria-hidden="true"><?= e(initials($n['name'])) ?></span>
                <?php endif; ?>
                <span class="nominee-badge" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3.2 3L13 4.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Your pick
                </span>
              </span>
              <span class="nominee-body">
                <span class="nominee-name"><?= e($n['name']) ?></span>
                <?php if (!empty($n['bio'])): ?><span class="nominee-bio"><?= e($n['bio']) ?></span><?php endif; ?>
              </span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>
    </div>

    <div class="ballot-bar">
      <div class="ballot-progress" aria-hidden="true"><div class="ballot-progress-fill" id="progress-fill"></div></div>
      <div class="container ballot-bar-inner">
        <div>
          <div class="ballot-count"><span id="picked-count">0</span> of <?= count($cats) ?> categories picked</div>
          <div class="ballot-hint" id="picked-hint">Pick a nominee to start your ballot.</div>
        </div>
        <button type="button" id="review-btn" class="btn btn-flame btn-lg" data-bs-toggle="offcanvas" data-bs-target="#review-sheet" aria-controls="review-sheet" disabled>Review ballot</button>
      </div>
    </div>

    <div class="offcanvas offcanvas-bottom ballot-sheet" tabindex="-1" id="review-sheet" aria-labelledby="review-title">
      <div class="offcanvas-header">
        <div class="sheet-inner d-flex align-items-center justify-content-between">
          <h2 class="offcanvas-title" id="review-title">Review your ballot</h2>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
      </div>
      <div class="offcanvas-body">
        <div class="sheet-inner">
          <div id="review-list"></div>
          <p class="review-skipped" id="review-skipped"></p>
          <div class="review-total">
            <span>One payment covers every pick</span>
            <strong><?= e($feeLabel) ?></strong>
          </div>
          <button type="submit" id="pay-btn" class="btn btn-flame btn-lg w-100">Pay <?= e($feeLabel) ?> and vote</button>
          <p class="sheet-note">You will finish on a secure payment page with Airtel Money or Mpamba.</p>
        </div>
      </div>
    </div>
  </form>
<?php endif; ?>
</main>

<footer class="site-footer">
  <div class="container">
    <strong>Built for more</strong>
    <span>Word Alive Ministries International, Ignite Blantyre</span>
  </div>
</footer>
<?php render_footer(['scripts' => $showBallot || $showCloses ? ['assets/js/ballot.js'] : []]); ?>