<?php
/** «هوش‌مارکت — نشان‌های اعتماد»: row of icon + title + text. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class AIM_El_Trust extends AIM_El_Widget {

	public function get_name() { return 'aim-trust'; }
	public function get_title() { return 'نشان‌های اعتماد'; }
	public function get_icon() { return 'eicon-check-circle'; }
	protected function section() { return 'trust'; }

	protected function register_controls() {
		$this->start( 'items', 'موارد' );
		$item = new Repeater();
		$item->add_control( 'icon', [ 'label' => 'آیکن', 'type' => Controls_Manager::ICONS, 'default' => self::ic( 'shield-check-line' ) ] );
		$item->add_control( 'title', [ 'label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'default' => 'ضمانت' ] );
		$item->add_control( 'text', [ 'label' => 'توضیح', 'type' => Controls_Manager::TEXT ] );
		$this->repeater( 'items', 'موارد', $item, self::default_items(), '{{{ title }}}' );
		$this->end_controls_section();
	}

	/** The built-in badges, as repeater rows (also used by the footer widget). */
	public static function default_items() {
		return array_map( fn( $t ) => [ 'icon' => self::ic( $t['icon'] ), 'title' => $t['title'], 'text' => $t['text'] ], aim_t_home()['trust'] );
	}

	protected function args( array $s ) {
		return [ 'items' => array_map( fn( $t ) => [ 'icon' => $this->icon( $t['icon'] ?? [] ), 'title' => $t['title'], 'text' => $t['text'] ], (array) $s['items'] ) ];
	}

	protected function render() {
		echo '<div class="container">';
		parent::render();
		echo '</div>';
	}
}
