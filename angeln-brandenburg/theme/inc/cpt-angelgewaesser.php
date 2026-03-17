<?php
/**
 * Angeln Brandenburg 2026 – Custom Post Type: angelgewaesser
 * PHASE 3: SEO-Architektur & Struktur
 *
 * Permalink: /gewaesser/beetzsee/
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// 1. REGISTER CUSTOM POST TYPE
// ============================================================

add_action( 'init', 'abb_register_cpt_angelgewaesser' );
function abb_register_cpt_angelgewaesser() {
	$labels = [
		'name'                  => 'Angelgewässer',
		'singular_name'         => 'Angelgewässer',
		'menu_name'             => 'Gewässer',
		'add_new'               => 'Gewässer hinzufügen',
		'add_new_item'          => 'Neues Gewässer hinzufügen',
		'edit_item'             => 'Gewässer bearbeiten',
		'new_item'              => 'Neues Gewässer',
		'view_item'             => 'Gewässer ansehen',
		'view_items'            => 'Alle Gewässer',
		'search_items'          => 'Gewässer suchen',
		'not_found'             => 'Keine Gewässer gefunden',
		'not_found_in_trash'    => 'Keine Gewässer im Papierkorb',
		'all_items'             => 'Alle Gewässer',
		'archives'              => 'Gewässer-Archiv',
		'featured_image'        => 'Titelbild (Gewässer)',
		'set_featured_image'    => 'Titelbild setzen',
		'remove_featured_image' => 'Titelbild entfernen',
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,   // Gutenberg + REST API
		'query_var'          => true,
		'rewrite'            => [
			'slug'       => 'gewaesser',   // /gewaesser/beetzsee/
			'with_front' => false,
		],
		'capability_type'    => 'post',
		'has_archive'        => 'gewaesser',
		'hierarchical'       => false,
		'menu_position'      => 5,
		'menu_icon'          => 'dashicons-location',
		'supports'           => [
			'title', 'editor', 'thumbnail', 'excerpt',
			'custom-fields', 'revisions', 'author',
		],
		'taxonomies'         => [ 'fischarten', 'region', 'gewaesser_typ' ],
	];

	register_post_type( 'angelgewaesser', $args );
}

// ============================================================
// 2. REGISTER TAXONOMIES
// ============================================================

add_action( 'init', 'abb_register_taxonomies' );
function abb_register_taxonomies() {

	// Fischarten (Bream, Pike, Perch, etc.)
	register_taxonomy( 'fischarten', 'angelgewaesser', [
		'label'             => 'Fischarten',
		'singular_label'    => 'Fischart',
		'hierarchical'      => false,
		'show_in_rest'      => true,
		'rewrite'           => [ 'slug' => 'fischart' ],
		'show_admin_column' => true,
	] );

	// Region (Brandenburg, Potsdam, Uckermark, etc.)
	register_taxonomy( 'region', 'angelgewaesser', [
		'label'             => 'Region',
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'rewrite'           => [ 'slug' => 'region' ],
		'show_admin_column' => true,
	] );

	// Gewässer-Typ (See, Fluss, Kanal, Teich)
	register_taxonomy( 'gewaesser_typ', 'angelgewaesser', [
		'label'             => 'Gewässer-Typ',
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'rewrite'           => [ 'slug' => 'gewaesser-typ' ],
		'show_admin_column' => true,
	] );
}

// ============================================================
// 3. CUSTOM META FIELDS (post meta for Schema.org data)
// ============================================================

add_action( 'add_meta_boxes', 'abb_gewaesser_metaboxes' );
function abb_gewaesser_metaboxes() {
	add_meta_box(
		'abb_gewaesser_data',
		'Gewässer-Daten & Koordinaten',
		'abb_gewaesser_metabox_callback',
		'angelgewaesser',
		'normal',
		'high'
	);
}

function abb_gewaesser_metabox_callback( $post ) {
	wp_nonce_field( 'abb_save_gewaesser_meta', 'abb_gewaesser_nonce' );

	$fields = abb_get_gewaesser_fields();
	$meta   = get_post_meta( $post->ID );

	echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin:1rem 0;">';
	foreach ( $fields as $key => $field ) {
		$value = isset( $meta[ $key ][0] ) ? esc_attr( $meta[ $key ][0] ) : '';
		printf(
			'<div><label for="%1$s" style="display:block;font-weight:600;margin-bottom:4px;">%2$s</label>
			<input type="%3$s" id="%1$s" name="%1$s" value="%4$s" step="%5$s" style="width:100%%" placeholder="%6$s"></div>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			esc_attr( $field['type'] ),
			$value,
			esc_attr( $field['step'] ?? 'any' ),
			esc_attr( $field['placeholder'] ?? '' )
		);
	}
	echo '</div>';

	// Fischarten Textarea
	$fischarten_raw = isset( $meta['abb_fischarten_raw'][0] ) ? esc_textarea( $meta['abb_fischarten_raw'][0] ) : '';
	echo '<div style="margin-top:1rem;"><label style="font-weight:600;">Fischarten (kommagetrennt, für Schema.org)</label>';
	echo '<textarea name="abb_fischarten_raw" style="width:100%;margin-top:4px;" rows="2" placeholder="Hecht, Barsch, Karpfen, Zander, Aal">' . $fischarten_raw . '</textarea></div>';

	// Pegelonline UUID
	$pegel_uuid = isset( $meta['abb_pegel_uuid'][0] ) ? esc_attr( $meta['abb_pegel_uuid'][0] ) : '';
	echo '<div style="margin-top:1rem;"><label style="font-weight:600;">Pegelonline Stations-UUID</label>';
	echo '<input type="text" name="abb_pegel_uuid" value="' . $pegel_uuid . '" style="width:100%;margin-top:4px;" placeholder="z.B. 592F5924-3A16-4892-8B51-..."></div>';

	// Angel-Erlaubnis
	$erlaubnis = isset( $meta['abb_erlaubnis'][0] ) ? esc_attr( $meta['abb_erlaubnis'][0] ) : '';
	echo '<div style="margin-top:1rem;"><label style="font-weight:600;">Angel-Erlaubnis / Verein</label>';
	echo '<input type="text" name="abb_erlaubnis" value="' . $erlaubnis . '" style="width:100%;margin-top:4px;" placeholder="DAV Ortsgruppe Brandenburg / Tageskarte 8€"></div>';
}

function abb_get_gewaesser_fields() {
	return [
		'abb_latitude'    => [ 'label' => 'Breitengrad (Latitude)',  'type' => 'number', 'step' => '0.000001', 'placeholder' => '52.398765' ],
		'abb_longitude'   => [ 'label' => 'Längengrad (Longitude)',  'type' => 'number', 'step' => '0.000001', 'placeholder' => '12.534567' ],
		'abb_flaeche_ha'  => [ 'label' => 'Fläche (Hektar)',          'type' => 'number', 'step' => '0.1',     'placeholder' => '320' ],
		'abb_tiefe_m'     => [ 'label' => 'Max. Tiefe (Meter)',       'type' => 'number', 'step' => '0.1',     'placeholder' => '8.5' ],
		'abb_uferlaenge'  => [ 'label' => 'Uferlänge (km)',           'type' => 'number', 'step' => '0.1',     'placeholder' => '12.3' ],
		'abb_hoehe_m'     => [ 'label' => 'Höhe über NN (Meter)',     'type' => 'number', 'step' => '0.1',     'placeholder' => '32' ],
		'abb_ort'         => [ 'label' => 'Ort / Adresse',            'type' => 'text',                        'placeholder' => 'Brandenburg an der Havel' ],
		'abb_bundesland'  => [ 'label' => 'Bundesland',               'type' => 'text',                        'placeholder' => 'Brandenburg' ],
	];
}

add_action( 'save_post_angelgewaesser', 'abb_save_gewaesser_meta' );
function abb_save_gewaesser_meta( $post_id ) {
	if ( ! isset( $_POST['abb_gewaesser_nonce'] ) ) return;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['abb_gewaesser_nonce'] ) ), 'abb_save_gewaesser_meta' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$fields = array_keys( abb_get_gewaesser_fields() );
	$fields = array_merge( $fields, [ 'abb_fischarten_raw', 'abb_pegel_uuid', 'abb_erlaubnis' ] );

	foreach ( $fields as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
}

// ============================================================
// 4. ARCHIVE QUERY – show 20 per page, order by title
// ============================================================

add_action( 'pre_get_posts', 'abb_gewaesser_archive_query' );
function abb_gewaesser_archive_query( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( 'angelgewaesser' ) ) {
		$query->set( 'posts_per_page', 20 );
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
}

// ============================================================
// 5. FLUSH REWRITE RULES on activation
// ============================================================

register_activation_hook( __FILE__, 'abb_flush_rewrites' );
add_action( 'init', 'abb_maybe_flush_rewrites' );
function abb_maybe_flush_rewrites() {
	if ( get_option( 'abb_flush_rewrites' ) ) {
		flush_rewrite_rules();
		delete_option( 'abb_flush_rewrites' );
	}
}
function abb_flush_rewrites() {
	abb_register_cpt_angelgewaesser();
	flush_rewrite_rules();
}
