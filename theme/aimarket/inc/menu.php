<?php
/**
 * Site menu: one tree, two renderings — the desktop mega menu in the header
 * (aim_t_header_menu) and the mobile drawer (aim_t_drawer_menu).
 *
 * Tree items: [ kind => link | mega | drop, title, url, icon, current,
 *   tabs (mega) => [ key, title, url, icon, count, products ],
 *   children (drop) => [ title, url, icon ] ].
 *
 * Without a WordPress menu the tree is built from the product categories;
 * with one, a top-level item that links to a product category becomes a
 * mega menu of that category's children, and an item with children a
 * dropdown.
 */
defined( 'ABSPATH' ) || exit;

const AIM_T_MENU_PRODUCTS = 6;

/** Icon for a product category: term meta "aim_icon", a guess from the slug, or a stack. */
function aim_t_cat_icon( WP_Term $term ) {
	$icon = (string) get_term_meta( $term->term_id, 'aim_icon', true );
	if ( ! $icon ) {
		$guess = [
			'text'     => 'quill-pen-line',
			'write'    => 'quill-pen-line',
			'image'    => 'image-2-line',
			'design'   => 'image-2-line',
			'video'    => 'movie-2-line',
			'movie'    => 'movie-2-line',
			'code'     => 'code-s-slash-line',
			'research' => 'search-eye-line',
			'daily'    => 'calendar-check-line',
			'chat'     => 'chat-3-line',
			'apple'    => 'smartphone-line',
			'mobile'   => 'smartphone-line',
			'music'    => 'pulse-line',
			'learn'    => 'book-open-line',
			'edu'      => 'book-open-line',
			'seo'      => 'line-chart-line',
		];
		foreach ( $guess as $needle => $name ) {
			if ( str_contains( $term->slug, $needle ) ) {
				$icon = $name;
				break;
			}
		}
	}
	return apply_filters( 'aim_menu_cat_icon', $icon ?: 'stack-line', $term );
}

/** A mega tab for a product category. */
function aim_t_menu_cat_tab( WP_Term $term ) {
	return [
		'key'      => 'c' . $term->term_id,
		'title'    => $term->name,
		'url'      => get_term_link( $term ),
		'icon'     => aim_t_cat_icon( $term ),
		'count'    => (int) $term->count,
		'products' => aim_t_section_products( 'popular', AIM_T_MENU_PRODUCTS, [], $term->slug ),
	];
}

/** The "best sellers" tab that opens every mega menu. */
function aim_t_menu_popular_tab() {
	$total = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
	return [
		'key'      => 'popular',
		'title'    => aim_t_opt( 'm_popular' ),
		'url'      => add_query_arg( 'orderby', 'popularity', wc_get_page_permalink( 'shop' ) ),
		'icon'     => 'rocket-2-line',
		'count'    => $total,
		'products' => aim_t_section_products( 'popular', AIM_T_MENU_PRODUCTS ),
	];
}

/** Product categories that have products, minus "Uncategorized". */
function aim_t_menu_cats( $parent ) {
	$cats = aim_t_cached_terms( [
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'parent'     => (int) $parent,
		'exclude'    => [ (int) get_option( 'default_product_cat' ) ],
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 14,
	] );
	return is_wp_error( $cats ) ? [] : $cats;
}

function aim_t_menu_mega( $title, $url, $icon, array $terms ) {
	return [
		'kind'  => 'mega',
		'title' => $title,
		'url'   => $url,
		'icon'  => $icon,
		'tabs'  => array_merge( [ aim_t_menu_popular_tab() ], array_map( 'aim_t_menu_cat_tab', $terms ) ),
	];
}

/** The menu tree (see the file header). Built once per request. */
function aim_t_menu_tree( $menu_id = 0 ) {
	static $cache = [];
	$menu_id = (int) $menu_id;
	if ( ! $menu_id && has_nav_menu( 'header' ) ) {
		$menu_id = (int) get_nav_menu_locations()['header'];
	}
	if ( isset( $cache[ $menu_id ] ) ) {
		return $cache[ $menu_id ];
	}
	$tree = $menu_id ? aim_t_menu_tree_from_wp( $menu_id ) : aim_t_menu_tree_default();
	return $cache[ $menu_id ] = apply_filters( 'aim_menu_tree', $tree, $menu_id );
}

