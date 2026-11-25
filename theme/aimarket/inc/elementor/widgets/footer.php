<?php
/**
 * «هوش‌مارکت — پابرگ سایت»: the site footer. Its content is edited in
 * panel → تنظیمات قالب → «فوتر» and «نمادهای اعتماد», so the built-in footer
 * and this widget always show the same thing.
 */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class AIM_El_Footer extends AIM_El_Widget {

	public function get_name() { return 'aim-footer'; }
	public function get_title() { return 'پابرگ سایت (فوتر)'; }
	public function get_icon() { return 'eicon-footer'; }
	protected function section() { return 'footer'; }

	protected function register_controls() {
		$this->start( 'info', 'محتوا' );
		$url = function_exists( 'aim_panel_url' ) ? aim_panel_url( 'admin', 'theme-options' ) : admin_url();
		$this->add_control( 'where', [
			'type' => Controls_Manager::RAW_HTML,
			'raw'  => 'همهٔ بخش‌های فوتر (تماس، ویژگی‌ها، ستون‌های لینک، کد تخفیف، شبکه‌های اجتماعی، نمادهای اعتماد و کپی‌رایت) در پنل مدیریت ویرایش می‌شوند.<br><br><a href="' . esc_url( $url ) . '" target="_blank">پنل ← تنظیمات قالب ← فوتر</a>',
		] );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		return aim_t_footer_args();
	}
}
