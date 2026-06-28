<?php

if (!defined('TABLE_FAMILY_GROUPS')) {
  define('TABLE_FAMILY_GROUPS', DB_PREFIX . 'family_groups');
}
if (!defined('TABLE_FAMILY_MEMBERS')) {
  define('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');
}
if (!defined('TABLE_SETTINGS')) {
  define('TABLE_SETTINGS', DB_PREFIX . 'settings');
}

function familyDb(): PDO {
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

function familyAuth(): int {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token mancante']);
    exit;
  }
  $token = substr($auth, 7);
  $db = familyDb();
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

function familyJsonBody(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function handleFamilyHandler(string $method, string $uri): void {
  $userId = familyAuth();
  $db = familyDb();

  $parts = explode('/', trim($uri, '/'));
  // parts[0] = 'family'
  $groupId = $parts[1] ?? null;
  $action = $parts[2] ?? null;
  $targetId = $parts[3] ?? null;

  // POST /family/{id}/members/{userId}/remove
  if ($groupId && $action === 'members' && $targetId && $method === 'DELETE') {
    removeMember($db, $userId, $groupId, (int)$targetId);
    return;
  }

  // POST /family/{id}/invite
  if ($groupId && $action === 'invite' && $method === 'POST') {
    inviteMember($db, $userId, $groupId);
    return;
  }

  // POST /family/{id}/accept
  if ($groupId && $action === 'accept' && $method === 'POST') {
    respondInvite($db, $userId, $groupId, 'accepted');
    return;
  }

  // POST /family/{id}/reject
  if ($groupId && $action === 'reject' && $method === 'POST') {
    respondInvite($db, $userId, $groupId, 'rejected');
    return;
  }

  // POST /family/{id}/leave
  if ($groupId && $action === 'leave' && $method === 'POST') {
    leaveGroup($db, $userId, $groupId);
    return;
  }

  // GET /family or /family/{id}
  if ($method === 'GET') {
    if ($groupId) {
      getGroup($db, $userId, $groupId);
    } else {
      listGroups($db, $userId);
    }
    return;
  }

  // POST /family (create)
  if ($method === 'POST' && !$groupId) {
    createGroup($db, $userId);
    return;
  }

  // DELETE /family/{id}
  if ($method === 'DELETE' && $groupId) {
    deleteGroup($db, $userId, $groupId);
    return;
  }

  http_response_code(405);
  echo json_encode(['error' => 'Metodo non consentito']);
}

function listGroups(PDO $db, int $userId): void {
  // Groups owned by user
  $ownedStmt = $db->prepare('SELECT id, name, owner_id, created_at FROM ' . TABLE_FAMILY_GROUPS . ' WHERE owner_id = ? ORDER BY created_at DESC');
  $ownedStmt->execute([$userId]);
  $ownedGroups = $ownedStmt->fetchAll();

  // Fetch members for each owned group
  if (!empty($ownedGroups)) {
    $memberStmt = $db->prepare(
      'SELECT m.id, m.user_id, m.status, m.invited_by, m.created_at,
              u.email, u.is_admin, u.is_moderator
       FROM ' . TABLE_FAMILY_MEMBERS . ' m
       JOIN ' . TABLE_USERS . ' u ON u.id = m.user_id
       WHERE m.group_id = ?
       ORDER BY FIELD(m.status, "accepted","pending","rejected"), m.created_at ASC'
    );
    foreach ($ownedGroups as &$g) {
      $memberStmt->execute([$g['id']]);
      $g['members'] = $memberStmt->fetchAll();
    }
    unset($g);
  }

  // Groups where user is accepted member (but not owner)
  $memberStmt2 = $db->prepare(
    'SELECT g.id, g.name, g.owner_id, g.created_at FROM ' . TABLE_FAMILY_GROUPS . ' g
     JOIN ' . TABLE_FAMILY_MEMBERS . ' m ON m.group_id = g.id
     WHERE m.user_id = ? AND m.status = ? AND g.owner_id != ? ORDER BY g.created_at DESC'
  );
  $memberStmt2->execute([$userId, 'accepted', $userId]);
  $memberGroups = $memberStmt2->fetchAll();

  // Fetch members for each member group
  if (!empty($memberGroups)) {
    $memberStmt3 = $db->prepare(
      'SELECT m.id, m.user_id, m.status, m.invited_by, m.created_at,
              u.email, u.is_admin, u.is_moderator
       FROM ' . TABLE_FAMILY_MEMBERS . ' m
       JOIN ' . TABLE_USERS . ' u ON u.id = m.user_id
       WHERE m.group_id = ?
       ORDER BY FIELD(m.status, "accepted","pending","rejected"), m.created_at ASC'
    );
    foreach ($memberGroups as &$g) {
      $memberStmt3->execute([$g['id']]);
      $g['members'] = $memberStmt3->fetchAll();
    }
    unset($g);
  }

  // Pending invitations
  $invitesStmt = $db->prepare(
    'SELECT g.id, g.name, g.owner_id, g.created_at,
            (SELECT email FROM ' . TABLE_USERS . ' WHERE id = g.owner_id) AS owner_email
     FROM ' . TABLE_FAMILY_GROUPS . ' g
     JOIN ' . TABLE_FAMILY_MEMBERS . ' m ON m.group_id = g.id
     WHERE m.user_id = ? AND m.status = ? ORDER BY g.created_at DESC'
  );
  $invitesStmt->execute([$userId, 'pending']);

  echo json_encode([
    'owned' => $ownedGroups,
    'member' => $memberGroups,
    'invitations' => $invitesStmt->fetchAll(),
  ]);
}

function getGroup(PDO $db, int $userId, string $groupId): void {
  // Verify user is owner or member
  $check = $db->prepare(
    'SELECT id FROM ' . TABLE_FAMILY_GROUPS . ' WHERE id = ?
     AND (owner_id = ? OR id IN (SELECT group_id FROM ' . TABLE_FAMILY_MEMBERS . ' WHERE group_id = ? AND user_id = ? AND status = ?))'
  );
  $check->execute([$groupId, $userId, $groupId, $userId, 'accepted']);
  if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Gruppo non trovato']);
    return;
  }

  $group = $db->prepare('SELECT * FROM ' . TABLE_FAMILY_GROUPS . ' WHERE id = ?');
  $group->execute([$groupId]);
  $result = $group->fetch();

  // Members with accepted status + user info
  $members = $db->prepare(
    'SELECT m.id, m.user_id, m.status, m.invited_by, m.created_at,
            u.email, u.is_admin, u.is_moderator
     FROM ' . TABLE_FAMILY_MEMBERS . ' m
     JOIN ' . TABLE_USERS . ' u ON u.id = m.user_id
     WHERE m.group_id = ?
     ORDER BY FIELD(m.status, "accepted","pending","rejected"), m.created_at ASC'
  );
  $members->execute([$groupId]);
  $result['members'] = $members->fetchAll();

  echo json_encode($result);
}

function createGroup(PDO $db, int $userId): void {
  // Limit: one group per user
  $existing = $db->prepare('SELECT id FROM ' . TABLE_FAMILY_GROUPS . ' WHERE owner_id = ?');
  $existing->execute([$userId]);
  if ($existing->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Puoi creare al massimo un gruppo famiglia']);
    return;
  }

  $data = familyJsonBody();
  $name = trim($data['name'] ?? '');
  if (!$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Nome del gruppo obbligatorio']);
    return;
  }

  $id = bin2hex(random_bytes(16));
  $stmt = $db->prepare('INSERT INTO ' . TABLE_FAMILY_GROUPS . ' (id, name, owner_id) VALUES (?, ?, ?)');
  $stmt->execute([$id, $name, $userId]);

  $memberStmt = $db->prepare('INSERT INTO ' . TABLE_FAMILY_MEMBERS . ' (group_id, user_id, status, invited_by) VALUES (?, ?, ?, ?)');
  $memberStmt->execute([$id, $userId, 'accepted', $userId]);

  http_response_code(201);
  echo json_encode(['id' => $id, 'name' => $name]);
}

function deleteGroup(PDO $db, int $userId, string $groupId): void {
  $check = $db->prepare('SELECT id FROM ' . TABLE_FAMILY_GROUPS . ' WHERE id = ? AND owner_id = ?');
  $check->execute([$groupId, $userId]);
  if (!$check->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo il proprietario può eliminare il gruppo']);
    return;
  }

  $db->prepare('DELETE FROM ' . TABLE_FAMILY_GROUPS . ' WHERE id = ?')->execute([$groupId]);
  echo json_encode(['success' => true]);
}

function inviteMember(PDO $db, int $userId, string $groupId): void {
  // Verify ownership
  $check = $db->prepare('SELECT id FROM ' . TABLE_FAMILY_GROUPS . ' WHERE id = ? AND owner_id = ?');
  $check->execute([$groupId, $userId]);
  if (!$check->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo il proprietario può invitare membri']);
    return;
  }

  $data = familyJsonBody();
  $email = trim($data['email'] ?? '');
  if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Email obbligatoria']);
    return;
  }

  // Find user by email
  $userStmt = $db->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ?');
  $userStmt->execute([$email]);
  $targetUser = $userStmt->fetch();

  if ($targetUser) {
    $targetId = (int)$targetUser['id'];

    if ($targetId === $userId) {
      http_response_code(400);
      echo json_encode(['error' => 'Non puoi invitare te stesso']);
      return;
    }

    // Check if user is already in another group (one group limit)
    $otherGroup = $db->prepare('SELECT id FROM ' . TABLE_FAMILY_MEMBERS . ' WHERE user_id = ? AND status = ?');
    $otherGroup->execute([$targetId, 'accepted']);
    if ($otherGroup->fetch()) {
      http_response_code(400);
      echo json_encode(['error' => 'Questo utente appartiene già a un gruppo famiglia']);
      return;
    }

    // Check if already invited/member
    $dup = $db->prepare('SELECT id, status FROM ' . TABLE_FAMILY_MEMBERS . ' WHERE group_id = ? AND user_id = ?');
    $dup->execute([$groupId, $targetId]);
    $existing = $dup->fetch();
    if ($existing) {
      if ($existing['status'] === 'accepted') {
        http_response_code(409);
        echo json_encode(['error' => 'Utente già membro']);
        return;
      }
      // Already pending or rejected → send notification email
      sendInviteEmail($db, $userId, $groupId, $email, true);
      echo json_encode(['success' => true, 'user_id' => $targetId]);
      return;
    }

    $stmt = $db->prepare('INSERT INTO ' . TABLE_FAMILY_MEMBERS . ' (group_id, user_id, status, invited_by) VALUES (?, ?, ?, ?)');
    $stmt->execute([$groupId, $targetId, 'pending', $userId]);
    sendInviteEmail($db, $userId, $groupId, $email, true);
    echo json_encode(['success' => true, 'user_id' => $targetId]);
  } else {
    // User not registered — send email with registration info
    sendInviteEmail($db, $userId, $groupId, $email, false);
    echo json_encode(['success' => true, 'registered' => false, 'message' => 'Email inviata. L\'utente dovrà registrarsi prima di accettare l\'invito.']);
  }
}

