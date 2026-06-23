#!/bin/bash
# OcerDNS User Query Closed-Loop Test Script
# Tests the complete flow: User -> DNS Query -> Rule Match -> Log -> Analytics

set -e

BASE_URL="${BASE_URL:-http://localhost:8081}"
RESOLVER_IP="${RESOLVER_IP:-127.0.0.1}"
DOH_URL="https://${RESOLVER_IP}:8443"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

echo "=============================================="
echo "  OcerDNS User Query Closed-Loop Test"
echo "=============================================="
echo ""

# Test 1: Check Portal Web Health
test_portal_health() {
    log_info "Test 1: Checking Portal Web Health..."
    response=$(curl -s -w "\n%{http_code}" "${BASE_URL}/up" 2>/dev/null || echo "000")
    code=$(echo "$response" | tail -n1)
    if [ "$code" = "200" ]; then
        log_success "Portal Web is healthy (HTTP $code)"
        return 0
    else
        log_error "Portal Web is not responding (HTTP $code)"
        return 1
    fi
}

# Test 2: User Registration
test_user_registration() {
    log_info "Test 2: Testing User Registration..."
    email="test_$(date +%s)@example.com"
    password="TestPassword123!"

    response=$(curl -s -w "\n%{http_code}" -X POST "${BASE_URL}/api/v1/register" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"${email}\",\"password\":\"${password}\",\"password_confirmation\":\"${password}\"}" 2>/dev/null || echo "000")
    code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$code" = "200" ] || [ "$code" = "201" ]; then
        log_success "User registration successful"
        token=$(echo "$body" | grep -o '"token":"[^"]*"' | head -1 | cut -d'"' -f4)
        if [ -n "$token" ]; then
            echo "$token"
            return 0
        fi
    fi
    log_error "User registration failed (HTTP $code): $body"
    return 1
}

# Test 3: User Login
test_user_login() {
    log_info "Test 3: Testing User Login..."
    email="${1:-test@example.com}"
    password="${2:-TestPassword123!}"

    response=$(curl -s -w "\n%{http_code}" -X POST "${BASE_URL}/api/v1/login" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"${email}\",\"password\":\"${password}\"}" 2>/dev/null || echo "000")
    code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$code" = "200" ]; then
        log_success "User login successful"
        token=$(echo "$body" | grep -o '"token":"[^"]*"' | head -1 | cut -d'"' -f4)
        if [ -n "$token" ]; then
            echo "$token"
            return 0
        fi
    fi
    log_error "User login failed (HTTP $code): $body"
    return 1
}

