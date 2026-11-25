<?php
/**
 * Storefront components shared by the homepage sections.
 */
defined( 'ABSPATH' ) || exit;

function aim_t_fa( $n ) {
	return function_exists( 'aim_fa_digits' ) ? aim_fa_digits( $n ) : $n;
}

function aim_t_money( $amount ) {
	return aim_t_fa( number_format( (float) $amount ) );
}

/** Lowest current and regular price for a product (variable products: cheapest variation). */
function aim_t_prices( WC_Product $p ) {
	if ( $p->is_type( 'variable' ) ) {
		$price   = (float) $p->get_variation_price( 'min' );
		$regular = (float) $p->get_variation_regular_price( 'min' );
	} else {
		$price   = (float) $p->get_price();
		$regular = (float) $p->get_regular_price();
	}
	$off = ( $regular > $price && $regular > 0 ) ? (int) round( ( 1 - $price / $regular ) * 100 ) : 0;
	return [ $price, $regular, $off ];
}

/** Brand tile: product image if set, otherwise the original artwork tile (inc/art.php). */
function aim_t_logo( WC_Product $p, $class = 'pc-logo' ) {
	$base = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
	if ( $base->get_image_id() ) {
		return wp_get_attachment_image( $base->get_image_id(), [ 96, 96 ], false, [ 'class' => $class, 'alt' => '' ] );
	}
	return aim_t_art( $base, $class );
}

function aim_t_delivery_label( WC_Product $p ) {
	$custom = $p->get_meta( '_aim_delivery_label' );
	if ( $custom ) {
		return $custom;
	}
	$mode = class_exists( 'AIM_Inventory' ) ? AIM_Inventory::delivery_mode( $p->get_id() ) : '';
	return 'auto' === $mode ? 'آنی، بلافاصله پس از پرداخت' : ( 'manual' === $mode ? 'تا ۲۴ ساعت پس از خرید' : '' );
}

/**
 * Product card.
 *
 * @param WC_Product $p
 * @param array      $args rank (int), flag ('best'|'new'|''), compact (bool)
 */
