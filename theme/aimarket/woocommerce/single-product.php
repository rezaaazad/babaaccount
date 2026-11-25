<?php
/**
 * Single product: dark hero, plan picker with sticky buy bar, after-purchase
 * steps, description, FAQ, reviews and related products.
 */
defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) :
	the_post();
	$product = wc_get_product( get_the_ID() );
	if ( ! $product ) {
		continue;
	}
	$plans    = aim_t_plans( $product );
	$cat      = aim_t_primary_cat( $product );
	$shop_url = wc_get_page_permalink( 'shop' );
	$mode     = class_exists( 'AIM_Inventory' ) ? AIM_Inventory::delivery_mode( $product->get_id() ) : '';
	$rating   = (float) $product->get_average_rating();
	$reviews  = (int) $product->get_review_count();
	$in_stock = (bool) array_filter( $plans, fn( $p ) => $p['in_stock'] );
	$alerts   = class_exists( 'AIM_Stock_Alerts' ) && AIM_Stock_Alerts::enabled();
	$min      = $plans ? min( array_column( $plans, 'price' ) ) : 0;
	$selected = 0;
	foreach ( $plans as $i => $pl ) {
		if ( ( $pl['featured'] || '' !== $pl['badge'] ) && $pl['in_stock'] ) {
			$selected = $i;
			break;
		}
	}
	if ( ! $plans[ $selected ]['in_stock'] ) {
		foreach ( $plans as $i => $pl ) {
			if ( $pl['in_stock'] ) {
				$selected = $i;
				break;
			}
		}
	}
	$related  = array_filter( array_map( 'wc_get_product', wc_get_related_products( $product->get_id(), 8 ) ) );
	$content  = trim( get_the_content() );
	$trail    = [ 'فروشگاه' => $shop_url ];
	if ( $cat ) {
		$trail[ $cat->name ] = get_term_link( $cat );
	}
	$trail[ $product->get_name() ] = '';

	// Texts and lists below are edited in panel → تنظیمات قالب → صفحهٔ محصول (inc/options.php).
	$after = (array) aim_t_opt( 'manual' === $mode ? 'p_after_manual' : 'p_after_auto' );
	?>

	<section class="pd-hero<?php echo $in_stock ? '' : ' is-out'; ?>">
		<div class="hero-grid" aria-hidden="true"></div>
		<div class="container pd-hero-in">
			<?php aim_t_breadcrumb( $trail ); ?>
			<div class="pd-top">
				<div class="pd-logo"><?php echo aim_t_logo( $product, 'pd-logo-img' ); // phpcs:ignore ?></div>
				<div class="pd-title">
					<?php if ( $cat ) : ?><a class="pd-cat" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a><?php endif; ?>
					<?php if ( $product->get_meta( '_aim_badge' ) ) : ?><span class="gold-badge pd-gold"><i class="ic-vip-crown-2-fill"></i><?php echo esc_html( $product->get_meta( '_aim_badge' ) ); ?></span><?php endif; ?>
					<h1><?php the_title(); ?></h1>
					<div class="pd-facts">
						<?php if ( $reviews ) : ?>
							<a href="#reviews" class="pd-rate"><i class="ic-star-fill"></i><b><?php echo esc_html( aim_t_fa( number_format( $rating, 1 ) ) ); ?></b> از ۵ (<?php echo esc_html( aim_t_fa( $reviews ) ); ?> نظر)</a>
						<?php endif; ?>
						<span class="pd-stock<?php echo $in_stock ? '' : ' is-out'; ?>"><?php echo $in_stock ? 'موجود برای خرید' : 'فعلاً ناموجود'; ?></span>
						<?php if ( $product->get_meta( '_aim_subtitle' ) ) : ?><span class="pd-fact"><i class="ic-apps-2-line"></i><?php echo esc_html( $product->get_meta( '_aim_subtitle' ) ); ?></span><?php endif; ?>
					</div>
				</div>
			</div>
			<?php if ( $product->get_short_description() ) : ?>
				<div class="pd-lead"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
			<?php endif; ?>
			<ul class="pd-chips">
				<li><i class="ic-stack-line"></i><?php echo esc_html( aim_t_fa( count( $plans ) ) ); ?> پلن برای انتخاب</li>
				<?php if ( $product->get_meta( '_aim_type_label' ) ) : ?><li><i class="ic-user-3-line"></i><?php echo esc_html( $product->get_meta( '_aim_type_label' ) ); ?></li><?php endif; ?>
				<?php if ( 'auto' === $mode ) : ?><li><i class="ic-flashlight-line"></i>تحویل آنی</li><?php elseif ( 'manual' === $mode ) : ?><li><i class="ic-mail-check-line"></i>فعال‌سازی روی ایمیل شما</li><?php endif; ?>
				<?php if ( aim_t_opt( 'p_guarantee' ) ) : ?><li><i class="ic-shield-check-line"></i><?php echo esc_html( aim_t_opt( 'p_guarantee' ) ); ?></li><?php endif; ?>
			</ul>
			<div class="pd-cta">
				<div class="pd-from"><small>شروع قیمت از</small><b><?php echo esc_html( aim_t_money( $min ) ); ?></b><small>تومان</small></div>
				<?php if ( $alerts && $in_stock && ! $product->is_on_sale() ) : ?><?php aim_t_alert_button( $product, 'sale' ); ?><?php endif; ?>
				<a class="pc-btn pd-go" href="#plans"><?php echo esc_html( $in_stock ? aim_t_opt( 'p_cta' ) : 'مشاهدهٔ پلن‌ها' ); ?><i class="ic-arrow-down-s-line"></i></a>
			</div>
		</div>
	</section>

	<nav class="pd-tabs" data-pd-tabs aria-label="بخش‌های صفحه">
		<div class="container">
			<a href="#plans" class="is-active">انتخاب پلن</a>
			<a href="#after">بعد از خرید</a>
			<?php if ( $content ) : ?><a href="#about">توضیحات</a><?php endif; ?>
			<a href="#pfaq">سؤالات</a>
			<a href="#reviews">نظرات<?php echo $reviews ? ' (' . esc_html( aim_t_fa( $reviews ) ) . ')' : ''; ?></a>
			<?php if ( $related ) : ?><a href="#related">محصولات مشابه</a><?php endif; ?>
		</div>
	</nav>

	<div class="container"><?php woocommerce_output_all_notices(); ?></div>

	<!-- Plans -->
	<section class="sec pd-sec" id="plans">
		<div class="container">
			<?php aim_t_head( aim_t_mark( aim_t_opt( 'p_plans_title' ) ), aim_t_opt( 'p_plans_text' ) ); ?>
			<?php if ( ! $in_stock ) : ?>
				<div class="pd-soldout"><i class="ic-time-line"></i><div><b><?php echo esc_html( aim_t_opt( 'p_soldout_title' ) ); ?></b><small><?php echo esc_html( aim_t_opt( 'p_soldout_text' ) ); ?><?php echo $alerts ? ' با «خبرم کن» به محض موجود شدن پیامک می‌گیرید.' : ' برای اطلاع از موجودی با پشتیبانی در ارتباط باشید.'; ?></small></div>
					<?php if ( $alerts ) : ?><?php aim_t_alert_button( $product, 'stock' ); ?><?php endif; ?>
				</div>
			<?php endif; ?>
			<form class="plans" method="post" action="<?php echo esc_url( $product->get_permalink() ); ?>" data-plans>
				<?php foreach ( $plans as $i => $pl ) : ?>
					<label class="plan<?php echo $pl['in_stock'] ? '' : ' is-out'; ?>">
						<input type="radio" name="aim_plan" value="<?php echo esc_attr( $pl['id'] ); ?>" <?php checked( $i, $selected ); ?> <?php disabled( ! $pl['in_stock'] ); ?>
							data-title="<?php echo esc_attr( $pl['title'] ); ?>" data-price="<?php echo esc_attr( aim_t_money( $pl['price'] ) ); ?>" data-regular="<?php echo esc_attr( $pl['off'] ? aim_t_money( $pl['regular'] ) : '' ); ?>"
							data-attrs="<?php echo esc_attr( wp_json_encode( $pl['attributes'] ) ); ?>">
						<span class="plan-radio" aria-hidden="true"></span>
						<span class="plan-body">
							<span class="plan-title">
								<b><?php echo esc_html( $pl['title'] ); ?></b>
								<?php if ( $pl['badge'] ) : ?><span class="gold-badge"><i class="ic-vip-crown-2-fill"></i><?php echo esc_html( $pl['badge'] ); ?></span><?php elseif ( $pl['featured'] ) : ?><span class="plan-badge"><i class="ic-star-fill"></i>پیشنهاد ما</span><?php endif; ?>
								<?php if ( ! $pl['in_stock'] ) : ?><span class="plan-badge is-out">ناموجود</span><?php endif; ?>
							</span>
							<?php if ( $pl['text'] ) : ?><span class="plan-text"><?php echo esc_html( $pl['text'] ); ?></span><?php endif; ?>
							<span class="plan-chips">
								<?php if ( $pl['days'] ) : ?><span><i class="ic-calendar-line"></i><?php echo esc_html( aim_t_days_label( $pl['days'] ) ); ?></span><?php endif; ?>
								<?php if ( $pl['type'] ) : ?><span><i class="ic-user-3-line"></i><?php echo esc_html( $pl['type'] ); ?></span><?php endif; ?>
								<?php if ( $pl['delivery'] ) : ?><span class="<?php echo 'auto' === $mode ? 'is-green' : ''; ?>"><i class="ic-flashlight-line"></i><?php echo esc_html( $pl['delivery'] ); ?></span><?php endif; ?>
							</span>
						</span>
						<span class="plan-price">
							<?php if ( $pl['off'] ) : ?><span class="plan-off"><?php echo esc_html( aim_t_fa( $pl['off'] ) ); ?>٪</span><del><?php echo esc_html( aim_t_money( $pl['regular'] ) ); ?></del><?php endif; ?>
							<b><?php echo esc_html( aim_t_money( $pl['price'] ) ); ?> <small>تومان</small></b>
						</span>
					</label>
				<?php endforeach; ?>

				<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
				<input type="hidden" name="quantity" value="1">
				<?php if ( $product->is_type( 'variable' ) ) : ?>
					<input type="hidden" name="variation_id" value="<?php echo esc_attr( $plans[ $selected ]['id'] ); ?>" data-variation>
					<span data-attr-fields></span>
				<?php endif; ?>
				<input type="hidden" name="aim_buy_now" value="0" data-buy-now>

				<div class="buybar" data-buybar>
					<div class="container buybar-in">
						<div class="buybar-info">
							<b><?php echo esc_html( $product->get_name() ); ?></b>
							<small data-bb-title><?php echo esc_html( $plans[ $selected ]['title'] ); ?></small>
						</div>
						<div class="buybar-price">
							<del data-bb-regular><?php echo $plans[ $selected ]['off'] ? esc_html( aim_t_money( $plans[ $selected ]['regular'] ) ) : ''; ?></del>
							<b><span data-bb-price><?php echo esc_html( aim_t_money( $plans[ $selected ]['price'] ) ); ?></span> <small>تومان</small></b>
						</div>
						<button type="submit" class="buybar-cart" aria-label="افزودن به سبد خرید" <?php disabled( ! $in_stock ); ?>><i class="ic-shopping-cart-2-line"></i></button>
						<button type="submit" class="pc-btn buybar-buy" data-buy <?php disabled( ! $in_stock ); ?>><?php echo esc_html( $in_stock ? aim_t_opt( 'p_buy' ) : 'ناموجود' ); ?></button>
					</div>
				</div>
			</form>
		</div>
	</section>

	<!-- After purchase -->
	<section class="sec sec-tint pd-sec" id="after">
		<div class="container">
			<?php aim_t_head( aim_t_mark( aim_t_opt( 'p_after_title' ) ) ); ?>
			<ol class="after">
				<?php foreach ( array_values( $after ) as $i => $step ) : ?>
					<li><?php echo aim_t_illus( [ 'step-pay', 'manual' === $mode ? 'step-login' : 'step-receive', 'step-launch' ][ $i ] ?? 'step-launch', 'after-art' ); // phpcs:ignore ?><b><?php echo esc_html( aim_t_fa( $i + 1 ) ); ?></b><div><h3><?php echo esc_html( $step['title'] ?? '' ); ?></h3><p><?php echo esc_html( $step['text'] ?? '' ); ?></p></div></li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>

	<!-- Description -->
	<?php if ( $content ) : ?>
	<section class="sec pd-sec" id="about">
		<div class="container">
			<?php aim_t_head( 'توضیحات <em>' . esc_html( $product->get_name() ) . '</em>' ); ?>
			<div class="pd-desc" data-readmore>
				<div class="pd-desc-in entry-content"><?php the_content(); ?></div>
				<button type="button" class="pd-more" data-readmore-btn hidden>ادامهٔ مطلب<i class="ic-arrow-down-s-line"></i></button>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<!-- FAQ -->
	<section class="sec sec-tint pd-sec" id="pfaq">
		<div class="container faq-wrap">
			<?php aim_t_head( aim_t_mark( aim_t_opt( 'p_faq_title' ) ), aim_t_opt( 'p_faq_text' ) ); ?>
			<div class="faq">
				<?php foreach ( apply_filters( 'aim_product_faq', (array) aim_t_opt( 'p_faq' ), $product ) as $i => $f ) : ?>
					<details <?php echo 0 === $i ? 'open' : ''; ?>><summary><?php echo esc_html( $f['q'] ); ?><i class="ic-add-line" aria-hidden="true"></i></summary><p><?php echo esc_html( $f['a'] ); ?></p></details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- Reviews -->
	<section class="sec pd-sec" id="reviews">
		<div class="container">
			<?php aim_t_head( aim_t_mark( aim_t_opt( 'p_reviews_title' ) ), aim_t_opt( 'p_reviews_text' ) ); ?>
			<?php
			$counts = $product->get_rating_counts();
			$list   = get_comments( [ 'post_id' => $product->get_id(), 'status' => 'approve', 'type' => 'review', 'number' => 10 ] );
			?>
			<div class="rv">
				<aside class="rv-sum">
					<b class="rv-avg"><?php echo esc_html( aim_t_fa( number_format( $rating, 1 ) ) ); ?></b>
					<span class="rv-stars" style="--r:<?php echo esc_attr( $rating / 5 * 100 ); ?>%" aria-label="<?php echo esc_attr( 'امتیاز ' . aim_t_fa( number_format( $rating, 1 ) ) . ' از ۵' ); ?>"></span>
					<small>بر اساس <?php echo esc_html( aim_t_fa( $reviews ) ); ?> نظر</small>
					<ul class="rv-bars">
						<?php for ( $s = 5; $s >= 1; $s-- ) : ?>
							<?php $c = (int) ( $counts[ $s ] ?? 0 ); ?>
							<li><span><?php echo esc_html( aim_t_fa( $s ) ); ?> <i class="ic-star-fill"></i></span><span class="rv-bar"><i style="width:<?php echo esc_attr( $reviews ? $c / $reviews * 100 : 0 ); ?>%"></i></span><span><?php echo esc_html( aim_t_fa( $c ) ); ?></span></li>
						<?php endfor; ?>
					</ul>
					<a class="pc-btn" href="#review-form"><i class="ic-edit-line"></i>ثبت نظر</a>
				</aside>
				<div class="rv-list">
					<?php if ( $list ) : ?>
						<?php foreach ( $list as $cm ) : ?>
							<?php
							$stars    = (int) get_comment_meta( $cm->comment_ID, 'rating', true );
							$verified = wc_review_is_from_verified_owner( $cm->comment_ID );
							?>
							<article class="rv-item">
								<header>
									<span class="avatar-sm"><?php echo esc_html( mb_substr( $cm->comment_author ?: '؟', 0, 1 ) ); ?></span>
									<div><b><?php echo esc_html( $cm->comment_author ); ?></b><?php if ( $verified ) : ?><span class="rv-verified"><i class="ic-checkbox-circle-fill"></i>خریدار</span><?php endif; ?><small><?php echo esc_html( function_exists( 'aim_jdate' ) ? aim_jdate( $cm->comment_date_gmt ) : get_comment_date( '', $cm ) ); ?></small></div>
									<?php if ( $stars ) : ?><span class="rv-stars sm" style="--r:<?php echo esc_attr( $stars * 20 ); ?>%"></span><?php endif; ?>
								</header>
								<p><?php echo esc_html( $cm->comment_content ); ?></p>
							</article>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="rv-empty"><i class="ic-chat-smile-3-line"></i><b>هنوز نظری ثبت نشده</b><small>اولین نفری باش که تجربه‌اش را می‌نویسد.</small></div>
					<?php endif; ?>

					<div class="rv-form" id="review-form">
						<?php
						if ( get_option( 'woocommerce_review_rating_verification_required' ) === 'no' || wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) {
							comment_form( [
								'title_reply'          => 'تجربه‌ات از خرید را بنویس',
								'title_reply_before'   => '<h3 class="rv-form-title">',
								'title_reply_after'    => '</h3>',
								'label_submit'         => 'ثبت نظر',
								'class_submit'         => 'pc-btn',
								'comment_notes_before' => '',
								'logged_in_as'         => '',
								'must_log_in'          => '<p class="rv-login">برای ثبت نظر <a href="' . esc_url( aim_theme_account_url() ) . '">وارد شو</a>.</p>',
								'fields'               => [
									'author' => '<p class="comment-form-author"><label for="author">نام</label><input id="author" name="author" type="text" required></p>',
									'email'   => '<p class="comment-form-email"><label for="email">ایمیل (نمایش داده نمی‌شود)</label><input id="email" name="email" type="email" required></p>',
									'cookies' => '',
								],
								'comment_field'        => '<div class="rv-rate"><span>امتیاز تو:</span><select name="rating" id="rating" required><option value="">انتخاب کن</option><option value="5">۵ — عالی</option><option value="4">۴ — خوب</option><option value="3">۳ — متوسط</option><option value="2">۲ — ضعیف</option><option value="1">۱ — خیلی بد</option></select></div><p class="comment-form-comment"><label for="comment">نظر تو</label><textarea id="comment" name="comment" rows="4" required placeholder="از کیفیت اکانت، سرعت تحویل و پشتیبانی بنویس."></textarea></p>',
							] );
						} else {
							echo '<p class="rv-login">فقط خریداران این محصول می‌توانند نظر ثبت کنند.</p>';
						}
						?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Related -->
	<?php if ( $related ) : ?>
	<section class="sec sec-tint pd-sec" id="related">
		<div class="container">
			<?php aim_t_head( aim_t_mark( aim_t_opt( 'p_related_title' ) ), aim_t_opt( 'p_related_text' ), $shop_url, '', aim_t_rail_controls( 'rail-rel' ) ); ?>
			<div class="rail" id="rail-rel">
				<?php foreach ( $related as $rp ) : ?><?php echo aim_t_card( $rp, [ 'compact' => true ] ); // phpcs:ignore ?><?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php
endwhile;
get_footer();
