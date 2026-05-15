#!/bin/bash
# Benchmark: APA static array vs Reflection, measured inside the server.
# Each endpoint does 10K lookups internally and reports timing.

set -e
PORT_APA=8771
PORT_REF=8772
DIR="tests/bench_http"

# Start servers
php -d extension=modules/apa.so -S 127.0.0.1:$PORT_APA -t $DIR > /dev/null 2>&1 &
PID_APA=$!
php -S 127.0.0.1:$PORT_REF -t $DIR > /dev/null 2>&1 &
PID_REF=$!
sleep 0.5
trap "kill $PID_APA $PID_REF 2>/dev/null; wait $PID_APA $PID_REF 2>/dev/null" EXIT

echo "=== APA vs Reflection: 10K permission checks inside HTTP request ==="
echo ""

# Run each 5 times
echo "APA (static array lookup):"
for i in 1 2 3 4 5; do
    php -r "echo file_get_contents('http://127.0.0.1:$PORT_APA/apa_server.php') . PHP_EOL;" 2>/dev/null
done

echo ""
echo "Reflection (ReflectionMethod + getAttributes + newInstance):"
for i in 1 2 3 4 5; do
    php -r "echo file_get_contents('http://127.0.0.1:$PORT_REF/reflection_server.php') . PHP_EOL;" 2>/dev/null
done
