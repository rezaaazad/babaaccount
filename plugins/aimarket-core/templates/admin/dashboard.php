<?php
/** Panel home: the store dashboard with WooCommerce, the site dashboard without it. */
defined( 'ABSPATH' ) || exit;
aim_template( class_exists( 'WooCommerce' ) ? 'admin/dashboard-store' : 'admin/dashboard-site', [ 'item' => $item ?? null ] );