function getAppName(): string {
  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $stmt = $pdo->prepare("SELECT `value` FROM " . TABLE_SETTINGS . " WHERE `key` = 'app_name'");
  $stmt->execute();
  $row = $stmt->fetch();
  return $row && $row['value'] ? $row['value'] : 'Carte Fedeltà';
}

function sendInviteEmail(PDO $db, int $inviterId, string $groupId, string $recipientEmail, bool $registered = true): void {
  require_once __DIR__ . '/auth.php';
  $stmt = $db->prepare('SELECT email FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$inviterId]);
  $inviter = $stmt->fetch();
  if (!$inviter) return;
  $inviterEmail = $inviter['email'];

  $stmt = $db->prepare('SELECT name FROM ' . TABLE_FAMILY_GROUPS . ' WHERE id = ?');
  $stmt->execute([$groupId]);
  $group = $stmt->fetch();
  if (!$group) return;
  $groupName = $group['name'];

  $appName = getAppName();
  $appUrl = getAppUrl();
  $acceptLink = $appUrl . '/#/family';

  $subject = 'Invito al gruppo famiglia ' . $groupName;

  $body = '<p>Ciao,</p>';
  $body .= '<p><strong>' . htmlspecialchars($inviterEmail) . '</strong> ti ha invitato nel gruppo familiare <strong>' . htmlspecialchars($groupName) . '</strong> di <strong>' . htmlspecialchars($appName) . '</strong>.</p>';
  $body .= '<p>Accettando l\'invito potrete condividere l\'archivio delle tessere in modo che ogni tessera aggiunta venga vista dagli altri membri del gruppo ad esclusione delle tessere memorizzate come Private.</p>';

  if ($registered) {
    $body .= '<p>Per accettare l\'invito clicca sul tasto qui sotto oppure copia questo link nel browser:</p>';
    $body .= '<p style="text-align:center;margin:20px 0"><a href="' . $acceptLink . '" style="display:inline-block;padding:12px 24px;background:#1a73e8;color:#fff;text-decoration:none;border-radius:6px;font-size:16px">Accetta invito</a></p>';
    $body .= '<p>' . htmlspecialchars($acceptLink) . '</p>';
    $body .= '<p>Se non vuoi accettare l\'invito, ignora semplicemente questa e-mail.</p>';
  } else {
    $allowReg = false;
    $regStmt = $db->prepare("SELECT `value` FROM " . TABLE_SETTINGS . " WHERE `key` = 'allow_registration'");
    $regStmt->execute();
    $regRow = $regStmt->fetch();
    if ($regRow && ($regRow['value'] === '1' || $regRow['value'] === 1)) {
      $allowReg = true;
    }
    if ($allowReg) {
      $body .= '<p>Per accettare l\'invito puoi registrarti a <strong>' . htmlspecialchars($appName) . '</strong>.</p>';
      $body .= '<p>La registrazione a <strong>' . htmlspecialchars($appName) . '</strong> è totalmente gratuita.</p>';
      $body .= '<p>Dopo la registrazione, collegati e vai alla sezione "Gestione Famiglia" per accettare l\'invito.</p>';
      $body .= '<p style="text-align:center;margin:20px 0"><a href="' . $appUrl . '/#/login" style="display:inline-block;padding:12px 24px;background:#1a73e8;color:#fff;text-decoration:none;border-radius:6px;font-size:16px">Registrati ora</a></p>';
      $body .= '<p>' . htmlspecialchars($appUrl) . '/#/login</p>';
    } else {
      $body .= '<p>Chiedi a <strong>' . htmlspecialchars($inviterEmail) . '</strong> di registrare un account per te.</p>';
      $body .= '<p>La registrazione di nuovi account è limitata solo agli amministratori.</p>';
    }
  }

  $body .= '<p>Grazie e speriamo di vederti condividere le Tessere Fedeltà con il resto del gruppo!</p>';

  sendMail($recipientEmail, $subject, $body);
}

function respondInvite(PDO $db, int $userId, string $groupId, string $status): void {
  $check = $db->prepare('SELECT id FROM ' . TABLE_FAMILY_MEMBERS . ' WHERE group_id = ? AND user_id = ? AND status = ?');
  $check->execute([$groupId, $userId, 'pending']);
  if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Invito non trovato']);
    return;
  }

  $db->prepare('UPDATE ' . TABLE_FAMILY_MEMBERS . ' SET status = ? WHERE group_id = ? AND user_id = ?')
    ->execute([$status, $groupId, $userId]);

  echo json_encode(['success' => true]);
}

function leaveGroup(PDO $db, int $userId, string $groupId): void {
  // Owner cannot leave (must delete)
  $check = $db->prepare('SELECT id FROM ' . TABLE_FAMILY_GROUPS . ' WHERE id = ? AND owner_id = ?');
  $check->execute([$groupId, $userId]);
  if ($check->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Il proprietario non può abbandonare il gruppo. Eliminalo.']);
    return;
  }

  $db->prepare('DELETE FROM ' . TABLE_FAMILY_MEMBERS . ' WHERE group_id = ? AND user_id = ?')
    ->execute([$groupId, $userId]);

  echo json_encode(['success' => true]);
}

function removeMember(PDO $db, int $userId, string $groupId, int $targetId): void {
  $check = $db->prepare('SELECT id FROM ' . TABLE_FAMILY_GROUPS . ' WHERE id = ? AND owner_id = ?');
  $check->execute([$groupId, $userId]);
  if (!$check->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo il proprietario può rimuovere membri']);
    return;
  }

  $db->prepare('DELETE FROM ' . TABLE_FAMILY_MEMBERS . ' WHERE group_id = ? AND user_id = ?')
    ->execute([$groupId, $targetId]);

  echo json_encode(['success' => true]);
}
