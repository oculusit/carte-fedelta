<?php

header('Content-Type: application/json; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
// Force HTTPS if the request came via HTTPS proxy/rewrite
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
  $scheme = 'https';
}
// Force HTTPS for known production host
if (strpos($_SERVER['HTTP_HOST'], 'fidappti') !== false) {
  $scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$apiPos = strpos($scriptDir, '/api');
if ($apiPos !== false) {
  $scriptDir = substr($scriptDir, 0, $apiPos);
}
$serverUrl = $scheme . '://' . $host . $scriptDir;

echo json_encode([
  'server_url' => $serverUrl,
  'name' => 'Carte Fedeltà',
  'version' => '1.1.0',
]);
