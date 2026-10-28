<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_auth.php';

const PHOTO_DIR = 'uploads/nominees/';

// Saves an uploaded nominee photo. Returns the stored path, or null if no file was chosen.
function handle_photo_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('That photo is too large. Use one under 2 MB.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The photo upload failed. Try again.');
    }
    if ($f['size'] > (int)cfg('upload_max_bytes')) {
        throw new RuntimeException('That photo is too large. Use one under 2 MB.');
    }
    $info = @getimagesize($f['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Use a JPG, PNG or WebP photo.');
    }
    $dir = dirname(__DIR__) . '/' . PHOTO_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Could not create the uploads folder.');
    }
    $name = bin2hex(random_bytes(10)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) {
        throw new RuntimeException('Could not save the photo. Check the uploads folder permissions.');
    }
    return PHOTO_DIR . $name;
}

function delete_photo_file(?string $path): void
{
    if ($path && strpos($path, PHOTO_DIR) === 0) {
        $file = dirname(__DIR__) . '/' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function is_fk_error(Throwable $e): bool
{
    return stripos($e->getMessage(), 'foreign key') !== false;
}

$open = (int)($_GET['open'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $open = (int)($_POST['open'] ?? 0);

    try {
        switch ($action) {
            case 'add_category':
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') throw new RuntimeException('Category name is required.');
                $desc = trim((string)($_POST['description'] ?? ''));
                $row = sb_insert('categories', [
                    'name' => $name,
                    'description' => $desc !== '' ? $desc : null,
                    'sort_order' => (int)($_POST['sort_order'] ?? 0),
                ]);
                $open = (int)$row[0]['id'];
                flash('success', 'Category added. Now add its nominees below.');
                break;

            case 'update_category':
                $id = (int)$_POST['id'];
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') throw new RuntimeException('Category name is required.');
                $desc = trim((string)($_POST['description'] ?? ''));
                sb_update('categories', ['id' => 'eq.' . $id], [
                    'name' => $name,
                    'description' => $desc !== '' ? $desc : null,
                    'sort_order' => (int)($_POST['sort_order'] ?? 0),
                    'is_active' => isset($_POST['is_active']),
                ]);
                $open = $id;
                flash('success', 'Category saved.');
                break;

            case 'delete_category':
                $id = (int)$_POST['id'];
                $photos = sb_select('nominees', ['category_id' => 'eq.' . $id, 'select' => 'photo_path']);
                try {
                    sb_delete('categories', ['id' => 'eq.' . $id]);
                    foreach ($photos as $p) delete_photo_file($p['photo_path'] ?? null);
                    flash('success', 'Category deleted.');
                } catch (RuntimeException $e) {
                    if (!is_fk_error($e)) throw $e;
                    flash('error', 'This category already has votes, so it cannot be deleted. Untick "Show on voting page" to hide it instead.');
                    $open = $id;
                    break;
                }
                $open = 0;
                break;

            case 'add_nominee':
                $cid = (int)$_POST['category_id'];
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') throw new RuntimeException('Nominee name is required.');
                $bio = trim((string)($_POST['bio'] ?? ''));
                $photo = handle_photo_upload('photo');
                try {
                    sb_insert('nominees', [
                        'category_id' => $cid,
                        'name' => $name,
                        'bio' => $bio !== '' ? $bio : null,
                        'photo_path' => $photo,
                    ]);
                } catch (Throwable $e) {
                    delete_photo_file($photo);
                    throw $e;
                }
                $open = $cid;
                flash('success', 'Nominee added.');
                break;

            case 'update_nominee':
                $id = (int)$_POST['id'];
                $cid = (int)$_POST['category_id'];
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') throw new RuntimeException('Nominee name is required.');
                $bio = trim((string)($_POST['bio'] ?? ''));
                $existing = sb_select('nominees', ['id' => 'eq.' . $id, 'select' => 'photo_path']);
                $oldPhoto = $existing[0]['photo_path'] ?? null;
                $row = ['name' => $name, 'bio' => $bio !== '' ? $bio : null];
                $newPhoto = handle_photo_upload('photo');
                if ($newPhoto) $row['photo_path'] = $newPhoto;
                try {
                    sb_update('nominees', ['id' => 'eq.' . $id], $row);
                } catch (Throwable $e) {
                    delete_photo_file($newPhoto);
                    throw $e;
                }
                if ($newPhoto) delete_photo_file($oldPhoto);
                $open = $cid;
                flash('success', 'Nominee saved.');
                break;

            case 'delete_nominee':
                $id = (int)$_POST['id'];
                $cid = (int)$_POST['category_id'];
                $existing = sb_select('nominees', ['id' => 'eq.' . $id, 'select' => 'photo_path']);
                try {
                    sb_delete('nominees', ['id' => 'eq.' . $id]);
                    delete_photo_file($existing[0]['photo_path'] ?? null);
                    flash('success', 'Nominee deleted.');
                } catch (RuntimeException $e) {
                    if (!is_fk_error($e)) throw $e;
                    flash('error', 'This nominee already has votes, so they cannot be deleted.');
                }
                $open = $cid;
                break;
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        flash('error', $e->getMessage());
    }
    redirect('admin/manage.php' . ($open ? '?open=' . $open : ''));
}

$error = null;
$cats = [];
$nomsByCat = [];
try {
    $cats = sb_select('categories', ['select' => '*', 'order' => 'sort_order.asc,id.asc']);
    foreach (sb_select('nominees', ['select' => '*', 'order' => 'id.asc']) as $n) {
        $nomsByCat[$n['category_id']][] = $n;
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = $e->getMessage();
}

render_header('Categories and nominees', ['noindex' => true]);
render_admin_nav('manage');
?>
<main class="container pb-5">
  <h1 class="admin-title">Categories and nominees</h1>
  <?php render_flash(); ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <div class="admin-card mb-4">
    <h2 class="h6 mb-3">Add a category</h2>
    <form method="post" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_category">
      <div class="col-md-4">
        <label class="form-label" for="new-cat-name">Name</label>
        <input class="form-control" id="new-cat-name" name="name" maxlength="120" required placeholder="Example: Youth of the Year">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="new-cat-desc">Description (optional)</label>
        <input class="form-control" id="new-cat-desc" name="description" maxlength="300">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label" for="new-cat-order">Order</label>
        <input class="form-control" id="new-cat-order" type="number" name="sort_order" value="<?= count($cats) + 1 ?>">
      </div>
      <div class="col-6 col-md-2">
        <button class="btn btn-flame w-100" type="submit">Add category</button>
      </div>
    </form>
  </div>

  <?php if (!$cats): ?>
    <p class="text-secondary">No categories yet. Add one above, then add its nominees.</p>
  <?php endif; ?>

  <div class="accordion" id="cats">
  <?php foreach ($cats as $c):
      $cid = (int)$c['id'];
      $list = $nomsByCat[$cid] ?? [];
      $isOpen = $open === $cid; ?>
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button <?= $isOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse"
                data-bs-target="#cat-<?= $cid ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="cat-<?= $cid ?>">
          <span class="fw-semibold"><?= e($c['name']) ?></span>
          <span class="badge text-bg-light border ms-2"><?= count($list) ?> <?= count($list) === 1 ? 'nominee' : 'nominees' ?></span>
          <?php if (!$c['is_active']): ?><span class="badge text-bg-secondary ms-2">Hidden</span><?php endif; ?>
        </button>
      </h2>
      <div id="cat-<?= $cid ?>" class="accordion-collapse collapse <?= $isOpen ? 'show' : '' ?>" data-bs-parent="#cats">
        <div class="accordion-body">

          <form method="post" class="row g-2 align-items-end mb-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="id" value="<?= $cid ?>">
            <div class="col-md-4">
              <label class="form-label" for="cn-<?= $cid ?>">Name</label>
              <input class="form-control" id="cn-<?= $cid ?>" name="name" value="<?= e($c['name']) ?>" maxlength="120" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="cd-<?= $cid ?>">Description</label>
              <input class="form-control" id="cd-<?= $cid ?>" name="description" value="<?= e($c['description'] ?? '') ?>" maxlength="300">
            </div>
            <div class="col-4 col-md-1">
              <label class="form-label" for="co-<?= $cid ?>">Order</label>
              <input class="form-control" id="co-<?= $cid ?>" type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>">
            </div>
            <div class="col-8 col-md-3">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="ca-<?= $cid ?>" name="is_active" <?= $c['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="ca-<?= $cid ?>">Show on voting page</label>
              </div>
              <button class="btn btn-flame btn-sm" type="submit">Save category</button>
            </div>
          </form>
          <form method="post" class="mb-4" onsubmit="return confirm('Delete this category and all its nominees? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="id" value="<?= $cid ?>">
            <button class="btn btn-link btn-sm text-danger p-0" type="submit">Delete category</button>
          </form>

          <h3 class="h6">Nominees</h3>
          <?php if (!$list): ?><p class="text-secondary">No nominees yet. Add the first one below.</p><?php endif; ?>

          <?php foreach ($list as $n): $nid = (int)$n['id']; ?>
            <div class="border-top py-3">
              <div class="d-flex align-items-center gap-3">
                <?php if (!empty($n['photo_path'])): ?>
                  <img class="admin-thumb" src="<?= e(url($n['photo_path'])) ?>" alt="">
                <?php else: ?>
                  <span class="admin-thumb-empty" aria-hidden="true"><?= e(initials($n['name'])) ?></span>
                <?php endif; ?>
                <div class="flex-grow-1">
                  <div class="fw-semibold"><?= e($n['name']) ?></div>
                  <?php if (!empty($n['bio'])): ?><div class="text-secondary small"><?= e($n['bio']) ?></div><?php endif; ?>
                </div>
                <button class="btn btn-outline-dark btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#nom-<?= $nid ?>" aria-expanded="false" aria-controls="nom-<?= $nid ?>">Edit</button>
                <form method="post" onsubmit="return confirm('Delete this nominee?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_nominee">
                  <input type="hidden" name="id" value="<?= $nid ?>">
                  <input type="hidden" name="category_id" value="<?= $cid ?>">
                  <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                </form>
              </div>
              <div class="collapse mt-3" id="nom-<?= $nid ?>">
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_nominee">
                  <input type="hidden" name="id" value="<?= $nid ?>">
                  <input type="hidden" name="category_id" value="<?= $cid ?>">
                  <div class="col-md-4">
                    <label class="form-label" for="nn-<?= $nid ?>">Name</label>
                    <input class="form-control" id="nn-<?= $nid ?>" name="name" value="<?= e($n['name']) ?>" maxlength="120" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="nb-<?= $nid ?>">Short bio (optional)</label>
                    <input class="form-control" id="nb-<?= $nid ?>" name="bio" value="<?= e($n['bio'] ?? '') ?>" maxlength="200">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="np-<?= $nid ?>">Replace photo (optional)</label>
                    <input class="form-control" id="np-<?= $nid ?>" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                  </div>
                  <div class="col-12"><button class="btn btn-flame btn-sm" type="submit">Save nominee</button></div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="border-top pt-3 mt-2">
            <h3 class="h6">Add a nominee to <?= e($c['name']) ?></h3>
            <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add_nominee">
              <input type="hidden" name="category_id" value="<?= $cid ?>">
              <div class="col-md-4">
                <label class="form-label" for="an-<?= $cid ?>">Name</label>
                <input class="form-control" id="an-<?= $cid ?>" name="name" maxlength="120" required>
              </div>
              <div class="col-md-3">
                <label class="form-label" for="ab-<?= $cid ?>">Short bio (optional)</label>
                <input class="form-control" id="ab-<?= $cid ?>" name="bio" maxlength="200">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="ap-<?= $cid ?>">Photo (optional)</label>
                <input class="form-control" id="ap-<?= $cid ?>" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
              </div>
              <div class="col-md-2"><button class="btn btn-flame w-100" type="submit">Add nominee</button></div>
            </form>
          </div>

        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</main>
<?php render_footer(); ?>
