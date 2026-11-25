<?php
defined( 'ABSPATH' ) || exit;
?>
</main>

<?php
// The footer is an Elementor template when one is chosen, else the built-in one.
if ( ! aim_t_elementor_part( 'footer' ) ) {
	aim_t_section( 'footer' );
}
?>

<div class="toast-stack" data-toasts aria-live="assertive"></div>
<?php wp_footer(); ?>
</body>
</html>
