<?php
/**
 * Under Construction Mode – Angeln Brandenburg
 *
 * - Zeigt Besuchern eine schöne "Bald verfügbar" Seite
 * - Admins + eingeloggte User sehen die echte Seite
 * - Kein Plugin-Overhead, kein .htaccess-Edit nötig
 * - Aktivierung: WP Admin → Einstellungen → Under Construction
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// 1. INTERCEPT FRONTEND REQUESTS
// ============================================================

add_action( 'template_redirect', 'abb_under_construction_redirect', 1 );
function abb_under_construction_redirect() {
	if ( ! get_option( 'abb_under_construction', false ) ) return;

	// Always allow: logged-in users, REST API, WP login/admin, cron, search engine verify
	if (
		is_user_logged_in()
		|| is_admin()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), '/wp-login.php' ) !== false
		|| strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), '/wp-admin' ) !== false
	) {
		return;
	}

	// Allow access to specific pages (e.g., Impressum) by slug
	$allowed_slugs = apply_filters( 'abb_uc_allowed_slugs', [
		'impressum',
		'datenschutz',
		'cookie-richtlinie',
	] );

	if ( is_page( $allowed_slugs ) ) return;

	// Allow IP whitelist (set in wp-config.php)
	$whitelist_ips = defined( 'ABB_UC_IP_WHITELIST' ) ? (array) ABB_UC_IP_WHITELIST : [];
	$visitor_ip    = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	if ( in_array( $visitor_ip, $whitelist_ips, true ) ) return;

	// Serve under construction page
	http_response_code( 503 );
	header( 'Retry-After: 86400' ); // Tell crawlers to retry in 24h
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );

	abb_render_under_construction_page();
	exit;
}

// ============================================================
// 2. HTML RENDER
// ============================================================

function abb_render_under_construction_page() {
	$launch_date  = get_option( 'abb_uc_launch_date', '' );
	$custom_msg   = get_option( 'abb_uc_message', 'Wir bauen gerade die beste Angelportal-Seite für Brandenburg.' );
	$show_signup  = get_option( 'abb_uc_show_signup', true );
	$social_links = get_option( 'abb_uc_social', [] );
	$site_name    = get_bloginfo( 'name' ) ?: 'Angeln Brandenburg';
	?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="description" content="<?php echo esc_attr( $site_name ); ?> – Bald verfügbar. <?php echo esc_attr( wp_strip_all_tags( $custom_msg ) ); ?>">
  <title><?php echo esc_html( $site_name ); ?> – Bald verfügbar</title>

  <style>
    /* ---- Reset & Base ---- */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue-dark:  #0d2137;
      --blue:       #1a3a5c;
      --blue-mid:   #2d5a8e;
      --blue-light: #5b9bd5;
      --green:      #3d6b4f;
      --green-light:#5a9470;
      --foam:       #e8f4f8;
      --white:      #ffffff;
      --radius:     1rem;
    }

    html { scroll-behavior: smooth; }

    body {
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--blue-dark);
      color: var(--foam);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      position: relative;
      overflow-x: hidden;
    }

    /* ---- Animated Background ---- */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 80%, rgba(61,107,79,.25) 0%, transparent 60%),
        radial-gradient(ellipse 60% 80% at 80% 20%, rgba(45,90,142,.3) 0%, transparent 60%),
        linear-gradient(180deg, var(--blue-dark) 0%, #0a1e30 100%);
      z-index: 0;
      animation: bg-shift 12s ease-in-out infinite alternate;
    }

    @keyframes bg-shift {
      0%   { opacity: 1; }
      100% { opacity: .85; }
    }

    /* ---- Water wave ---- */
    .wave-wrap {
      position: fixed;
      bottom: 0; left: 0; right: 0;
      height: 120px;
      overflow: hidden;
      z-index: 0;
      opacity: .35;
    }
    .wave {
      position: absolute;
      bottom: 0;
      width: 200%;
      height: 100%;
    }
    .wave svg { width: 100%; height: 100%; }
    .wave--1 { animation: wave1 8s linear infinite; }
    .wave--2 { animation: wave2 12s linear infinite; opacity: .5; }

    @keyframes wave1 { 0% { transform: translateX(0);    } 100% { transform: translateX(-50%); } }
    @keyframes wave2 { 0% { transform: translateX(-50%); } 100% { transform: translateX(0);    } }

    /* ---- Card ---- */
    .card {
      position: relative;
      z-index: 1;
      background: rgba(255,255,255,.06);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 1.5rem;
      padding: clamp(2rem, 5vw, 3.5rem);
      max-width: 600px;
      width: 100%;
      text-align: center;
      box-shadow: 0 24px 64px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.1);
    }

    /* ---- Logo / Icon ---- */
    .logo {
      font-size: 3.5rem;
      margin-bottom: .75rem;
      display: block;
      animation: bob 3s ease-in-out infinite;
    }
    @keyframes bob {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-8px); }
    }

    /* ---- Badge ---- */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      background: var(--green);
      color: #fff;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      padding: .3rem .9rem;
      border-radius: 999px;
      margin-bottom: 1.25rem;
    }
    .badge::before { content: '●'; font-size: .5rem; animation: pulse 1.5s ease-in-out infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }

    /* ---- Title ---- */
    h1 {
      font-size: clamp(1.6rem, 5vw, 2.5rem);
      font-weight: 800;
      color: var(--white);
      margin-bottom: .75rem;
      line-height: 1.15;
    }
    h1 span { color: var(--blue-light); }

    p.subtitle {
      font-size: clamp(.95rem, 2.5vw, 1.1rem);
      opacity: .8;
      line-height: 1.65;
      margin-bottom: 2rem;
    }

    /* ---- Countdown ---- */
    .countdown {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: .75rem;
      margin-bottom: 2rem;
    }
    .countdown__item {
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: .75rem;
      padding: .75rem .5rem .5rem;
    }
    .countdown__num {
      display: block;
      font-size: clamp(1.5rem, 4vw, 2.2rem);
      font-weight: 800;
      color: var(--blue-light);
      line-height: 1;
    }
    .countdown__label {
      display: block;
      font-size: .65rem;
      opacity: .6;
      text-transform: uppercase;
      letter-spacing: .08em;
      margin-top: .3rem;
    }

    /* ---- Email Signup ---- */
    .signup-form {
      display: flex;
      gap: .5rem;
      max-width: 420px;
      margin: 0 auto 1.5rem;
    }
    .signup-form input {
      flex: 1;
      background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 999px;
      padding: .75rem 1.25rem;
      color: #fff;
      font-size: .9rem;
      outline: none;
      transition: border-color .2s;
      min-width: 0;
    }
    .signup-form input::placeholder { opacity: .6; color: var(--foam); }
    .signup-form input:focus { border-color: var(--blue-light); }
    .signup-form button {
      background: var(--green);
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: .75rem 1.4rem;
      font-size: .9rem;
      font-weight: 700;
      cursor: pointer;
      white-space: nowrap;
      transition: background .2s, transform .15s;
      flex-shrink: 0;
    }
    .signup-form button:hover { background: var(--green-light); transform: translateY(-1px); }

    .signup-success {
      display: none;
      background: rgba(61,107,79,.25);
      border: 1px solid var(--green);
      border-radius: .5rem;
      padding: .75rem 1rem;
      font-size: .9rem;
      color: #a8dba8;
      margin-bottom: 1.5rem;
    }

    /* ---- Features Teaser ---- */
    .features {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: .75rem;
      margin-bottom: 2rem;
    }
    @media (max-width: 400px) { .features { grid-template-columns: 1fr; } }
    .feature {
      background: rgba(255,255,255,.05);
      border-radius: .75rem;
      padding: .75rem .5rem;
      font-size: .8rem;
      opacity: .8;
    }
    .feature-icon { font-size: 1.3rem; display: block; margin-bottom: .3rem; }

    /* ---- Admin link ---- */
    .admin-link {
      font-size: .75rem;
      opacity: .4;
      color: var(--foam);
      text-decoration: none;
      display: block;
      margin-top: 1rem;
      transition: opacity .2s;
    }
    .admin-link:hover { opacity: .8; }

    /* ---- Legal links ---- */
    .legal-links {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 1.25rem;
      font-size: .8rem;
      opacity: .5;
    }
    .legal-links a { color: var(--foam); text-decoration: none; transition: opacity .2s; }
    .legal-links a:hover { opacity: 1; text-decoration: underline; }
  </style>
