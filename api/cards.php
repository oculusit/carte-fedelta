<?php

// Ensure family table constants are defined (for existing configs without them)
if (!defined('TABLE_FAMILY_MEMBERS')) {
  define('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');
}
if (!defined('TABLE_CARD_GROUP_SHARES')) {
  define('TABLE_CARD_GROUP_SHARES', DB_PREFIX . 'card_group_shares');
}
if (!defined('TABLE_SETTINGS')) {
  define('TABLE_SETTINGS', DB_PREFIX . 'settings');
}
if (!defined('TABLE_USERS')) {
  define('TABLE_USERS', DB_PREFIX . 'users');
}
define('ENC_PREFIX', '##ENC##');

function getEncryptionSeed(): ?string {
  static $seed = null;
  static $loaded = false;
  if ($loaded) return $seed;
  try {
    $db = cardsGetDb();
    $stmt = $db->prepare('SELECT `value` FROM ' . TABLE_SETTINGS . ' WHERE `key` = ?');
    $stmt->execute(['encryption_seed']);
    $row = $stmt->fetch();
    $seed = $row ? trim($row['value']) : null;
    if ($seed === '') $seed = null;
  } catch (\Exception $e) {
    $seed = null;
  }
  $loaded = true;
  return $seed;
}

function encryptCardNumber(string $number, string $seed): string {
  $key = hash('sha256', $seed, true);
  $iv = openssl_random_pseudo_bytes(16);
  $encrypted = openssl_encrypt($number, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  return ENC_PREFIX . base64_encode($iv . $encrypted);
}

function decryptCardNumber(string $encrypted, string $seed): ?string {
  if (!str_starts_with($encrypted, ENC_PREFIX)) return $encrypted;
  $raw = base64_decode(substr($encrypted, strlen(ENC_PREFIX)));
  if ($raw === false || strlen($raw) < 16) return null;
  $iv = substr($raw, 0, 16);
  $ciphertext = substr($raw, 16);
  $result = @openssl_decrypt($ciphertext, 'aes-256-cbc', hash('sha256', $seed, true), OPENSSL_RAW_DATA, $iv);
  return $result !== false ? $result : null;
}

function decryptCardNumberSafe(string $encrypted, ?string $seed): string {
  if (!$seed) return $encrypted;
  $decrypted = decryptCardNumber($encrypted, $seed);
  return $decrypted !== null ? $decrypted : $encrypted;
}

function cardsGetDb(): PDO {
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

function cardsJsonBody(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function cardsAuthenticate(): int {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token mancante']);
    exit;
  }
  $token = substr($auth, 7);
  $db = cardsGetDb();
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

function handleCardsHandler(string $method, string $uri): void {
  $userId = cardsAuthenticate();
  $db = cardsGetDb();

  // Parse ID from URI: /cards/123
  $parts = explode('/', trim($uri, '/'));
  $cardId = $parts[1] ?? null;

  switch ($method) {
    case 'GET':
      if ($cardId) {
        getCard($db, $userId, $cardId);
      } else {
        getCards($db, $userId);
      }
      break;
    case 'POST':
      createCard($db, $userId);
      break;
    case 'PUT':
      if (!$cardId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID carta mancante']);
        return;
      }
      updateCard($db, $userId, $cardId);
      break;
    case 'DELETE':
      if (!$cardId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID carta mancante']);
        return;
      }
      deleteCard($db, $userId, $cardId);
      break;
    default:
      http_response_code(405);
      echo json_encode(['error' => 'Metodo non consentito']);
  }
}

function handleBatchHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $userId = cardsAuthenticate();
  $db = cardsGetDb();
  $data = cardsJsonBody();
  $action = $data['action'] ?? '';
  $cards = $data['cards'] ?? [];

  if (!is_array($cards)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato non valido']);
    return;
  }

  $imported = 0;
  $errors = [];

  if ($action === 'import') {
    $seed = getEncryptionSeed();
      $stmt = $db->prepare(
        'INSERT INTO ' . TABLE_CARDS . ' (id, user_id, store_name, card_number, holder_name, barcode_type, logo_type, logo_path, logo_data, notes, is_private, color, is_favorite) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
      );
      foreach ($cards as $card) {
        try {
          $cardNumber = $seed ? encryptCardNumber($card['card_number'] ?? '', $seed) : ($card['card_number'] ?? '');
          $stmt->execute([
            $card['id'] ?? bin2hex(random_bytes(16)),
            $userId,
            $card['store_name'] ?? '',
            $cardNumber,
            $card['holder_name'] ?? '',
            $card['barcode_type'] ?? 'CODE128',
            $card['logo_type'] ?? 'none',
            $card['logo_path'] ?? '',
            $card['logo_data'] ?? null,
            $card['notes'] ?? '',
            !empty($card['is_private']) ? 1 : 0,
            $card['color'] ?? '#ffffff',
            !empty($card['is_favorite']) ? 1 : 0,
          ]);
          $imported++;
        } catch (Exception $e) {
          $errors[] = $e->getMessage();
        }
      }
  } elseif ($action === 'export') {
    $stmt = $db->prepare('SELECT * FROM ' . TABLE_CARDS . ' WHERE user_id = ?');
    $stmt->execute([$userId]);
    $allCards = $stmt->fetchAll();
    $seed = getEncryptionSeed();
    if ($seed) {
      foreach ($allCards as &$c) {
        $c['card_number'] = decryptCardNumberSafe($c['card_number'], $seed);
      }
    }
    echo json_encode(['cards' => $allCards]);
    return;
  } else {
    http_response_code(400);
    echo json_encode(['error' => 'Azione non specificata (import/export)']);
    return;
  }

  echo json_encode(['imported' => $imported, 'errors' => $errors]);
}

function getCards(PDO $db, int $userId): void {
  // Own cards + non-private cards of accepted family group members
  $stmt = $db->prepare("
    SELECT c.*, u.email AS owner_email FROM " . TABLE_CARDS . " c
    LEFT JOIN " . TABLE_USERS . " u ON c.user_id = u.id
    WHERE c.user_id = ?
       OR (c.is_private = 0 AND c.user_id IN (
            SELECT fgm.user_id FROM " . TABLE_FAMILY_MEMBERS . " fgm
            WHERE fgm.group_id IN (
              SELECT fgm2.group_id FROM " . TABLE_FAMILY_MEMBERS . " fgm2
              WHERE fgm2.user_id = ? AND fgm2.status = 'accepted'
            )
            AND fgm.status = 'accepted'
            AND fgm.user_id != ?
            UNION
            SELECT g.owner_id FROM " . TABLE_FAMILY_GROUPS . " g
            WHERE g.id IN (
              SELECT fgm3.group_id FROM " . TABLE_FAMILY_MEMBERS . " fgm3
              WHERE fgm3.user_id = ? AND fgm3.status = 'accepted'
            )
            AND g.owner_id != ?
          ))
    ORDER BY c.store_name ASC
  ");
  $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
  $cards = $stmt->fetchAll();
  $seed = getEncryptionSeed();
  foreach ($cards as &$card) {
    if ($seed) {
      $card['is_encrypted'] = str_starts_with($card['card_number'], ENC_PREFIX);
      $card['card_number'] = decryptCardNumberSafe($card['card_number'], $seed);
    } else {
      $card['is_encrypted'] = false;
    }
  }
  echo json_encode($cards);
}

function getCard(PDO $db, int $userId, string $cardId): void {
  $adminCheck = $db->prepare('SELECT is_admin FROM ' . TABLE_USERS . ' WHERE id = ?');
  $adminCheck->execute([$userId]);
  $isAdmin = $adminCheck->fetch()['is_admin'] ?? false;

  if ($isAdmin) {
    $stmt = $db->prepare('SELECT c.*, u.email AS owner_email FROM ' . TABLE_CARDS . ' c LEFT JOIN ' . TABLE_USERS . ' u ON c.user_id = u.id WHERE c.id = ?');
  } else {
    // Own card OR non-private card of family member
    $stmt = $db->prepare("
      SELECT c.*, u.email AS owner_email FROM " . TABLE_CARDS . " c
      LEFT JOIN " . TABLE_USERS . " u ON c.user_id = u.id
      WHERE c.id = ? AND (
        c.user_id = ?
        OR (c.is_private = 0 AND c.user_id IN (
          SELECT fgm.user_id FROM " . TABLE_FAMILY_MEMBERS . " fgm
          WHERE fgm.group_id IN (
            SELECT fgm2.group_id FROM " . TABLE_FAMILY_MEMBERS . " fgm2
            WHERE fgm2.user_id = ? AND fgm2.status = 'accepted'
          )
          AND fgm.status = 'accepted'
          AND fgm.user_id != ?
          UNION
          SELECT g.owner_id FROM " . TABLE_FAMILY_GROUPS . " g
          WHERE g.id IN (
            SELECT fgm3.group_id FROM " . TABLE_FAMILY_MEMBERS . " fgm3
            WHERE fgm3.user_id = ? AND fgm3.status = 'accepted'
          )
          AND g.owner_id != ?
        ))
      )
      LIMIT 1
    ");
  }
  $stmt->execute($isAdmin
    ? [$cardId]
    : [$cardId, $userId, $userId, $userId, $userId, $userId]
  );
  $card = $stmt->fetch();
  if (!$card) {
    http_response_code(404);
    echo json_encode(['error' => 'Carta non trovata']);
    return;
  }

  $seed = getEncryptionSeed();
  $card['is_encrypted'] = $seed && str_starts_with($card['card_number'], ENC_PREFIX);
  if ($seed) {
    $card['card_number'] = decryptCardNumberSafe($card['card_number'], $seed);
  }

  echo json_encode($card);
}

function createCard(PDO $db, int $userId): void {
  $data = cardsJsonBody();
  $required = ['store_name', 'card_number'];
  foreach ($required as $field) {
    if (empty($data[$field])) {
      http_response_code(400);
      echo json_encode(['error' => "Campo obbligatorio: $field"]);
      return;
    }
  }

  $id = $data['id'] ?? bin2hex(random_bytes(16));

  $seed = getEncryptionSeed();
  $newNumber = preg_replace('/\s/', '', $data['card_number']);
  $dupStmt = $db->prepare('SELECT id, store_name, card_number FROM ' . TABLE_CARDS . ' WHERE user_id = ?');
  $dupStmt->execute([$userId]);
  $dup = null;
  foreach ($dupStmt->fetchAll() as $existing) {
    $existingNumber = $seed ? decryptCardNumberSafe($existing['card_number'], $seed) : $existing['card_number'];
    if (preg_replace('/\s/', '', $existingNumber) === $newNumber) {
      $dup = $existing;
      break;
    }
  }
  if ($dup) {
    http_response_code(409);
    echo json_encode(['error' => 'Carta già presente per ' . $dup['store_name']]);
    return;
  }
  $cardNumber = $seed ? encryptCardNumber($data['card_number'], $seed) : $data['card_number'];

  $stmt = $db->prepare(
    'INSERT INTO ' . TABLE_CARDS . ' (id, user_id, store_name, card_number, holder_name, barcode_type, logo_type, logo_path, logo_data, notes, is_private, color, is_favorite) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $stmt->execute([
    $id,
    $userId,
    $data['store_name'],
    $cardNumber,
    $data['holder_name'] ?? '',
    $data['barcode_type'] ?? 'CODE128',
    $data['logo_type'] ?? 'none',
    $data['logo_path'] ?? '',
    $data['logo_data'] ?? null,
    $data['notes'] ?? '',
    !empty($data['is_private']) ? 1 : 0,
    $data['color'] ?? '#ffffff',
    !empty($data['is_favorite']) ? 1 : 0,
  ]);

  $stmt = $db->prepare('SELECT * FROM ' . TABLE_CARDS . ' WHERE id = ?');
  $stmt->execute([$id]);
  $card = $stmt->fetch();

  http_response_code(201);
  echo json_encode($card);
}

function updateCard(PDO $db, int $userId, string $cardId): void {
  // Check existence first (SELECT, not rowCount — UPDATE may affect 0 rows if values unchanged)
  $check = $db->prepare('SELECT id FROM ' . TABLE_CARDS . ' WHERE id = ? AND user_id = ?');
  $check->execute([$cardId, $userId]);
  if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Carta non trovata']);
    return;
  }

  $data = cardsJsonBody();
  $fields = [];
  $params = [];

  $seed = getEncryptionSeed();
  if ($seed && array_key_exists('card_number', $data)) {
    $data['card_number'] = encryptCardNumber($data['card_number'], $seed);
  }

  foreach (['store_name', 'card_number', 'holder_name', 'barcode_type', 'logo_type', 'logo_path', 'logo_data', 'notes', 'color'] as $f) {
    if (array_key_exists($f, $data) && ($f !== 'logo_data' || $data[$f] !== null)) {
      $fields[] = "$f = ?";
      $params[] = $data[$f];
    }
  }
  if (array_key_exists('is_private', $data)) {
    $fields[] = 'is_private = ?';
    $params[] = !empty($data['is_private']) ? 1 : 0;
  }
  if (array_key_exists('is_favorite', $data)) {
    $fields[] = 'is_favorite = ?';
    $params[] = !empty($data['is_favorite']) ? 1 : 0;
  }

  if (!empty($fields)) {
    $params[] = $cardId;
    $params[] = $userId;
    $sql = 'UPDATE ' . TABLE_CARDS . ' SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
  }

  $stmt = $db->prepare('SELECT * FROM ' . TABLE_CARDS . ' WHERE id = ?');
  $stmt->execute([$cardId]);
  $updated = $stmt->fetch();
  if ($updated && $seed) {
    $updated['card_number'] = decryptCardNumberSafe($updated['card_number'], $seed);
  }
  echo json_encode($updated);
}

function deleteCard(PDO $db, int $userId, string $cardId): void {
  $stmt = $db->prepare('DELETE FROM ' . TABLE_CARDS . ' WHERE id = ? AND user_id = ?');
  $stmt->execute([$cardId, $userId]);
  if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Carta non trovata']);
    return;
  }
  echo json_encode(['success' => true]);
}

function handleCardEncryptHandler(string $method, string $uri): void {
  if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Metodo non consentito']); return; }
  $userId = cardsAuthenticate();
  $db = cardsGetDb();
  $parts = explode('/', trim($uri, '/'));
  $cardId = $parts[1] ?? null;
  if (!$cardId) { http_response_code(400); echo json_encode(['error' => 'ID carta mancante']); return; }

  $seed = getEncryptionSeed();
  if (!$seed) { http_response_code(400); echo json_encode(['error' => 'Seed crittografia non impostato']); return; }

  $stmt = $db->prepare('SELECT id, card_number FROM ' . TABLE_CARDS . ' WHERE id = ? AND user_id = ?');
  $stmt->execute([$cardId, $userId]);
  $card = $stmt->fetch();
  if (!$card) { http_response_code(404); echo json_encode(['error' => 'Carta non trovata']); return; }
  if (str_starts_with($card['card_number'], ENC_PREFIX)) { http_response_code(400); echo json_encode(['error' => 'Carta già crittografata']); return; }

  $encrypted = encryptCardNumber($card['card_number'], $seed);
  $db->prepare('UPDATE ' . TABLE_CARDS . ' SET card_number = ? WHERE id = ?')->execute([$encrypted, $cardId]);
  echo json_encode(['success' => true]);
}

function handleCardDecryptHandler(string $method, string $uri): void {
  if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Metodo non consentito']); return; }
  $userId = cardsAuthenticate();
  $db = cardsGetDb();
  $parts = explode('/', trim($uri, '/'));
  $cardId = $parts[1] ?? null;
  if (!$cardId) { http_response_code(400); echo json_encode(['error' => 'ID carta mancante']); return; }

  $seed = getEncryptionSeed();
  if (!$seed) { http_response_code(400); echo json_encode(['error' => 'Seed crittografia non impostato']); return; }

  $stmt = $db->prepare('SELECT id, card_number FROM ' . TABLE_CARDS . ' WHERE id = ? AND user_id = ?');
  $stmt->execute([$cardId, $userId]);
  $card = $stmt->fetch();
  if (!$card) { http_response_code(404); echo json_encode(['error' => 'Carta non trovata']); return; }
  if (!str_starts_with($card['card_number'], ENC_PREFIX)) { http_response_code(400); echo json_encode(['error' => 'Carta non crittografata']); return; }

  $decrypted = decryptCardNumberSafe($card['card_number'], $seed);
  $db->prepare('UPDATE ' . TABLE_CARDS . ' SET card_number = ? WHERE id = ?')->execute([$decrypted, $cardId]);
  echo json_encode(['success' => true]);
}

function handleCardEncryptAllHandler(string $method, string $uri): void {
  if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Metodo non consentito']); return; }
  cardsAuthenticate();
  $db = cardsGetDb();

  $seed = getEncryptionSeed();
  if (!$seed) { http_response_code(400); echo json_encode(['error' => 'Seed crittografia non impostato']); return; }

  $stmt = $db->query('SELECT id, card_number FROM ' . TABLE_CARDS);
  $cards = $stmt->fetchAll();

  $count = 0;
  $update = $db->prepare('UPDATE ' . TABLE_CARDS . ' SET card_number = ? WHERE id = ?');
  foreach ($cards as $card) {
    if (str_starts_with($card['card_number'], ENC_PREFIX)) continue;
    $encrypted = encryptCardNumber($card['card_number'], $seed);
    $update->execute([$encrypted, $card['id']]);
    $count++;
  }

  echo json_encode(['success' => true, 'count' => $count]);
}

function handleCardDecryptAllHandler(string $method, string $uri): void {
  if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Metodo non consentito']); return; }
  cardsAuthenticate();
  $db = cardsGetDb();

  $seed = getEncryptionSeed();
  if (!$seed) { http_response_code(400); echo json_encode(['error' => 'Seed crittografia non impostato']); return; }

  $stmt = $db->query('SELECT id, card_number FROM ' . TABLE_CARDS);
  $cards = $stmt->fetchAll();

  $count = 0;
  $update = $db->prepare('UPDATE ' . TABLE_CARDS . ' SET card_number = ? WHERE id = ?');
  foreach ($cards as $card) {
    if (!str_starts_with($card['card_number'], ENC_PREFIX)) continue;
    $decrypted = decryptCardNumberSafe($card['card_number'], $seed);
    $update->execute([$decrypted, $card['id']]);
    $count++;
  }

  echo json_encode(['success' => true, 'count' => $count]);
}