function aim_t_card( WC_Product $p, $args = [] ) {
	$args = wp_parse_args( $args, [ 'rank' => 0, 'flag' => '', 'compact' => false ] );
	[ $price, $regular, $off ] = aim_t_prices( $p );
	$url      = $p->get_permalink();
	$subtitle = $p->get_meta( '_aim_subtitle' ) ?: wp_strip_all_tags( $p->get_short_description() );
	$type     = $p->get_meta( '_aim_type_label' );
	$delivery = aim_t_delivery_label( $p );
	$in_stock = $p->is_in_stock();
	$badge    = trim( (string) $p->get_meta( '_aim_badge' ) );
	ob_start();
	?>
	<article class="pc<?php echo 1 === $args['rank'] ? ' is-top' : ''; ?><?php echo $in_stock ? '' : ' is-out'; ?>">
		<a class="pc-cover" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true"></a>
		<div class="pc-flags">
			<?php if ( $args['rank'] ) : ?>
				<span class="pc-rank"><b><?php echo esc_html( aim_t_fa( $args['rank'] ) ); ?></b>پرفروش</span>
			<?php elseif ( 'new' === $args['flag'] ) : ?>
				<span class="pc-rank is-new"><i class="ic-sparkling-line"></i>جدید</span>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
			<span class="pc-flags-end">
				<?php if ( $badge ) : ?><span class="gold-badge"><i class="ic-vip-crown-2-fill"></i><?php echo esc_html( $badge ); ?></span><?php endif; ?>
				<?php if ( ! $in_stock ) : ?><span class="pc-soldout"><i class="ic-time-line"></i>ناموجود</span><?php elseif ( $off ) : ?><span class="pc-off"><?php echo esc_html( aim_t_fa( $off ) ); ?>٪ تخفیف</span><?php endif; ?>
			</span>
		</div>
		<a class="pc-head" href="<?php echo esc_url( $url ); ?>">
			<?php echo aim_t_logo( $p ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span>
				<span class="pc-name"><?php echo esc_html( $p->get_name() ); ?></span>
				<?php if ( $subtitle ) : ?><span class="pc-sub"><?php echo esc_html( wp_trim_words( $subtitle, 6, '…' ) ); ?></span><?php endif; ?>
			</span>
		</a>
		<?php if ( ! $args['compact'] && ( $delivery || $type ) ) : ?>
			<ul class="pc-meta">
				<?php if ( $delivery ) : ?><li><i class="ic-time-line"></i><span>تحویل:</span><b><?php echo esc_html( $delivery ); ?></b></li><?php endif; ?>
				<?php if ( $type ) : ?><li><i class="ic-user-3-line"></i><span>نوع:</span><b><?php echo esc_html( $type ); ?></b></li><?php endif; ?>
				<?php if ( $p->is_type( 'variable' ) ) : ?><li><i class="ic-stack-line"></i><span>پلن‌ها:</span><b><?php echo esc_html( aim_t_fa( count( $p->get_children() ) ) ); ?> پلن</b></li><?php endif; ?>
			</ul>
		<?php endif; ?>
		<div class="pc-price">
			<div>
				<small><?php echo esc_html( aim_t_opt( 'card_from' ) ); ?></small>
				<?php if ( $off ) : ?><del><?php echo esc_html( aim_t_money( $regular ) ); ?></del><?php endif; ?>
				<b><?php echo esc_html( aim_t_money( $price ) ); ?> <small>تومان</small></b>
			</div>
			<span class="pc-stock<?php echo $in_stock ? '' : ' is-out'; ?>"><?php echo $in_stock ? 'موجود' : 'ناموجود'; ?></span>
		</div>
		<a class="pc-btn<?php echo $in_stock ? '' : ' is-ghost'; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( aim_t_opt( $in_stock ? 'card_button' : 'card_soldout' ) ); ?></a>
	</article>
	<?php
	return ob_get_clean();
}

/** Section heading with optional kicker, description and "see all" link. */
function aim_t_head( $title, $desc = '', $link = '', $kicker = '', $controls = '', $link_text = 'همه محصولات' ) {
	echo '<div class="sec-head"><div>';
	if ( $kicker ) {
		echo '<span class="sec-kicker">' . esc_html( $kicker ) . '</span>';
	}
	echo '<h2>' . wp_kses( $title, [ 'em' => [] ] ) . '</h2>';
	if ( $desc ) {
		echo '<p>' . esc_html( $desc ) . '</p>';
	}
	echo '</div><div class="sec-tools">' . $controls; // phpcs:ignore WordPress.Security.EscapeOutput -- static markup from templates.
	if ( $link ) {
		echo '<a class="sec-all" href="' . esc_url( $link ) . '">' . esc_html( $link_text ) . '<i class="ic-arrow-left-s-line"></i></a>';
	}
	echo '</div></div>';
}

/** Prev/next buttons for a horizontal rail. */
function aim_t_rail_controls( $id ) {
	return sprintf(
		'<div class="rail-ctrl"><button type="button" data-rail="%1$s" data-dir="prev" aria-label="قبلی"><i class="ic-arrow-right-s-line"></i></button><button type="button" data-rail="%1$s" data-dir="next" aria-label="بعدی"><i class="ic-arrow-left-s-line"></i></button></div>',
		esc_attr( $id )
	);
}

/**
 * Homepage content that the shop owner may want to change without code.
 * Override any key with the `aim_home_content` filter.
 */
function aim_t_home() {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ); // Also read by the panel while WooCommerce is off.
	return apply_filters( 'aim_home_content', [
		'announce'    => [ 'text' => 'اشتراک‌های ChatGPT و Claude با تحویل آنی — همین حالا پلن خودت را انتخاب کن.', 'link' => $shop, 'cta' => 'مشاهدهٔ پلن‌ها' ],
		'hero_kicker' => 'فروشگاه اکانت هوش مصنوعی',
		'hero_title'  => 'هوش مصنوعی‌های محبوب دنیا، <em>بدون دردسر</em> در دستان تو',
		'hero_text'   => 'پلن مناسبت را انتخاب کن، ریالی پرداخت کن و چند ثانیه بعد اطلاعات اکانت را در حسابت ببین.',
		'popular'     => [ 'ChatGPT', 'Claude', 'Gemini', 'Midjourney', 'Grok' ],
		'offer'       => [ 'code' => 'WELCOME', 'amount' => '۱۰۰ هزار تومان', 'title' => 'تخفیف اولین خرید از هوش‌مارکت', 'text' => 'کد زیر را در صفحهٔ پرداخت وارد کن و اولین اشتراکت را ارزان‌تر بخر.' ],
		'uses'        => [
			[ 'key' => 'write', 'icon' => 'quill-pen-line', 'label' => 'متن و محتوا', 'cat' => 'text', 'title' => 'نوشتن، ترجمه و تولید محتوا', 'text' => 'برای نوشتن مقاله، کپشن، ایمیل و ترجمهٔ روان.' ],
			[ 'key' => 'image', 'icon' => 'image-2-line', 'label' => 'عکس و طراحی', 'cat' => 'image', 'title' => 'ساخت و ویرایش تصویر', 'text' => 'تصویرسازی از متن، طراحی پوستر و ویرایش عکس.' ],
			[ 'key' => 'video', 'icon' => 'movie-2-line', 'label' => 'ساخت ویدیو', 'cat' => 'video', 'title' => 'ساخت ویدیو با هوش مصنوعی', 'text' => 'ویدیوی تبلیغاتی و آموزشی از متن یا عکس.' ],
			[ 'key' => 'code', 'icon' => 'code-s-slash-line', 'label' => 'برنامه‌نویسی', 'cat' => 'code', 'title' => 'کدنویسی و رفع خطا', 'text' => 'نوشتن کد، توضیح کد و دیباگ سریع‌تر.' ],
			[ 'key' => 'research', 'icon' => 'book-open-line', 'label' => 'تحقیق و تحلیل', 'cat' => 'research', 'title' => 'تحقیق، خلاصه‌سازی و تحلیل', 'text' => 'خلاصهٔ مقاله، تحلیل داده و جست‌وجوی دقیق.' ],
			[ 'key' => 'daily', 'icon' => 'chat-smile-3-line', 'label' => 'کارهای روزمره', 'cat' => 'daily', 'title' => 'دستیار کارهای روزمره', 'text' => 'برنامه‌ریزی، ایده‌پردازی و پاسخ به سؤال‌ها.' ],
		],
		'steps'       => [
			[ 'icon' => 'apps-2-line', 'label' => 'انتخاب پلن', 'title' => 'سرویس و پلن را انتخاب کن', 'text' => 'در صفحهٔ هر محصول، مدت اشتراک، نوع اکانت و زمان تحویل هر پلن نوشته شده است.' ],
			[ 'icon' => 'smartphone-line', 'label' => 'ورود', 'title' => 'با شمارهٔ موبایل وارد شو', 'text' => 'بدون رمز عبور؛ یک کد پیامک می‌شود و حسابت ساخته می‌شود.' ],
			[ 'icon' => 'bank-card-line', 'label' => 'پرداخت', 'title' => 'ریالی و امن پرداخت کن', 'text' => 'پرداخت از درگاه بانکی یا کیف پول؛ نیازی به کارت ارزی نیست.' ],
			[ 'icon' => 'key-2-line', 'label' => 'دریافت', 'title' => 'اطلاعات اکانت را بگیر', 'text' => 'برای پلن‌های آنی، اطلاعات ورود بلافاصله در «اکانت‌های من» نمایش داده و پیامک اطلاع داده می‌شود.' ],
			[ 'icon' => 'rocket-2-line', 'label' => 'فعال‌سازی', 'title' => 'استفاده کن و خیالت راحت باشد', 'text' => 'قبل از پایان اشتراک یادآوری می‌گیری و تا پایان دوره پشتیبانی داری.' ],
		],
		'faq'         => [
			[ 'q' => 'بعد از پرداخت چقدر طول می‌کشد اکانت را بگیرم؟', 'a' => 'پلن‌هایی که برچسب «آنی» دارند بلافاصله تحویل می‌شوند. زمان تحویل بقیهٔ پلن‌ها روی کارت هر پلن نوشته شده است.' ],
			[ 'q' => 'اکانت اختصاصی با اشتراکی چه فرقی دارد؟', 'a' => 'اکانت اختصاصی فقط در اختیار شماست. در اکانت اشتراکی چند کاربر از یک اشتراک استفاده می‌کنند و قیمت پایین‌تر است.' ],
			[ 'q' => 'اطلاعات اکانت کجا نمایش داده می‌شود؟', 'a' => 'در بخش «اکانت‌های من» در حساب کاربری. برای امنیت، اطلاعات ورود در پیامک یا ایمیل فرستاده نمی‌شود.' ],
			[ 'q' => 'پول از حسابم کم شد اما سفارش ثبت نشد، چه کنم؟', 'a' => 'معمولاً مبلغ تا ۷۲ ساعت به حساب برمی‌گردد. اگر برنگشت، شمارهٔ پیگیری را در تیکت برای ما بفرستید.' ],
			[ 'q' => 'اگر اکانت مشکل داشت چه می‌شود؟', 'a' => 'تا پایان دورهٔ اشتراک ضمانت دارید؛ از حساب کاربری درخواست تعویض بدهید تا بررسی و جایگزین شود.' ],
		],
		'trust'       => [
			[ 'icon' => 'flashlight-line', 'title' => 'تحویل آنی', 'text' => 'برای پلن‌های خودکار' ],
			[ 'icon' => 'shield-check-line', 'title' => 'ضمانت تا پایان اشتراک', 'text' => 'تعویض اکانت معیوب' ],
			[ 'icon' => 'customer-service-2-line', 'title' => 'پشتیبانی واقعی', 'text' => 'تیکت، تلگرام و تلفن' ],
			[ 'icon' => 'notification-3-line', 'title' => 'یادآوری تمدید', 'text' => 'پیش از پایان اشتراک' ],
		],
	] );
}

