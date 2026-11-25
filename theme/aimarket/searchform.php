<?php defined( 'ABSPATH' ) || exit; ?>
<form role="search" method="get" class="sf-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<i class="ic-search-line" aria-hidden="true"></i>
	<label class="visually-hidden" for="sf-s-<?php echo esc_attr( wp_unique_id() ); ?>">جست‌وجو</label>
	<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="جست‌وجو در سرویس‌ها و مقاله‌ها…">
	<button type="submit">جست‌وجو</button>
</form>
