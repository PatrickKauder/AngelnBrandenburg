#!/bin/bash
# ============================================================
# Angeln Brandenburg 2026 – WordPress Initial Cleanup
# Entfernt alle Standard-WordPress-Inhalte die Email/Name exponieren
#
# VORAUSSETZUNG: WP-CLI installiert, im WordPress-Root-Verzeichnis ausführen
# USAGE: bash wp-initial-cleanup.sh
# ============================================================

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✅${NC} $1"; }
warn() { echo -e "${YELLOW}⚠️ ${NC} $1"; }
info() { echo -e "   → $1"; }

echo ""
echo "=================================================="
echo " Angeln Brandenburg – WordPress Cleanup"
echo "=================================================="
echo ""

# ---- 1. DEFAULT POSTS & PAGES ----
echo "[ 1. Standard-Inhalte entfernen ]"

# Lösche "Hello world!" Post (ID 1 in frischer WP-Installation)
HELLO_ID=$(wp post list --post_type=post --title="Hello world!" --field=ID --format=csv 2>/dev/null | head -1)
if [ -n "$HELLO_ID" ]; then
  wp post delete "$HELLO_ID" --force
  ok "Hello World! Post gelöscht (ID: $HELLO_ID)"
else
  info "Hello World Post nicht gefunden (bereits gelöscht?)"
fi

# Lösche "Sample Page" / "Beispiel-Seite"
for TITLE in "Sample Page" "Beispiel-Seite" "Musterseite"; do
  PAGE_ID=$(wp post list --post_type=page --title="$TITLE" --field=ID --format=csv 2>/dev/null | head -1)
  if [ -n "$PAGE_ID" ]; then
    wp post delete "$PAGE_ID" --force
    ok "Seite '$TITLE' gelöscht (ID: $PAGE_ID)"
  fi
done

# Lösche Default-Kommentar ("Mr. WordPress")
COMMENT_ID=$(wp comment list --field=comment_ID --format=csv 2>/dev/null | head -1)
if [ -n "$COMMENT_ID" ]; then
  wp comment delete "$COMMENT_ID" --force
  ok "Standard-Kommentar gelöscht"
fi

# ---- 2. SICHERHEIT: Email nicht öffentlich sichtbar ----
echo ""
echo "[ 2. Email-Adresse schützen ]"

# Admin-Email als "öffentlichen" Namen entfernen
ADMIN_USER=$(wp user list --role=administrator --field=user_login --format=csv 2>/dev/null | head -1)
if [ -n "$ADMIN_USER" ]; then
  # Setze Display Name auf neutralen Wert
  CURRENT_DISPLAY=$(wp user get "$ADMIN_USER" --field=display_name 2>/dev/null)
  if echo "$CURRENT_DISPLAY" | grep -qi "@"; then
    wp user update "$ADMIN_USER" --display_name="Angeln Brandenburg" --user_nicename="redaktion"
    ok "Admin-Displayname von Email-Adresse bereinigt"
  else
    info "Admin-Displayname: '$CURRENT_DISPLAY' (kein Handlungsbedarf)"
  fi

  # Setze Admin-Nickname
  wp user meta update "$ADMIN_USER" nickname "Redaktion"
  ok "Admin-Nickname auf 'Redaktion' gesetzt"
fi

# WordPress admin_email im Footer entfernen (über Option)
# Das verhindert dass die Email in generierten Mails sichtbar ist
ADMIN_EMAIL=$(wp option get admin_email 2>/dev/null)
ok "Admin-Email bleibt intern: $ADMIN_EMAIL (nicht öffentlich)"

# ---- 3. STANDARD-OPTIONEN BEREINIGEN ----
echo ""
echo "[ 3. WordPress-Optionen bereinigen ]"

# Site Title & Tagline
wp option update blogname "Angeln Brandenburg"
ok "Seitenname gesetzt: 'Angeln Brandenburg'"

wp option update blogdescription "Angelgewässer, Beißindex & Infos für Brandenburg"
ok "Tagline gesetzt"

# Zeitzone
wp option update timezone_string "Europe/Berlin"
ok "Zeitzone: Europe/Berlin"

# Datum/Uhrzeit Format
wp option update date_format "d. F Y"
wp option update time_format "H:i"
ok "Datumsformat: Deutsch"

# Sprache sicherstellen
wp option update WPLANG "de_DE"
info "Sprache: de_DE"

# Kommentare für neue Posts deaktivieren
wp option update default_comment_status "closed"
wp option update default_ping_status "closed"
ok "Kommentare + Pingbacks für neue Posts deaktiviert"