/**
 * Purchasable plans of a product. Variable products: one plan per variation;
 * simple products: the product itself is the single plan.
 *
 * @return array[] id, title, text, price, regular, off, in_stock, days, type, delivery, attributes, featured
 */
function aim_t_plans( WC_Product $p ) {
	$type_label = $p->get_meta( '_aim_type_label' );
	$delivery   = aim_t_delivery_label( $p );
	$base_days  = (int) $p->get_meta( '_aim_duration_days' );
	$plans      = [];

	$make = function ( WC_Product $v, $title, $attrs ) use ( $type_label, $delivery, $base_days ) {
		$price   = (float) $v->get_price();
		$regular = (float) $v->get_regular_price();
		$days    = (int) $v->get_meta( '_aim_duration_days' ) ?: $base_days;
		return [
			'id'         => $v->get_id(),
			'title'      => $title,
			'text'       => wp_strip_all_tags( $v->get_description() ),
			'price'      => $price,
			'regular'    => $regular,
			'off'        => ( $regular > $price && $regular > 0 ) ? (int) round( ( 1 - $price / $regular ) * 100 ) : 0,
			'in_stock'   => $v->is_in_stock() && $v->is_purchasable(),
			'days'       => $days,
			'type'       => $v->get_meta( '_aim_type_label' ) ?: $type_label,
			'delivery'   => $delivery,
			'attributes' => $attrs,
			'featured'   => false,
			'badge'      => trim( (string) $v->get_meta( '_aim_plan_badge' ) ),
		];
	};

	if ( $p->is_type( 'variable' ) ) {
		foreach ( $p->get_available_variations( 'objects' ) as $v ) {
			$attrs = [];
			foreach ( $v->get_variation_attributes() as $key => $val ) {
				$attrs[ $key ] = $val;
			}
			$title   = wc_get_formatted_variation( $v, true, false, false ) ?: $v->get_name();
			$plans[] = $make( $v, $title, $attrs );
		}
		usort( $plans, fn( $a, $b ) => $a['price'] <=> $b['price'] );
	} else {
		// Single plan: name it by its length ("اشتراک ۱ ماهه") instead of repeating the product name.
		$label   = aim_t_days_label( $base_days );
		$plans[] = $make( $p, $label ? 'اشتراک ' . $label : $p->get_name(), [] );
	}

	// Highlight the plan with the biggest discount as "recommended", unless the shop set its own plan labels.
	$custom = array_filter( $plans, fn( $pl ) => '' !== $pl['badge'] );
	if ( count( $plans ) > 1 && ! $custom ) {
		$best = 0;
		foreach ( $plans as $i => $pl ) {
			if ( $pl['in_stock'] && $pl['off'] > $plans[ $best ]['off'] ) {
				$best = $i;
			}
		}
		$plans[ $best ]['featured'] = true;
	}
	return $plans;
}

