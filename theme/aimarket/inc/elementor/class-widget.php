<?php
/**
 * Base class for the «هوش‌مارکت» Elementor widgets.
 *
 * A widget only declares its controls and maps its settings onto the array
 * its section template expects (template-parts/sections/{section}.php). Control
 * defaults come from aim_t_section_defaults(), so a new widget shows the
 * built-in content until it is edited.
 */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

abstract class AIM_El_Widget extends Widget_Base {

	/** Section template name in template-parts/sections/. */
	abstract protected function section();

	/** Settings for the section template, built from the widget's settings. */
	abstract protected function args( array $s );

	public function get_categories() {
		return [ 'aimarket' ];
	}

	public function get_keywords() {
		return [ 'aimarket', 'هوش‌مارکت', $this->section() ];
	}

	protected function render() {
		aim_t_section( $this->section(), $this->args( $this->get_settings_for_display() ) );
	}

	/**
	 * Our widgets print live data (site name, menus, products, cart, panel settings), so
	 * Elementor's element cache must not keep their HTML.
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	// ---- Defaults ----

	/** A value from the built-in content of this widget's section. */
	protected function d( $key, $fallback = '' ) {
		static $cache = [];
		$section = $this->section();
		$cache[ $section ] ??= aim_t_section_defaults( $section );
		return $cache[ $section ][ $key ] ?? $fallback;
	}

	// ---- Shared controls ----

	/** Open a panel section; ids get a "sec_" prefix so they never clash with a control inside them. */
	protected function start( $id, $label, $tab = Controls_Manager::TAB_CONTENT ) {
		$this->start_controls_section( 'sec_' . $id, [ 'label' => $label, 'tab' => $tab ] );
	}

	protected function text( $id, $label, $default = '', $args = [] ) {
		$this->add_control( $id, $args + [ 'label' => $label, 'type' => Controls_Manager::TEXT, 'default' => $default, 'label_block' => true ] );
	}

	protected function textarea( $id, $label, $default = '', $args = [] ) {
		$this->add_control( $id, $args + [ 'label' => $label, 'type' => Controls_Manager::TEXTAREA, 'default' => $default, 'rows' => 3 ] );
	}

