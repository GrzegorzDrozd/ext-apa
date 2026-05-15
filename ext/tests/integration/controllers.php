<?php

abstract class AbstractController {
    #[\AfterParseAction([Router::class, 'add'], path: '/health', httpMethod: 'GET')]
    public function health(): array {
        return ['status' => 'ok'];
    }
}

class UserController extends AbstractController {
    #[\AfterParseAction([Router::class, 'add'], path: '/users', httpMethod: 'GET')]
    public function list(): array {
        return ['users' => ['alice', 'bob']];
    }

    #[\AfterParseAction([Router::class, 'add'], path: '/users', httpMethod: 'POST', auth: true)]
    public function create(): array {
        return ['created' => true];
    }
}

class ProductController extends AbstractController {
    #[\AfterParseAction([Router::class, 'add'], path: '/products', httpMethod: 'GET')]
    public function list(): array {
        return ['products' => ['widget', 'gadget']];
    }
}
