<?php
/** «هوش‌مارکت — کد تخفیف»: banner with amount, text and a copyable discount code. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class AIM_El_Offer extends AIM_El_Widget {

	public function get_name() { return 'aim-offer'; }
	public function get_title() { return 'بنر کد تخفیف'; }
	public function get_icon() { return 'eicon-price-table'; }
	protected function section() { return 'offer'; }

	protected function register_controls() {
		$this->start( 'content', 'متن‌ها' );
		$this->art_controls( 'art', 'تصویر کنار متن', 'gift' );
		$this->text( 'kicker', 'برچسب بالا', $this->d( 'kicker' ) );
		$this->text( 'amount', 'مبلغ یا درصد (درشت)', $this->d( 'amount' ) );
		$this->text( 'title', 'عنوان', $this->d( 'title' ) );
		$this->textarea( 'text', 'توضیح', $this->d( 'text' ) );
		$this->anchor_control();
		$this->end_controls_section();

		$this->start( 'code', 'کد تخفیف' );
		$this->text( 'code', 'کد', $this->d( 'code' ), [ 'description' => 'کدی که در «کدهای تخفیف» پنل ساخته‌اید. خالی = کادر کد نمایش داده نشود.' ] );
		$this->text( 'code_label', 'برچسب کد', $this->d( 'code_label' ) );
		$this->text( 'copy_text', 'متن دکمهٔ کپی', $this->d( 'copy_text' ) );
		$point = new Repeater();
		$point->add_control( 'text', [ 'label' => 'متن', 'type' => Controls_Manager::TEXT, 'default' => 'روی همهٔ سرویس‌ها' ] );
		$this->repeater( 'points', 'نکته‌ها (با تیک)', $point, array_map( fn( $t ) => [ 'text' => $t ], $this->d( 'points', [] ) ), '{{{ text }}}' );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		return [
			'id'         => sanitize_title( $s['anchor'] ),
			'art'        => $this->art( $s, 'art', 'offer-img' ),
			'kicker'     => $s['kicker'],
			'amount'     => $s['amount'],
			'title'      => $s['title'],
			'text'       => $s['text'],
			'code'       => $s['code'],
			'code_label' => $s['code_label'],
			'copy_text'  => $s['copy_text'],
			'points'     => array_values( array_filter( array_column( (array) $s['points'], 'text' ) ) ),
		];
	}
}
