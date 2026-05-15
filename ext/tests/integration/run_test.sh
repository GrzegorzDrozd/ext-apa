#!/bin/bash
# Integration test: starts PHP built-in server with APA extension,
# makes HTTP requests, verifies routes are auto-registered.
#
# Run from inside Docker container:
#   cd /usr/src/myapp && bash tests/integration/run_test.sh

set -e

PORT=8765
DOCROOT="tests/integration"
PASS=0
FAIL=0

# Start server in background
php -d extension=modules/apa.so -S 127.0.0.1:$PORT -t $DOCROOT > /dev/null 2>&1 &
SERVER_PID=$!
sleep 0.5

# Verify server started
if ! kill -0 $SERVER_PID 2>/dev/null; then
    echo "FAIL: Server failed to start"
    exit 1
fi

cleanup() {
    kill $SERVER_PID 2>/dev/null || true
    wait $SERVER_PID 2>/dev/null || true
}
trap cleanup EXIT

assert_contains() {
    local url="$1" expected="$2" label="$3"
    local response
    # Collapse whitespace so grep works on pretty-printed JSON
    response=$(php -r "echo file_get_contents('http://127.0.0.1:$PORT$url');" 2>/dev/null | tr -d '\n ')
    if echo "$response" | grep -q "$expected"; then
        echo "PASS: $label"
        PASS=$((PASS + 1))
    else
        echo "FAIL: $label"
        echo "  Expected to contain: $expected"
        echo "  Got: $response"
        FAIL=$((FAIL + 1))
    fi
}

assert_status() {
    local url="$1" expected_status="$2" label="$3"
    local status
    status=$(php -r "
        \$ctx = stream_context_create(['http' => ['ignore_errors' => true]]);
        file_get_contents('http://127.0.0.1:$PORT$url', false, \$ctx);
        preg_match('/HTTP\/\S+ (\d+)/', \$http_response_header[0], \$m);
        echo \$m[1];
    " 2>/dev/null)
    if [ "$status" = "$expected_status" ]; then
        echo "PASS: $label"
        PASS=$((PASS + 1))
    else
        echo "FAIL: $label (got status $status)"
        FAIL=$((FAIL + 1))
    fi
}

echo "=== APA Integration Test ==="
echo ""

# Test 1: /routes endpoint shows all auto-registered routes
assert_contains "/routes" "UserController" "Routes contain UserController"
assert_contains "/routes" "ProductController" "Routes contain ProductController"
assert_contains "/routes" "/health" "Routes contain /health (inherited)"
assert_contains "/routes" "/users" "Routes contain /users"
assert_contains "/routes" "/products" "Routes contain /products"

# Test 2: Inherited route works for both controllers
assert_contains "/routes" '"class":"UserController","method":"health"' \
    "UserController inherits /health"
assert_contains "/routes" '"class":"ProductController","method":"health"' \
    "ProductController inherits /health"

# Test 3: Actual dispatch works
assert_contains "/users" "alice" "GET /users returns user list"
assert_contains "/products" "widget" "GET /products returns product list"
assert_contains "/health" "ok" "GET /health returns ok"

# Test 4: 404 for unknown route
assert_status "/unknown" "404" "GET /unknown returns 404"

# Test 5: Extra args (auth: true on POST /users)
assert_contains "/routes" '"auth":true' "POST /users has auth:true extra arg"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="

if [ $FAIL -gt 0 ]; then
    exit 1
fi