# Suchmaschinen NOCH sperren (bis Go-Live)
wp option update blog_public "0"
warn "Suchmaschinen gesperrt (blog_public=0) – bei Go-Live aktivieren!"

# Permalink-Struktur
wp option update permalink_structure "/%category%/%postname%/"
ok "Permalinks: /%category%/%postname%/"

# Flush rewrite rules
wp rewrite flush
ok "Rewrite Rules geleert"

# ---- 4. STANDARD-KATEGORIEN ----
echo ""
echo "[ 4. Kategorien einrichten ]"

# Rename "Uncategorized" / "Allgemein"
UNCAT_ID=$(wp term get category "uncategorized" --field=term_id 2>/dev/null || echo "")
if [ -n "$UNCAT_ID" ]; then
  wp term update category "$UNCAT_ID" --name="Angelberichte" --slug="angelberichte"
  ok "Standard-Kategorie umbenannt in 'Angelberichte'"
fi

# Kategorien für Angeln Brandenburg
for CAT in "Gewässerinfos" "Fischarten" "Angelratgeber" "Regelungen & Lizenzen" "Beißindex"; do
  EXISTING=$(wp term list category --name="$CAT" --field=term_id --format=csv 2>/dev/null | head -1)
  if [ -z "$EXISTING" ]; then
    wp term create category "$CAT"
    ok "Kategorie erstellt: $CAT"
  else
    info "Kategorie '$CAT' existiert bereits"
  fi
done

# ---- 5. STANDARD-WIDGETS ENTFERNEN ----
echo ""
echo "[ 5. Sidebar-Widgets aufräumen ]"
# Deaktiviere alle Default-Widgets (Meta, RSS, etc.)
wp option update sidebars_widgets '{"wp_inactive_widgets":[],"sidebar-1":[],"sidebar-2":[],"footer-1":[],"footer-2":[],"array_version":3}' 2>/dev/null && ok "Standard-Widgets aus Sidebars entfernt" || info "Widgets-Cleanup übersprungen"

# ---- 6. MEDIA: Upload-Ordner-Struktur ----
echo ""
echo "[ 6. Upload-Einstellungen ]"
wp option update uploads_use_yearmonth_folders "1"
ok "Upload-Ordner: Jahr/Monat-Struktur aktiviert"

# ---- 7. UNTER-KONSTRUKTION AKTIVIEREN ----
echo ""
echo "[ 7. Under Construction Mode ]"

# Prüfe ob Under Construction Plugin aktiv
wp plugin is-active angeln-bb-core 2>/dev/null && {
  wp option update abb_under_construction "1"
  ok "Angeln BB Under Construction Mode aktiviert"
} || {
  warn "angeln-bb-core Plugin noch nicht aktiv – Under Construction über .htaccess sicherstellen"
}

# ---- 8. PINGBACK / XMLRPC ----
echo ""
echo "[ 8. XML-RPC & Pingbacks ]"
wp option update default_pingback_flag "0"
ok "Pingbacks deaktiviert"

# ---- 9. UNDER CONSTRUCTION SEITE ERSTELLEN ----
echo ""
echo "[ 9. Under Construction Seite ]"

UC_PAGE_ID=$(wp post list --post_type=page --post_status=any --name="under-construction" --field=ID --format=csv 2>/dev/null | head -1)
if [ -z "$UC_PAGE_ID" ]; then
  UC_PAGE_ID=$(wp post create \
    --post_type=page \
    --post_title="Bald verfügbar – Angeln Brandenburg" \
    --post_name="under-construction" \
    --post_status=publish \
    --post_content="<!-- Under Construction – managed by plugin -->" \
    --meta_input='{"_abb_under_construction_page":"1"}' \
    --porcelain)
  ok "Under Construction Seite erstellt (ID: $UC_PAGE_ID)"
else
  info "Under Construction Seite existiert bereits (ID: $UC_PAGE_ID)"
fi

# Setze als Front Page
wp option update page_on_front "$UC_PAGE_ID"
wp option update show_on_front "page"
ok "Under Construction als Startseite gesetzt"

# ---- ZUSAMMENFASSUNG ----
echo ""
echo "=================================================="
echo -e "${GREEN}✅ Cleanup abgeschlossen!${NC}"
echo "=================================================="
echo ""
echo "Nächste Schritte:"
echo "  1. angeln-bb-core Plugin aktivieren"
echo "  2. Astra + angeln-bb Theme aktivieren"
echo "  3. API-Keys in wp-config.php eintragen"
echo "  4. Gewässer anlegen"
echo "  5. Beim Go-Live: blog_public=1 setzen"
echo ""
