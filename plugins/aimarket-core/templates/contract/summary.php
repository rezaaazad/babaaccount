<?php
/**
 * Plan cards above the draft in the checkout window: what exactly is being bought.
 *
 * @var array $data AIM_Contract::from_cart().
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="aimc-summary">
	<?php foreach ( $data['items'] as $item ) : ?>
		<section class="aimc-sum-item">
			<b><?php echo esc_html( $item['name'] ); ?></b>
			<dl>
				<?php foreach ( $item['specs'] as [ $label, $value ] ) : ?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		</section>
	<?php endforeach; ?>
</div>