</head>
<body>

  <!-- Water animation -->
  <div class="wave-wrap" aria-hidden="true">
    <div class="wave wave--1">
      <svg viewBox="0 0 1440 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path fill="rgba(91,155,213,0.3)" d="M0,60 C240,100 480,20 720,60 C960,100 1200,20 1440,60 L1440,120 L0,120 Z"/>
      </svg>
    </div>
    <div class="wave wave--2">
      <svg viewBox="0 0 1440 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path fill="rgba(61,107,79,0.25)" d="M0,40 C360,80 720,0 1080,40 C1260,60 1380,30 1440,40 L1440,120 L0,120 Z"/>
      </svg>
    </div>
  </div>

  <div class="card">
    <span class="logo" role="img" aria-label="Angel-Icon">🎣</span>

    <div class="badge">In Entwicklung</div>

    <h1><?php echo esc_html( $site_name ); ?><br><span>– Bald verfügbar</span></h1>

    <p class="subtitle"><?php echo esc_html( $custom_msg ); ?> Aktuelle Gewässerinfos, Beißindex und mehr – bald live!</p>

    <?php if ( $launch_date ) : ?>
    <!-- Countdown -->
    <div class="countdown" id="countdown" aria-label="Countdown bis zum Launch">
      <div class="countdown__item"><span class="countdown__num" id="cd-days">--</span><span class="countdown__label">Tage</span></div>
      <div class="countdown__item"><span class="countdown__num" id="cd-hours">--</span><span class="countdown__label">Stunden</span></div>
      <div class="countdown__item"><span class="countdown__num" id="cd-mins">--</span><span class="countdown__label">Minuten</span></div>
      <div class="countdown__item"><span class="countdown__num" id="cd-secs">--</span><span class="countdown__label">Sekunden</span></div>
    </div>
    <?php endif; ?>

    <!-- Feature teaser -->
    <div class="features" aria-label="Kommende Features">
      <div class="feature"><span class="feature-icon">📊</span>Beißindex täglich</div>
      <div class="feature"><span class="feature-icon">🗺️</span>3.000+ Gewässer</div>
      <div class="feature"><span class="feature-icon">💧</span>Live-Pegelstände</div>
    </div>

    <?php if ( $show_signup ) : ?>
    <!-- Email signup -->
    <div class="signup-success" id="signup-success">
      ✅ Super! Wir benachrichtigen dich beim Launch.
    </div>
    <form class="signup-form" id="signup-form" aria-label="Launch-Benachrichtigung">
      <input
        type="email"
        id="signup-email"
        placeholder="deine@email.de"
        required
        autocomplete="email"
        aria-label="E-Mail-Adresse"
      >
      <button type="submit">Benachrichtigen</button>
    </form>
    <p style="font-size:.75rem;opacity:.45;margin-bottom:0;">Kein Spam. Nur eine Mail bei Launch. Jederzeit abmeldbar.</p>
    <?php endif; ?>

    <a href="<?php echo esc_url( wp_login_url() ); ?>" class="admin-link" aria-label="Admin-Login">
      Admin →
    </a>

    <nav class="legal-links" aria-label="Rechtliche Links">
      <?php if ( get_page_by_path( 'impressum' ) && get_page_by_path( 'impressum' )->post_status === 'publish' ) : ?>
        <a href="<?php echo esc_url( home_url( '/impressum/' ) ); ?>">Impressum</a>
      <?php endif; ?>
      <?php if ( get_page_by_path( 'datenschutz' ) && get_page_by_path( 'datenschutz' )->post_status === 'publish' ) : ?>
        <a href="<?php echo esc_url( home_url( '/datenschutz/' ) ); ?>">Datenschutz</a>
      <?php endif; ?>
    </nav>
  </div>

  <?php if ( $launch_date ) : ?>
  <script>
    (function() {
      var target = new Date("<?php echo esc_js( $launch_date ); ?>T00:00:00").getTime();
      function update() {
        var now  = Date.now();
        var diff = target - now;
        if (diff <= 0) { document.getElementById('countdown').innerHTML = '<p style="color:#5a9470;font-weight:700;">🎉 Wir sind live!</p>'; return; }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        document.getElementById('cd-days').textContent  = String(d).padStart(2,'0');
        document.getElementById('cd-hours').textContent = String(h).padStart(2,'0');
        document.getElementById('cd-mins').textContent  = String(m).padStart(2,'0');
        document.getElementById('cd-secs').textContent  = String(s).padStart(2,'0');
      }
      update();
      setInterval(update, 1000);
    })();
  </script>
  <?php endif; ?>

  <?php if ( $show_signup ) : ?>
  <script>
    document.getElementById('signup-form').addEventListener('submit', function(e) {
      e.preventDefault();
      var email = document.getElementById('signup-email').value;
      if (!email) return;
      // Store in WordPress via AJAX
      var fd = new FormData();
      fd.append('action', 'abb_uc_signup');
      fd.append('email', email);
      fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'abb_uc_signup' ) ); ?>');
      fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: fd })
        .finally(function() {
          document.getElementById('signup-form').style.display = 'none';
          document.getElementById('signup-success').style.display = 'block';
        });
    });
  </script>
  <?php endif; ?>

