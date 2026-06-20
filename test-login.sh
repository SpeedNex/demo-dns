#!/bin/bash
set -e
cd "/Users/472733389qq.com/Desktop/ai agent/docs/ai-doc/ai-doc/ocer-dns/portal-web"

nohup php artisan serve --host=127.0.0.1 --port=8768 > /tmp/srv2.log 2>&1 &
SP=$!
sleep 4
echo "===HEALTH==="
curl -s -o /dev/null -w "HTTP=%{http_code}\n" http://127.0.0.1:8768/up
echo "===USER LOGIN==="
curl -s -X POST http://127.0.0.1:8768/api/v1/auth/login -H "Accept: application/json" -H "Content-Type: application/json" --data-binary '{"email":"admin@ocerdns.local","password":"admin12345"}' -w "\nHTTP=%{http_code}\n"
echo "===ADMIN LOGIN==="
curl -s -X POST http://127.0.0.1:8768/api/v1/admin/login -H "Accept: application/json" -H "Content-Type: application/json" --data-binary '{"email":"admin@ocerdns.local","password":"admin12345"}' -w "\nHTTP=%{http_code}\n"
kill $SP 2>/dev/null || true
wait 2>/dev/null || true
echo "DONE"
