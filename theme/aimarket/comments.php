<?php
/**
 * Comments on posts and pages (product reviews use their own template).
 */
defined( 'ABSPATH' ) || exit;
if ( post_password_required() ) {
	return;
}
$count = (int) get_comments_number();
?>
<section class="cm" id="comments">
	<h2 class="cm-title">دیدگاه‌ها <small><?php echo esc_html( aim_t_fa( $count ) ); ?></small></h2>
	<?php if ( have_comments() ) : ?>
		<ol class="cm-list">
			<?php
			wp_list_comments( [
				'style'       => 'ol',
				'avatar_size' => 0,
				'callback'    => function ( $c, $args, $depth ) {
					?>
					<li <?php comment_class( 'cm-item' ); ?> id="comment-<?php comment_ID(); ?>">
						<header>
							<span class="avatar-sm"><?php echo esc_html( mb_substr( get_comment_author( $c ) ?: '؟', 0, 1 ) ); ?></span>
							<div><b><?php echo esc_html( get_comment_author( $c ) ); ?></b><small><?php echo esc_html( function_exists( 'aim_jdate' ) ? aim_jdate( $c->comment_date_gmt ) : get_comment_date( '', $c ) ); ?></small></div>
						</header>
						<?php if ( '0' === $c->comment_approved ) : ?><p class="cm-wait">دیدگاه شما پس از بررسی نمایش داده می‌شود.</p><?php endif; ?>
						<div class="cm-text"><?php comment_text(); ?></div>
						<?php comment_reply_link( array_merge( $args, [ 'depth' => $depth, 'reply_text' => 'پاسخ', 'before' => '<div class="cm-reply">', 'after' => '</div>' ] ) ); ?>
					<?php
				},
			] );
			?>
		</ol>
		<?php the_comments_pagination( [ 'prev_text' => '<i class="ic-arrow-right-s-line"></i>', 'next_text' => '<i class="ic-arrow-left-s-line"></i>' ] ); ?>
	<?php endif; ?>
	<?php if ( comments_open() ) : ?>
		<div class="rv-form">
			<?php
			comment_form( [
				'title_reply'          => 'دیدگاهت را بنویس',
				'title_reply_to'       => 'پاسخ به %s',
				'cancel_reply_link'    => 'انصراف',
				'title_reply_before'   => '<h3 class="rv-form-title">',
				'title_reply_after'    => '</h3>',
				'label_submit'         => 'ارسال دیدگاه',
				'class_submit'         => 'pc-btn',
				'comment_notes_before' => '<p class="rv-login">نشانی ایمیلت منتشر نمی‌شود.</p>',
				'logged_in_as'         => '',
				'must_log_in'          => '<p class="rv-login">برای نوشتن دیدگاه <a href="' . esc_url( aim_theme_account_url() ) . '">وارد شو</a>.</p>',
				'fields'               => [
					'author'  => '<p class="comment-form-author"><label for="author">نام</label><input id="author" name="author" type="text" required></p>',
					'email'   => '<p class="comment-form-email"><label for="email">ایمیل</label><input id="email" name="email" type="email" required></p>',
					'cookies' => '',
				],
				'comment_field'        => '<p class="comment-form-comment"><label for="comment">دیدگاه</label><textarea id="comment" name="comment" rows="5" required></textarea></p>',
			] );
			?>
		</div>
	<?php endif; ?>
</section>
