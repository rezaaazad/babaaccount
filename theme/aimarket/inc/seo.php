<?php
defined( 'ABSPATH' ) || exit;

add_action( 'init', static function () {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
		return;
	}
	add_filter( 'document_title_parts', static function ( $parts ) {
		if ( is_front_page() ) {
			$parts['tagline'] = get_bloginfo( 'description' ) ?: 'فروشگاه اکانت‌های هوش مصنوعی';
		}
		return $parts;
	} );
	add_filter( 'wp_robots', static function ( $robots ) {
		if ( is_search() || is_page( [ 'login', 'panel', 'cart', 'checkout', 'my-account' ] ) || ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) && array_intersect( array_keys( $_GET ), [ 'orderby', 'min_price', 'max_price', 'instock', 'onsale' ] ) ) ) {
			$robots['noindex'] = true;
			$robots['follow'] = true;
		}
		return $robots;
	} );
	add_action( 'wp_head', 'aim_t_seo_meta', 5 );
	add_filter( 'wp_sitemaps_posts_query_args', static function ( $args, $type ) {
		if ( 'page' === $type ) {
			$exclude = (array) ( $args['post__not_in'] ?? [] );
			foreach ( [ 'sample-page', 'cart', 'checkout', 'my-account', 'login' ] as $slug ) {
				$page = get_page_by_path( $slug );
				if ( $page ) {
					$exclude[] = $page->ID;
				}
			}
			$args['post__not_in'] = $exclude;
		}
		return $args;
	}, 10, 2 );
} );

function aim_t_seo_meta() {
	$description = get_bloginfo( 'description' );
	if ( is_singular() && ! is_front_page() ) {
		$description = get_the_excerpt();
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$description = term_description();
	}
	$description = wp_html_excerpt( wp_strip_all_tags( $description ?: 'فروشگاه اکانت‌های هوش مصنوعی' ), 155, '' );
	$url = is_singular() ? get_permalink() : get_pagenum_link( max( 1, get_query_var( 'paged' ) ), false );
	$url = remove_query_arg( [ 'orderby', 'min_price', 'max_price', 'instock', 'onsale', 'rating_filter' ], $url );
	if ( ! is_singular() ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
	$title = wp_get_document_title();
	echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	$properties = [ 'title' => $title, 'description' => $description, 'url' => $url, 'type' => is_singular( 'post' ) ? 'article' : 'website', 'site_name' => get_bloginfo( 'name' ), 'locale' => 'fa_IR' ];
	$image = is_singular() ? get_the_post_thumbnail_url( get_queried_object_id(), 'large' ) : false;
	if ( ! $image ) {
		$image = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
	}
	if ( $image ) {
		$properties['image'] = $image;
	}
	foreach ( $properties as $key => $value ) {
		echo '<meta property="og:' . esc_attr( $key ) . '" content="' . esc_attr( $value ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	foreach ( [ 'title', 'description', 'image' ] as $key ) {
		if ( isset( $properties[ $key ] ) ) {
			echo '<meta name="twitter:' . esc_attr( $key ) . '" content="' . esc_attr( $properties[ $key ] ) . '">' . "\n";
		}
	}
	$schema = [];
	if ( is_front_page() ) {
		$schema[] = [ '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'logo' => $image ?: null ];
		$schema[] = [ '@type' => 'WebSite', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'potentialAction' => [ '@type' => 'SearchAction', 'target' => home_url( '/?s={search_term_string}' ), 'query-input' => 'required name=search_term_string' ] ];
	} elseif ( is_singular( 'post' ) ) {
		$schema[] = [ '@type' => 'Article', 'headline' => get_the_title(), 'description' => $description, 'datePublished' => get_the_date( DATE_W3C ), 'dateModified' => get_the_modified_date( DATE_W3C ), 'mainEntityOfPage' => $url, 'image' => $image ?: null ];
	}
	if ( $schema ) {
		echo '<script type="application/ld+json">' . wp_json_encode( [ '@context' => 'https://schema.org', '@graph' => $schema ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
	}
}
