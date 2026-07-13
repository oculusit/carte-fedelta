<?php

if (!function_exists('jsonBody')) {
  function jsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw) {
      $data = json_decode($raw, true);
      if (is_array($data) && count($data) > 0) return $data;
    }
    if (!empty($_POST)) return $_POST;
    return [];
  }
}

function logosGetDb(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  }
  return $pdo;
}

function logosAuthenticate(): int {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token mancante']);
    exit;
  }
  $token = substr($auth, 7);
  $db = logosGetDb();
  $stmt = $db->prepare('SELECT user_id FROM ' . TABLE_AUTH_TOKENS . ' WHERE token = ?');
  $stmt->execute([$token]);
  $row = $stmt->fetch();
  if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'Token non valido']);
    exit;
  }
  return (int)$row['user_id'];
}

/**
 * Loghi predefiniti (modificabili dall'utente).
 * Le immagini SVG sono incluse come base64 inline in modo da non
 * dover gestire file esterni per i loghi predefiniti.
 */
function getPredefinedLogos(): array {
  return [
    'conad'    => ['name' => 'Conad', 'color' => '#e31b23'],
    'coop'     => ['name' => 'Coop', 'color' => '#0066a1'],
    'esselunga' => ['name' => 'Esselunga', 'color' => '#d52b1e'],
    'carrefour' => ['name' => 'Carrefour', 'color' => '#004990'],
    'auchan'   => ['name' => 'Auchan', 'color' => '#e2001a'],
    'decathlon' => ['name' => 'Decathlon', 'color' => '#003a70'],
    'mediaworld' => ['name' => 'MediaWorld', 'color' => '#d71921'],
    'unieuro'  => ['name' => 'Unieuro', 'color' => '#e30613'],
    'euronics' => ['name' => 'Euronics', 'color' => '#ed1c24'],
    'pam'      => ['name' => 'PAM', 'color' => '#00843d'],
    'lidl'     => ['name' => 'Lidl', 'color' => '#0050aa'],
    'aldi'     => ['name' => 'Aldi', 'color' => '#003d7a'],
    'tigota'   => ['name' => 'Tigotà', 'color' => '#e6007e'],
    'acquafoli' => ['name' => 'Acqua & Sapone', 'color' => '#0088ce'],
    'bennet'   => ['name' => 'Bennet', 'color' => '#e30613'],
    'iper'     => ['name' => 'Iper', 'color' => '#e31b23'],
    'famila'   => ['name' => 'Famila', 'color' => '#00843d'],
    'interspar' => ['name' => 'Interspar', 'color' => '#e30613'],
    'despar'   => ['name' => 'Despar', 'color' => '#e30613'],
    'simply'   => ['name' => 'Simply', 'color' => '#00ae41'],
  ];
}

function getPredefinedHandler(string $method, string $uri): void {
  if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $logos = getPredefinedLogos();
  echo json_encode($logos);
}

