<?php
/** «هوش‌مارکت — مقاله‌ها»: latest posts, optionally from one category. */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class AIM_El_Posts extends AIM_El_Widget {

	public function get_name() { return 'aim-posts'; }
	public function get_title() { return 'آخرین مقاله‌ها'; }
	public function get_icon() { return 'eicon-posts-grid'; }
	protected function section() { return 'posts'; }

	protected function register_controls() {
		$this->start( 'content', 'عنوان' );
		$this->heading_controls();
		$this->text( 'link_text', 'متن لینک «همه»', $this->d( 'link_text' ) );
		$this->link( 'link_url', 'آدرس لینک «همه»', $this->d( 'link_url' ) );
		$this->anchor_control();
		$this->end_controls_section();

		$this->start( 'query', 'کدام مقاله‌ها؟' );
		$cats = [ 0 => 'همهٔ دسته‌ها' ];
		foreach ( get_categories( [ 'hide_empty' => false ] ) as $c ) {
			$cats[ $c->term_id ] = $c->name;
		}
		$this->add_control( 'category', [ 'label' => 'دسته', 'type' => Controls_Manager::SELECT, 'default' => 0, 'options' => $cats ] );
		$this->add_control( 'count', [ 'label' => 'تعداد', 'type' => Controls_Manager::NUMBER, 'default' => 4, 'min' => 1, 'max' => 12 ] );
		$this->end_controls_section();
	}

	protected function args( array $s ) {
		return [
			'link_text' => $s['link_text'],
			'link_url'  => $this->url( $s['link_url'] ),
			'posts'     => get_posts( [ 'numberposts' => max( 1, (int) $s['count'] ), 'post_status' => 'publish', 'cat' => (int) $s['category'] ] ),
		] + $this->heading_args( $s );
	}
}
