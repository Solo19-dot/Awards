<?php
require __DIR__ . '/../includes/bootstrap.php';

if (!empty($_SESSION['admin'])) {
    redirect('admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (time() < (int)($_SESSION['lock_until'] ?? 0)) {
        flash('error', 'Too many wrong attempts. Wait a minute and try again.');
        redirect('admin/index.php');
    }

    $hash = (string)cfg('admin_password_hash');
    $pw = (string)($_POST['password'] ?? '');

    if ($hash !== '' && password_verify($pw, $hash)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['fails'] = 0;
        redirect('admin/dashboard.php');
    }

    $_SESSION['fails'] = (int)($_SESSION['fails'] ?? 0) + 1;
    if ($_SESSION['fails'] >= 5) {
        $_SESSION['lock_until'] = time() + 60;
        $_SESSION['fails'] = 0;
    }
    usleep(900000);
    flash('error', 'Wrong password.');
    redirect('admin/index.php');
}

render_header('Admin login', ['noindex' => true]);
?>
<main class="container login-wrap">
  <div class="admin-card">
    <h1 class="h4 mb-3">Admin login</h1>
    <?php render_flash(); ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label class="form-label" for="password">Password</label>
      <input class="form-control mb-3" type="password" id="password" name="password" required autofocus>
      <button class="btn btn-flame w-100" type="submit">Log in</button>
    </form>
  </div>
</main>
<?php render_footer(); ?>
