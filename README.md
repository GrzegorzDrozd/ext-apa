# AfterParseAction

A PHP extension that calls your code when `#[\AfterParseAction]` appears on a class, method, or function. The call happens automatically during class loading. No reflection needed. No container compilation. No cache warmup.

## Why?

Attributes in PHP are passive. You annotate a method with `#[Route('/users')]` but nothing happens until some framework code scans every class with reflection and acts on what it finds. That scanning is a compiler pass, a container build step, or a cache warmup command.

This extension removes that step. Put `#[\AfterParseAction]` on a method, tell it what to call, and the extension does the rest when the class loads.

## Quick Example

```php
class Router {
    public static array $routes = [];

    public static function add(string $class, string $method, string $path, string $httpMethod, ...$args): void {
        self::$routes[$path][$httpMethod] = [$class, $method, $args];
    }
}

class UserController {
    #[\AfterParseAction([Router::class, 'add'], path: '/users', httpMethod: 'GET')]
    public function list() {}

    #[\AfterParseAction([Router::class, 'add'], path: '/users/{id}', httpMethod: 'DELETE')]
    public function delete(int $id) {}
}
```

When `UserController` loads (via `require`, autoload, whatever), the extension calls:
```php
Router::add('UserController', 'list', path: '/users', httpMethod: 'GET');
Router::add('UserController', 'delete', path: '/users/{id}', httpMethod: 'DELETE');
```

That's it. Routes are on a list. No scanning needed.

## More Examples

### Event Listeners

```php
class EventDispatcher {
    public static array $listeners = [];

    public static function addListener(string $class, string $method, string $event, int $priority = 0): void {
        self::$listeners[$event][] = ['handler' => [$class, $method], 'priority' => $priority];
    }
}

class OrderSubscriber {
    #[\AfterParseAction([EventDispatcher::class, 'addListener'], event: 'order.created', priority: 10)]
    public function onOrderCreated(OrderEvent $event) {}

    #[\AfterParseAction([EventDispatcher::class, 'addListener'], event: 'order.shipped')]
    public function onOrderShipped(OrderEvent $event) {}
}
```

### Console Commands

```php
#[\AfterParseAction([CommandRegistry::class, 'add'], name: 'app:import', description: 'Import data from CSV')]
class ImportCommand {
    public function execute(): int { /* ... */ }
}
```

### Middleware

```php
class SecurityMiddleware {
    #[\AfterParseAction([MiddlewareStack::class, 'add'], name: 'csrf', priority: 10)]
    public function csrf(Request $request): Response {}

    #[\AfterParseAction([MiddlewareStack::class, 'add'], name: 'auth', priority: 20)]
    public function auth(Request $request): Response {}
}
```

### The Bootstrap

```php
// Your entire "compiler pass":
foreach (glob('src/Controller/*.php') as $file) require $file;
foreach (glob('src/Listener/*.php') as $file) require $file;

// Done. Everything is registered.
$routes = Router::$routes;
$listeners = EventDispatcher::$listeners;
```

You still need to load every class. Autoloading won't help here since it only triggers when a class is first referenced. A glob loop, a class map, or `composer`'s `classmap` autoload directive all work fine.

## Symfony Standalone Components

Symfony's routing, event-dispatcher and console components work without the full framework. But wiring them requires manual `addRoute()`, `addListener()`, `addCommand()` calls. Symfony's own attributes (`#[AsCommand]`, `#[AsEventListener]`) only work with the DI container.

APA gives you attribute-based registration with standalone components. No container, no compiler passes, no `cache:clear`.

### Routing

```php
// bootstrap.php
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

class Routes {
    public static RouteCollection $collection;

    public static function add(string $class, string $method, string $path, string $httpMethod): void {
        self::$collection->add(
            "$class::$method",
            new Route($path, ['_controller' => [$class, $method], '_method' => $httpMethod])
        );
    }
}
Routes::$collection = new RouteCollection();

foreach (glob('src/Controller/*.php') as $file) require $file;
// Routes::$collection is now populated. Pass it to UrlMatcher.
```

```php
// src/Controller/UserController.php
class UserController {
    #[\AfterParseAction([Routes::class, 'add'], path: '/users', httpMethod: 'GET')]
    public function list(): Response { /* ... */ }

    #[\AfterParseAction([Routes::class, 'add'], path: '/users/{id}', httpMethod: 'GET')]
    public function show(int $id): Response { /* ... */ }
}
```

Without APA you'd write this by hand:
```php
$routes->add('user_list', new Route('/users', ['_controller' => [UserController::class, 'list']]));
$routes->add('user_show', new Route('/users/{id}', ['_controller' => [UserController::class, 'show']]));
// ... for every route
```

### EventDispatcher