function aim_t_days_label( $days ) {
	if ( ! $days ) {
		return '';
	}
	if ( 0 === $days % 365 ) {
		return aim_t_fa( $days / 365 ) . ' ساله';
	}
	if ( 0 === $days % 30 ) {
		return aim_t_fa( $days / 30 ) . ' ماهه';
	}
	return aim_t_fa( $days ) . ' روزه';
}

/** Breadcrumb: Home › Shop › [category] › current. */
function aim_t_breadcrumb( array $trail ) {
	echo '<nav class="crumbs" aria-label="مسیر صفحه"><a href="' . esc_url( home_url( '/' ) ) . '">خانه</a>';
	foreach ( $trail as $label => $url ) {
		echo '<i class="ic-arrow-left-s-line" aria-hidden="true"></i>';
		echo $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : '<span aria-current="page">' . esc_html( $label ) . '</span>';
	}
	echo '</nav>';
}

/** Primary (non-default) category of a product. */
function aim_t_primary_cat( WC_Product $p ) {
	$default = (int) get_option( 'default_product_cat' );
	foreach ( $p->get_category_ids() as $id ) {
		if ( $id !== $default ) {
			$t = get_term( $id, 'product_cat' );
			if ( $t && ! is_wp_error( $t ) ) {
				return $t;
			}
		}
	}
	return null;
}