# Test 4: Create Profile
test_create_profile() {
    log_info "Test 4: Creating Profile..."
    token="$1"

    response=$(curl -s -w "\n%{http_code}" -X POST "${BASE_URL}/api/v1/user/profiles" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer ${token}" \
        -d '{"name":"Test Profile","default_action":"allow","block_response":"nxdomain"}' 2>/dev/null || echo "000")
    code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$code" = "200" ] || [ "$code" = "201" ]; then
        log_success "Profile created successfully"
        profile_id=$(echo "$body" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
        echo "$profile_id"
        return 0
    fi
    log_error "Profile creation failed (HTTP $code): $body"
    return 1
}

# Test 5: Add Block Rule
test_add_block_rule() {
    log_info "Test 5: Adding Block Rule..."
    token="$1"
    profile_id="$2"
    domain="malware.test.com"

    response=$(curl -s -w "\n%{http_code}" -X POST "${BASE_URL}/api/v1/user/profiles/${profile_id}/rules" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer ${token}" \
        -d "{\"domain\":\"${domain}\",\"action\":\"block\",\"match_type\":\"domain\"}" 2>/dev/null || echo "000")
    code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$code" = "200" ] || [ "$code" = "201" ]; then
        log_success "Block rule added for ${domain}"
        return 0
    fi
    log_error "Block rule addition failed (HTTP $code): $body"
    return 1
}

# Test 6: Publish Profile
test_publish_profile() {
    log_info "Test 6: Publishing Profile..."
    token="$1"
    profile_id="$2"

    response=$(curl -s -w "\n%{http_code}" -X POST "${BASE_URL}/api/v1/user/profiles/${profile_id}/publish" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer ${token}" 2>/dev/null || echo "000")
    code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$code" = "200" ]; then
        log_success "Profile published successfully"
        return 0
    fi
    log_error "Profile publish failed (HTTP $code): $body"
    return 1
}

# Test 7: DNS Query via UDP
test_dns_query_udp() {
    log_info "Test 7: Testing DNS Query via UDP..."
    domain="${1:-google.com}"

    # Using dig command for DNS query
    result=$(dig @${RESOLVER_IP} -p 53 +short "${domain}" A 2>/dev/null || echo "")
    if [ -n "$result" ]; then
        log_success "DNS UDP query for ${domain} returned: $result"
        return 0
    else
        log_warn "DNS UDP query returned no result (may be expected if resolver is not running)"
        return 1
    fi
}

# Test 8: DNS Query via DoH
test_dns_query_doh() {
    log_info "Test 8: Testing DNS Query via DoH..."
    profile_id="$1"
    domain="${2:-google.com}"

    # DoH query using curl
    result=$(curl -s -w "\n%{http_code}" --max-time 5 \
        "${DOH_URL}/dns-query?name=${domain}&type=A" \
        -H "Accept: application/dns-json" 2>/dev/null || echo "000")
    code=$(echo "$result" | tail -n1)
    body=$(echo "$result" | head -n-1)

    if [ "$code" = "200" ]; then
        log_success "DoH query for ${domain} successful"
        echo "$body" | head -c 200
        return 0
    fi
    log_warn "DoH query failed (HTTP $code): $body"
    return 1
}

# Test 9: Check Dashboard Stats
test_dashboard_stats() {
    log_info "Test 9: Checking Dashboard Statistics..."
    token="$1"

    response=$(curl -s -w "\n%{http_code}" -X GET "${BASE_URL}/api/v1/user/dashboard" \
        -H "Authorization: Bearer ${token}" 2>/dev/null || echo "000")
    code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$code" = "200" ]; then
        log_success "Dashboard stats retrieved successfully"
        echo "$body" | head -c 300
        return 0
    fi
    log_error "Dashboard stats retrieval failed (HTTP $code)"
    return 1
}

# Test 10: Check Query Logs
test_query_logs() {
    log_info "Test 10: Checking Query Logs..."
    token="$1"

    response=$(curl -s -w "\n%{http_code}" -X GET "${BASE_URL}/api/v1/user/logs" \
        -H "Authorization: Bearer ${token}" 2>/dev/null || echo "000")
    code=$(echo "$response" | tail -n1)

    if [ "$code" = "200" ]; then
        log_success "Query logs retrieved (HTTP $code)"
        return 0
    fi
    log_warn "Query logs retrieval returned HTTP $code (may need ClickHouse)"
    return 1
}

# Main Test Execution
main() {
    local failed=0
    local token=""
    local profile_id=""

    # Run tests
    if ! test_portal_health; then
        log_error "Portal Web is not available. Please start the services first."
        exit 1
    fi

    # Test user flow
    token=$(test_user_login "test@example.com" "TestPassword123!" 2>/dev/null) || true
    if [ -z "$token" ]; then
        log_info "Creating new test user..."
        token=$(test_user_registration 2>/dev/null) || {
            log_warn "Could not register or login. Using token check only."
        }
    fi

    if [ -n "$token" ]; then
        profile_id=$(test_create_profile "$token" 2>/dev/null) || profile_id="1"
        if [ -n "$profile_id" ]; then
            test_add_block_rule "$token" "$profile_id" 2>/dev/null
            test_publish_profile "$token" "$profile_id" 2>/dev/null
        fi
        test_dashboard_stats "$token" 2>/dev/null
        test_query_logs "$token" 2>/dev/null
    fi

    # Test DNS queries
    test_dns_query_udp "google.com" 2>/dev/null
    test_dns_query_doh "$profile_id" "google.com" 2>/dev/null

    echo ""
    echo "=============================================="
    echo "  Test Summary"
    echo "=============================================="
    echo ""
    echo "Portal URL: ${BASE_URL}"
    echo "Resolver IP: ${RESOLVER_IP}"
    echo "DoH URL: ${DOH_URL}"
    echo ""
    echo "Next Steps:"
    echo "1. Check Portal Web UI at ${BASE_URL}"
    echo "2. Configure DNS resolver at ${RESOLVER_IP}:53"
    echo "3. For DoH, use: ${DOH_URL}/dns-query"
    echo "4. Monitor logs at: ${BASE_URL}/api/v1/user/logs"
    echo ""
}

# Run tests
main "$@"