```php
// bootstrap.php
use Symfony\Component\EventDispatcher\EventDispatcher;

class Events {
    public static EventDispatcher $dispatcher;

    public static function listen(string $class, string $method, string $event, int $priority = 0): void {
        self::$dispatcher->addListener($event, [new $class(), $method], $priority);
    }
}
Events::$dispatcher = new EventDispatcher();

foreach (glob('src/Listener/*.php') as $file) require $file;
```

```php
// src/Listener/OrderListener.php
class OrderListener {
    #[\AfterParseAction([Events::class, 'listen'], event: 'order.created', priority: 10)]
    public function onCreated(OrderEvent $event): void { /* ... */ }

    #[\AfterParseAction([Events::class, 'listen'], event: 'order.shipped')]
    public function onShipped(OrderEvent $event): void { /* ... */ }
}
```

### Console

```php
// bin/console
use Symfony\Component\Console\Application;

class Commands {
    public static Application $app;

    public static function register(string $class, ?string $method, string $name, string $description = ''): void {
        $command = new $class();
        $command->setName($name);
        $command->setDescription($description);
        self::$app->add($command);
    }
}
Commands::$app = new Application();

foreach (glob('src/Command/*.php') as $file) require $file;
Commands::$app->run();
```

```php
// src/Command/ImportCommand.php
use Symfony\Component\Console\Command\Command;

#[\AfterParseAction([Commands::class, 'register'], name: 'app:import', description: 'Import data')]
class ImportCommand extends Command {
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln('Importing...');
        return Command::SUCCESS;
    }
}
```

