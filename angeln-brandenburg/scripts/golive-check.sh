#!/bin/bash
# ============================================================
# Angeln Brandenburg 2026 – Go-Live Checklist Script
# Phase 6: Finaler Check vor Freischaltung
#
# Usage: bash golive-check.sh https://angeln-brandenburg.de
# ============================================================

SITE_URL="${1:-https://angeln-brandenburg.de}"
PASS=0
FAIL=0
WARN=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

check_pass() { echo -e "${GREEN}✅ PASS${NC} – $1"; ((PASS++)); }
check_fail() { echo -e "${RED}❌ FAIL${NC} – $1"; ((FAIL++)); }
check_warn() { echo -e "${YELLOW}⚠️  WARN${NC} – $1"; ((WARN++)); }

echo ""
echo "=================================================="
echo " Angeln Brandenburg – Go-Live Check"
echo " Site: $SITE_URL"
echo " Date: $(date)"
echo "=================================================="

# ============================================================
# 1. CONNECTIVITY
# ============================================================
echo ""
echo "[ 1. Connectivity ]"

HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL" 2>/dev/null)
if [ "$HTTP_STATUS" = "200" ]; then
  check_pass "Site erreichbar (HTTP $HTTP_STATUS)"
elif [ "$HTTP_STATUS" = "301" ] || [ "$HTTP_STATUS" = "302" ]; then
  check_warn "Redirect erkannt (HTTP $HTTP_STATUS)"
else
  check_fail "Site nicht erreichbar (HTTP $HTTP_STATUS)"
fi

