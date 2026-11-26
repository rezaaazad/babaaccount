<?php
defined( 'ABSPATH' ) || exit;

function aim_t_cache_key( $name, $args ) {
	return 'aim_t_' . $name . '_' . md5( wp_json_encode( [ get_option( 'aim_t_catalog_version', '1' ), $args, get_locale() ] ) );
}

function aim_t_bump_catalog_cache() {
	update_option( 'aim_t_catalog_version', wp_generate_uuid4(), false );
}

foreach ( [ 'save_post_product', 'woocommerce_update_product', 'woocommerce_new_product', 'edited_product_cat', 'created_product_cat', 'delete_product_cat', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock' ] as $hook ) {
	add_action( $hook, 'aim_t_bump_catalog_cache' );
}
add_action( 'deleted_post', 'aim_t_bump_catalog_cache' );

function aim_t_cached_products( $args ) {
	$key = aim_t_cache_key( 'products', $args );
	$ids = get_transient( $key );
	if ( false === $ids ) {
		$ids = wc_get_products( $args + [ 'return' => 'ids' ] );
		set_transient( $key, $ids, 6 * HOUR_IN_SECONDS );
	}
	return array_values( array_filter( array_map( 'wc_get_product', $ids ) ) );
}

function aim_t_cached_terms( $args ) {
	$key = aim_t_cache_key( 'terms', $args );
	$ids = get_transient( $key );
	if ( false === $ids ) {
		$ids = get_terms( $args + [ 'fields' => 'ids' ] );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
		set_transient( $key, $ids, 6 * HOUR_IN_SECONDS );
	}
	return array_values( array_filter( array_map( 'get_term', $ids ), static fn( $term ) => $term instanceof WP_Term ) );
}
