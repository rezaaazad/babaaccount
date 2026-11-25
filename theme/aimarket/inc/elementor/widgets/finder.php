<?php
/** «هوش‌مارکت — راهنمای انتخاب»: use-case tabs, each listing products from a category. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class AIM_El_Finder extends AIM_El_Widget {

	public function get_name() { return 'aim-finder'; }
	public function get_title() { return 'راهنمای انتخاب (کاربردها)'; }
	public function get_icon() { return 'eicon-tabs'; }
	protected function section() { return 'finder'; }

	protected function register_controls() {
		$this->start( 'content', 'عنوان' );
		$this->heading_controls( false );
		$this->toggle( 'ask', 'کادر «بنویس چه کاری می‌خواهی»' );
		$this->text( 'ask_label', 'عنوان کادر', $this->d( 'ask_label' ), [ 'condition' => [ 'ask' => 'yes' ] ] );
		$this->text( 'ask_placeholder', 'متن داخل کادر', $this->d( 'ask_placeholder' ), [ 'condition' => [ 'ask' => 'yes' ] ] );
		$this->text( 'cta', 'متن دکمهٔ هر محصول', $this->d( 'cta' ) );
		$this->anchor_control();
		$this->end_controls_section();

		$this->start( 'uses', 'کاربردها (تب‌ها)' );
		$use = new Repeater();
		$use->add_control( 'label', [ 'label' => 'نام تب', 'type' => Controls_Manager::TEXT, 'default' => 'کاربرد جدید' ] );
		$use->add_control( 'icon', [ 'label' => 'آیکن', 'type' => Controls_Manager::ICONS, 'default' => self::ic( 'sparkling-2-line' ) ] );
		$use->add_control( 'title', [ 'label' => 'عنوان', 'type' => Controls_Manager::TEXT, 'label_block' => true ] );
		$use->add_control( 'text', [ 'label' => 'توضیح', 'type' => Controls_Manager::TEXTAREA, 'rows' => 2 ] );
		$use->add_control( 'category', [ 'label' => 'محصولات از دستهٔ', 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => self::product_categories() ] );
		$use->add_control( 'count', [ 'label' => 'تعداد محصول', 'type' => Controls_Manager::NUMBER, 'default' => 4, 'min' => 1, 'max' => 8 ] );
		$use->add_control( 'art_image', [ 'label' => 'تصویر', 'type' => Controls_Manager::MEDIA ] );
		$use->add_control( 'art_builtin', [ 'label' => 'یا تصویر پیش‌فرض', 'type' => Controls_Manager::SELECT, 'default' => 'text', 'options' => self::illustrations() ] );
		$home = aim_t_home()['uses'];
		$this->repeater( 'uses', 'تب‌ها', $use, array_map( fn( $u ) => [
			'label'       => $u['label'],
			'icon'        => self::ic( $u['icon'] ),
			'title'       => $u['title'],
			'text'        => $u['text'],
			'category'    => $u['cat'],
			'count'       => 4,
			'art_builtin' => $u['cat'],
		], $home ), '{{{ label }}}' );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		$uses = [];
		foreach ( (array) $s['uses'] as $i => $u ) {
			$cat    = $u['category'] && term_exists( $u['category'], 'product_cat' ) ? $u['category'] : '';
			$uses[] = [
				'key'      => 'u' . $i,
				'icon'     => $this->icon( $u['icon'] ?? [] ),
				'label'    => $u['label'],
				'title'    => $u['title'],
				'text'     => $u['text'],
				'art'      => $this->art( $u, 'art', 'finder-art' ),
				'products' => aim_t_section_products( 'popular', (int) ( $u['count'] ?: 4 ), [], $cat ),
			];
		}
		return [
			'id'              => sanitize_title( $s['anchor'] ),
			'title_before'    => $s['title_before'],
			'title_highlight' => $s['title_highlight'],
			'title_after'     => $s['title_after'],
			'text'            => $s['text'],
			'ask'             => $this->on( $s['ask'] ),
			'ask_label'       => $s['ask_label'],
			'ask_placeholder' => $s['ask_placeholder'],
			'cta'             => $s['cta'],
			'uses'            => $uses,
		];
	}
}
