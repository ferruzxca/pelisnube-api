#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080/api/v1}"

printf "\n[1] Health\n"
curl -sS "$BASE_URL/health" | jq .

printf "\n[2] Public catalog\n"
curl -sS "$BASE_URL/catalog?page=1&pageSize=5" | jq '.data.items | length'

printf "\n[3] Admin login\n"
LOGIN=$(curl -sS -X POST "$BASE_URL/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@pelisnube.local","password":"Admin12345!"}')

echo "$LOGIN" | jq '.message'
TOKEN=$(echo "$LOGIN" | jq -r '.data.token')

printf "\n[4] Auth catalog detail\n"
SLUG=$(curl -sS "$BASE_URL/catalog?page=1&pageSize=1" | jq -r '.data.items[0].slug')
curl -sS "$BASE_URL/catalog/$SLUG" -H "Authorization: Bearer $TOKEN" | jq '.success'

printf "\nSmoke complete\n"
