<?php
// Reflection version: check permissions via ReflectionMethod on every lookup

#[Attribute]
class RequireRole {
    public function __construct(public string $role) {}
}

class AdminController {
    #[RequireRole('admin')]
    public function deleteUser(): string { return 'deleted'; }
}

$checks = 10000;
$start = hrtime(true);
for ($i = 0; $i < $checks; $i++) {
    $ref = new ReflectionMethod(AdminController::class, 'deleteUser');
    $attrs = $ref->getAttributes(RequireRole::class);
    $role = $attrs ? $attrs[0]->newInstance()->role : null;
    $allowed = ($role === 'admin');
}
$ref_us = (hrtime(true) - $start) / 1000;

header('Content-Type: application/json');
echo json_encode([
    'checks' => $checks,
    'time_us' => round($ref_us, 1),
    'per_check_ns' => round($ref_us * 1000 / $checks, 1),
    'method' => 'reflection',
]);
