<?php
/** «هوش‌مارکت — سؤالات متداول». */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class AIM_El_Faq extends AIM_El_Widget {

	public function get_name() { return 'aim-faq'; }
	public function get_title() { return 'سؤالات متداول'; }
	public function get_icon() { return 'eicon-accordion'; }
	protected function section() { return 'faq'; }

	protected function register_controls() {
		$this->start( 'content', 'عنوان' );
		$this->heading_controls( false );
		$this->art_controls( 'art', 'تصویر کنار عنوان', 'faq' );
		$this->toggle( 'tint', 'پس‌زمینهٔ رنگی' );
		$this->toggle( 'open_first', 'سؤال اول باز باشد' );
		$this->anchor_control();
		$this->end_controls_section();

		$this->start( 'items', 'سؤال‌ها' );
		$item = new Repeater();
		$item->add_control( 'q', [ 'label' => 'سؤال', 'type' => Controls_Manager::TEXT, 'label_block' => true, 'default' => 'سؤال جدید' ] );
		$item->add_control( 'a', [ 'label' => 'پاسخ', 'type' => Controls_Manager::TEXTAREA, 'rows' => 4 ] );
		$this->repeater( 'items', 'سؤال‌ها', $item, $this->d( 'items', [] ), '{{{ q }}}' );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		return [
			'art'        => $this->art( $s, 'art', 'faq-art' ),
			'tint'       => $this->on( $s['tint'] ),
			'open_first' => $this->on( $s['open_first'] ),
			'items'      => array_map( fn( $f ) => [ 'q' => $f['q'], 'a' => $f['a'] ], (array) $s['items'] ),
		] + $this->heading_args( $s );
	}
}
