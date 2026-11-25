<?php
/** «هوش‌مارکت — سربرگ سایت»: announcement bar, logo, search, menu and action buttons. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class AIM_El_Header extends AIM_El_Widget {

	public function get_name() { return 'aim-header'; }
	public function get_title() { return 'سربرگ سایت (هدر)'; }
	public function get_icon() { return 'eicon-header'; }
	protected function section() { return 'header'; }

	protected function register_controls() {
		$this->start( 'announce', 'نوار اطلاعیهٔ بالا' );
		$this->toggle( 'announce', 'نمایش نوار اطلاعیه' );
		$this->textarea( 'announce_text', 'متن', $this->d( 'announce_text' ), [ 'condition' => [ 'announce' => 'yes' ] ] );
		$this->text( 'announce_cta', 'متن دکمه', $this->d( 'announce_cta' ), [ 'condition' => [ 'announce' => 'yes' ] ] );
		$this->link( 'announce_url', 'لینک دکمه', $this->d( 'announce_url' ) );
		$this->end_controls_section();

		$this->start( 'brand', 'لوگو' );
		$this->add_control( 'logo', [ 'label' => 'تصویر لوگو', 'type' => Controls_Manager::MEDIA, 'description' => 'خالی = نشان پیش‌فرض و نام سایت.' ] );
		$this->text( 'brand', 'نام (اختیاری)', '', [ 'placeholder' => 'نام سایت', 'description' => 'خالی = نام سایت (پنل ← تنظیمات سایت ← «نام سایت / فروشگاه»). فقط اگر سربرگ باید نام دیگری نشان دهد پر کنید.' ] );
		$this->text( 'brand_sub', 'زیرنویس نام', $this->d( 'brand_sub' ) );
		$this->end_controls_section();

		$this->start( 'actions', 'جست‌وجو و دکمه‌ها' );
		$this->toggle( 'search', 'کادر جست‌وجو' );
		$this->text( 'placeholder', 'متن داخل جست‌وجو', $this->d( 'placeholder' ), [ 'condition' => [ 'search' => 'yes' ] ] );
		$this->toggle( 'theme_toggle', 'دکمهٔ حالت شب و روز' );
		$this->toggle( 'login', 'دکمهٔ ورود / حساب' );
		$this->text( 'login_out', 'متن دکمه برای مهمان', $this->d( 'login_out' ), [ 'condition' => [ 'login' => 'yes' ] ] );
		$this->text( 'login_in', 'متن دکمه برای کاربر واردشده', $this->d( 'login_in' ), [ 'condition' => [ 'login' => 'yes' ] ] );
		$this->toggle( 'cart', 'دکمهٔ سبد خرید' );
		$this->end_controls_section();

		$this->start( 'menu', 'منو' );
		$menus = [ 0 => 'منوی جایگاه «سربرگ» (یا لینک‌های پیش‌فرض)' ];
		foreach ( wp_get_nav_menus() as $m ) {
			$menus[ $m->term_id ] = $m->name;
		}
		$this->add_control( 'menu', [ 'label' => 'منو', 'type' => Controls_Manager::SELECT, 'default' => 0, 'options' => $menus, 'description' => 'منوها را در پنل ← محتوا ← منوها بسازید.' ] );
		$this->text( 'track_text', 'لینک کنار منو', $this->d( 'track_text' ) );
		$this->link( 'track_url', 'آدرس لینک کنار منو', $this->d( 'track_url' ) );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		return [
			'announce'      => $this->on( $s['announce'] ),
			'announce_text' => $s['announce_text'],
			'announce_cta'  => $s['announce_cta'],
			'announce_url'  => $this->url( $s['announce_url'] ),
			'logo'          => ! empty( $s['logo']['id'] ) ? wp_get_attachment_image( (int) $s['logo']['id'], 'medium', false, [ 'alt' => $s['brand'] ?: get_bloginfo( 'name' ) ] ) : '',
			'brand'         => $s['brand'] ?: get_bloginfo( 'name' ),
			'brand_sub'     => $s['brand_sub'],
			'search'        => $this->on( $s['search'] ),
			'placeholder'   => $s['placeholder'],
			'theme_toggle'  => $this->on( $s['theme_toggle'] ),
			'login'         => $this->on( $s['login'] ),
			'login_in'      => $s['login_in'],
			'login_out'     => $s['login_out'],
			'cart'          => $this->on( $s['cart'] ),
			'menu'          => aim_t_header_menu( (int) $s['menu'] ),
			'track_text'    => $s['track_text'],
			'track_url'     => $this->url( $s['track_url'] ),
		];
	}
}
