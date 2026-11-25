<?php
/** «هوش‌مارکت — اطلاعات تماس»: heading + contact cards, for the «تماس با ما» page. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class AIM_El_Contact extends AIM_El_Widget {

	public function get_name() { return 'aim-contact'; }
	public function get_title() { return 'اطلاعات تماس'; }
	public function get_icon() { return 'eicon-envelope'; }
	protected function section() { return 'contact'; }

	protected function register_controls() {
		$this->start( 'content', 'عنوان' );
		$this->heading_controls();
		$this->anchor_control();
		$this->end_controls_section();

		$this->start( 'items', 'راه‌های تماس' );
		$item = new Repeater();
		$item->add_control( 'icon', [ 'label' => 'آیکن', 'type' => Controls_Manager::ICONS, 'default' => self::ic( 'phone-line' ) ] );
		$item->add_control( 'title', [ 'label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'default' => 'تلفن' ] );
		$item->add_control( 'value', [ 'label' => 'مقدار', 'type' => Controls_Manager::TEXT, 'label_block' => true ] );
		$item->add_control( 'url', [ 'label' => 'لینک', 'type' => Controls_Manager::URL, 'options' => false, 'description' => 'مثلاً tel:02112345678 یا mailto:info@site.ir — خالی = بدون لینک.' ] );
		$defaults = array_map( fn( $c ) => [
			'icon'  => self::ic( preg_match( '/ic-([a-z0-9-]+)/', $c['icon'], $m ) ? $m[1] : 'phone-line' ),
			'title' => $c['title'],
			'value' => $c['value'],
			'url'   => [ 'url' => $c['url'] ],
		], $this->d( 'items', [] ) );
		$this->repeater( 'items', 'موارد', $item, $defaults, '{{{ title }}}' );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		return [
			'items' => array_map( fn( $c ) => [ 'icon' => $this->icon( $c['icon'] ?? [] ), 'title' => $c['title'], 'value' => $c['value'], 'url' => $this->url( $c['url'] ?? '' ) ], (array) $s['items'] ),
		] + $this->heading_args( $s );
	}
}
