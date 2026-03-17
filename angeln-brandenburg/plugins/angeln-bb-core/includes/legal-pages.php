<?php
/**
 * Legal Pages Helper – Phase 1/6
 *
 * Ensures Impressum and Datenschutz pages exist.
 * Run on plugin activation.
 */

defined( 'ABSPATH' ) || exit;

register_activation_hook( ABB_CORE_DIR . '../angeln-bb-core.php', 'abb_create_legal_pages' );

function abb_create_legal_pages() {
	$pages = [
		'impressum'  => [
			'title'   => 'Impressum',
			'slug'    => 'impressum',
			'content' => abb_get_impressum_template(),
		],
		'datenschutz' => [
			'title'   => 'Datenschutzerklärung',
			'slug'    => 'datenschutz',
			'content' => abb_get_datenschutz_template(),
		],
		'cookie-richtlinie' => [
			'title'   => 'Cookie-Richtlinie',
			'slug'    => 'cookie-richtlinie',
			'content' => '[complianz_policies type="cookie-statement"]',
		],
	];

	foreach ( $pages as $slug => $page ) {
		// Skip if page already exists
		if ( get_page_by_path( $page['slug'] ) ) continue;

		wp_insert_post( [
			'post_title'   => $page['title'],
			'post_name'    => $page['slug'],
			'post_content' => $page['content'],
			'post_status'  => 'draft', // Editor must review before publishing
			'post_type'    => 'page',
			'meta_input'   => [ '_abb_legal_page' => '1' ],
		] );
	}
}

function abb_get_impressum_template(): string {
	return <<<HTML
<!-- WICHTIG: Diesen Text vollständig mit deinen echten Daten ersetzen! -->
<!-- Nutze den Impressumsgenerator auf https://www.e-recht24.de/impressum-generator.html -->

<h2>Angaben gemäß § 5 TMG</h2>
<p>
[Vorname Nachname]<br>
[Straße Hausnummer]<br>
[PLZ Ort]<br>
Deutschland
</p>

<h2>Kontakt</h2>
<p>
Telefon: [Telefonnummer]<br>
E-Mail: [E-Mail-Adresse]
</p>

<h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
<p>
[Vorname Nachname]<br>
[Straße Hausnummer]<br>
[PLZ Ort]
</p>

<h2>Haftungsausschluss</h2>

<h3>Haftung für Inhalte</h3>
<p>Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.</p>

<h3>Haftung für Links</h3>
<p>Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich.</p>

<h3>Urheberrecht</h3>
<p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Beiträge Dritter sind als solche gekennzeichnet. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.</p>

<h2>Streitschlichtung</h2>
<p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr/</a>.<br>Unsere E-Mail-Adresse finden Sie oben im Impressum.</p>
<p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>
HTML;
}

function abb_get_datenschutz_template(): string {
	return <<<HTML
<!-- WICHTIG: Datenschutzerklärung individuell anpassen! -->
<!-- Nutze den Generator: https://www.e-recht24.de/muster-datenschutzerklaerung.html -->
<!-- Alternativ: Complianz-Plugin generiert diese automatisch -->

[complianz_policies type="privacy-statement"]

<!-- Fallback wenn Complianz nicht aktiv: -->
<h2>Datenschutzerklärung</h2>

<h3>1. Datenschutz auf einen Blick</h3>
<h4>Allgemeine Hinweise</h4>
<p>Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, mit denen Sie persönlich identifiziert werden können.</p>

<h4>Datenerfassung auf dieser Website</h4>
<p><strong>Wer ist verantwortlich für die Datenerfassung auf dieser Website?</strong><br>
Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen Kontaktdaten können Sie dem Impressum dieser Website entnehmen.</p>

<h3>2. Hosting</h3>
<p>Diese Website wird bei einem externen Dienstleister gehostet (Hoster). Die personenbezogenen Daten, die auf dieser Website erfasst werden, werden auf den Servern des Hosters gespeichert. Hierbei kann es sich v. a. um IP-Adressen, Kontaktanfragen, Meta- und Kommunikationsdaten, Vertragsdaten, Kontaktdaten, Namen, Websitezugriffe und sonstige Daten, die über eine Website generiert werden, handeln.</p>

<h3>3. Pegelonline WSV API</h3>
<p>Diese Website nutzt die öffentliche API von Pegelonline (Wasser- und Schifffahrtsverwaltung des Bundes) zur Anzeige von Pegelständen. Beim Abruf dieser Daten werden Anfragen an die Server der WSV gesendet. Details: <a href="https://pegelonline.wsv.de/webservice/dokuIndividuell" target="_blank">pegelonline.wsv.de</a></p>

<h3>4. Wetterdaten (OpenWeatherMap)</h3>
<p>Für den Beißindex nutzen wir die API von OpenWeatherMap Ltd. Dabei werden anonymisierte Standortdaten (Brandenburg) übertragen. Datenschutzrichtlinie: <a href="https://openweathermap.org/privacy-policy" target="_blank">openweathermap.org/privacy-policy</a></p>

<h3>5. Cookies</h3>
<p>Unsere Website setzt Cookies ein, die für den Betrieb der Website notwendig sind. Weitere Cookies werden nur nach Ihrer ausdrücklichen Einwilligung gesetzt (Cookie-Consent-Banner).</p>

<h3>6. Ihre Rechte</h3>
<p>Sie haben jederzeit das Recht auf unentgeltliche Auskunft über Ihre gespeicherten personenbezogenen Daten, deren Herkunft und Empfänger und den Zweck der Datenverarbeitung sowie ein Recht auf Berichtigung oder Löschung dieser Daten. Hierzu sowie zu weiteren Fragen zum Thema personenbezogene Daten können Sie sich jederzeit an uns wenden.</p>

<p><em>Stand: [Datum einfügen] | Diese Datenschutzerklärung wurde mit dem e-Recht24 Generator erstellt.</em></p>
HTML;
}