function handleLogosHandler(string $method, string $uri): void {
  $userId = logosAuthenticate();
  $db = logosGetDb();

  if ($method === 'POST') {
    // Upload logo personalizzato
    if (empty($_FILES['logo'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Nessun file inviato']);
      return;
    }

    $file = $_FILES['logo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

    if (!in_array($ext, $allowed)) {
      http_response_code(400);
      echo json_encode(['error' => 'Formato non supportato (jpg, png, gif, svg, webp)']);
      return;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
      http_response_code(400);
      echo json_encode(['error' => 'File troppo grande (max 5MB)']);
      return;
    }

    $uploadDir = UPLOAD_DIR;
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    $filename = $userId . '_' . uniqid() . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
      http_response_code(500);
      echo json_encode(['error' => 'Errore durante il salvataggio']);
      return;
    }

    // Associa il logo all'utente/store
    $storeName = $_POST['store_name'] ?? '';
    if (!empty($storeName)) {
      $stmt = $db->prepare('INSERT INTO ' . TABLE_CUSTOM_LOGOS . ' (user_id, store_name, filename) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE filename = ?');
      $stmt->execute([$userId, $storeName, $filename, $filename]);
    }

    echo json_encode([
      'success' => true,
      'filename' => $filename,
      'url' => 'uploads/logos/' . $filename,
    ]);
  } elseif ($method === 'GET') {
    // Recupera loghi personalizzati dell'utente
    $stmt = $db->prepare('SELECT store_name, filename FROM ' . TABLE_CUSTOM_LOGOS . ' WHERE user_id = ?');
    $stmt->execute([$userId]);
    $logos = $stmt->fetchAll();
    echo json_encode($logos);
  } else {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
  }
}

function getStoreLogoHandler(string $method, string $uri): void {
  if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  preg_match('#/logos/(.+)#', $uri, $m);
  $storeName = $m[1] ?? '';
  $storeName = urldecode($storeName);
  if (empty($storeName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nome negozio mancante']);
    return;
  }

  // Check filesystem first (admin panel saves here) — try multiple extensions
  $uploadDir = __DIR__ . '/../uploads/logos/';
  $safeName = str_replace(['/', '\\', "\0"], '_', $storeName);
  $fsPath = null;
  if (is_dir($uploadDir)) {
    foreach (['webp','png','jpg','jpeg','svg'] as $ext) {
      $candidate = $uploadDir . $safeName . '.' . $ext;
      if (file_exists($candidate)) { $fsPath = $candidate; break; }
    }
    // Case-insensitive fallback
    if (!$fsPath) {
      $dh = opendir($uploadDir);
      if ($dh) {
        $lower = strtolower($safeName);
        while (($f = readdir($dh)) !== false) {
          if (strtolower(pathinfo($f, PATHINFO_FILENAME)) === $lower && preg_match('/\.(webp|png|jpe?g|svg)$/i', $f)) {
            $fsPath = $uploadDir . $f;
            break;
          }
        }
        closedir($dh);
      }
    }
  }
  if ($fsPath && file_exists($fsPath)) {
    $data = file_get_contents($fsPath);
    $ext = strtolower(pathinfo($fsPath, PATHINFO_EXTENSION));
    $mime = 'image/' . ($ext === 'svg' ? 'svg+xml' : ($ext === 'jpg' ? 'jpeg' : $ext));
    $logoData = 'data:' . $mime . ';base64,' . base64_encode($data);
    echo json_encode([
      'store_name' => $storeName,
      'color' => null,
      'logo_type' => 'upload',
      'logo_data' => $logoData,
    ]);
    return;
  }

  // Then check predefined logos
  $normalized = strtolower(preg_replace('/\s+/', '', $storeName));
  $predefined = getPredefinedLogos();
  if (isset($predefined[$normalized])) {
    // Check if user hidden this predefined logo
    $hiddenFile = $uploadDir . $normalized . '.hidden';
    if (!file_exists($hiddenFile)) {
      echo json_encode([
        'store_name' => $storeName,
        'color' => $predefined[$normalized]['color'],
        'logo_type' => 'predefined',
        'logo_data' => null,
      ]);
      return;
    }
  }

  // Then check database
  try {
    $db = logosGetDb();
    $stmt = $db->prepare('SELECT store_name, filename FROM ' . TABLE_CUSTOM_LOGOS . ' WHERE store_name = ? LIMIT 1');
    $stmt->execute([$storeName]);
    $custom = $stmt->fetch();
    if ($custom && !empty($custom['filename'])) {
      $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : 'uploads/logos/';
      $path = __DIR__ . '/../' . $uploadDir . $custom['filename'];
      if (file_exists($path)) {
        $data = file_get_contents($path);
        $ext = pathinfo($custom['filename'], PATHINFO_EXTENSION);
        $mime = 'image/' . ($ext === 'svg' ? 'svg+xml' : $ext);
        $logoData = 'data:' . $mime . ';base64,' . base64_encode($data);
        echo json_encode([
          'store_name' => $storeName,
          'color' => null,
          'logo_type' => 'upload',
          'logo_data' => $logoData,
        ]);
        return;
      }
    }
  } catch (Exception $e) {}

  // Then check stores table (match by name or aliases)
  try {
    $db = logosGetDb();
    $lowerName = strtolower($storeName);
    $stmt = $db->prepare('SELECT name, aliases, logo_type, logo_data, logo_path FROM ' . TABLE_STORES . ' WHERE status = ?');
    $stmt->execute(['approved']);
    $stores = $stmt->fetchAll();
    foreach ($stores as $s) {
      $match = strtolower($s['name']) === $lowerName;
      if (!$match && !empty($s['aliases'])) {
        $aliasList = array_map('trim', explode("\n", $s['aliases']));
        foreach ($aliasList as $alias) {
          if (strtolower($alias) === $lowerName) {
            $match = true;
            break;
          }
        }
      }
      if ($match) {
        // Prefer logo_data (base64), fall back to logo_path file
        if (!empty($s['logo_data']) && preg_match('/^data:image\//', $s['logo_data'])) {
          echo json_encode([
            'store_name' => $storeName,
            'color' => null,
            'logo_type' => $s['logo_type'] ?: 'upload',
            'logo_data' => $s['logo_data'],
          ]);
          return;
        }
        if (!empty($s['logo_path'])) {
          $lp = __DIR__ . '/../uploads/logos/' . $s['logo_path'];
          if (file_exists($lp)) {
            $data = file_get_contents($lp);
            $ext = strtolower(pathinfo($lp, PATHINFO_EXTENSION));
            $mime = 'image/' . ($ext === 'svg' ? 'svg+xml' : ($ext === 'jpg' ? 'jpeg' : $ext));
            $logoData = 'data:' . $mime . ';base64,' . base64_encode($data);
            echo json_encode([
              'store_name' => $storeName,
              'color' => null,
              'logo_type' => $s['logo_type'] ?: 'upload',
              'logo_data' => $logoData,
            ]);
            return;
          }
        }
      }
    }
  } catch (Exception $e) {}

  http_response_code(404);
  echo json_encode(['error' => 'Logo non trovato']);
}

function submitLogoHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $data = jsonBody();
  $storeName = trim($data['store_name'] ?? '');
  $imageData = $data['image_data'] ?? '';

  if (empty($storeName) || empty($imageData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nome negozio e immagine obbligatori']);
    return;
  }

  $decoded = base64_decode($imageData, true);
  if ($decoded === false || strlen($decoded) < 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Immagine non valida']);
    return;
  }

  $db = logosGetDb();
  $userId = null;
  try {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (strpos($auth, 'Bearer ') === 0) {
      $token = substr($auth, 7);
      $stmt = $db->prepare('SELECT user_id FROM ' . TABLE_AUTH_TOKENS . ' WHERE token = ?');
      $stmt->execute([$token]);
      $row = $stmt->fetch();
      if ($row) $userId = (int)$row['user_id'];
    }
  } catch (Exception $e) {}

  $stmt = $db->prepare('INSERT INTO ' . TABLE_PENDING_LOGOS . ' (user_id, store_name, image_data) VALUES (?, ?, ?)');
  $stmt->execute([$userId, $storeName, $imageData]);

  echo json_encode([
    'success' => true,
    'message' => 'Logo inviato per approvazione. Sarà disponibile dopo la revisione di un amministratore.',
  ]);
}

function reportMissingHandler(string $method): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $input = json_decode(file_get_contents('php://input'), true);
  $storeName = $input['store_name'] ?? '';
  if (empty($storeName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nome negozio mancante']);
    return;
  }
  try {
    $db = logosGetDb();
    // Create missing_logos table if needed
    $db->exec("
      CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "missing_logos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_name VARCHAR(255) NOT NULL,
        reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved TINYINT(1) DEFAULT 0,
        UNIQUE KEY unique_store (store_name)
      )
    ");
    $table = DB_PREFIX . 'missing_logos';
    $stmt = $db->prepare("INSERT IGNORE INTO $table (store_name) VALUES (?)");
    $stmt->execute([$storeName]);
    echo json_encode(['success' => true]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
  }
}