# HTTPS check
if [[ "$SITE_URL" == https://* ]]; then
  check_pass "HTTPS aktiv"
else
  check_fail "HTTPS nicht konfiguriert!"
fi

# WWW redirect
WWW_URL="${SITE_URL//https:\/\//https://www.}"
WWW_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$WWW_URL" 2>/dev/null)
if [ "$WWW_STATUS" = "301" ]; then
  check_pass "WWW-Redirect aktiv"
else
  check_warn "WWW-Redirect prüfen ($WWW_STATUS)"
fi

# ============================================================
# 2. SECURITY HEADERS
# ============================================================
echo ""
echo "[ 2. Security Headers ]"

HEADERS=$(curl -s -I "$SITE_URL" 2>/dev/null)

echo "$HEADERS" | grep -qi "X-Content-Type-Options: nosniff" && check_pass "X-Content-Type-Options" || check_fail "X-Content-Type-Options fehlt"
echo "$HEADERS" | grep -qi "X-Frame-Options" && check_pass "X-Frame-Options" || check_fail "X-Frame-Options fehlt"
echo "$HEADERS" | grep -qi "X-XSS-Protection" && check_pass "X-XSS-Protection" || check_fail "X-XSS-Protection fehlt"
echo "$HEADERS" | grep -qi "Referrer-Policy" && check_pass "Referrer-Policy" || check_warn "Referrer-Policy fehlt"
echo "$HEADERS" | grep -qi "Permissions-Policy" && check_pass "Permissions-Policy" || check_warn "Permissions-Policy fehlt"

# Check HSTS
echo "$HEADERS" | grep -qi "Strict-Transport-Security" && check_pass "HSTS Header" || check_warn "HSTS Header fehlt – für SSL-Score empfohlen"

# ============================================================
# 3. WORDPRESS SPECIFIC
# ============================================================
echo ""
echo "[ 3. WordPress Security ]"

# XML-RPC should be disabled
XML_RPC_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/xmlrpc.php" 2>/dev/null)
if [ "$XML_RPC_STATUS" = "403" ] || [ "$XML_RPC_STATUS" = "404" ]; then
  check_pass "XML-RPC deaktiviert ($XML_RPC_STATUS)"
else
  check_fail "XML-RPC erreichbar ($XML_RPC_STATUS) – deaktivieren!"
fi

# wp-config.php should not be accessible
WPCONFIG_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/wp-config.php" 2>/dev/null)
if [ "$WPCONFIG_STATUS" = "403" ] || [ "$WPCONFIG_STATUS" = "404" ] || [ "$WPCONFIG_STATUS" = "500" ]; then
  check_pass "wp-config.php geschützt ($WPCONFIG_STATUS)"
else
  check_fail "wp-config.php erreichbar ($WPCONFIG_STATUS)!"
fi

# .env should not be accessible
ENV_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/.env" 2>/dev/null)
if [ "$ENV_STATUS" = "403" ] || [ "$ENV_STATUS" = "404" ]; then
  check_pass ".env geschützt"
else
  check_fail ".env erreichbar!"
fi

# ============================================================
# 4. SEO CHECKS
# ============================================================
echo ""
echo "[ 4. SEO ]"

# robots.txt
ROBOTS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/robots.txt" 2>/dev/null)
[ "$ROBOTS_STATUS" = "200" ] && check_pass "robots.txt erreichbar" || check_fail "robots.txt fehlt ($ROBOTS_STATUS)"

ROBOTS_CONTENT=$(curl -s "$SITE_URL/robots.txt" 2>/dev/null)
echo "$ROBOTS_CONTENT" | grep -qi "Disallow: /$" && check_fail "robots.txt blockiert noch alle Crawler!" || check_pass "robots.txt erlaubt Indexierung"
echo "$ROBOTS_CONTENT" | grep -qi "Sitemap:" && check_pass "Sitemap in robots.txt" || check_warn "Sitemap-URL fehlt in robots.txt"

# Sitemap
SITEMAP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/sitemap.xml" 2>/dev/null)
[ "$SITEMAP_STATUS" = "200" ] && check_pass "sitemap.xml erreichbar" || check_fail "sitemap.xml nicht erreichbar ($SITEMAP_STATUS)"

SITEMAP_GW_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/sitemap-gewaesser.xml" 2>/dev/null)
[ "$SITEMAP_GW_STATUS" = "200" ] && check_pass "sitemap-gewaesser.xml erreichbar" || check_warn "sitemap-gewaesser.xml nicht erreichbar"

# Canonical URL check
PAGE_SOURCE=$(curl -s "$SITE_URL" 2>/dev/null)
echo "$PAGE_SOURCE" | grep -qi 'rel="canonical"' && check_pass "Canonical Tag vorhanden" || check_warn "Canonical Tag prüfen"

# Schema.org on homepage
echo "$PAGE_SOURCE" | grep -qi 'application/ld+json' && check_pass "JSON-LD Schema.org vorhanden" || check_warn "JSON-LD Schema fehlt auf Homepage"

# ============================================================
# 5. PERFORMANCE
# ============================================================
echo ""
echo "[ 5. Performance ]"

# Response time
START=$(date +%s%N)
curl -s -o /dev/null "$SITE_URL" 2>/dev/null
END=$(date +%s%N)
ELAPSED=$(( (END - START) / 1000000 ))

if [ "$ELAPSED" -lt 1500 ]; then
  check_pass "Ladezeit: ${ELAPSED}ms (Ziel: <1500ms)"
elif [ "$ELAPSED" -lt 3000 ]; then
  check_warn "Ladezeit: ${ELAPSED}ms (Ziel: <1500ms)"
else
  check_fail "Ladezeit: ${ELAPSED}ms – Optimierung erforderlich!"
fi

# Compression
GZIP_CHECK=$(curl -s -H "Accept-Encoding: gzip" -I "$SITE_URL" 2>/dev/null | grep -i "content-encoding: gzip")
[ -n "$GZIP_CHECK" ] && check_pass "GZIP/Deflate-Kompression aktiv" || check_warn "Kompression prüfen"

# Cache headers
CACHE_CHECK=$(curl -s -I "$SITE_URL" 2>/dev/null | grep -i "Cache-Control\|Expires")
[ -n "$CACHE_CHECK" ] && check_pass "Cache-Header gesetzt" || check_warn "Cache-Header prüfen"

# ============================================================
# 6. LEGAL (DSGVO)
# ============================================================
echo ""
echo "[ 6. Legal / DSGVO ]"

IMPRESSUM_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/impressum/" 2>/dev/null)
[ "$IMPRESSUM_STATUS" = "200" ] && check_pass "Impressum erreichbar" || check_fail "Impressum fehlt! ($IMPRESSUM_STATUS)"

DATENSCHUTZ_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/datenschutz/" 2>/dev/null)
[ "$DATENSCHUTZ_STATUS" = "200" ] && check_pass "Datenschutzerklärung erreichbar" || check_fail "Datenschutzerklärung fehlt! ($DATENSCHUTZ_STATUS)"

# Cookie consent
echo "$PAGE_SOURCE" | grep -qi "complianz\|cookiebot\|cookieconsent\|cc-" && check_pass "Cookie-Consent erkannt" || check_warn "Cookie-Consent prüfen"

# ============================================================
# 7. CONTENT QUALITY
# ============================================================
echo ""
echo "[ 7. Content Quality ]"

# Check for alt texts on images (sample)
MISSING_ALT=$(echo "$PAGE_SOURCE" | grep -c 'img[^>]*src[^>]*>' 2>/dev/null)
ALT_COUNT=$(echo "$PAGE_SOURCE" | grep -c 'img[^>]*alt=' 2>/dev/null)
if [ "$ALT_COUNT" -gt 0 ]; then
  check_pass "Alt-Texte für Bilder vorhanden ($ALT_COUNT)"
else
  check_warn "Alt-Texte für Bilder prüfen"
fi

# H1 check
H1_COUNT=$(echo "$PAGE_SOURCE" | grep -c '<h1' 2>/dev/null)
if [ "$H1_COUNT" = "1" ]; then
  check_pass "Genau 1 H1-Tag"
elif [ "$H1_COUNT" -gt 1 ]; then
  check_warn "Mehrere H1-Tags ($H1_COUNT) – SEO-Problem!"
else
  check_fail "Kein H1-Tag gefunden!"
fi

# Meta description
echo "$PAGE_SOURCE" | grep -qi 'name="description"' && check_pass "Meta-Description vorhanden" || check_fail "Meta-Description fehlt!"

# ============================================================
# SUMMARY
# ============================================================
echo ""
echo "=================================================="
echo " ERGEBNIS"
echo "=================================================="
echo -e "${GREEN}✅ PASS: $PASS${NC}"
echo -e "${YELLOW}⚠️  WARN: $WARN${NC}"
echo -e "${RED}❌ FAIL: $FAIL${NC}"
echo ""

if [ "$FAIL" -gt 0 ]; then
  echo -e "${RED}❌ GO-LIVE NICHT EMPFOHLEN – $FAIL kritische Fehler beheben!${NC}"
  exit 1
elif [ "$WARN" -gt 3 ]; then
  echo -e "${YELLOW}⚠️  Vorsichtig – $WARN Warnungen vor Go-Live prüfen${NC}"
  exit 0
else
  echo -e "${GREEN}✅ BEREIT FÜR GO-LIVE!${NC}"
  exit 0
fi
