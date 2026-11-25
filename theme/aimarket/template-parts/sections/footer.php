<?php
/**
 * Site footer: contact bar, feature row, link columns + discount card, about text,
 * trust seals, copyright. Data: aim_t_footer_args() (panel → تنظیمات قالب → فوتر).
 *
 * @var array $args
 */
defined( 'ABSPATH' ) || exit;
$a = $args;
?>
<footer class="sf-foot">
	<div class="container">

		<div class="ft-bar">
			<a class="ft-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php if ( $a['logo'] ) : ?>
					<img src="<?php echo esc_url( $a['logo'] ); ?>" alt="<?php echo esc_attr( $a['brand'] ); ?>">
				<?php else : ?>
					<?php echo aim_t_brand_mark( 'ft-logo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="ft-name"><b><?php echo aim_t_brand_name( $a['brand'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by aim_t_brand_name(). ?></b><?php if ( $a['brand_sub'] ) : ?><small><?php echo esc_html( $a['brand_sub'] ); ?></small><?php endif; ?></span>
				<?php endif; ?>
			</a>
			<div class="ft-contact">
				<a class="ft-pill is-main" href="<?php echo esc_url( $a['ticket']['url'] ); ?>">
					<span class="ft-pill-ico"><i class="ic-chat-3-line"></i></span>
					<span><small><?php echo esc_html( $a['ticket']['label'] ); ?></small><b><?php echo esc_html( $a['ticket']['text'] ); ?></b></span>
				</a>
				<?php if ( $a['telegram'] ) : ?>
					<a class="ft-pill" href="<?php echo esc_url( $a['telegram']['url'] ); ?>" target="_blank" rel="noopener">
						<span class="ft-pill-ico"><?php echo aim_t_social_icon( 'telegram' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
						<span><small><?php echo esc_html( $a['telegram']['label'] ); ?></small><b dir="ltr"><?php echo esc_html( $a['telegram']['id'] ); ?></b></span>
					</a>
				<?php endif; ?>
				<?php if ( $a['phone'] ) : ?>
					<a class="ft-phone" href="<?php echo esc_url( $a['phone']['url'] ); ?>">
						<span class="ft-pill-ico"><i class="ic-phone-line"></i></span>
						<span><small><?php echo esc_html( $a['phone']['label'] ); ?></small><b dir="ltr"><?php echo esc_html( $a['phone']['text'] ); ?></b></span>
					</a>
				<?php endif; ?>
			</div>
			<?php if ( $a['backtop'] ) : ?>
				<button type="button" class="ft-top" data-backtop aria-label="بازگشت به بالا"><span>بازگشت به بالا</span><i class="ic-arrow-down-s-line"></i></button>
			<?php endif; ?>
		</div>

		<?php if ( $a['features'] ) : ?>
			<ul class="ft-feats">
				<?php foreach ( $a['features'] as $f ) : ?>
					<li><span class="ft-feat-ico"><i class="ic-<?php echo esc_attr( $f['icon'] ); ?>"></i></span><span><b><?php echo esc_html( $f['title'] ); ?></b><?php if ( $f['text'] ) : ?><small><?php echo esc_html( $f['text'] ); ?></small><?php endif; ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="ft-main<?php echo $a['news'] || $a['socials'] ? '' : ' no-card'; ?>">
			<div class="ft-cols">
				<?php foreach ( $a['columns'] as $col ) : ?>
					<details class="ft-col" open data-ft-col>
						<summary><?php echo esc_html( $col['title'] ); ?><i class="ic-arrow-down-s-line"></i></summary>
						<ul>
							<?php foreach ( $col['links'] as $l ) : ?>
								<li><?php if ( $l['url'] ) : ?><a href="<?php echo esc_url( $l['url'] ); ?>" dir="auto"><?php echo esc_html( $l['text'] ); ?></a><?php else : ?><span dir="auto"><?php echo esc_html( $l['text'] ); ?></span><?php endif; ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endforeach; ?>
			</div>

			<?php if ( $a['news'] || $a['socials'] ) : ?>
				<div class="ft-card">
					<?php if ( $a['news'] ) : ?>
						<div class="ft-card-head"><span><i class="ic-coupon-3-line"></i></span><h3><?php echo esc_html( $a['news']['title'] ); ?></h3></div>
						<?php if ( $a['news']['text'] ) : ?><p><?php echo esc_html( $a['news']['text'] ); ?></p><?php endif; ?>
						<form class="ft-news" data-newsletter>
							<label class="visually-hidden" for="nl-phone">شمارهٔ موبایل</label>
							<input id="nl-phone" type="tel" inputmode="numeric" name="phone" placeholder="<?php echo esc_attr( $a['news']['placeholder'] ); ?>" required>
							<button type="submit"><?php echo esc_html( $a['news']['button'] ); ?></button>
						</form>
						<small class="foot-news-msg" data-newsletter-msg></small>
					<?php endif; ?>
					<?php if ( $a['socials'] ) : ?>
						<div class="ft-social">
							<?php if ( $a['social_t'] ) : ?><span class="ft-social-title"><?php echo esc_html( $a['social_t'] ); ?></span><?php endif; ?>
							<div class="ft-social-grid">
								<?php $nets = aim_t_socials(); ?>
								<?php foreach ( $a['socials'] as $so ) : ?>
									<a href="<?php echo esc_url( $so['url'] ); ?>" target="_blank" rel="noopener"><?php echo aim_t_social_icon( $so['net'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $so['label'] ?: ( $nets[ $so['net'] ][0] ?? 'لینک' ) ); ?></a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $a['about'] || $a['badges'] ) : ?>
			<div class="ft-trust">
				<?php if ( $a['about'] ) : ?><p class="ft-about"><?php echo esc_html( $a['about'] ); ?></p><?php endif; ?>
				<?php if ( $a['badges'] ) : ?>
					<ul class="ft-badges">
						<?php foreach ( $a['badges'] as $b ) : ?>
							<li>
								<span class="ft-badge">
									<?php if ( ! empty( $b['code'] ) ) : ?>
										<?php echo $b['code']; // phpcs:ignore WordPress.Security.EscapeOutput -- trust-seal snippet entered by an administrator (sanitised on save for users without unfiltered_html). ?>
									<?php elseif ( ! empty( $b['url'] ) ) : ?>
										<a href="<?php echo esc_url( $b['url'] ); ?>" target="_blank" rel="noopener" referrerpolicy="origin"><img src="<?php echo esc_url( $b['image'] ); ?>" alt="<?php echo esc_attr( $b['title'] ); ?>" loading="lazy"></a>
									<?php else : ?>
										<img src="<?php echo esc_url( $b['image'] ); ?>" alt="<?php echo esc_attr( $b['title'] ); ?>" loading="lazy">
									<?php endif; ?>
								</span>
								<?php if ( $b['title'] ) : ?><small><?php echo esc_html( $b['title'] ); ?></small><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="ft-copy">
			<span><?php echo esc_html( $a['copyright'] ); ?></span>
			<?php if ( $a['email'] ) : ?><a href="mailto:<?php echo esc_attr( $a['email'] ); ?>" dir="ltr"><i class="ic-mail-line"></i><?php echo esc_html( $a['email'] ); ?></a><?php endif; ?>
		</div>
	</div>
</footer>