function aim_t_menu_tree_default() {
	$shop = wc_get_page_permalink( 'shop' );
	$tree = [ [ 'kind' => 'link', 'title' => 'فروشگاه', 'url' => $shop, 'icon' => 'store-2-line' ] ];

	// Parent categories get a mega menu each; the rest share one.
	$flat = [];
	$own  = [];
	foreach ( aim_t_menu_cats( 0 ) as $cat ) {
		$kids = aim_t_menu_cats( $cat->term_id );
		if ( $kids ) {
			$own[] = aim_t_menu_mega( $cat->name, get_term_link( $cat ), aim_t_cat_icon( $cat ), $kids );
		} else {
			$flat[] = $cat;
		}
	}
	if ( $flat || ! $own ) {
		$tree[] = aim_t_menu_mega( aim_t_opt( 'm_title' ), $shop, 'sparkling-2-line', $flat );
	}
	$tree = array_merge( $tree, $own );

	$blog = (int) get_option( 'page_for_posts' );
	if ( $blog && 'publish' === get_post_status( $blog ) ) {
		$tree[] = [ 'kind' => 'link', 'title' => get_the_title( $blog ), 'url' => get_permalink( $blog ), 'icon' => 'article-line' ];
	}

	$help = [
		[ 'title' => 'راهنمای خرید', 'url' => home_url( '/#steps' ), 'icon' => 'route-line' ],
		[ 'title' => 'سؤالات متداول', 'url' => home_url( '/#faq' ), 'icon' => 'question-line' ],
		[ 'title' => 'تماس با ما', 'url' => aim_t_contact_url(), 'icon' => 'customer-service-2-line' ],
	];
	if ( function_exists( 'aim_panel_url' ) ) {
		array_unshift( $help, [ 'title' => 'پیگیری سفارش', 'url' => aim_panel_url( 'user', 'orders' ), 'icon' => 'file-list-3-line' ] );
	}
	$tree[] = [ 'kind' => 'drop', 'title' => aim_t_opt( 'm_support' ), 'url' => aim_t_contact_url(), 'icon' => 'customer-service-2-line', 'children' => $help ];
	return $tree;
}

function aim_t_menu_tree_from_wp( $menu_id ) {
	$items = wp_get_nav_menu_items( $menu_id ) ?: [];
	$top   = array_filter( $items, fn( $i ) => ! (int) $i->menu_item_parent );
	$tree  = [];
	foreach ( $top as $item ) {
		$icon = 'arrow-left-s-line';
		foreach ( (array) $item->classes as $class ) {
			if ( str_starts_with( (string) $class, 'ic-' ) ) {
				$icon = substr( $class, 3 );
			}
		}
		$node = [ 'kind' => 'link', 'title' => $item->title, 'url' => $item->url, 'icon' => $icon ];
		$kids = array_values( array_filter( $items, fn( $i ) => (int) $i->menu_item_parent === (int) $item->ID ) );

		if ( 'taxonomy' === $item->type && 'product_cat' === $item->object ) {
			$term  = get_term( (int) $item->object_id, 'product_cat' );
			$terms = $term instanceof WP_Term ? aim_t_menu_cats( $term->term_id ) : [];
			// Children in the WP menu that are categories override the term's own children.
			$picked = array_filter( array_map( fn( $k ) => 'product_cat' === $k->object ? get_term( (int) $k->object_id, 'product_cat' ) : null, $kids ), fn( $t ) => $t instanceof WP_Term );
			if ( $picked ) {
				$terms = array_values( $picked );
			}
			if ( $terms ) {
				$node = aim_t_menu_mega( $item->title, $item->url, $term instanceof WP_Term ? aim_t_cat_icon( $term ) : $icon, $terms );
			}
		} elseif ( $kids ) {
			$node['kind']     = 'drop';
			$node['children'] = array_map( fn( $k ) => [ 'title' => $k->title, 'url' => $k->url, 'icon' => 'arrow-left-s-line' ], $kids );
		}
		$tree[] = $node;
	}
	return $tree;
}

/** "از ۱۲۰,۰۰۰ تومان" for a product. */
function aim_t_menu_price( WC_Product $p ) {
	[ $price ] = aim_t_prices( $p );
	return $price > 0 ? 'از ' . aim_t_money( $price ) . ' تومان' : 'ناموجود';
}

/** Desktop menu: <ul class="sh-menu"> with mega panels and dropdowns. */
function aim_t_header_menu( $menu_id = 0 ) {
	$GLOBALS['aim_t_menu_id'] = (int) $menu_id; // the mobile drawer uses the same menu
	$tree = aim_t_menu_tree( $menu_id );
	ob_start();
	echo '<ul class="sh-menu">';
	foreach ( $tree as $n => $node ) {
		$id = 'mm-' . $n;
		if ( 'mega' === $node['kind'] ) {
			aim_t_mega_panel( $node, $id );
		} elseif ( 'drop' === $node['kind'] ) {
			printf( '<li class="has-sub"><a href="%s" aria-haspopup="true">%s<i class="ic-arrow-down-s-line"></i></a><ul class="sub-menu">', esc_url( $node['url'] ), esc_html( $node['title'] ) );
			foreach ( $node['children'] as $c ) {
				printf( '<li><a href="%s"><i class="ic-%s"></i>%s</a></li>', esc_url( $c['url'] ), esc_attr( $c['icon'] ), esc_html( $c['title'] ) );
			}
			echo '</ul></li>';
		} else {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $node['url'] ), esc_html( $node['title'] ) );
		}
	}
	echo '</ul>';
	return ob_get_clean();
}

