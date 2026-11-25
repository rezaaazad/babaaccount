<?php
/** «هوش‌مارکت — بخش اصلی (هیرو)»: title, search, popular tags and floating service chips. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class AIM_El_Hero extends AIM_El_Widget {

	public function get_name() { return 'aim-hero'; }
	public function get_title() { return 'بخش اصلی (هیرو)'; }
	public function get_icon() { return 'eicon-banner'; }
	protected function section() { return 'hero'; }

	protected function register_controls() {
		$this->start( 'content', 'متن‌ها' );
		$this->heading_controls();
		$this->anchor_control();
		$this->end_controls_section();

		$this->start( 'search', 'جست‌وجو و برچسب‌ها' );
		$this->toggle( 'search', 'نمایش کادر جست‌وجو' );
		$this->text( 'placeholder', 'متن داخل کادر', $this->d( 'placeholder' ), [ 'condition' => [ 'search' => 'yes' ] ] );
		$this->text( 'button', 'متن دکمه', $this->d( 'button' ), [ 'condition' => [ 'search' => 'yes' ] ] );
		$this->text( 'tags_label', 'عنوان برچسب‌ها', $this->d( 'tags_label' ) );
		$tag = new Repeater();
		$tag->add_control( 'text', [ 'label' => 'متن', 'type' => Controls_Manager::TEXT, 'default' => 'ChatGPT' ] );
		$tag->add_control( 'url', [ 'label' => 'لینک', 'type' => Controls_Manager::URL, 'options' => false, 'description' => 'خالی = جست‌وجوی همین متن در محصولات.' ] );
		$this->repeater( 'tags', 'برچسب‌ها', $tag, array_map( fn( $t ) => [ 'text' => $t['text'] ], $this->d( 'tags', [] ) ), '{{{ text }}}' );
		$this->text( 'hint_text', 'لینک راهنما (زیر برچسب‌ها)', $this->d( 'hint_text' ) );
		$this->link( 'hint_url', 'آدرس لینک راهنما', $this->d( 'hint_url' ) );
		$this->text( 'scroll_text', 'متن پایین بخش', $this->d( 'scroll_text' ) );
		$this->link( 'scroll_url', 'آدرس متن پایین', $this->d( 'scroll_url' ) );
		$this->end_controls_section();

		$this->start( 'art', 'تصویر کنار متن (دسکتاپ)' );
		$this->add_control( 'art_light', [ 'label' => 'تصویر حالت روشن', 'type' => Controls_Manager::MEDIA, 'description' => 'PNG یا WebP بدون پس‌زمینه. خالی = تصویر پیش‌فرض قالب.' ] );
		$this->add_control( 'art_dark', [ 'label' => 'تصویر حالت تاریک', 'type' => Controls_Manager::MEDIA, 'description' => 'خالی = تصویر پیش‌فرض قالب.' ] );
		$this->end_controls_section();

		$this->start( 'chips', 'سرویس‌های شناور اطراف (در طرح جدید نمایش داده نمی‌شود)' );
		$this->add_control( 'chips_source', [
			'label'   => 'منبع',
			'type'    => Controls_Manager::SELECT,
			'default' => 'auto',
			'options' => [ 'auto' => 'خودکار: پرفروش‌ترین محصولات', 'manual' => 'دستی: موارد زیر', 'none' => 'نمایش داده نشود' ],
		] );
		$chip = new Repeater();
		$chip->add_control( 'label', [ 'label' => 'نام', 'type' => Controls_Manager::TEXT, 'default' => 'ChatGPT Plus' ] );
		$chip->add_control( 'image', [ 'label' => 'لوگو', 'type' => Controls_Manager::MEDIA ] );
		$chip->add_control( 'icon', [ 'label' => 'یا آیکن', 'type' => Controls_Manager::ICONS, 'default' => self::ic( 'sparkling-2-fill' ) ] );
		$chip->add_control( 'url', [ 'label' => 'لینک', 'type' => Controls_Manager::URL, 'options' => false ] );
		$this->repeater( 'chips', 'موارد (حداکثر ۶)', $chip, [], '{{{ label }}}' );
		$this->add_control( 'chips_note', [ 'type' => Controls_Manager::RAW_HTML, 'raw' => 'در حالت «دستی» این فهرست جای پرفروش‌ها را می‌گیرد.', 'content_classes' => 'elementor-descriptor' ] );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		$chips = [];
		if ( 'auto' === $s['chips_source'] ) {
			$chips = $this->d( 'chips', [] );
		} elseif ( 'manual' === $s['chips_source'] ) {
			foreach ( (array) $s['chips'] as $c ) {
				$logo    = ! empty( $c['image']['id'] ) ? wp_get_attachment_image( (int) $c['image']['id'], [ 72, 72 ], false, [ 'class' => 'chip-logo' ] ) : '<span class="chip-logo chip-ico">' . $this->icon( $c['icon'] ?? [] ) . '</span>';
				$chips[] = [ 'label' => $c['label'], 'url' => $this->url( $c['url'] ?? '' ), 'logo' => $logo ];
			}
		}
		return [
			'search'      => $this->on( $s['search'] ),
			'placeholder' => $s['placeholder'],
			'button'      => $s['button'],
			'tags_label'  => $s['tags_label'],
			'tags'        => array_map( fn( $t ) => [ 'text' => $t['text'], 'url' => $this->url( $t['url'] ?? '' ) ], (array) $s['tags'] ),
			'hint_text'   => $s['hint_text'],
			'hint_url'    => $this->url( $s['hint_url'] ),
			'scroll_text' => $s['scroll_text'],
			'scroll_url'  => $this->url( $s['scroll_url'] ),
			'chips'       => $chips,
			'art_light'   => ! empty( $s['art_light']['url'] ) ? $s['art_light']['url'] : $this->d( 'art_light' ),
			'art_dark'    => ! empty( $s['art_dark']['url'] ) ? $s['art_dark']['url'] : $this->d( 'art_dark' ),
		] + $this->heading_args( $s );
	}
}
