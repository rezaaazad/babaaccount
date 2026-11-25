<?php
/** «هوش‌مارکت — محصولات»: a rail or grid of product cards from a chosen source. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class AIM_El_Products extends AIM_El_Widget {

	public function get_name() { return 'aim-products'; }
	public function get_title() { return 'محصولات (اسلایدر / شبکه)'; }
	public function get_icon() { return 'eicon-products'; }
	protected function section() { return 'products'; }

	protected function register_controls() {
		$this->start( 'content', 'عنوان' );
		$this->heading_controls();
		$this->text( 'link_text', 'متن لینک «همه»', $this->d( 'link_text' ) );
		$this->link( 'link_url', 'آدرس لینک «همه»', $this->d( 'link_url' ) );
		$this->anchor_control();
		$this->end_controls_section();

		$this->start( 'query', 'کدام محصولات؟' );
		$this->add_control( 'source', [
			'label'   => 'منبع',
			'type'    => Controls_Manager::SELECT,
			'default' => 'popular',
			'options' => [ 'popular' => 'پرفروش‌ترین', 'newest' => 'جدیدترین', 'sale' => 'تخفیف‌دار', 'rating' => 'بیشترین امتیاز', 'manual' => 'انتخاب دستی' ],
		] );
		$this->add_control( 'ids', [
			'label'       => 'محصولات (به ترتیب نمایش)',
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'options'     => self::product_options(),
			'label_block' => true,
			'condition'   => [ 'source' => 'manual' ],
		] );
		$this->add_control( 'category', [ 'label' => 'فقط از دستهٔ', 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => self::product_categories(), 'condition' => [ 'source!' => 'manual' ] ] );
		$this->add_control( 'count', [ 'label' => 'تعداد', 'type' => Controls_Manager::NUMBER, 'default' => 8, 'min' => 1, 'max' => 24, 'condition' => [ 'source!' => 'manual' ] ] );
		$this->end_controls_section();

		$this->start( 'look', 'نمایش' );
		$this->add_control( 'layout', [ 'label' => 'چیدمان', 'type' => Controls_Manager::SELECT, 'default' => 'rail', 'options' => [ 'rail' => 'اسلایدر افقی', 'grid' => 'شبکه' ] ] );
		$this->add_control( 'flag', [ 'label' => 'نشان روی کارت', 'type' => Controls_Manager::SELECT, 'default' => 'rank', 'options' => [ 'rank' => 'رتبهٔ پرفروش (۱، ۲، ۳…)', 'new' => '«جدید»', 'none' => 'بدون نشان' ] ] );
		$this->toggle( 'tint', 'پس‌زمینهٔ رنگی' );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		return [
			'link_text' => $s['link_text'],
			'link_url'  => $this->url( $s['link_url'] ),
			'layout'    => $s['layout'],
			'flag'      => $s['flag'],
			'tint'      => $this->on( $s['tint'] ),
			'products'  => aim_t_section_products( $s['source'], (int) $s['count'], (array) ( $s['ids'] ?? [] ), $s['category'] ?? '' ),
		] + $this->heading_args( $s );
	}
}
