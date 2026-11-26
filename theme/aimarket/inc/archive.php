<?php
/**
 * Shop sidebar (woocommerce/archive-product.php): category tree, brand list
 * and the price range of the products in view, plus the ?brand[] filter.
 */
defined( 'ABSPATH' ) || exit;

const AIM_T_SIDE_CATS = 8; // top-level categories shown before «همهٔ دسته‌ها»

/** Brand taxonomy: WooCommerce's own (9.6+), else a "brand" product attribute. */
function aim_t_brand_tax() {
	if ( taxonomy_exists( 'product_brand' ) ) {
		return 'product_brand';
	}
	return taxonomy_exists( 'pa_brand' ) ? 'pa_brand' : '';
}

/**
 * Brands (with how many products in view have each) and the lowest/highest
 * price, for the category or search being shown — before the sidebar's own filters.
 *
 * @return array{ brands: array[], min: float, max: float }
 */
function aim_t_archive_facets( $term = null, $search = '' ) {
	global $wpdb;
	$key = aim_t_cache_key( 'facets', [ $term instanceof WP_Term ? $term->term_id : 0, $search ] );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$args = [ 'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 2000, 'no_found_rows' => true ];
	if ( $term instanceof WP_Term ) {
		$args['tax_query'] = [ [ 'taxonomy' => $term->taxonomy, 'terms' => $term->term_id ] ]; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	$ids = ( new WP_Query( $args ) )->posts;
	$out = [ 'brands' => [], 'min' => 0, 'max' => 0 ];
	if ( ! $ids ) {
		set_transient( $key, $out, 6 * HOUR_IN_SECONDS );
		return $out;
	}

	$tax = aim_t_brand_tax();
	if ( $tax ) {
		$counts = [];
		foreach ( (array) wp_get_object_terms( $ids, $tax, [ 'fields' => 'all_with_object_id' ] ) as $t ) {
			$counts[ $t->slug ] ??= [ 'slug' => $t->slug, 'name' => $t->name, 'count' => 0 ];
			++$counts[ $t->slug ]['count'];
		}
		uasort( $counts, fn( $x, $y ) => $y['count'] <=> $x['count'] ?: strcmp( $x['name'], $y['name'] ) );
		$out['brands'] = array_values( $counts );
	}

	$in    = implode( ',', array_map( 'absint', $ids ) );
	$range = $wpdb->get_row( "SELECT MIN(min_price) AS lo, MAX(max_price) AS hi FROM {$wpdb->wc_product_meta_lookup} WHERE product_id IN ($in) AND max_price > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- ids are integers.
	if ( $range ) {
		$out['min'] = (float) $range->lo;
		$out['max'] = (float) $range->hi;
	}
	set_transient( $key, $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

/** «دسته‌بندی‌ها» card: all products, top-level categories, and the open branch's children. */
function aim_t_archive_cat_tree( $term = null ) {
	$exclude = [ (int) get_option( 'default_product_cat' ) ];
	$tops    = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => $exclude ] );
	if ( is_wp_error( $tops ) || ! $tops ) {
		return;
	}
	$current = $term instanceof WP_Term && 'product_cat' === $term->taxonomy ? $term : null;
	$branch  = $current ? array_merge( [ $current->term_id ], get_ancestors( $current->term_id, 'product_cat' ) ) : [];
	$row     = function ( WP_Term $t, $class = '' ) use ( $current ) {
		$on = $current && $current->term_id === $t->term_id;
		printf(
			'<a class="side-cat%s%s" href="%s"%s><span class="side-ico"><i class="ic-%s"></i></span><span class="side-name">%s</span><em>%s</em></a>',
			$on ? ' is-active' : '',
			$class ? ' ' . esc_attr( $class ) : '',
			esc_url( get_term_link( $t ) ),
			$on ? ' aria-current="page"' : '',
			esc_attr( aim_t_cat_icon( $t ) ),
			esc_html( $t->name ),
			esc_html( aim_t_fa( $t->count ) )
		);
	};
	$hidden = 0;
	?>
	<nav class="side-card side-cats" aria-label="دسته‌بندی‌ها" data-side-cats>
		<h2 class="side-title">دسته‌بندی‌ها</h2>
		<a class="side-cat<?php echo $current ? '' : ' is-active'; ?>" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><span class="side-ico"><i class="ic-stack-line"></i></span><span class="side-name">همهٔ محصولات</span></a>
		<?php foreach ( $tops as $i => $top ) : ?>
			<?php
			$open = in_array( $top->term_id, $branch, true );
			$more = $i >= AIM_T_SIDE_CATS && ! $open;
			$hidden += (int) $more;
			echo $more ? '<div class="side-more" hidden>' : '<div>';
			$row( $top, $open ? 'is-open' : '' );
			if ( $open ) {
				$kids = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $top->term_id ] );
				if ( ! is_wp_error( $kids ) && $kids ) {
					echo '<div class="side-sub">';
					foreach ( $kids as $kid ) {
						$row( $kid, in_array( $kid->term_id, $branch, true ) && $kid->term_id !== $current->term_id ? 'is-open' : '' );
					}
					echo '</div>';
				}
			}
			echo '</div>';
			?>
		<?php endforeach; ?>
		<?php if ( $hidden ) : ?>
			<button type="button" class="side-all" data-side-all aria-expanded="false">همهٔ دسته‌ها (<?php echo esc_html( aim_t_fa( count( $tops ) ) ); ?>)<i class="ic-arrow-down-s-line"></i></button>
		<?php endif; ?>
	</nav>
	<?php
}

// ?brand[]=slug filters the shop like a category.
add_action( 'woocommerce_product_query', function ( WP_Query $q ) {
	$tax = aim_t_brand_tax();
	if ( ! $tax || empty( $_GET['brand'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		return;
	}
	$slugs = array_filter( array_map( 'sanitize_title', (array) wp_unslash( $_GET['brand'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $slugs ) {
		$tq   = (array) $q->get( 'tax_query' );
		$tq[] = [ 'taxonomy' => $tax, 'field' => 'slug', 'terms' => $slugs ];
		$q->set( 'tax_query', $tq );
	}
} );