</body>
</html>
	<?php
}

// ============================================================
// 3. AJAX: Email Signup Collection
// ============================================================

add_action( 'wp_ajax_nopriv_abb_uc_signup', 'abb_uc_signup_handler' );
add_action( 'wp_ajax_abb_uc_signup',        'abb_uc_signup_handler' );
function abb_uc_signup_handler() {
	check_ajax_referer( 'abb_uc_signup', 'nonce' );

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		wp_send_json_error( 'Ungültige E-Mail' );
	}

	$list   = get_option( 'abb_uc_signups', [] );
	$list[] = [
		'email' => $email,
		'date'  => current_time( 'mysql' ),
		'ip'    => md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ), // Hashed for DSGVO
	];

	// Deduplicate
	$list = array_unique( $list, SORT_REGULAR );

	update_option( 'abb_uc_signups', $list, false );
	wp_send_json_success();
}

// ============================================================
// 4. ADMIN MENU: Under Construction Settings
// ============================================================

add_action( 'admin_menu', 'abb_uc_admin_menu' );
function abb_uc_admin_menu() {
	add_options_page(
		'Under Construction',
		'🚧 Under Construction',
		'manage_options',
		'abb-under-construction',
		'abb_uc_settings_page'
	);
}

function abb_uc_settings_page() {
	if ( isset( $_POST['abb_uc_save'] ) && check_admin_referer( 'abb_uc_settings' ) ) {
		update_option( 'abb_under_construction', isset( $_POST['abb_under_construction'] ) ? 1 : 0 );
		update_option( 'abb_uc_message',         sanitize_textarea_field( wp_unslash( $_POST['abb_uc_message'] ?? '' ) ) );
		update_option( 'abb_uc_launch_date',     sanitize_text_field( wp_unslash( $_POST['abb_uc_launch_date'] ?? '' ) ) );
		update_option( 'abb_uc_show_signup',     isset( $_POST['abb_uc_show_signup'] ) ? 1 : 0 );

		echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert!</p></div>';
	}

	$is_active   = get_option( 'abb_under_construction', false );
	$message     = get_option( 'abb_uc_message', 'Wir bauen gerade die beste Angelportal-Seite für Brandenburg.' );
	$launch_date = get_option( 'abb_uc_launch_date', '' );
	$show_signup = get_option( 'abb_uc_show_signup', true );
	$signups     = get_option( 'abb_uc_signups', [] );
	?>
	<div class="wrap">
		<h1>🚧 Under Construction</h1>

		<?php if ( $is_active ) : ?>
			<div class="notice notice-warning inline" style="padding:1rem;">
				<strong>Under Construction Modus ist aktiv.</strong>
				Besucher sehen die "Bald verfügbar" Seite. Admins sehen die echte Seite.
				<a href="<?php echo esc_url( home_url() ); ?>" target="_blank">→ Vorschau als Besucher</a>
			</div>
		<?php else : ?>
			<div class="notice notice-success inline" style="padding:1rem;">
				<strong>Seite ist öffentlich sichtbar.</strong>
			</div>
		<?php endif; ?>

		<form method="post" style="max-width:700px;margin-top:1.5rem;">
			<?php wp_nonce_field( 'abb_uc_settings' ); ?>
			<table class="form-table">
				<tr>
					<th>Under Construction Modus</th>
					<td>
						<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
							<input type="checkbox" name="abb_under_construction" value="1" <?php checked( $is_active ); ?> style="width:18px;height:18px;">
							<strong>Aktiviert</strong>
						</label>
						<p class="description">Deaktiviere dies wenn die Seite live geht (vor Go-Live auch blog_public=1 setzen).</p>
					</td>
				</tr>
				<tr>
					<th><label for="abb_uc_message">Nachricht für Besucher</label></th>
					<td>
						<textarea id="abb_uc_message" name="abb_uc_message" class="large-text" rows="3"><?php echo esc_textarea( $message ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th><label for="abb_uc_launch_date">Launch-Datum (optional)</label></th>
					<td>
						<input type="date" id="abb_uc_launch_date" name="abb_uc_launch_date"
							   value="<?php echo esc_attr( $launch_date ); ?>" min="<?php echo esc_attr( date('Y-m-d') ); ?>">
						<p class="description">Zeigt einen Countdown-Timer auf der Under-Construction-Seite.</p>
					</td>
				</tr>
				<tr>
					<th>E-Mail-Anmeldung</th>
					<td>
						<label><input type="checkbox" name="abb_uc_show_signup" value="1" <?php checked( $show_signup ); ?>> Launch-Benachrichtigung anzeigen</label>
						<p class="description">Sammelt E-Mail-Adressen für die Launch-Benachrichtigung (DSGVO-konform, keine Weitergabe).</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="abb_uc_save" class="button button-primary button-large" value="Speichern">
				<?php if ( $is_active ) : ?>
					&nbsp;&nbsp;<a href="<?php echo esc_url( add_query_arg( [ 'abb_preview_uc' => '1' ], home_url() ) ); ?>" class="button" target="_blank">Vorschau anzeigen</a>
				<?php endif; ?>
			</p>
		</form>

		<?php if ( ! empty( $signups ) ) : ?>
		<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:1.5rem;margin-top:1.5rem;max-width:700px;">
			<h2><?php echo count( $signups ); ?> E-Mail-Anmeldungen</h2>
			<form method="post">
				<?php wp_nonce_field( 'abb_uc_export_signups' ); ?>
				<input type="submit" name="abb_export_signups" class="button" value="Als CSV exportieren">
			</form>
			<p class="description" style="margin-top:.5rem;">E-Mail-Adressen werden DSGVO-konform gespeichert. Nur für einmalige Launch-Benachrichtigung verwenden.</p>
		</div>
		<?php endif; ?>

		<div style="background:#f0f7ff;border-left:4px solid #5b9bd5;padding:1rem 1.5rem;margin-top:1.5rem;max-width:700px;border-radius:0 .5rem .5rem 0;">
			<h3 style="margin-top:0;">IP-Whitelist (für Team-Zugang ohne Login)</h3>
			<p>In <code>wp-config.php</code> eintragen:</p>
			<pre style="background:#fff;padding:.75rem;border-radius:.25rem;font-size:.85rem;">define( 'ABB_UC_IP_WHITELIST', [
    '123.456.789.0',  // Deine Büro-IP
    '98.76.54.321',   // Weitere IPs
] );</pre>
		</div>
	</div>
	<?php

	// CSV Export
	if ( isset( $_POST['abb_export_signups'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'abb_uc_export_signups' )
		&& current_user_can( 'manage_options' ) ) {
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="abb-signups-' . date( 'Y-m-d' ) . '.csv"' );
		echo "E-Mail,Datum\n";
		foreach ( $signups as $s ) {
			echo esc_html( $s['email'] ) . ',' . esc_html( $s['date'] ) . "\n";
		}
		exit;
	}
}

// ============================================================
// 5. ADMIN BAR INDICATOR
// ============================================================

add_action( 'admin_bar_menu', 'abb_uc_admin_bar_badge', 999 );
function abb_uc_admin_bar_badge( $wp_admin_bar ) {
	if ( ! get_option( 'abb_under_construction' ) ) return;
	if ( ! current_user_can( 'manage_options' ) ) return;

	$wp_admin_bar->add_node( [
		'id'     => 'abb-uc-status',
		'title'  => '🚧 Under Construction aktiv',
		'href'   => admin_url( 'options-general.php?page=abb-under-construction' ),
		'meta'   => [ 'title' => 'Under Construction Modus deaktivieren' ],
	] );

	// Inline style for red badge
	add_action( 'wp_head',    fn() => print '<style>#wp-admin-bar-abb-uc-status > a { background: #d63638 !important; color:#fff!important; }</style>' );
	add_action( 'admin_head', fn() => print '<style>#wp-admin-bar-abb-uc-status > a { background: #d63638 !important; color:#fff!important; }</style>' );
}
