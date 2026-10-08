<?php
require __DIR__ . '/config.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
if (!is_dir(TOUR_DIR)) mkdir(TOUR_DIR, 0755, true);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid request.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        if (hash_equals(ADMIN_PASSWORD, $_POST['password'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
        } else {
            sleep(1);
            $err = 'Wrong password.';
        }

    } elseif (!empty($_SESSION['admin'])) {

        if ($action === 'logout') {
            session_destroy();
            header('Location: admin.php');
            exit;
        }

        if ($action === 'upload') {
            $files  = $_FILES['photo'] ?? null;
            $base   = mb_substr(trim($_POST['caption'] ?? ''), 0, 90);
            $isPano = !empty($_POST['pano']);
            $types  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            $count  = $files ? count($files['name']) : 0;

            if ($base === '') {
                $err = 'Please enter a caption.';
            } elseif ($count === 0 || $files['error'][0] === UPLOAD_ERR_NO_FILE) {
                $err = 'Please choose at least one photo.';
            } else {
                $photos = load_photos();
                $ok = 0;
                $errors = [];

                for ($k = 0; $k < $count; $k++) {
                    $label = $files['name'][$k];

                    if ($files['error'][$k] !== UPLOAD_ERR_OK) {
                        $errors[] = "$label: upload failed (it may exceed the server limit).";
                        continue;
                    }
                    if ($files['size'][$k] > MAX_BYTES) {
                        $errors[] = "$label: larger than 5 MB.";
                        continue;
                    }
                    $info = @getimagesize($files['tmp_name'][$k]);
                    if (!$info || !isset($types[$info[2]])) {
                        $errors[] = "$label: not a JPG, PNG, or WEBP image.";
                        continue;
                    }
                    if ($isPano) {
                        $ratio = $info[0] / max(1, $info[1]);
                        if ($ratio < 1.9 || $ratio > 2.1) {
                            $errors[] = "$label: a 360° photo must be about 2:1 (twice as wide as tall).";
                            continue;
                        }
                    }
                    $name = bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
                    if (!move_uploaded_file($files['tmp_name'][$k], TOUR_DIR . $name)) {
                        $errors[] = "$label: could not be saved. Check folder permissions.";
                        continue;
                    }
                    $photos[] = [
                        'file'    => $name,
                        'caption' => $count > 1 ? $base . ' ' . ($k + 1) : $base,
                        'type'    => $isPano ? 'pano' : 'photo',
                    ];
                    $ok++;
                }

                save_photos($photos);
                if ($ok)     $msg = "$ok photo(s) uploaded.";
                if ($errors) $err = implode(' ', $errors);
            }
        }

        if ($action === 'delete') {
            $target = basename($_POST['file'] ?? '');
            $photos = array_filter(load_photos(), fn($p) => $p['file'] !== $target);
            save_photos($photos);
            if ($target !== '' && is_file(TOUR_DIR . $target)) unlink(TOUR_DIR . $target);
            $msg = 'Photo deleted.';
        }

        if ($action === 'move') {
            $photos = load_photos();
            $i = (int)($_POST['index'] ?? -1);
            $j = $i + ($_POST['dir'] === 'up' ? -1 : 1);
            if (isset($photos[$i], $photos[$j])) {
                [$photos[$i], $photos[$j]] = [$photos[$j], $photos[$i]];
                save_photos($photos);
            }
        }
    }
}

$isAdmin = !empty($_SESSION['admin']);
$photos  = $isAdmin ? load_photos() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Tour Photos Admin</title>
<style>
  body { font-family: Arial, sans-serif; background: #eef0f4; margin: 0; padding: 24px; color: #1a1a1a; }
  .card { max-width: 720px; margin: 0 auto 20px; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.12); }
  h1 { margin: 0 0 16px; font-size: 24px; color: #16337a; }
  h2 { margin: 0 0 12px; font-size: 18px; }
  input[type=text], input[type=password], input[type=file] { width: 100%; padding: 10px; margin: 6px 0 14px; border: 1px solid #b8bfd0; border-radius: 6px; box-sizing: border-box; }
  button { background: #16337a; color: #fff; border: 0; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
  button.danger { background: #b3261e; }
  button.small { padding: 6px 10px; font-size: 13px; }
  .ok { background: #e6f4ea; color: #1e6b34; padding: 10px; border-radius: 6px; margin-bottom: 14px; }
  .bad { background: #fdecea; color: #b3261e; padding: 10px; border-radius: 6px; margin-bottom: 14px; }
  .row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid #e3e6ee; }
  .row img { width: 90px; height: 60px; object-fit: cover; border-radius: 6px; }
  .row .cap { flex: 1; font-weight: bold; }
  .row form { display: inline; }
  .top { display: flex; justify-content: space-between; align-items: center; }
  .badge { background: #16337a; color: #fff; font-size: 11px; padding: 2px 7px; border-radius: 10px; margin-left: 6px; }
  label.check { display: flex; gap: 8px; align-items: center; margin-bottom: 14px; }
  label.check input { width: auto; margin: 0; }
  .hint { font-size: 13px; color: #555; margin: -6px 0 14px; }
</style>
</head>
<body>

<?php if (!$isAdmin): ?>
  <div class="card" style="max-width:380px">
    <h1>Marketing Login</h1>
    <?php if ($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="login">
      <label>Password</label>
      <input type="password" name="password" required autofocus>
      <button type="submit">Log in</button>
    </form>
  </div>

<?php else: ?>
  <div class="card">
    <div class="top">
      <h1>Virtual Tour Photos</h1>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="logout">
        <button class="small" type="submit">Log out</button>
      </form>
    </div>

    <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

    <h2>Add a photo</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="upload">
      <label>Photos (JPG, PNG, or WEBP, max 5 MB each). You can select several at once.</label>
      <input type="file" name="photo[]" accept="image/jpeg,image/png,image/webp" multiple required>
      <label>Caption</label>
      <input type="text" name="caption" maxlength="90" placeholder="e.g. Library" required>
      <p class="hint">If you upload several photos, a number is added automatically (Library 1, Library 2, ...). You can reorder them below.</p>
      <label class="check"><input type="checkbox" name="pano" value="1"> These are 360&deg; panorama photos (must be 2:1, twice as wide as tall)</label>
      <button type="submit">Upload</button>
    </form>
  </div>

  <div class="card">
    <h2>Current photos (<?= count($photos) ?>)</h2>
    <p class="hint">Photos appear in the tour in this order.</p>
    <?php if (!$photos): ?>
      <p>No photos yet.</p>
    <?php endif; ?>
    <?php foreach ($photos as $i => $p): ?>
      <div class="row">
        <img src="<?= h(TOUR_URL . $p['file']) ?>" alt="">
        <div class="cap"><?= h($p['caption']) ?><?php if (($p['type'] ?? 'photo') === 'pano'): ?> <span class="badge">360&deg;</span><?php endif; ?></div>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="index" value="<?= $i ?>">
          <button class="small" name="dir" value="up" <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
          <button class="small" name="dir" value="down" <?= $i === count($photos) - 1 ? 'disabled' : '' ?>>&darr;</button>
        </form>

        <form method="post" onsubmit="return confirm('Delete this photo?');">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="file" value="<?= h($p['file']) ?>">
          <button class="small danger" type="submit">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</body>
</html>
