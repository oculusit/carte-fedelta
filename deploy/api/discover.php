<?php

header('Content-Type: application/json; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$serverUrl = $scheme . '://' . $host . $scriptDir;
$serverUrl = str_replace('/api', '', $serverUrl);

echo json_encode([
  'server_url' => $serverUrl,
  'name' => 'Carte Fedeltà',
  'version' => '1.1.0',
]);