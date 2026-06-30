<?php

session_start();

$passwordFile = __DIR__ . '/../../../admin_password.txt';
$scriptUrl = strtok($_SERVER['REQUEST_URI'], '?');

function getAdminPassword(): string {
  global $passwordFile;
  if (file_exists($passwordFile)) {
    return trim(file_get_contents($passwordFile));
  }
  return 'admin';
}

function isAdmin(): bool {
  return !empty($_SESSION['logos_admin']);
}

function requireAdmin(): void {
  if (!isAdmin()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
      http_response_code(401);
      echo json_encode(['error' => 'Non autorizzato']);
      exit;
    }
    showLogin();
    exit;
  }
}

function showLogin(): void {
  ?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Loghi - Carte Fedeltà</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.card { background: white; border-radius: 12px; padding: 32px; width: 340px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
h1 { font-size: 20px; margin-bottom: 20px; }
input { width: 100%; padding: 10px 14px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; margin-bottom: 12px; }
input:focus { outline: none; border-color: #1a73e8; }
button { width: 100%; padding: 10px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; }
button:hover { background: #1557b0; }
.error { color: #c5221f; font-size: 13px; margin-bottom: 12px; }
</style>
</head>
<body>
<div class="card">
<h1>Accesso Amministratore</h1>
<?php if (isset($_GET['error'])): ?><p class="error">Password errata</p><?php endif; ?>
<form method="post">
<input type="password" name="password" placeholder="Password amministratore" autofocus />
<button type="submit" name="login" value="1">Accedi</button>
</form>
</div>
</body>
</html><?php
}

if (isset($_POST['login'])) {
  if ($_POST['password'] === getAdminPassword()) {
    $_SESSION['logos_admin'] = true;
    header('Location: ' . $scriptUrl);
    exit;
  }
  header('Location: ' . $scriptUrl . '?error=1');
  exit;
}

if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: ' . $scriptUrl);
  exit;
}

requireAdmin();

require_once __DIR__ . '/../../logos.php';

$uploadDir = __DIR__ . '/../../../uploads/logos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ─── POST handlers ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json; charset=utf-8');

  if ($_POST['action'] === 'upload_cropped') {
    $storeName = trim($_POST['store_name'] ?? '');
    $imageData = $_POST['image_data'] ?? '';
    if (empty($storeName) || empty($imageData)) {
      echo json_encode(['error' => 'Dati mancanti']);
      exit;
    }
    $data = base64_decode($imageData);
    if ($data === false) {
      echo json_encode(['error' => 'Immagine non valida']);
      exit;
    }
    $sanitized = preg_replace('/[^a-zA-Z0-9_. -]/', '_', $storeName);
    $filename = $sanitized . '.webp';
    file_put_contents($uploadDir . $filename, $data);
    echo json_encode(['success' => true, 'filename' => $filename, 'store_name' => $storeName]);
    exit;
  }

  if ($_POST['action'] === 'delete') {
    $filename = trim($_POST['filename'] ?? '');
    if (empty($filename)) {
      echo json_encode(['error' => 'File mancante']);
      exit;
    }
    $path = $uploadDir . basename($filename);
    if (file_exists($path)) unlink($path);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($_POST['action'] === 'update_predefined') {
    $logos = getPredefinedLogos();
    $key = $_POST['key'] ?? '';
    if (isset($logos[$key])) {
      $color = $_POST['color'] ?? $logos[$key]['color'];
      $name = $_POST['name'] ?? $logos[$key]['name'];
      $overrides = [];
      $overridesFile = __DIR__ . '/../../../predefined_overrides.json';
      if (file_exists($overridesFile)) {
        $overrides = json_decode(file_get_contents($overridesFile), true) ?: [];
      }
      $overrides[$key] = ['name' => $name, 'color' => $color];
      file_put_contents($overridesFile, json_encode($overrides, JSON_PRETTY_PRINT));
      echo json_encode(['success' => true]);
      exit;
    }
    echo json_encode(['error' => 'Logo non trovato']);
    exit;
  }

  if ($_POST['action'] === 'change_password') {
    $newPass = $_POST['new_password'] ?? '';
    if (strlen($newPass) < 4) {
      echo json_encode(['error' => 'Password troppo corta (min 4 caratteri)']);
      exit;
    }
    file_put_contents($passwordFile, $newPass);
    echo json_encode(['success' => true]);
    exit;
  }

  echo json_encode(['error' => 'Azione sconosciuta']);
  exit;
}

// ─── HTML Admin Page ───
$logos = getPredefinedLogos();
$customFiles = is_dir($uploadDir) ? array_diff(scandir($uploadDir), ['.', '..']) : [];
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$apiPos = strpos($scriptPath, '/api');
if ($apiPos !== false) {
  $scriptPath = substr($scriptPath, 0, $apiPos);
}
$baseUrl = $scheme . '://' . $host . $scriptPath;

?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Loghi - Carte Fedeltà</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; padding: 20px; color: #333; }
.container { max-width: 900px; margin: 0 auto; }
h1 { font-size: 22px; margin-bottom: 4px; }
.desc { color: #666; font-size: 14px; margin-bottom: 24px; }
.card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.card h2 { font-size: 16px; margin-bottom: 16px; }
.header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.header-bar a { color: #666; font-size: 13px; text-decoration: none; }
.header-bar a:hover { text-decoration: underline; }
.btn { padding: 8px 16px; border-radius: 6px; border: none; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: #1a73e8; color: white; }
.btn-primary:hover { background: #1557b0; }
.btn-warning { background: #f59e0b; color: white; }
.btn-warning:hover { background: #d97706; }
.btn-danger { background: #dc2626; color: white; }
.btn-danger:hover { background: #b91c1c; }
.btn-success { background: #16a34a; color: white; }
.btn-success:hover { background: #15803d; }
.btn-outline { background: none; border: 2px solid #ddd; color: #333; }
.btn-outline:hover { border-color: #999; }
.btn-sm { padding: 4px 12px; font-size: 12px; }
.toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 10px 20px; border-radius: 8px; font-size: 13px; z-index: 9999; display: none; }
.toast.show { display: block; }

/* Upload area */
.upload-area { border: 2px dashed #ddd; border-radius: 10px; padding: 40px 20px; text-align: center; cursor: pointer; transition: border-color 0.2s; }
.upload-area:hover, .upload-area.dragover { border-color: #1a73e8; background: #f0f6ff; }
.upload-area .icon { font-size: 40px; margin-bottom: 8px; }
.upload-area p { font-size: 14px; color: #666; }
.upload-area input[type="file"] { display: none; }

/* Crop modal */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9998; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: white; border-radius: 12px; width: 90%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #eee; }
.modal-header h3 { font-size: 16px; }
.modal-header .close { background: none; border: none; font-size: 22px; cursor: pointer; color: #999; padding: 4px; }
.modal-body { flex: 1; overflow: hidden; padding: 20px; min-height: 300px; }
.modal-body .img-container { max-width: 100%; max-height: 50vh; }
.modal-body img { max-width: 100%; display: block; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 16px 20px; border-top: 1px solid #eee; }
.modal-footer .info { flex: 1; font-size: 12px; color: #999; align-self: center; }

/* Logo grid */
.logo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
.logo-item { border: 2px solid #eee; border-radius: 8px; padding: 12px; text-align: center; position: relative; }
.logo-item .preview { width: 56px; height: 56px; border-radius: 8px; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 700; color: white; overflow: hidden; }
.logo-item .preview img { width: 100%; height: 100%; object-fit: contain; }
.logo-item .name { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
.logo-item .key { font-size: 11px; color: #999; margin-bottom: 8px; }
.logo-item .size { font-size: 10px; color: #999; }
.logo-item .del-btn { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(220,38,38,0.9); color: white; border: none; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; }
.logo-item:hover .del-btn { opacity: 1; }
</style>
</head>
<body>
<div class="container">
  <div class="header-bar">
    <div>
      <h1>Gestione Loghi</h1>
      <p class="desc">URL backend: <code><?= htmlspecialchars($baseUrl) ?></code></p>
    </div>
    <a href="?logout=1">Esci</a>
  </div>

  <!-- Upload -->
  <div class="card">
    <h2>Carica logo personalizzato</h2>
    <p style="font-size:13px;color:#666;margin-bottom:12px">I loghi verranno automaticamente compressi in WebP (formato 8:5, max 320×200 px) per occupare poco spazio e caricare velocemente.</p>
    <div class="upload-area" id="dropArea">
      <div class="icon">🖼</div>
      <p>Clicca o trascina un'immagine qui<br><small>PNG, JPG, SVG, WebP</small></p>
      <input type="file" id="fileInput" accept="image/*" />
    </div>
    <div style="margin-top:12px">
      <input type="text" id="storeNameInput" placeholder="Nome negozio (es. Conad)" style="width:100%;padding:10px 14px;border:2px solid #ddd;border-radius:8px;font-size:14px" />
    </div>
  </div>

  <!-- Custom logos -->
  <div class="card">
    <h2>Loghi personalizzati <?php if ($customFiles): ?><span style="font-weight:400;color:#999">(<?= count($customFiles) ?>)</span><?php endif; ?></h2>
    <?php if ($customFiles): ?>
    <div class="logo-grid">
      <?php $totalSize = 0; foreach ($customFiles as $f):
        $size = filesize($uploadDir . $f);
        $totalSize += $size;
        $sizeHuman = $size < 1024 ? $size . ' B' : round($size / 1024, 1) . ' KB';
        $storeName = pathinfo($f, PATHINFO_FILENAME);
      ?>
      <div class="logo-item">
        <div class="preview"><img src="../../../uploads/logos/<?= htmlspecialchars($f) ?>" alt="" /></div>
        <div class="name"><?= htmlspecialchars($storeName) ?></div>
        <div class="size"><?= $sizeHuman ?></div>
        <button class="del-btn" onclick="deleteLogo('<?= htmlspecialchars($f) ?>')" title="Elimina">✕</button>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="font-size:12px;color:#999;margin-top:12px">Spazio occupato: <?= $totalSize < 1024 ? $totalSize . ' B' : round($totalSize / 1024, 1) . ' KB' ?></p>
    <?php else: ?>
    <p style="font-size:14px;color:#999">Nessun logo personalizzato ancora caricato.</p>
    <?php endif; ?>
  </div>

  <!-- Predefined logos -->
  <div class="card">
    <h2>Loghi predefiniti</h2>
    <div class="logo-grid">
      <?php foreach ($logos as $key => $logo): ?>
      <div class="logo-item">
        <div class="preview" style="background:<?= htmlspecialchars($logo['color']) ?>"><?= strtoupper($logo['name'][0]) ?></div>
        <div class="name"><?= htmlspecialchars($logo['name']) ?></div>
        <div class="key"><?= htmlspecialchars($key) ?></div>
        <form method="post" style="display:flex;gap:4px;margin-top:4px" onsubmit="return updateColor(event, '<?= htmlspecialchars($key) ?>')">
          <input type="color" name="color" value="<?= htmlspecialchars($logo['color']) ?>" style="flex:1;height:28px;padding:2px;border:1px solid #ddd;border-radius:4px;cursor:pointer" />
          <button type="submit" class="btn btn-outline btn-sm">Salva</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Password -->
  <div class="card">
    <h2>Cambia password amministratore</h2>
    <div style="display:flex;gap:8px;max-width:400px">
      <input type="password" id="newPass" placeholder="Nuova password (min 4 caratteri)" style="flex:1;padding:8px 12px;border:2px solid #ddd;border-radius:6px;font-size:13px" />
      <button class="btn btn-warning" onclick="changePass()">Cambia</button>
    </div>
  </div>
</div>

<!-- Crop modal -->
<div class="modal-overlay" id="cropModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Ritaglia logo</h3>
      <button class="close" onclick="closeCrop()">✕</button>
    </div>
    <div class="modal-body">
      <div class="img-container">
        <img id="cropImage" src="" alt="" />
      </div>
    </div>
    <div class="modal-footer">
      <span class="info">Zoom con rotellina · trascina per ritagliare · formato orizzontale 8:5</span>
      <button class="btn btn-outline" onclick="closeCrop()">Annulla</button>
      <button class="btn btn-primary" id="saveCropBtn">Salva logo</button>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
let cropper = null;
let currentFile = null;

// Upload area
const dropArea = document.getElementById('dropArea');
const fileInput = document.getElementById('fileInput');

dropArea.addEventListener('click', () => fileInput.click());
dropArea.addEventListener('dragover', (e) => { e.preventDefault(); dropArea.classList.add('dragover'); });
dropArea.addEventListener('dragleave', () => dropArea.classList.remove('dragover'));
dropArea.addEventListener('drop', (e) => {
  e.preventDefault();
  dropArea.classList.remove('dragover');
  if (e.dataTransfer.files.length) openCrop(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => {
  if (fileInput.files.length) openCrop(fileInput.files[0]);
});

function openCrop(file) {
  currentFile = file;
  const reader = new FileReader();
  reader.onload = (e) => {
    const img = document.getElementById('cropImage');
    img.src = e.target.result;
    document.getElementById('cropModal').classList.add('open');
    if (cropper) cropper.destroy();
    cropper = new Cropper(img, {
      aspectRatio: 8 / 5,
      viewMode: 1,
      dragMode: 'move',
      cropBoxResizable: true,
      cropBoxMovable: true,
      minContainerWidth: 300,
      minContainerHeight: 300,
    });
  };
  reader.readAsDataURL(file);
}

function closeCrop() {
  document.getElementById('cropModal').classList.remove('open');
  if (cropper) { cropper.destroy(); cropper = null; }
  fileInput.value = '';
}

document.getElementById('saveCropBtn').addEventListener('click', async () => {
  if (!cropper) return;
  const storeName = document.getElementById('storeNameInput').value.trim();
  if (!storeName) {
    toast('Inserisci il nome del negozio');
    return;
  }

  const canvas = cropper.getCroppedCanvas({
    width: 320,
    height: 200,
    fillColor: '#fff',
    imageSmoothingQuality: 'high',
  });

  const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', 0.7));
  if (!blob) { toast('Errore compressione immagine'); return; }

  const reader = new FileReader();
  reader.onload = async () => {
    const base64 = reader.result.split(',')[1];
    const fd = new FormData();
    fd.append('action', 'upload_cropped');
    fd.append('store_name', storeName);
    fd.append('image_data', base64);

    const saveBtn = document.getElementById('saveCropBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Salvataggio...';

    try {
      const res = await fetch('', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        toast('Logo salvato! (' + (blob.size < 1024 ? blob.size + ' B' : (blob.size / 1024).toFixed(1) + ' KB') + ')');
        closeCrop();
        setTimeout(() => location.reload(), 800);
      } else {
        toast('Errore: ' + (data.error || 'sconosciuto'));
      }
    } catch (e) {
      toast('Errore di rete');
    } finally {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Salva logo';
    }
  };
  reader.readAsDataURL(blob);
});

async function deleteLogo(filename) {
  if (!confirm('Eliminare questo logo?')) return;
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('filename', filename);
  const res = await fetch('', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) { toast('Logo eliminato'); location.reload(); }
  else { toast('Errore: ' + (data.error || 'sconosciuto')); }
}

async function updateColor(e, key) {
  e.preventDefault();
  const color = e.target.querySelector('[name=color]').value;
  const fd = new FormData();
  fd.append('action', 'update_predefined');
  fd.append('key', key);
  fd.append('color', color);
  const res = await fetch('', { method: 'POST', body: fd });
  const data = await res.json();
  toast(data.success ? 'Colore aggiornato!' : 'Errore');
  return false;
}

async function changePass() {
  const pass = document.getElementById('newPass').value;
  if (pass.length < 4) { toast('Password troppo corta'); return; }
  const fd = new FormData();
  fd.append('action', 'change_password');
  fd.append('new_password', pass);
  const res = await fetch('', { method: 'POST', body: fd });
  const data = await res.json();
  toast(data.success ? 'Password cambiata!' : 'Errore');
  document.getElementById('newPass').value = '';
}

function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg; el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3000);
}
</script>
</body>
</html><?php
