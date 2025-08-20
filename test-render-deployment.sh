#!/bin/bash

echo "=== Testing Render Deployment ==="

BASE_URL="https://back-eypq.onrender.com"

echo "1. Testing health endpoint..."
curl -s "$BASE_URL/health" | jq . || echo "Health check failed"

echo "2. Testing API health..."
curl -s "$BASE_URL/api/public/health" | jq . || echo "API health check failed"

echo "3. Testing API info..."
curl -s "$BASE_URL/api/public/info" | jq . || echo "API info failed"

echo "4. Testing Mercure endpoint..."
curl -s -H "Accept: text/event-stream" "$BASE_URL/.well-known/mercure?topic=test" --max-time 5 || echo "Mercure test failed"

echo "=== Test completed ==="
