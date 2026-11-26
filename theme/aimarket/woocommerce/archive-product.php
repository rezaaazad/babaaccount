<?php
/**
 * Shop, category and product search archive.
 */
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
get_header();

global $wp_query;
$shop_url = wc_get_page_permalink( 'shop' );
$term     = is_product_taxonomy() ? get_queried_object() : null;
$is_cat   = $term && 'product_cat' === $term->taxonomy;
$title    = is_search() ? 'نتایج جست‌وجو برای «' . get_search_query() . '»' : ( $term ? $term->name : aim_t_opt( 's_title' ) );
$desc     = $term && $term->description ? wp_strip_all_tags( $term->description ) : aim_t_opt( 's_text' );
$total    = (int) $wp_query->found_posts;
$paged    = max( 1, (int) get_query_var( 'paged' ) );
$per_page = (int) $wp_query->get( 'posts_per_page' );
$from     = $total ? ( $paged - 1 ) * $per_page + 1 : 0;
$to       = min( $total, $paged * $per_page );
$default  = (int) get_option( 'default_product_cat' );
$cats     = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => [ $default ] ] );
$orderby  = isset( $_GET['orderby'] ) && is_scalar( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : 'popularity';
$sorts    = [ 'popularity' => 'پرفروش‌ترین', 'date' => 'جدیدترین', 'price' => 'ارزان‌ترین', 'price-desc' => 'گران‌ترین', 'rating' => 'بیشترین امتیاز' ];
$min      = isset( $_GET['min_price'] ) && is_scalar( $_GET['min_price'] ) ? absint( aim_en_digits( wc_clean( wp_unslash( $_GET['min_price'] ) ) ) ) : '';
$max      = isset( $_GET['max_price'] ) && is_scalar( $_GET['max_price'] ) ? absint( aim_en_digits( wc_clean( wp_unslash( $_GET['max_price'] ) ) ) ) : '';
$instock  = ! empty( $_GET['instock'] );
$onsale   = ! empty( $_GET['onsale'] );
$brands_q = isset( $_GET['brand'] ) ? array_filter( array_map( 'sanitize_title', array_filter( (array) wp_unslash( $_GET['brand'] ), 'is_scalar' ) ) ) : [];
$active   = (int) (bool) $min + (int) (bool) $max + (int) $instock + (int) $onsale + count( $brands_q );
$facets   = aim_t_archive_facets( $term, is_search() ? get_search_query() : '' );
$base_url = $term ? get_term_link( $term ) : $shop_url;

$trail = [ 'فروشگاه' => $term || is_search() ? $shop_url : '' ];
if ( $term ) {
	foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) ) as $anc ) {
		$a                  = get_term( $anc, $term->taxonomy );
		$trail[ $a->name ] = get_term_link( $a );
	}
	$trail[ $term->name ] = '';
} elseif ( is_search() ) {
	$trail['جست‌وجو'] = '';
}
?>

