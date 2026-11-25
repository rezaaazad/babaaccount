<?php
/** Appearance → «راه‌اندازی هوش‌مارکت» (inc/setup.php); assets/js/setup.js runs the steps. */
defined( 'ABSPATH' ) || exit;
$status = aim_t_requirement_status();
$labels = [ 'active' => 'فعال', 'installed' => 'نصب شده، غیرفعال', 'missing' => 'نصب نیست' ];
$ready  = ! array_diff( $status, [ 'active' ] ) && get_option( 'aim_theme_setup_done' );
$log    = (array) get_option( 'aim_theme_setup_log', [] ); // Results of the install on theme activation.
$bundled    = aim_t_bundled_status(); // Plugins shipped inside the theme follow it automatically; shown here for peace of mind.
$stale      = in_array( 'stale', array_column( $bundled, 'state' ), true );
$b_missing  = in_array( 'missing', array_column( $bundled, 'state' ), true );
$b_state    = $stale ? 'is-stale' : ( $b_missing ? 'is-missing' : 'is-ok' );
$b_pill     = $stale ? 'همگام‌سازی لازم است' : ( $b_missing ? 'نصب نیست' : 'هم‌نسخهٔ قالب' );
?>
<div class="aimt-setup" data-theme-setup data-panel="<?php echo esc_url( admin_url( 'admin.php?page=aimarket' ) ); ?>">
        <div class="aimt-glow" aria-hidden="true"><i></i><i></i></div>
        <div class="aimt-card">
                <div class="aimt-art"><?php echo aim_t_setup_rocket(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></div>
                <?php if ( $ready ) : ?>
                        <h1>سلام! خوش برگشتید</h1>
                        <p class="aimt-lead">قالب هوش‌مارکت قبلاً کامل راه‌اندازی شده است. اگر چیزی جا افتاده یا افزونه‌ای را پاک کرده‌اید، دوباره راه‌اندازی کنید؛ محتوای شما پاک نمی‌شود.</p>
                <?php else : ?>
                        <h1>راه‌اندازی قالب هوش‌مارکت</h1>
                        <p class="aimt-lead">با یک دکمه، افزونه‌های لازم نصب و فعال می‌شوند، فروشگاه برای فروش اکانت تنظیم می‌شود و صفحهٔ اصلی، تماس با ما، سربرگ و پابرگ با المنتور ساخته می‌شوند.</p>
                <?php endif; ?>

                <ul class="aimt-reqs">
                        <?php foreach ( aim_t_requirements() as $key => $r ) : ?>
                                <li class="is-<?php echo esc_attr( $status[ $key ] ); ?>" data-req="<?php echo esc_attr( $key ); ?>">
                                        <i class="<?php echo 'active' === $status[ $key ] ? 'ic-checkbox-circle-fill' : ( 'missing' === $status[ $key ] ? 'ic-error-warning-fill' : 'ic-alert-fill' ); ?>" aria-hidden="true"></i>
                                        <div><b><?php echo esc_html( $r['label'] ); ?></b><small data-msg<?php echo 'active' !== $status[ $key ] && ! empty( $log[ $key ] ) ? ' data-err' : ''; ?>><?php echo esc_html( 'active' !== $status[ $key ] && ! empty( $log[ $key ] ) ? $log[ $key ] : $r['text'] ); ?></small></div>
                                        <span class="aimt-pill" data-pill><?php echo esc_html( $labels[ $status[ $key ] ] ); ?></span>
                                </li>
                        <?php endforeach; ?>
                        <li class="is-<?php echo $ready ? 'active' : 'pending'; ?>" data-req="configure">
                                <i class="<?php echo $ready ? 'ic-checkbox-circle-fill' : 'ic-circle-line'; ?>" aria-hidden="true"></i>
                                <div><b>تنظیم فروشگاه و پنل</b><small data-msg>تومان، کشور ایران، کالای دیجیتال بدون ارسال، منوی پنل و زمان ایران.</small></div>
                                <span class="aimt-pill" data-pill><?php echo $ready ? 'انجام شد' : 'در انتظار'; ?></span>
                        </li>
                        <li class="is-<?php echo $ready ? 'active' : 'pending'; ?>" data-req="content">
                                <i class="<?php echo $ready ? 'ic-checkbox-circle-fill' : 'ic-circle-line'; ?>" aria-hidden="true"></i>
                                <div><b>ساخت صفحه‌ها</b><small data-msg>صفحهٔ اصلی، تماس با ما، سربرگ و پابرگ قابل ویرایش با المنتور.</small></div>
                                <span class="aimt-pill" data-pill><?php echo $ready ? 'انجام شد' : 'در انتظار'; ?></span>
                        </li>
                </ul>

                <div class="aimt-bundled <?php echo esc_attr( $b_state ); ?>" data-bundled>
                        <div class="aimt-bundled-head">
                                <b>افزونه‌های همراه قالب</b>
                                <span class="aimt-pill" data-bundled-pill><?php echo esc_html( $b_pill ); ?></span>
                        </div>
                        <ul>
                                <?php foreach ( $bundled as $key => $b ) : ?>
                                        <li data-plugin="<?php echo esc_attr( $key ); ?>">
                                                <b><?php echo esc_html( $b['label'] ); ?></b>
                                                <small data-ver><?php echo null === $b['installed'] ? 'هنوز نصب نشده · همراه قالب ' . esc_html( $b['shipped'] ) : 'نصب‌شده: ' . esc_html( $b['installed'] ) . ' · همراه قالب: ' . esc_html( $b['shipped'] ); ?></small>
                                        </li>
                                <?php endforeach; ?>
                        </ul>
                        <small class="aimt-bundled-msg" data-bundled-msg hidden></small>
                        <button type="button" class="aimt-btn aimt-ghost" data-sync<?php echo $stale ? '' : ' hidden'; ?>>همگام‌سازی افزونه‌های همراه</button>
                </div>

                <?php if ( 'missing' === $status['woocommerce'] ) : ?>
                        <p class="aimt-alert"><i class="ic-error-warning-fill" aria-hidden="true"></i><span><b>ووکامرس نصب نیست.</b> این قالب یک فروشگاه است و بدون ووکامرس سایت به‌جای فروشگاه صفحهٔ «در حال راه‌اندازی» نشان می‌دهد. با دکمهٔ زیر خودکار از مخزن وردپرس نصب می‌شود.</span></p>
                <?php endif; ?>

                <div class="aimt-progress"><i data-progress></i></div>
                <div class="aimt-actions">
                        <button type="button" class="aimt-btn aimt-primary" data-run><?php echo $ready ? 'راه‌اندازی دوباره' : 'نصب و راه‌اندازی خودکار'; ?></button>
                        <a class="aimt-btn aimt-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=aimarket' ) ); ?>" data-go<?php echo $ready ? '' : ' hidden'; ?>>رفتن به پنل مدیریت</a>
                </div>
        </div>
</div>
