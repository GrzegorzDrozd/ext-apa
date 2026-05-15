<?php
// Controller using APA — permissions register at class load time

class AccessControl {
    public static array $requirements = [];
    public static function require(string $class, string $method, string $role): void {
        self::$requirements["$class::$method"] = $role;
    }
}

class AdminController {
    #[\AfterParseAction([AccessControl::class, 'require'], role: 'admin')]
    public function deleteUser(): string { return 'deleted'; }
}
