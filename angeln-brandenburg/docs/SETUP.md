# Angeln Brandenburg 2026 – Setup & Deployment Guide

## Schnellübersicht

```
angeln-brandenburg/
├── config/           # .htaccess, robots.txt, wp-config additions
├── plugins/
│   ├── angeln-bb-core/         # Legal pages, 404-monitor, alt-text checker
│   ├── angeln-bb-beissindex/   # Beißindex algorithm (Luftdruck+Mondphase+Temp)
│   ├── angeln-bb-pegel/        # Pegelonline API real-time water levels
│   └── angeln-bb-ai-content/   # AI content generation (always Draft)
├── theme/            # Astra child theme (Mobile-First, Core Web Vitals)
├── scripts/          # golive-check.sh
└── docs/             # This file
```

---

## Phase 1: Server & WordPress Setup

### 1.1 WordPress Installation
```bash
wp core download --locale=de_DE
wp config create --dbname=angeln_bb --dbuser=USER --dbpass=PASS --dbprefix=abb_
wp core install --url=https://angeln-brandenburg.de --title="Angeln Brandenburg" --admin_user=admin
```

### 1.2 wp-config.php Ergänzungen
Kopiere den Inhalt von `config/wp-config-additions.php` in deine `wp-config.php`.

### 1.3 .htaccess
Ersetze die WordPress `.htaccess` mit `config/.htaccess`.

### 1.4 Maintenance Mode aktivieren
Entkommentiere den Maintenance-Block in der `.htaccess` (Zeilen 8–14).
Stelle sicher, dass `config/maintenance.html` im WordPress-Root liegt.

Aktiviere die maintenance `robots.txt`:
```bash
cp config/robots.txt.maintenance /var/www/html/robots.txt
```

---

## Phase 2: Theme Installation

### 2.1 Astra (Parent Theme)
```bash
wp theme install astra --activate
```

### 2.2 Child Theme
Kopiere den `theme/` Ordner nach `wp-content/themes/angeln-bb/` und aktiviere:
```bash
wp theme activate angeln-bb
```

### 2.3 Google Fonts
Montserrat wird automatisch geladen. Für optimale Performance:
- Downloade die Fonts mit `npm run download-fonts` (optional)
- Oder nutze Local Fonts (Plugin: OMGF)

---

## Phase 3: Plugins installieren

### 3.1 Empfohlene externe Plugins
```bash
# SEO
wp plugin install seo-by-rank-math --activate

# Performance
wp plugin install litespeed-cache --activate
wp plugin install ewww-image-optimizer --activate

# Security
wp plugin install wps-hide-login --activate
wp plugin install wordfence --activate

# DSGVO
wp plugin install complianz-gdpr --activate

# Formulare
wp plugin install contact-form-7 --activate
```

### 3.2 Eigene Plugins aktivieren
```bash
# Kopiere Plugins nach wp-content/plugins/
wp plugin activate angeln-bb-core
wp plugin activate angeln-bb-beissindex
wp plugin activate angeln-bb-pegel
wp plugin activate angeln-bb-ai-content
```

---

## Phase 3: SEO Konfiguration

### Permalinks
WordPress Admin → Einstellungen → Permalinks → **Benutzerdefiniert**: `/%category%/%postname%/`

Dies aktiviert automatisch die Gewässer-Permalink-Struktur:
- Gewässer: `/gewaesser/beetzsee/`
- News: `/news/hecht-angeln-tipps/`

### Rank Math SEO
1. Setup-Wizard durchlaufen
2. Sitemap: Aktiviere "Angelgewässer" in der Sitemap
3. Füge `https://angeln-brandenburg.de/sitemap.xml` in der Google Search Console ein

---

## Phase 4: API-Keys konfigurieren

### OpenWeatherMap (Beißindex)
1. Kostenloser Account: https://openweathermap.org/api
2. API-Key in `wp-config.php`:
```php
define( 'ABB_OPENWEATHER_KEY', 'dein-key-hier' );
```