/** Purchase progress shown on cart, checkout and order-received pages. */
function aim_t_progress( $current ) {
	$steps = [ 'cart' => [ 'shopping-cart-2-line', 'سبد خرید' ], 'checkout' => [ 'bank-card-line', 'اطلاعات و پرداخت' ], 'done' => [ 'key-2-line', 'دریافت اکانت' ] ];
	$keys  = array_keys( $steps );
	$pos   = array_search( $current, $keys, true );
	echo '<ol class="co-steps">';
	foreach ( $keys as $i => $k ) {
		$cls = $i < $pos ? 'is-done' : ( $i === $pos ? 'is-active' : '' );
		$url = ( 'cart' === $k && $i < $pos ) ? wc_get_cart_url() : '';
		echo '<li class="' . esc_attr( $cls ) . '">';
		echo $url ? '<a href="' . esc_url( $url ) . '">' : '<span>';
		echo '<b><i class="ic-' . esc_attr( $i < $pos ? 'check-line' : $steps[ $k ][0] ) . '"></i></b><small>' . esc_html( $steps[ $k ][1] ) . '</small>';
		echo $url ? '</a>' : '</span>';
		echo '</li>';
	}
	echo '</ol>';
}

/** Cart/checkout page shell: breadcrumb, title, progress. */
function aim_t_checkout_head( $title, $step ) {
	echo '<section class="co-head"><div class="container">';
	aim_t_breadcrumb( [ 'فروشگاه' => wc_get_page_permalink( 'shop' ), $title => '' ] );
	echo '<div class="co-head-row"><h1>' . esc_html( $title ) . '</h1>';
	aim_t_progress( $step );
	echo '</div></div></section>';
}

