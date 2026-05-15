<?php
// APA version: permissions registered at class load, checked via static array
require __DIR__ . '/apa_controller.php';

$checks = 10000;
$start = hrtime(true);
for ($i = 0; $i < $checks; $i++) {
    $role = AccessControl::$requirements['AdminController::deleteUser'] ?? null;
    $allowed = ($role === 'admin');
}
$apa_us = (hrtime(true) - $start) / 1000;

header('Content-Type: application/json');
echo json_encode([
    'checks' => $checks,
    'time_us' => round($apa_us, 1),
    'per_check_ns' => round($apa_us * 1000 / $checks, 1),
    'method' => 'apa',
]);
