<?php
// Include after bootstrap.php on every admin page that needs a logged-in admin.
header('Cache-Control: no-store');
if (empty($_SESSION['admin'])) {
    redirect('admin/index.php');
}