### Google Maps (Gewässer-Karte)
1. Google Cloud Console → Maps Embed API aktivieren
2. Key in WordPress Admin → Angeln BB → Einstellungen

### KI-Content API (optional)
WordPress Admin → KI-Content → Einstellungen:
- **Anthropic Claude**: API-Key von console.anthropic.com
- **OpenAI GPT-4o**: API-Key von platform.openai.com

---

## Phase 5: Erstes Gewässer anlegen

1. WordPress Admin → Gewässer → Neu hinzufügen
2. Pflichtfelder:
   - Titel: `Beetzsee`
   - Koordinaten: `52.XXX, 12.XXX`
   - Fischarten: `Hecht, Zander, Barsch, Karpfen`
   - Tiefe, Fläche, Erlaubnis
3. Permalink prüfen: `/gewaesser/beetzsee/`
4. Schema.org wird automatisch generiert

---

## Phase 6: Go-Live Checklist

### Automatischer Check
```bash
bash scripts/golive-check.sh https://angeln-brandenburg.de
```

### Manuell: WordPress Admin → Einstellungen → Go-Live Check

### Finale Schritte
```bash
# 1. Maintenance Mode deaktivieren (.htaccess)

# 2. robots.txt freischalten
cp config/robots.txt.golive /var/www/html/robots.txt

# 3. WordPress Suchmaschinen-Einstellung
wp option update blog_public 1

# 4. Google Search Console
# → Sitemap einreichen: https://angeln-brandenburg.de/sitemap.xml
# → URL-Inspektion für Startseite

# 5. Bing Webmaster Tools
# → Sitemap einreichen

# 6. Cache leeren
wp cache flush
wp litespeed-purge --all
```

---

## Shortcode Referenz

| Shortcode | Beschreibung |
|-----------|-------------|
| `[beissindex]` | Vollständiger Beißindex-Widget |
| `[beissindex_mini]` | Kompakter Score-Badge |
| `[pegel_ticker]` | Pegel-Tabelle aller Stationen |
| `[pegel_ticker rivers="havel,oder"]` | Gefiltert nach Fluss |
| `[pegel_station uuid="..."]` | Einzelne Station |

---

## Cron Jobs (Server)

```cron
# Beißindex alle 3 Stunden
0 */3 * * * wget -q -O - "https://angeln-brandenburg.de/wp-cron.php?doing_wp_cron" >/dev/null 2>&1

# Pegel alle 30 Minuten
*/30 * * * * wget -q -O - "https://angeln-brandenburg.de/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
```

---

## Performance-Ziele (Core Web Vitals)

| Metrik | Ziel | Status |
|--------|------|--------|
| LCP (Largest Contentful Paint) | < 2,5s | ✅ Astra + Lazy Loading |
| FID/INP (Interaction to Next Paint) | < 200ms | ✅ Minimales JS |
| CLS (Cumulative Layout Shift) | < 0,1 | ✅ aspect-ratio CSS |
| TTFB (Time to First Byte) | < 800ms | Caching-Plugin |
| Ladezeit gesamt | < 1,5s | CDN + Kompression |

---

## Technologie-Stack

- **CMS**: WordPress 6.x
- **Theme**: Astra (Elternt-Theme) + Angeln BB Child Theme
- **Sprache**: PHP 8.0+, Vanilla JS (kein jQuery im Frontend)
- **CSS**: Custom Properties (CSS Variables), Mobile-First
- **APIs**: Pegelonline WSV, OpenWeatherMap, Google Maps Embed
- **KI**: Claude (Anthropic) oder GPT-4o (OpenAI) – optional
- **SEO**: Rank Math + eigene Schema.org JSON-LD
- **DSGVO**: Complianz Cookie Consent
- **Hosting**: Empfohlen: Netcup, IONOS, Hetzner (PHP 8.2, MariaDB)
