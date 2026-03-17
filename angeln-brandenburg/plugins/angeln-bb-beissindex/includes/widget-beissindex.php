<?php
/**
 * WordPress Widget: Beißindex Sidebar
 */

defined( 'ABSPATH' ) || exit;

add_action( 'widgets_init', fn() => register_widget( 'ABB_Beissindex_Widget' ) );

class ABB_Beissindex_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'abb_beissindex_widget',
			'Angeln BB – Beißindex',
			[ 'description' => 'Zeigt den aktuellen Beißindex für Brandenburg in der Sidebar.' ]
		);
	}

	public function widget( $args, $instance ) {
		$data  = abb_get_beissindex();
		$title = ! empty( $instance['title'] ) ? $instance['title'] : 'Beißindex Brandenburg';

		echo $args['before_widget']; // phpcs:ignore
		echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore

		printf(
			'<div class="abb-beissindex-widget-inner" style="text-align:center;padding:1rem;">
				<div style="font-size:3rem;margin-bottom:.5rem;">%s</div>
				<div style="font-size:2rem;font-weight:700;color:%s;">%d/100</div>
				<div style="font-weight:600;color:%s;margin:.25rem 0;">%s %s</div>
				<div style="font-size:.8rem;color:#666;margin-bottom:.75rem;">%s</div>
				<div style="background:#eee;border-radius:999px;height:10px;overflow:hidden;">
					<div style="background:%s;width:%d%%;height:100%%;border-radius:999px;transition:width .8s ease;"></div>
				</div>
				<div style="margin-top:.75rem;font-size:.8rem;color:#888;">%s</div>
				<a href="%s" style="display:inline-block;margin-top:.75rem;font-size:.85rem;font-weight:600;color:#1a3a5c;">→ Vollständiger Beißindex</a>
			</div>',
			esc_html( $data['emoji'] ),
			esc_attr( $data['color'] ),
			$data['score'],
			esc_attr( $data['color'] ),
			esc_html( $data['emoji'] ),
			esc_html( $data['label'] ),
			esc_html( $data['factors']['mondphase']['value'] ),
			esc_attr( $data['color'] ),
			$data['score'],
			esc_html( $data['tip'] ),
			esc_url( home_url( '/beissindex/' ) )
		);

		echo $args['after_widget']; // phpcs:ignore
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : 'Beißindex Brandenburg';
		printf(
			'<p><label for="%s">Titel:</label><input class="widefat" id="%s" name="%s" type="text" value="%s"></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $title )
		);
	}

	public function update( $new_instance, $old_instance ) {
		return [ 'title' => sanitize_text_field( $new_instance['title'] ) ];
	}
}