<section class="arc-head">
	<div class="container">
		<?php aim_t_breadcrumb( $trail ); ?>
		<div class="arc-title">
			<div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo nl2br( esc_html( $desc ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped first. ?></p>
			</div>
			<div class="arc-side">
				<span class="arc-collage" aria-hidden="true">
					<?php foreach ( array_slice( wc_get_products( [ 'status' => 'publish', 'limit' => 3, 'orderby' => 'popularity', 'category' => $is_cat ? [ $term->slug ] : [] ] ), 0, 3 ) as $cp ) : ?>
						<?php echo aim_t_logo( $cp, 'arc-tile' ); // phpcs:ignore ?>
					<?php endforeach; ?>
				</span>
			<span class="arc-count"><i class="ic-store-2-line"></i><?php echo esc_html( aim_t_fa( $total ) ); ?> محصول</span>
			</div>
		</div>
		<?php if ( ! is_wp_error( $cats ) && $cats ) : ?>
			<nav class="arc-cats is-phone" aria-label="دسته‌بندی‌ها">
				<a class="<?php echo ! $term ? 'is-active' : ''; ?>" href="<?php echo esc_url( $shop_url ); ?>">همه</a>
				<?php foreach ( $cats as $c ) : ?>
					<a class="<?php echo $is_cat && ( $term->term_id === $c->term_id || term_is_ancestor_of( $c, $term, 'product_cat' ) ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?> <small><?php echo esc_html( aim_t_fa( $c->count ) ); ?></small></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
</section>

<section class="sec arc-body">
	<div class="container arc-layout">
		<div class="arc-scrim" data-filter-close hidden></div>
		<aside class="arc-aside" id="arc-filter" data-filter aria-label="دسته‌بندی و فیلتر">
			<div class="arc-aside-head">
				<b>فیلتر و دسته‌بندی</b>
				<button type="button" class="dm-x" data-filter-close aria-label="بستن"><i class="ic-close-line"></i></button>
			</div>
			<div class="arc-aside-body">
				<?php aim_t_archive_cat_tree( $term ); ?>

				<form class="side-card side-filter" method="get" action="<?php echo esc_url( $base_url ); ?>">
					<h2 class="side-title">فیلتر</h2>
					<?php if ( is_search() ) : ?>
						<input type="hidden" name="s" value="<?php echo esc_attr( get_search_query() ); ?>"><input type="hidden" name="post_type" value="product">
					<?php endif; ?>
					<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">

					<?php if ( $facets['brands'] ) : ?>
						<fieldset class="side-group">
							<legend>برند</legend>
							<div class="side-brands">
								<?php foreach ( $facets['brands'] as $br ) : ?>
									<label class="side-check">
										<input type="checkbox" name="brand[]" value="<?php echo esc_attr( $br['slug'] ); ?>" <?php checked( in_array( $br['slug'], $brands_q, true ) ); ?>>
										<span dir="auto"><?php echo esc_html( $br['name'] ); ?></span>
										<em><?php echo esc_html( aim_t_fa( $br['count'] ) ); ?></em>
									</label>
								<?php endforeach; ?>
							</div>
						</fieldset>
					<?php endif; ?>

					<fieldset class="side-group">
						<legend>محدودهٔ قیمت <small>(تومان)</small></legend>
						<div class="flt-range">
							<label><span>از</span><input type="text" inputmode="numeric" name="min_price" value="<?php echo esc_attr( $min ); ?>" placeholder="<?php echo esc_attr( $facets['min'] ? aim_t_money( $facets['min'] ) : '۰' ); ?>" data-money></label>
							<label><span>تا</span><input type="text" inputmode="numeric" name="max_price" value="<?php echo esc_attr( $max ); ?>" placeholder="<?php echo esc_attr( $facets['max'] ? aim_t_money( $facets['max'] ) : 'بدون سقف' ); ?>" data-money></label>
						</div>
					</fieldset>

					<div class="side-group">
						<label class="flt-switch"><input type="checkbox" name="instock" value="1" <?php checked( $instock ); ?>><span>فقط محصولات موجود</span></label>
						<label class="flt-switch"><input type="checkbox" name="onsale" value="1" <?php checked( $onsale ); ?>><span>فقط تخفیف‌دارها</span></label>
					</div>

					<button type="submit" class="pc-btn side-submit">نمایش نتیجه</button>
					<?php if ( $active ) : ?><a class="side-clear" href="<?php echo esc_url( $base_url ); ?>">حذف همهٔ فیلترها</a><?php endif; ?>
				</form>
			</div>
		</aside>

		<div class="arc-main">
			<div class="arc-bar">
				<span class="arc-showing"><?php echo $total ? esc_html( aim_t_fa( $total ) . ' محصول' . ( $total > $per_page ? ' — نمایش ' . aim_t_fa( $from ) . ' تا ' . aim_t_fa( $to ) : '' ) ) : ''; ?></span>
				<div class="arc-tools">
					<button type="button" class="arc-btn" data-filter-open aria-controls="arc-filter">
						<i class="ic-equalizer-line"></i>فیلترها<?php if ( $active ) : ?><b><?php echo esc_html( aim_t_fa( $active ) ); ?></b><?php endif; ?>
					</button>
					<form class="arc-sort" method="get">
						<?php foreach ( [ 'min_price', 'max_price', 'instock', 'onsale', 's', 'post_type' ] as $keep ) : ?>
							<?php if ( isset( $_GET[ $keep ] ) && '' !== $_GET[ $keep ] ) : ?><input type="hidden" name="<?php echo esc_attr( $keep ); ?>" value="<?php echo esc_attr( wc_clean( wp_unslash( $_GET[ $keep ] ) ) ); ?>"><?php endif; ?>
						<?php endforeach; ?>
						<?php foreach ( $brands_q as $bslug ) : ?><input type="hidden" name="brand[]" value="<?php echo esc_attr( $bslug ); ?>"><?php endforeach; ?>
						<i class="ic-sort-desc"></i>
						<label for="arc-orderby">مرتب‌سازی:</label>
						<select id="arc-orderby" name="orderby" data-auto-submit>
							<?php foreach ( $sorts as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $orderby, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
						</select>
					</form>
				</div>
			</div>

			<?php if ( $active ) : ?>
				<div class="arc-chips">
					<?php foreach ( $facets['brands'] as $br ) : ?>
						<?php if ( in_array( $br['slug'], $brands_q, true ) ) : ?><a href="<?php echo esc_url( add_query_arg( 'brand', array_values( array_diff( $brands_q, [ $br['slug'] ] ) ), remove_query_arg( [ 'brand', 'paged' ] ) ) ); ?>"><?php echo esc_html( $br['name'] ); ?><i class="ic-close-line"></i></a><?php endif; ?>
					<?php endforeach; ?>
					<?php if ( $min || $max ) : ?><a href="<?php echo esc_url( remove_query_arg( [ 'min_price', 'max_price', 'paged' ] ) ); ?>">قیمت: <?php echo esc_html( ( $min ? aim_t_money( $min ) : '۰' ) . ' تا ' . ( $max ? aim_t_money( $max ) : 'بی‌نهایت' ) ); ?><i class="ic-close-line"></i></a><?php endif; ?>
					<?php if ( $instock ) : ?><a href="<?php echo esc_url( remove_query_arg( [ 'instock', 'paged' ] ) ); ?>">فقط موجود<i class="ic-close-line"></i></a><?php endif; ?>
					<?php if ( $onsale ) : ?><a href="<?php echo esc_url( remove_query_arg( [ 'onsale', 'paged' ] ) ); ?>">فقط تخفیف‌دار<i class="ic-close-line"></i></a><?php endif; ?>
					<a class="is-clear" href="<?php echo esc_url( $base_url ); ?>">حذف همه</a>
				</div>
			<?php endif; ?>

			<?php woocommerce_output_all_notices(); ?>

			<?php if ( have_posts() ) : ?>
				<div class="arc-grid">
					<?php while ( have_posts() ) : the_post(); ?>
						<?php $p = wc_get_product( get_the_ID() ); ?>
						<?php if ( $p ) { echo aim_t_card( $p ); } // phpcs:ignore ?>
					<?php endwhile; ?>
				</div>
				<?php
				$links = paginate_links( [
					'total'     => (int) $wp_query->max_num_pages,
					'current'   => $paged,
					'type'      => 'array',
					'prev_text' => '<i class="ic-arrow-right-s-line"></i>',
					'next_text' => '<i class="ic-arrow-left-s-line"></i>',
				] );
				if ( $links ) {
					echo '<nav class="arc-pages" aria-label="صفحه‌ها">';
					foreach ( $links as $l ) {
						echo aim_t_fa( $l ); // phpcs:ignore -- core-generated markup; only digits are swapped.
					}
					echo '</nav>';
				}
				?>
			<?php else : ?>
				<div class="arc-empty">
					<i class="ic-search-eye-line"></i>
					<h2><?php echo esc_html( aim_t_opt( 's_empty_title' ) ); ?></h2>
					<p><?php echo esc_html( $active || is_search() ? aim_t_opt( 's_empty_text' ) : 'به‌زودی محصولات این بخش اضافه می‌شوند.' ); ?></p>
					<a class="pc-btn" href="<?php echo esc_url( $shop_url ); ?>">مشاهدهٔ همهٔ محصولات</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
get_footer();
