<?php

function siteLogoHandler(string $method, string $uri = ''): void {
  if ($method === 'GET') {
    serveLogo();
  } elseif ($method === 'POST') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos($uri, '/site-logo/reset') !== false) {
      resetLogo();
    } else {
      uploadLogo();
    }
  } else {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
  }
}

function serveLogo(): void {
  $customPath = __DIR__ . '/../uploads/app-logo.png';
  $svgPath = __DIR__ . '/../uploads/app-logo.svg';
  $size = isset($_GET['size']) ? (int)$_GET['size'] : null;

  if (file_exists($customPath)) {
    $etag = md5_file($customPath);
    header('ETag: "' . $etag . '"');
    header('Cache-Control: public, max-age=86400');
    header('Content-Type: image/png');

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
      http_response_code(304);
      return;
    }

    readfile($customPath);
    return;
  }

  if (file_exists($svgPath)) {
    $etag = md5_file($svgPath);
    header('ETag: "' . $etag . '"');
    header('Cache-Control: public, max-age=86400');
    header('Content-Type: image/svg+xml');

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
      http_response_code(304);
      return;
    }
    readfile($svgPath);
    return;
  }

  // Default icon — serve the correct PNG size or fallback SVG
  if ($size === 192 || $size === 512) {
    $defaultPng = __DIR__ . '/../icons/icon-' . $size . '.png';
    if (file_exists($defaultPng)) {
      $etag = md5($size . '-default');
      header('ETag: "' . $etag . '"');
      header('Cache-Control: public, max-age=86400');
      header('Content-Type: image/png');
      readfile($defaultPng);
      return;
    }
  }

  $defaultSvg = __DIR__ . '/../icons/icon.svg';
  if (file_exists($defaultSvg)) {
    $etag = md5_file($defaultSvg);
    header('ETag: "' . $etag . '"');
    header('Cache-Control: public, max-age=86400');
    header('Content-Type: image/svg+xml');

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
      http_response_code(304);
      return;
    }
    readfile($defaultSvg);
    return;
  }

  http_response_code(404);
  echo 'Logo non trovato';
}

function uploadLogo(): void {
  $userId = siteLogoAuth();
  siteLogoRequireAdmin($userId);

  if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Nessun file caricato o errore di upload']);
    return;
  }

  $file = $_FILES['logo'];
  $allowedTypes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mime, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato non supportato. Usa PNG, JPG, SVG o WebP.']);
    return;
  }

  if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File troppo grande (max 5 MB)']);
    return;
  }

  $uploadDir = __DIR__ . '/../uploads';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $dest = $uploadDir . '/app-logo.png';

  // Convert to PNG if not already
  if ($mime === 'image/png') {
    move_uploaded_file($file['tmp_name'], $dest);
  } elseif ($mime === 'image/svg+xml') {
    copy($file['tmp_name'], $uploadDir . '/app-logo.svg');
    // Also try to create a PNG version from SVG (use a placeholder if GD doesn't support SVG)
    $dest = $uploadDir . '/app-logo.svg';
  } elseif ($mime === 'image/jpeg') {
    $img = imagecreatefromjpeg($file['tmp_name']);
    if ($img) {
      imagepng($img, $dest);
      imagedestroy($img);
    }
  } elseif ($mime === 'image/webp') {
    $img = imagecreatefromwebp($file['tmp_name']);
    if ($img) {
      imagepng($img, $dest);
      imagedestroy($img);
    }
  }

  // Remove old SVG fallback if PNG was created
  $oldSvg = $uploadDir . '/app-logo.svg';
  if (file_exists($dest) && file_exists($oldSvg) && $dest !== $oldSvg) {
    unlink($oldSvg);
  }

  echo json_encode(['success' => true, 'message' => 'Logo aggiornato con successo']);
}

function resetLogo(): void {
  $userId = siteLogoAuth();
  siteLogoRequireAdmin($userId);

  $pngPath = __DIR__ . '/../uploads/app-logo.png';
  $svgPath = __DIR__ . '/../uploads/app-logo.svg';

  if (file_exists($pngPath)) unlink($pngPath);
  if (file_exists($svgPath)) unlink($svgPath);

  echo json_encode(['success' => true, 'message' => 'Logo predefinito ripristinato']);
}

function siteLogoAuth(): int {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token mancante']);
    exit;
  }
  $token = substr($auth, 7);
  $db = siteLogoDb();
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

function siteLogoRequireAdmin(int $userId): void {
  $db = siteLogoDb();
  $stmt = $db->prepare('SELECT is_admin FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$userId]);
  $row = $stmt->fetch();
  if (!$row || !$row['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo l\'amministratore pu\u00f2 modificare il logo']);
    exit;
  }
}

function siteLogoDb(): PDO {
  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
  return new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

function manifestHandler(string $method = ''): void {
  header('Content-Type: application/json');
  echo json_encode([
    'name' => 'Carte Fedeltà',
    'short_name' => 'Carte',
    'description' => 'Gestisci le tue carte fedeltà con codici a barre',
    'start_url' => '../index.html',
    'display' => 'standalone',
    'background_color' => '#f5f5f5',
    'theme_color' => '#1a73e8',
    'orientation' => 'portrait',
    'scope' => '../',
    'icons' => [
      ['src' => '../icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
      ['src' => '../icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
      ['src' => '../icons/icon.svg', 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any'],
    ],
    'categories' => ['utilities', 'finance'],
  ], JSON_UNESCAPED_UNICODE);
}
