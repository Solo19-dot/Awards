<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain');

$hash = (string)cfg('admin_password_hash');
echo 'Hash length (should be 60): ' . strlen($hash) . "\n";
echo 'Hash starts with (should be $2y$10$): ' . substr($hash, 0, 7) . "\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = password_verify((string)($_POST['pw'] ?? ''), $hash);
    echo 'Password matches: ' . ($ok ? 'YES' : 'NO') . "\n";
}
?>

<form method="post">
  Type your password: <input type="text" name="pw"> <button>Test</button>
</form>