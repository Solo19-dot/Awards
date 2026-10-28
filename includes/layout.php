<?php
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function render_flash(): void
{
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $cls = $f['type'] === 'error' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info');
        echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
            . e($f['msg'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
}

function render_header(string $title, array $opts = []): void
{
    $refresh = (int)($opts['refresh'] ?? 0);
    $bodyClass = (string)($opts['body_class'] ?? '');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($refresh): ?><meta http-equiv="refresh" content="<?= $refresh ?>"><?php endif; ?>
<meta name="robots" content="<?= !empty($opts['noindex']) ? 'noindex,nofollow' : 'index,follow' ?>">
<title><?= e($title) ?> | <?= e(cfg('site_name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Unbounded:wght@600;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

function render_footer(array $opts = []): void
{
    ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($opts['scripts'] ?? [] as $s): ?>
<script src="<?= e(url($s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

function render_admin_nav(string $active): void
{
    $links = [
        'dashboard' => ['admin/dashboard.php', 'Dashboard'],
        'manage'    => ['admin/manage.php', 'Categories and nominees'],
    ];
    ?>
<nav class="navbar navbar-expand-md admin-nav">
  <div class="container">
    <span class="navbar-brand text-white fw-bold">Ignite admin</span>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto">
        <?php foreach ($links as $key => [$path, $label]): ?>
        <li class="nav-item"><a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">View voting page</a></li>
      </ul>
      <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="d-flex">
        <?= csrf_field() ?>
        <button class="btn btn-outline-light btn-sm" type="submit">Log out</button>
      </form>
    </div>
  </div>
</nav>
<?php
}

// Used by the public results page and the admin dashboard
function render_results_blocks(array $cats): void
{
    foreach ($cats as $c) {
        echo '<section class="result-block"><h2>' . e($c['name']) . '</h2>';
        if (!$c['nominees']) {
            echo '<p class="text-secondary mb-0">No nominees yet.</p></section>';
            continue;
        }
        $max = (int)$c['nominees'][0]['total_votes'];
        foreach ($c['nominees'] as $n) {
            $v = (int)$n['total_votes'];
            $pct = $c['total'] > 0 ? round($v / $c['total'] * 100) : 0;
            $width = $max > 0 ? round($v / $max * 100) : 0;
            $lead = $max > 0 && $v === $max;
            echo '<div class="result-row' . ($lead ? ' is-lead' : '') . '">';
            echo '<div class="d-flex justify-content-between gap-3"><span class="result-name">' . e($n['name'])
                . ($lead ? ' <span class="badge text-bg-warning ms-1">Leading</span>' : '') . '</span>';
            echo '<span class="result-count">' . $v . ' ' . ($v === 1 ? 'vote' : 'votes') . ' (' . $pct . '%)</span></div>';
            echo '<div class="bar" role="img" aria-label="' . e($n['name']) . ' has ' . $pct . ' percent of votes"><div class="bar-fill" style="width:' . $width . '%"></div></div>';
            echo '</div>';
        }
        echo '</section>';
    }
}

// Slim top band for the secondary pages (status, results)
function render_band(): void
{
    echo '<div class="band"><div class="container">'
        . '<a class="band-brand" href="' . e(url('index.php')) . '">Built for more</a>'
        . '<span class="band-org">Ignite Blantyre</span></div></div>';
}
