<?php
/** Checkout window that shows the draft contract (filled by assets/js/contract.js). */
defined( 'ABSPATH' ) || exit;
?>
<div class="aimc-modal" data-contract-modal hidden>
	<div class="aimc-dialog" role="dialog" aria-modal="true" aria-labelledby="aimc-title">
		<div class="aimc-modal-head">
			<b id="aimc-title">قرارداد سفارش</b>
			<button type="button" class="aimc-x" data-contract-close aria-label="بستن"><?php echo aim_icon( 'close-line' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</div>
		<div class="aimc-modal-body" data-contract-body tabindex="-1"></div>
		<div class="aimc-modal-foot">
			<button type="button" class="aimc-btn is-primary" data-contract-agree>می‌پذیرم</button>
			<button type="button" class="aimc-btn" data-contract-print><?php echo aim_icon( 'file-copy-line' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>چاپ / ذخیرهٔ PDF</button>
			<button type="button" class="aimc-btn is-ghost" data-contract-close>بستن</button>
		</div>
	</div>
</div>