function aim_t_mega_panel( array $node, $id ) {
	$promo_title = 'off' !== aim_t_opt( 'm_promo' ) ? aim_t_opt( 'm_promo_title' ) : '';
	?>
	<li class="has-mega" data-mega>
		<a href="<?php echo esc_url( $node['url'] ); ?>" class="mega-trigger" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $node['title'] ); ?><i class="ic-arrow-down-s-line"></i></a>
		<div class="mega" id="<?php echo esc_attr( $id ); ?>" data-mega-panel>
			<div class="mega-body<?php echo $promo_title ? '' : ' no-promo'; ?>">
				<div class="mega-rail" role="tablist" aria-orientation="vertical" aria-label="<?php echo esc_attr( $node['title'] ); ?>">
					<?php foreach ( $node['tabs'] as $i => $tab ) : ?>
						<button type="button" role="tab" id="<?php echo esc_attr( "$id-t-{$tab['key']}" ); ?>" aria-controls="<?php echo esc_attr( "$id-p-{$tab['key']}" ); ?>" aria-selected="<?php echo $i ? 'false' : 'true'; ?>" tabindex="<?php echo $i ? '-1' : '0'; ?>" data-mega-tab>
							<span class="mega-ico"><i class="ic-<?php echo esc_attr( $tab['icon'] ); ?>"></i></span><span><?php echo esc_html( $tab['title'] ); ?></span><em><?php echo esc_html( aim_t_fa( $tab['count'] ) ); ?></em>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="mega-panes">
					<?php foreach ( $node['tabs'] as $i => $tab ) : ?>
						<section class="mega-pane" role="tabpanel" id="<?php echo esc_attr( "$id-p-{$tab['key']}" ); ?>" aria-labelledby="<?php echo esc_attr( "$id-t-{$tab['key']}" ); ?>"<?php echo $i ? ' hidden' : ''; ?>>
							<div class="mega-head">
								<b><?php echo esc_html( $tab['title'] ); ?></b>
								<a href="<?php echo esc_url( $tab['url'] ); ?>">همه <span>(<?php echo esc_html( aim_t_fa( $tab['count'] ) ); ?>)</span><i class="ic-arrow-left-line"></i></a>
							</div>
							<?php if ( $tab['products'] ) : ?>
								<div class="mega-grid">
									<?php foreach ( $tab['products'] as $p ) : ?>
										<a class="mega-item" href="<?php echo esc_url( $p->get_permalink() ); ?>">
											<?php echo aim_t_logo( $p, 'mega-logo' ); // phpcs:ignore WordPress.Security.EscapeOutput -- image tag / SVG tile. ?>
											<span><b dir="auto"><?php echo esc_html( $p->get_name() ); ?></b><small><?php echo esc_html( aim_t_fa( aim_t_menu_price( $p ) ) ); ?></small></span>
										</a>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<p class="mega-empty">هنوز محصولی در این دسته نیست.</p>
							<?php endif; ?>
						</section>
					<?php endforeach; ?>
				</div>
				<?php if ( $promo_title ) : ?>
					<aside class="mega-promo">
						<?php if ( aim_t_opt( 'm_promo_kicker' ) ) : ?><span class="mega-promo-kicker"><i class="ic-sparkling-2-fill"></i><?php echo esc_html( aim_t_opt( 'm_promo_kicker' ) ); ?></span><?php endif; ?>
						<b><?php echo aim_t_mark( $promo_title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by aim_t_mark(). ?></b>
						<p><?php echo esc_html( aim_t_opt( 'm_promo_text' ) ); ?></p>
						<a href="<?php echo esc_url( aim_t_footer_fill( aim_t_opt( 'm_promo_url' ) ) ?: wc_get_page_permalink( 'shop' ) ); ?>"><?php echo esc_html( aim_t_opt( 'm_promo_button' ) ); ?><i class="ic-arrow-left-line"></i></a>
					</aside>
				<?php endif; ?>
			</div>
			<div class="mega-foot">
				<ul>
					<li><i class="ic-flashlight-line"></i>تحویل فوری</li>
					<li><i class="ic-shield-check-line"></i>ضمانت تا پایان اشتراک</li>
					<li><i class="ic-customer-service-2-line"></i>پشتیبانی واقعی</li>
				</ul>
				<a class="mega-all" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php echo esc_html( aim_t_opt( 'm_all' ) ); ?><i class="ic-arrow-left-line"></i></a>
			</div>
		</div>
	</li>
	<?php
}