	protected function link( $id, $label, $default = '' ) {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::URL, 'default' => [ 'url' => $default ], 'options' => false, 'label_block' => true ] );
	}

	protected function toggle( $id, $label, $default = true ) {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::SWITCHER, 'default' => $default ? 'yes' : '', 'label_on' => 'بله', 'label_off' => 'خیر' ] );
	}

	/** Kicker + three-part title (the middle part is drawn in the accent colour) + description. */
	protected function heading_controls( $kicker = true, $text = true ) {
		if ( $kicker ) {
			$this->text( 'kicker', 'برچسب بالای عنوان', $this->d( 'kicker' ) );
		}
		$this->text( 'title_before', 'عنوان — بخش اول', $this->d( 'title_before' ) );
		$this->text( 'title_highlight', 'عنوان — بخش رنگی', $this->d( 'title_highlight' ), [ 'description' => 'این بخش با رنگ اصلی سایت نمایش داده می‌شود. خالی = بدون بخش رنگی.' ] );
		$this->text( 'title_after', 'عنوان — بخش آخر', $this->d( 'title_after' ) );
		if ( $text ) {
			$this->textarea( 'text', 'توضیح زیر عنوان', $this->d( 'text' ) );
		}
	}

	/** Anchor id, so menus and buttons can link to the section (#best, #faq…). */
	protected function anchor_control() {
		$this->text( 'anchor', 'شناسهٔ بخش (برای لینک با #)', $this->d( 'id' ), [ 'description' => 'مثلاً با شناسهٔ faq، لینک «#faq» به این بخش می‌رود.', 'label_block' => false ] );
	}

	/** Built-in illustration or an uploaded image. */
	protected function art_controls( $id, $label, $builtin ) {
		$this->add_control( $id . '_image', [ 'label' => $label, 'type' => Controls_Manager::MEDIA, 'description' => 'خالی = تصویر پیش‌فرض قالب.' ] );
		$this->add_control( $id . '_builtin', [
			'label'     => 'تصویر پیش‌فرض',
			'type'      => Controls_Manager::SELECT,
			'default'   => $builtin,
			'options'   => self::illustrations(),
			'condition' => [ $id . '_image[url]' => '' ],
		] );
	}

	protected static function illustrations() {
		return [
			''             => 'بدون تصویر',
			'gift'         => 'هدیه',
			'cart'         => 'سبد خرید',
			'success'      => 'تیک موفقیت',
			'faq'          => 'پرسش و پاسخ',
			'text'         => 'گفت‌وگو و متن',
			'image'        => 'تصویر و طراحی',
			'video'        => 'ویدیو',
			'code'         => 'کدنویسی',
			'research'     => 'تحقیق و نمودار',
			'daily'        => 'کارهای روزمره',
			'step-plan'    => 'انتخاب پلن',
			'step-login'   => 'گوشی و کد',
			'step-pay'     => 'کارت بانکی',
			'step-receive' => 'تحویل اکانت',
			'step-launch'  => 'موشک',
		];
	}

	protected static function product_categories() {
		$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		$out   = [ '' => 'همهٔ دسته‌ها' ];
		foreach ( is_wp_error( $terms ) ? [] : $terms as $t ) {
			$out[ $t->slug ] = $t->name;
		}
		return $out;
	}

	protected static function product_options() {
		$out = [];
		foreach ( wc_get_products( [ 'status' => 'publish', 'limit' => 300, 'orderby' => 'title', 'order' => 'ASC', 'return' => 'objects' ] ) as $p ) {
			$out[ $p->get_id() ] = $p->get_name();
		}
		return $out;
	}

	protected function repeater( $id, $label, Repeater $fields, array $default, $title_field ) {
		$this->add_control( $id, [
			'label'       => $label,
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $fields->get_controls(),
			'default'     => $default,
			'title_field' => $title_field,
		] );
	}

	// ---- Value helpers ----

	protected function on( $v ) {
		return 'yes' === $v;
	}

	protected function url( $v ) {
		return is_array( $v ) ? (string) ( $v['url'] ?? '' ) : (string) $v;
	}

	protected function icon( $v ) {
		if ( empty( $v['value'] ) ) {
			return '';
		}
		ob_start();
		Icons_Manager::render_icon( $v, [ 'aria-hidden' => 'true' ] );
		return ob_get_clean();
	}

	/** Uploaded image as <img>, else the chosen built-in illustration. */
	protected function art( array $s, $id, $class ) {
		$image = $s[ $id . '_image' ] ?? [];
		if ( ! empty( $image['id'] ) ) {
			return wp_get_attachment_image( (int) $image['id'], 'large', false, [ 'class' => $class . ' is-image' ] );
		}
		if ( ! empty( $image['url'] ) ) {
			return '<img class="' . esc_attr( $class ) . ' is-image" src="' . esc_url( $image['url'] ) . '" alt="">';
		}
		$builtin = $s[ $id . '_builtin' ] ?? '';
		return $builtin ? aim_t_illus( $builtin, $class ) : '';
	}

	/** The three title parts + kicker/text, as the section templates expect them. */
	protected function heading_args( array $s ) {
		return [
			'id'              => sanitize_title( $s['anchor'] ?? '' ) ?: ( 'hero' === $this->section() ? 'hero' : '' ),
			'kicker'          => $s['kicker'] ?? '',
			'title_before'    => $s['title_before'] ?? '',
			'title_highlight' => $s['title_highlight'] ?? '',
			'title_after'     => $s['title_after'] ?? '',
			'text'            => $s['text'] ?? '',
		];
	}

	/** Icon control value for one of the theme's icons. */
	protected static function ic( $name ) {
		return [ 'value' => 'ic-' . $name, 'library' => 'aim-icons' ];
	}
}
