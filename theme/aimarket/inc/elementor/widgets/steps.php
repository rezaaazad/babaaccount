<?php
/** «هوش‌مارکت — مراحل خرید»: numbered steps, one card each. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class AIM_El_Steps extends AIM_El_Widget {

	public function get_name() { return 'aim-steps'; }
	public function get_title() { return 'مراحل خرید'; }
	public function get_icon() { return 'eicon-number-field'; }
	protected function section() { return 'steps'; }

	protected function register_controls() {
		$this->start( 'content', 'عنوان' );
		$this->heading_controls();
		$this->anchor_control();
		$this->end_controls_section();

		$this->start( 'steps', 'مراحل' );
		$step = new Repeater();
		$step->add_control( 'label', [ 'label' => 'نام کوتاه (در نوار)', 'type' => Controls_Manager::TEXT, 'default' => 'مرحله' ] );
		$step->add_control( 'title', [ 'label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'label_block' => true ] );
		$step->add_control( 'text', [ 'label' => 'توضیح', 'type' => Controls_Manager::TEXTAREA, 'rows' => 3 ] );
		$step->add_control( 'art_image', [ 'label' => 'تصویر', 'type' => Controls_Manager::MEDIA ] );
		$step->add_control( 'art_builtin', [ 'label' => 'یا تصویر پیش‌فرض', 'type' => Controls_Manager::SELECT, 'default' => 'step-plan', 'options' => self::illustrations() ] );
		$art  = [ 'step-plan', 'step-login', 'step-pay', 'step-receive', 'step-launch' ];
		$home = aim_t_home()['steps'];
		$this->repeater( 'steps', 'مراحل', $step, array_map( fn( $st, $i ) => [ 'label' => $st['label'], 'title' => $st['title'], 'text' => $st['text'], 'art_builtin' => $art[ $i ] ?? 'step-plan' ], $home, array_keys( $home ) ), '{{{ label }}}' );
		$this->text( 'prev_text', 'دکمهٔ قبلی', $this->d( 'prev_text' ) );
		$this->text( 'next_text', 'دکمهٔ بعدی', $this->d( 'next_text' ) );
		$this->text( 'last_text', 'دکمهٔ مرحلهٔ آخر', $this->d( 'last_text' ) );
		$this->link( 'last_url', 'لینک دکمهٔ مرحلهٔ آخر', $this->d( 'last_url' ) );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		return [
			'prev_text' => $s['prev_text'],
			'next_text' => $s['next_text'],
			'last_text' => $s['last_text'],
			'last_url'  => $this->url( $s['last_url'] ),
			'steps'     => array_map( fn( $st ) => [ 'label' => $st['label'], 'title' => $st['title'], 'text' => $st['text'], 'art' => $this->art( $st, 'art', 'step-art' ) ], (array) $s['steps'] ),
		] + $this->heading_args( $s );
	}
}