/** Trust bullets under the order summary. */
function aim_t_trust_list() {
	echo '<ul class="co-trust">'
		. '<li><i class="ic-shield-check-line"></i>پرداخت امن از درگاه بانکی</li>'
		. '<li><i class="ic-flashlight-line"></i>تحویل آنی برای پلن‌های خودکار</li>'
		. '<li><i class="ic-refresh-line"></i>ضمانت تعویض تا پایان اشتراک</li>'
		. '</ul>';
}

/** Light page header used by pages, posts, blog lists, search and 404. */
function aim_t_page_head( $title, array $trail = [], $sub = '', $kicker = '' ) {
	echo '<section class="pg-head"><div class="container">';
	aim_t_breadcrumb( $trail ?: [ $title => '' ] );
	if ( $kicker ) {
		echo '<span class="sec-kicker">' . esc_html( $kicker ) . '</span>';
	}
	echo '<h1>' . esc_html( $title ) . '</h1>';
	if ( $sub ) {
		echo '<p>' . esc_html( $sub ) . '</p>';
	}
	echo '</div></section>';
}

/** Blog post card for lists. */
function aim_t_post_card( $post = null ) {
	$post = get_post( $post );
	$cats = get_the_category( $post->ID );
	ob_start();
	?>
	<a class="post-card" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
		<span class="post-img"><?php echo has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, 'medium_large' ) : aim_t_illus( 'text' ); // phpcs:ignore ?></span>
		<?php if ( $cats ) : ?><span class="post-cat"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
		<b><?php echo esc_html( get_the_title( $post ) ); ?></b>
		<small><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 18, '…' ) ); ?></small>
		<span class="post-meta"><i class="ic-calendar-line"></i><?php echo esc_html( function_exists( 'aim_jdate' ) ? aim_jdate( get_post_time( 'U', true, $post ) ) : get_the_date( '', $post ) ); ?></span>
	</a>
	<?php
	return ob_get_clean();
}

/** Pagination for WP queries, Persian digits. */
function aim_t_paginate( $query = null ) {
	global $wp_query;
	$q     = $query ?: $wp_query;
	$links = paginate_links( [
		'total'     => (int) $q->max_num_pages,
		'current'   => max( 1, (int) get_query_var( 'paged' ) ),
		'type'      => 'array',
		'prev_text' => '<i class="ic-arrow-right-s-line"></i>',
		'next_text' => '<i class="ic-arrow-left-s-line"></i>',
	] );
	if ( $links ) {
		echo '<nav class="arc-pages" aria-label="صفحه‌ها">';
		foreach ( $links as $l ) {
			echo aim_t_fa( $l ); // phpcs:ignore -- core markup, digits swapped.
		}
		echo '</nav>';
	}
}

/** Suggested texts for the gold labels (shown as a dropdown hint in the product editor). */
function aim_t_badge_suggestions() {
	return apply_filters( 'aim_badge_suggestions', [ 'پیشنهادی', 'محبوب', 'پرفروش', 'به‌صرفه‌ترین', 'جدید', 'ویژه', 'محدود', 'تحویل آنی' ] );
}

/**
 * «خبرم کن» button for stock / sale alerts (AIM_Stock_Alerts in the core plugin).
 * Guests are sent to the login page by the REST reply; theme.js handles the click.
 */
function aim_t_alert_button( WC_Product $p, $type ) {
	$done  = AIM_Stock_Alerts::is_subscribed( $p->get_id(), $type );
	$label = 'stock' === $type ? 'وقتی موجود شد خبرم کن' : 'اگر تخفیف خورد خبرم کن';
	printf(
		'<button type="button" class="alert-btn alert-%1$s%2$s" data-alert="%1$s" data-product="%3$d" %4$s><i class="ic-%5$s" aria-hidden="true"></i><span>%6$s</span></button>',
		esc_attr( $type ),
		$done ? ' is-done' : '',
		(int) $p->get_id(),
		disabled( $done, true, false ),
		$done ? 'check-line' : 'notification-3-line',
		esc_html( $done ? 'در فهرست اطلاع‌رسانی هستید' : $label )
	);
}
