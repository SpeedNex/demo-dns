#!/bin/bash
pkill -f "php artisan serve" 2>/dev/null
sleep 2
cd "/Users/472733389qq.com/Desktop/ai agent/docs/ai-doc/ai-doc/ocer-dns/portal-web"
nohup php artisan serve --host=127.0.0.1 --port=8770 > /tmp/srv4.log 2>&1 &
SP=$!
echo "PID=$SP"
sleep 4
echo "===HEALTH==="
curl -s -o /dev/null -w "HTTP=%{http_code}\n" http://127.0.0.1:8770/up
echo "===USER LOGIN==="
curl -s -X POST http://127.0.0.1:8770/api/v1/auth/login -H "Accept: application/json" -H "Content-Type: application/json" --data-binary '{"email":"admin@ocerdns.local","password":"admin12345"}' -w "\nHTTP=%{http_code}\n"
echo "===ADMIN LOGIN==="
curl -s -X POST http://127.0.0.1:8770/api/v1/admin/login -H "Accept: application/json" -H "Content-Type: application/json" --data-binary '{"email":"admin@ocerdns.local","password":"admin12345"}' -w "\nHTTP=%{http_code}\n"
echo "DONE"
kill $SP 2>/dev/null
wait 2>/dev/null