/** Mobile drawer content (inside .drawer-panel). */
function aim_t_drawer_menu() {
	$tree    = aim_t_menu_tree( $GLOBALS['aim_t_menu_id'] ?? 0 );
	$popular = [];
	foreach ( $tree as $node ) {
		if ( 'mega' === $node['kind'] ) {
			$popular = $node['tabs'][0]['products'];
			break;
		}
	}
	$user = wp_get_current_user();
	?>
	<div class="dm-head">
		<a class="dm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo aim_t_brand_mark( 'dm-logo-mark' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><b><?php echo aim_t_brand_name( get_bloginfo( 'name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></b></a>
		<button type="button" class="dm-x" data-drawer-close aria-label="بستن منو"><i class="ic-close-line"></i></button>
	</div>
	<div class="dm-body">
		<form class="dm-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<i class="ic-search-line" aria-hidden="true"></i>
			<label class="visually-hidden" for="dm-s">جست‌وجو</label>
			<input id="dm-s" type="search" name="s" placeholder="اسم سرویس را بنویس…" autocomplete="off">
			<input type="hidden" name="post_type" value="product">
		</form>

		<?php if ( $popular ) : ?>
			<div class="dm-pop">
				<div class="dm-label"><i class="ic-rocket-2-line"></i><?php echo esc_html( aim_t_opt( 'm_popular' ) ); ?></div>
				<div class="dm-strip">
					<?php foreach ( $popular as $p ) : ?>
						<a href="<?php echo esc_url( $p->get_permalink() ); ?>">
							<?php echo aim_t_logo( $p, 'dm-logo' ); // phpcs:ignore WordPress.Security.EscapeOutput -- image tag / SVG tile. ?>
							<b dir="auto"><?php echo esc_html( $p->get_name() ); ?></b>
							<small><?php echo esc_html( aim_t_fa( aim_t_menu_price( $p ) ) ); ?></small>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<ul class="dm-nav">
			<?php foreach ( $tree as $node ) : ?>
				<li>
					<?php if ( 'link' === $node['kind'] ) : ?>
						<a class="dm-row" href="<?php echo esc_url( $node['url'] ); ?>"><span class="dm-ico"><i class="ic-<?php echo esc_attr( $node['icon'] ); ?>"></i></span><b><?php echo esc_html( $node['title'] ); ?></b><i class="ic-arrow-left-s-line dm-go"></i></a>
					<?php else : ?>
						<details class="dm-acc">
							<summary class="dm-row"><span class="dm-ico"><i class="ic-<?php echo esc_attr( $node['icon'] ); ?>"></i></span><b><?php echo esc_html( $node['title'] ); ?></b><i class="ic-arrow-down-s-line dm-plus"></i></summary>
							<div class="dm-sub">
								<?php if ( 'mega' === $node['kind'] ) : ?>
									<?php foreach ( array_slice( $node['tabs'], 1 ) as $tab ) : ?>
										<a href="<?php echo esc_url( $tab['url'] ); ?>"><i class="ic-<?php echo esc_attr( $tab['icon'] ); ?>"></i><span><?php echo esc_html( $tab['title'] ); ?></span><em><?php echo esc_html( aim_t_fa( $tab['count'] ) ); ?></em></a>
									<?php endforeach; ?>
									<a class="dm-all" href="<?php echo esc_url( $node['url'] ); ?>"><?php echo esc_html( aim_t_opt( 'm_all' ) ); ?><i class="ic-arrow-left-line"></i></a>
								<?php else : ?>
									<?php foreach ( $node['children'] as $c ) : ?>
										<a href="<?php echo esc_url( $c['url'] ); ?>"><i class="ic-<?php echo esc_attr( $c['icon'] ); ?>"></i><span><?php echo esc_html( $c['title'] ); ?></span></a>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</details>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<div class="dm-foot">
		<a class="dm-me" href="<?php echo esc_url( aim_theme_account_url() ); ?>">
			<span class="dm-ico"><i class="ic-user-3-line"></i></span>
			<span><small><?php echo is_user_logged_in() ? 'حساب کاربری' : 'خوش آمدی'; ?></small><b><?php echo esc_html( is_user_logged_in() ? $user->display_name : 'ورود | ثبت‌نام' ); ?></b></span>
		</a>
		<?php if ( is_user_logged_in() && function_exists( 'aim_panel_url' ) ) : ?>
			<a class="dm-btn" href="<?php echo esc_url( aim_panel_url( 'user', 'orders' ) ); ?>"><i class="ic-file-list-3-line"></i>سفارش‌ها</a>
		<?php endif; ?>
		<button type="button" class="dm-btn dm-theme" data-theme-toggle aria-label="تغییر حالت شب و روز"><i class="ic-moon-line"></i></button>
	</div>
	<?php
}