Without APA (or with Symfony's `#[AsCommand]` which needs the DI container):
```php
$app->add(new ImportCommand());
// For every command in your app
```

### Use as a build step

These examples collect data into static arrays at runtime, but you can also use them as a compiler pass. Load all classes, collect the routes/listeners/commands, then dump the result to a PHP file:

```php
// Commands.php
class Commands
{
    public static array $commands = [];

    public static function register(string $class, ?string $method, string $name, string $description = ''): void {
        self::$commands[] = [
            'class'=>$class,
            'method'=>$method,
            'name'=>$name,
            'description'=>$description
        ];
    }
};

// TestCommand.php
use Symfony\Component\Console\Command\Command;

#[\AfterParseAction([Commands::class, 'register'], name: 'app:test')]
class TestCommand extends Command {

}

// build.php
use Symfony\Component\Console\Command\Command;

require_once '../vendor/autoload.php';

foreach (glob('Commands/*.php', GLOB_BRACE) as $file) {
    require_once $file;
}

$file = "<?php\nreturn [\n";
foreach(Commands::$commands as $command) {
    $file .= "'".$command['name']."' => function() use (\$di): ".Command::class." { return \$di->get(".$command['class']."::class);},\n";
}
$file .= "];\n";

file_put_contents('commands.php', $file);

// cli.php
// fake di container
$di = new class {
    public function get($class) {
        return new $class();
    }
};
$commands = require_once 'commands.php';
$loader = new Symfony\Component\Console\CommandLoader\FactoryCommandLoader($commands);
var_dump($loader->get('app:test'));

```
Deploy the cached file. Load it in production without the extension. APA becomes a build tool that runs during deployment and produces config your app reads at runtime.

## When Does It Fire?

Every time a class loads, including with opcache.

It replaces the per-request reflection scanning that frameworks already do, just faster (C level instead of PHP reflection).

Two ways to use it:

1. **Always loaded.** Callables must be cheap (a static array append costs ~1 µs). The collected data is used directly at runtime.

2. **CLI only (build step).** Load the extension during deployment to generate route caches or compiled containers. Ship without the extension so web requests never see it. The attribute becomes a zero-cost annotation that only activates in your build pipeline.

Either way, keep callables minimal. They run during class loading, before your application logic. Heavy work belongs in the code that *consumes* the collected data.

### Runtime usage (no glob required)

The bootstrap pattern above requires loading every class upfront. But there's a second pattern that works without it: any code that creates an instance first and calls a method later. Autoloading the class triggers AfterParseAction, so by the time your code runs checks or middleware the metadata is already there.

Most frameworks work this way. The router resolves the controller class, the container instantiates it (which triggers autoload, which triggers AfterParseAction), and then middleware runs before the action. Permission checks, feature flags, or rate limits can use the collected metadata without scanning:

```php
class AccessControl {
    public static array $permissions = [];

    public static function permissionRequirements(string $class, string $method, $role, ...$args): void {
        self::$permissions[$class][$method] = ['role'=> $role, ...$args];
    }
}

class FeatureGate {
    public static array $gates = [];

    public static function featureRequirements(string $class, string $method, $feature,...$args): void {
        self::$gates[$class][$method] = ['feature' => $feature, ...$args];
    }
}

class AdminController {
    #[\AfterParseAction([AccessControl::class, 'permissionRequirements'], role: 'admin')]
    public function deleteUser(int $id) { /* ... */ }

    #[\AfterParseAction([FeatureGate::class, 'featureRequirements'], feature: 'bulk-delete')]
    public function bulkDelete(array $ids) { /* ... */ }
}

// In your middleware (runs after controller class loads, before action):
class PermissionMiddleware {
    public function handle(Request $req, string $class, string $method) {
        $requiredRole = AccessControl::$requirements[$class][$method] ?? null;
        if ($requiredRole && !$req->user()->hasRole($requiredRole['role'])) {
            return new Response(403);
        }
        return $this->next($req);
    }
}
```

Compare with the typical reflection approach:

```php
// Without APA — standard attribute, checked via reflection

#[Attribute]
class RequireRole {
    public function __construct(public string $role) {}
}

class AdminController {
    #[RequireRole('admin')]
    public function deleteUser(int $id) { /* ... */ }

    #[RequireRole('admin')]
    public function bulkDelete(array $ids) { /* ... */ }
}

// Middleware must reflect on every request
class PermissionMiddleware {
    public function handle(Request $req, string $class, string $method) {
        $ref = new ReflectionMethod($class, $method);
        $attrs = $ref->getAttributes(RequireRole::class);
        if ($attrs) {
            $role = $attrs[0]->newInstance()->role;
            if (!$req->user()->hasRole($role)) {
                return new Response(403);
            }
        }
        return $this->next($req);
    }
}
```

With APA the controller registered its own requirements when it loaded. The middleware reads a static array. No `ReflectionMethod`, no `getAttributes`, no `newInstance`.

Measured inside an actual HTTP request (10K permission checks, PHP 8.3 debug build):

| Method | Per check | Speedup |
|--------|-----------|---------|
| APA (static array) | ~120 ns | — |
| Reflection | ~1,560 ns | **13x slower** |

For 5-10 middleware checks per request that's ~0.6 µs vs ~8 µs. Small in absolute terms, but it adds up. And the code is simpler.

This works for any flow where the class is autoloaded before the method is actually called. Controllers, queue job handlers, CLI commands, GraphQL resolvers.

## Inheritance

Works like you'd expect:

```php
abstract class AbstractController {
    #[\AfterParseAction([Router::class, 'add'], path: '/index', httpMethod: 'GET')]
    public function indexAction() {}
}

class LoginController extends AbstractController {
    #[\AfterParseAction([Router::class, 'add'], path: '/login', httpMethod: 'POST')]
    public function loginAction() {}
}
```

When `LoginController` loads, both fire. `indexAction` gets `LoginController` as the class name, not `AbstractController`:
```php
Router::add('LoginController', 'indexAction', path: '/index', httpMethod: 'GET');
Router::add('LoginController', 'loginAction', path: '/login', httpMethod: 'POST');
```

### Interfaces

PHP doesn't propagate attributes from interface methods to implementations. The extension handles this. When a concrete class implements an interface, the interface's method attributes fire with the concrete class name:

```php
interface Auditable {
    #[\AfterParseAction([AuditLog::class, 'register'], level: 'high')]
    public function sensitiveOperation(): void;
}

class PaymentService implements Auditable {
    public function sensitiveOperation(): void { /* ... */ }
}
// Fires: AuditLog::register('PaymentService', 'sensitiveOperation', level: 'high')
```

### Rules

- Abstract classes are skipped. Their methods fire for each concrete child.
- Interfaces are skipped. Their methods fire for each implementing class.
- Traits are skipped. Their methods fire for each using class.
- Abstract and interface method attrs always propagate to implementations, even if the implementation doesn't repeat the attribute.
- If a child overrides a *concrete* inherited method without the attribute, it doesn't fire. That's treated as intentional removal.
- An interface and its implementation can have *different* `#[\AfterParseAction]` attrs calling different callables. Both fire.
- If the same callable appears on both the interface and the implementation, it fires twice. That's the developer's choice.

## Attribute Syntax

```php
#[\AfterParseAction(callable, ...args)]
```

First argument is any PHP callable. The rest gets passed through. Named args are preserved.

```php
#[\AfterParseAction([MyClass::class, 'method'], key: 'value')]   // array callable
#[\AfterParseAction('MyClass::method', key: 'value')]             // string callable
```

The callable receives:
```php
function handler(string $className, string $methodName, mixed ...$args): void {}
// For class-level:  handler('ClassName', null, ...)
// For function-level: handler(null, 'functionName', ...)
```

Extra named args that don't match the callable's parameters are collected by `...$args`:

```php
public static function add(string $class, string $method, string $path, string $httpMethod, ...$args): void {
    // $args = ['middleware' => 'auth', 'cache' => 3600]
}

#[\AfterParseAction([Router::class, 'add'], path: '/users', httpMethod: 'GET', middleware: 'auth', cache: 3600)]
```

## Error Handling

Invalid callable: warning, script continues.

If a callable throws, remaining actions still fire (same as `register_shutdown_function`). The first exception is re-thrown after all actions complete.

## Performance

Benchmarked on PHP 8.3.10 release build (-O2). 200 `require` calls.

### Flat (200 classes, 5 methods each)

| Scenario | Time | Per action |
|----------|------|------------|
| Baseline (no extension) | ~5.5 ms | — |
| APA loaded, no attributes | ~5.8 ms | ~0% |
| APA + 1000 actions | ~7.0 ms | ~1.5 µs |

### Deep Hierarchy (200 classes + 5 interfaces + abstract base, 2-3 interfaces per class)

| Scenario | Time | Per action |
|----------|------|------------|
| Baseline | ~7.1 ms | — |
| APA loaded, no attributes | ~6.9 ms | 0% |
| APA + 1800 actions | ~6.3 ms | < 1 µs |

Hierarchy traversal is free. Just pointer chasing through already-linked class entries.

### Synthetic (1M empty user function calls)

| Scenario | Time | Per call |
|----------|------|----------|
| Baseline | ~14 ms | — |
| APA loaded | ~21 ms | ~6 ns |

The 6 ns/call overhead only applies to **user-defined PHP functions and methods**. Built-in functions (`strlen`, `array_map`, `json_encode`, etc.) go through `zend_execute_internal` which the extension doesn't touch. Zero cost for native calls. A language-level PHP RFC would eliminate the user function overhead too.

A typical app with 50–100 attributed methods: **< 0.1 ms** added to boot time.

## Compatibility

### PHP Versions

| Version | Build | Tests |
|---------|-------|-------|
| 8.0, 8.1 | FAIL | — |
| **8.2** | PASS | **13/13** |
| **8.3** | PASS | **13/13** |
| **8.4** | PASS | **13/13** |

Requires 8.2+ (`zend_observer_class_linked_register` and `zend_mark_internal_attribute` were added in 8.2).

PHP 8.3 core test suite with APA loaded: 688/688 passed, 0 failures.

### Works With

- Opcache: `class_linked` fires for cached classes
- Preloading: RINIT scan picks up preloaded classes
- Autoloading: callable class autoloaded on demand
- xdebug: `execute_ex` chaining is standard practice
- Multiple attributes: all fire in declaration order

## How It Works

1. **At compile time:** `zend_observer_class_linked` callback scans methods for `#[\AfterParseAction]` and copies the attribute data into a queue. No PHP code runs.
2. **When the file finishes loading:** `zend_execute_ex` override flushes the queue. Each action calls its callable via `zend_call_function`. By the time `require` returns, all actions have fired.

## Install

### Via PIE (recommended)

[PIE](https://github.com/php/pie) is the new PHP extension installer (replacement for PECL):

```bash
pie install grzegorzdrozd/ext-apa
```

That's it. PIE handles phpize, configure, make, and enables the extension in php.ini.

For dev/unreleased versions:
```bash
pie install grzegorzdrozd/ext-apa:@dev
```

### Manual build

```bash
cd ext/
phpize
./configure --enable-apa
make -j$(nproc)
make install
```

Then add `extension=apa` to your php.ini.

### Docker (for development)

```bash
cd apa/
make build-image    # first time
make build
make test           # 13/13 passing, valgrind clean
make bench          # run all benchmarks
make integration    # HTTP integration test
make pie            # test PIE install
make shell          # interactive debugging
```

Requires Docker.

## Tests

| Test | What it covers |
|------|----------------|
| basic_method | Positional args on a method |
| named_args | Named args, array + string callables |
| autoload | Callable class autoloaded on demand |
| class_level | Attribute on the class itself |
| function_level | Attribute on a global function |
| multiple_attrs | Multiple attributes fire in order |
| invalid_callable | Bad callable: warning, script survives |
| exception_continues | One throws, rest still fire, exception re-thrown |
| opcache_file_cache | Second request from opcache works |
| trait_and_inheritance | Traits per-class, abstract skipped, inherited methods fire |
| variadic_extra_args | Extra named args collected by `...$args` |
| interface | Interface attributes fire for each implementation |
| complex_hierarchy | Abstract + interface + trait + override combined |

## Limitations

- The `execute_ex` override adds ~6 ns per user-defined PHP function call. Native functions (`strlen` etc.) are unaffected.
- Attribute values are resolved at execution time, not compile time
- A PHP RFC could make this a language feature with zero overhead

## License

MIT
