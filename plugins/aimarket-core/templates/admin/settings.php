<?php
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
        aim_page_head( 'تنظیمات کلی' );
        echo '<div class="card">';
        aim_empty( 'lock-2-line', 'دسترسی به تنظیمات فقط برای مدیر کل است' );
        echo '</div>';
        return;
}

$s    = AIM_Settings::all();
$code = AIM_Site_Tweaks::code();
$can_code = current_user_can( 'unfiltered_html' ); // CodeMirror for its boxes is loaded by AIM_WP_Admin::assets().

/** Text-like input row. */
$field = function ( $key, $label, $args = [] ) use ( $s ) {
        $args = wp_parse_args( $args, [ 'type' => 'text', 'help' => '', 'placeholder' => '', 'ltr' => false, 'span' => false, 'attrs' => '' ] );
        $id   = 'set-' . $key;
        printf( '<div class="form-row%s"><label class="form-label" for="%s">%s</label>', $args['span'] ? ' span-2' : '', esc_attr( $id ), esc_html( $label ) );
        printf(
                '<input id="%1$s" class="field%2$s" type="%3$s" name="%4$s" value="%5$s" placeholder="%6$s" %7$s>',
                esc_attr( $id ),
                $args['ltr'] ? ' ltr' : '',
                esc_attr( $args['type'] ),
                esc_attr( $key ),
                esc_attr( $s[ $key ] ),
                esc_attr( $args['placeholder'] ),
                $args['attrs'] // phpcs:ignore -- static attribute strings from this file.
        );
        if ( $args['help'] ) {
                echo '<p class="form-help">' . esc_html( $args['help'] ) . '</p>';
        }
        echo '</div>';
};

$switch = function ( $key, $label, $help = '' ) use ( $s ) {
        printf(
                '<div class="form-row span-2"><label class="switch"><input type="checkbox" name="%s" data-bool %s>%s</label>%s</div>',
                esc_attr( $key ),
                checked( (int) $s[ $key ], 1, false ),
                esc_html( $label ),
                $help ? '<p class="form-help">' . esc_html( $help ) . '</p>' : ''
        );
};

aim_page_head( 'تنظیمات فروشگاه' );
?>
<label class="search settings-search"><?php echo aim_icon( 'search-line' ); // phpcs:ignore ?><span class="visually-hidden">جست‌وجو در تنظیمات</span><input class="field" type="search" data-settings-search placeholder="جست‌وجو در همهٔ تنظیمات… (مثلاً پیامک، تخفیف، کد)"></label>
<p class="text-3 settings-search-empty" data-settings-empty hidden>تنظیمی با این عبارت پیدا نشد.</p>
<div data-tabs-root>
<form data-api="settings">
        <nav class="seg-tabs" data-tabs role="tablist">
                <button type="button" class="seg-tab is-active" data-tab="general" role="tab" aria-selected="true"><?php echo aim_icon( 'store-2-line' ); // phpcs:ignore ?>اصلی</button>
                <button type="button" class="seg-tab" data-tab="login" role="tab"><?php echo aim_icon( 'shield-keyhole-line' ); // phpcs:ignore ?>ورود و امنیت</button>
                <button type="button" class="seg-tab" data-tab="sms" role="tab"><?php echo aim_icon( 'message-2-line' ); // phpcs:ignore ?>درگاه پیامک</button>
                <?php do_action( 'aim_settings_tabs' ); // Modules add their own tab buttons here… ?>
                <button type="button" class="seg-tab" data-tab="features" role="tab"><?php echo aim_icon( 'sparkling-2-line' ); // phpcs:ignore ?>امکانات فروشگاه</button>
                <button type="button" class="seg-tab" data-tab="look" role="tab"><?php echo aim_icon( 'layout-masonry-line' ); // phpcs:ignore ?>ظاهر سایت</button>
                <button type="button" class="seg-tab" data-tab="speed" role="tab"><?php echo aim_icon( 'rocket-2-line' ); // phpcs:ignore ?>بهینه‌سازی</button>
                <button type="button" class="seg-tab" data-tab="menu" role="tab"><?php echo aim_icon( 'menu-line' ); // phpcs:ignore ?>منوی پنل</button>
                <?php if ( $can_code ) : ?><button type="button" class="seg-tab" data-tab="code" role="tab"><?php echo aim_icon( 'code-s-slash-line' ); // phpcs:ignore ?>کدهای سفارشی</button><?php endif; ?>
        </nav>

        <section class="card" data-tab-panel="general">
                <div class="card-head"><h2>اطلاعات فروشگاه</h2></div>
                <div class="card-body form-grid">
                        <?php
                        $field( 'shop_title', 'نام سایت / فروشگاه', [ 'placeholder' => 'مثال: هوش‌مارکت', 'help' => 'همان «عنوان سایت» وردپرس است؛ در سربرگ، فوتر، پنل، ایمیل‌ها و پیامک‌ها همین نام نمایش داده می‌شود.' ] );
                        $field( 'support_phone', 'تلفن پشتیبانی', [ 'ltr' => true, 'placeholder' => '021-12345678' ] );
                        $field( 'support_email', 'ایمیل پشتیبانی', [ 'type' => 'email', 'ltr' => true, 'help' => 'هشدار کمبود موجودی به این ایمیل فرستاده می‌شود.' ] );
                        $field( 'response_time', 'ساعات پاسخگویی', [ 'placeholder' => 'مثال: هر روز از ۹ تا ۲۴' ] );
                        ?>
                        <div class="form-row">
                                <label class="form-label" for="set-theme_mode">حالت پیش‌فرض نمایش</label>
                                <select id="set-theme_mode" name="theme_mode">
                                        <?php foreach ( [ 'light' => 'روشن', 'dark' => 'تیره', 'auto' => 'مطابق دستگاه کاربر' ] as $k => $l ) : ?>
                                                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['theme_mode'], $k ); ?>><?php echo esc_html( $l ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                        </div>
                </div>
        </section>

        <section class="card" data-tab-panel="login" hidden>
                <div class="card-head"><h2>ورود با کد یک‌بار مصرف</h2></div>
                <div class="card-body form-grid">
                        <?php
                        $field( 'otp_length', 'تعداد ارقام کد', [ 'type' => 'number', 'attrs' => 'min="4" max="8"' ] );
                        $field( 'otp_ttl', 'اعتبار کد (ثانیه)', [ 'type' => 'number', 'attrs' => 'min="60" max="900"', 'help' => 'پیشنهاد: ۱۲۰ ثانیه' ] );
                        $field( 'otp_resend_wait', 'فاصلهٔ ارسال مجدد (ثانیه)', [ 'type' => 'number', 'attrs' => 'min="30" max="600"' ] );
                        $field( 'otp_max_attempts', 'حداکثر تلاش اشتباه برای هر کد', [ 'type' => 'number', 'attrs' => 'min="3" max="10"' ] );
                        $field( 'otp_hourly_limit', 'حداکثر کد برای هر شماره در ساعت', [ 'type' => 'number', 'attrs' => 'min="3" max="20"', 'help' => 'برای هر IP سه برابر این عدد مجاز است.' ] );
                        $switch( 'password_login', 'ورود با گذرواژه هم فعال باشد', 'کاربرانی که از پروفایل خود گذرواژه تعیین کرده‌اند، می‌توانند بدون پیامک وارد شوند.' );
                        ?>
                </div>
        </section>

        <section class="card" data-tab-panel="sms" hidden>
                <div class="card-head"><h2>درگاه پیامک</h2></div>
                <div class="card-body">
                        <div class="form-row">
                                <span class="form-label">سرویس‌دهنده</span>
                                <div class="radio-cards" role="radiogroup" aria-label="سرویس‌دهندهٔ پیامک">
                                        <?php foreach ( AIM_SMS::providers() as $k => $p ) : ?>
                                                <label class="radio-card"><input type="radio" name="sms_driver" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['sms_driver'], $k ); ?>><?php echo esc_html( $p['label'] ); ?></label>
                                        <?php endforeach; ?>
                                </div>
                                <p class="form-help">هر درگاه کلیدها و کدهای الگوی خودش را جدا نگه می‌دارد؛ با تغییر درگاه چیزی پاک نمی‌شود و همهٔ درگاه‌ها هم‌زمان ذخیره می‌مانند.</p>
                        </div>
                        <?php
                        $vars_help = [
                                'otp'         => 'code',
                                'alert_stock' => 'product',
                                'alert_sale'  => 'product',
                                'delivery'    => 'order، count',
                                'reminder'    => 'service، days',
                        ];
                        foreach ( AIM_SMS::providers() as $driver => $p ) :
                                $is_log = empty( $p['creds'] );
                                ?>
                                <div class="sms-provider" data-sms-provider="<?php echo esc_attr( $driver ); ?>" <?php echo $driver === $s['sms_driver'] ? '' : 'hidden'; ?>>
                                        <?php if ( $is_log ) : ?>
                                                <div class="notice inline"><?php echo aim_icon( 'flask-line' ); // phpcs:ignore ?><p><?php echo esc_html( $p['note'] ); ?></p></div>
                                        <?php else : ?>
                                                <div class="form-grid">
                                                        <?php foreach ( $p['creds'] as $cred => $meta ) : ?>
                                                                <?php $field( AIM_SMS::cred_key( $driver, $cred ), $meta['label'], [ 'ltr' => true, 'type' => $meta['type'] ?? 'text', 'help' => $meta['help'], 'attrs' => 'autocomplete="off"' ] ); ?>
                                                        <?php endforeach; ?>
                                                </div>
                                                <div class="form-row span-2">
                                                        <span class="form-label"><?php echo esc_html( $p['pattern_label'] ); ?>ها</span>
                                                        <p class="form-help"><?php echo esc_html( $p['pattern_help'] ); ?></p>
                                                </div>
                                                <div class="form-grid">
                                                        <?php foreach ( AIM_SMS::events() as $event => $label ) : ?>
                                                                <?php $field( AIM_SMS::pattern_key( $driver, $event ), sprintf( '%1$s «%2$s»', $p['pattern_label'], $label ), [ 'ltr' => true, 'help' => isset( $vars_help[ $event ] ) ? 'متغیرها: ' . $vars_help[ $event ] : '' ] ); ?>
                                                        <?php endforeach; ?>
                                                </div>
                                                <div class="notice inline"><?php echo aim_icon( 'book-2-line' ); // phpcs:ignore ?><p>ساخت الگوها داخل پنل <?php echo esc_html( $p['label'] ); ?> انجام می‌شود؛ <a href="<?php echo esc_url( $p['docs'] ); ?>" target="_blank" rel="noopener noreferrer">مستندات وب‌سرویس <?php echo esc_html( $p['label'] ); ?></a></p></div>
                                        <?php endif; ?>
                                </div>
                        <?php endforeach; ?>
                </div>
                <div class="card-body" style="border-top:1px solid var(--border);display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                        <div style="flex:1;min-width:200px"><label class="form-label" for="sms-test">ارسال آزمایشی (پس از ذخیره)</label><input id="sms-test" class="field ltr" type="tel" inputmode="numeric" placeholder="09123456789" form="sms-test-form" name="phone"></div>
                        <button class="btn btn-light" form="sms-test-form" type="submit"><?php echo aim_icon( 'send-plane-line' ); // phpcs:ignore ?>ارسال پیامک آزمایشی</button>
                </div>
        </section>

        <?php do_action( 'aim_settings_panels', $field, $switch, $s ); // …and their tab panels (inside this form, saved with it). ?>

        <section class="card" data-tab-panel="features" hidden>
                <div class="card-head"><h2>موجودی</h2></div>
                <div class="card-body form-grid">
                        <?php $field( 'low_stock_threshold', 'هشدار موجودی کم از', [ 'type' => 'number', 'attrs' => 'min="0" max="100"', 'help' => 'وقتی موجودی یک محصول یا پلن به این عدد برسد، در پیشخوان هشدار داده می‌شود.' ] ); ?>
                </div>
                <div class="card-head" style="border-top:1px solid var(--border)"><h2>اعلان موجودی و تخفیف</h2></div>
                <div class="card-body form-grid">
                        <?php $switch( 'alerts_enabled', 'دکمهٔ «خبرم کن» در صفحهٔ محصول نمایش داده شود', 'روی محصول ناموجود «وقتی موجود شد خبرم کن» و روی محصول بدون تخفیف «اگر تخفیف خورد خبرم کن». فهرست درخواست‌ها در «اعلان موجودی و تخفیف» است.' ); ?>
                </div>
                <div class="card-head" style="border-top:1px solid var(--border)"><h2>کد تخفیف در صفحهٔ تشکر از خرید</h2></div>
                <div class="card-body form-grid">
                        <?php
                        $field( 'thankyou_coupon', 'کد تخفیف', [ 'ltr' => true, 'placeholder' => 'NEXT10', 'help' => 'کدی که در «کدهای تخفیف» ساخته‌اید. خالی = نمایش داده نشود.' ] );
                        $field( 'thankyou_coupon_text', 'متن کنار کد', [ 'placeholder' => 'کد تخفیف خرید بعدی شما' ] );
                        ?>
                </div>
        </section>

        <section class="card" data-tab-panel="look" hidden>
                <div class="card-head"><h2>ویرایش ظاهر سایت با المنتور</h2></div>
                <div class="card-body">
                        <?php if ( ! did_action( 'elementor/loaded' ) || ! function_exists( 'aim_t_elementor_part_id' ) ) : ?>
                                <div class="notice inline notice-warning" style="margin:0"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>برای ویرایش متن، عکس و ترتیب بخش‌ها، افزونهٔ رایگان «Elementor» را نصب و فعال کنید (پنل ← همهٔ بخش‌های وردپرس ← افزونه‌ها). قالب هوش‌مارکت هم باید فعال باشد.</p></div>
                        <?php else : ?>
                                <?php
                                $home_id   = (int) get_option( 'page_on_front' );
                                // Not the site's style kit ("Default Kit"), which is also an elementor_library post.
                                $templates = get_posts( [ 'post_type' => 'elementor_library', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => [ [ 'key' => '_elementor_template_type', 'value' => 'kit', 'compare' => '!=' ] ] ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
                                $edit      = fn( $id ) => admin_url( 'post.php?post=' . (int) $id . '&action=elementor' );
                                $parts     = [ 'header' => 'سربرگ (هدر)', 'footer' => 'پابرگ (فوتر)' ];
                                ?>
                                <p class="text-2" style="margin-top:0">همهٔ بخش‌های صفحهٔ اصلی، سربرگ و پابرگ ویجت‌های «هوش‌مارکت» در المنتور هستند: متن، عکس، آیکن و لینک هر بخش را عوض کنید، بخش‌ها را جابه‌جا یا حذف کنید، یا بخش تازه اضافه کنید.</p>
                                <div class="look-links">
                                        <?php if ( $home_id && aim_t_is_elementor_page( $home_id ) ) : ?>
                                                <a class="btn btn-primary" href="<?php echo esc_url( $edit( $home_id ) ); ?>" target="_blank" rel="noopener"><?php echo aim_icon( 'edit-line' ); // phpcs:ignore ?>ویرایش صفحهٔ اصلی</a>
                                        <?php endif; ?>
                                        <?php foreach ( $parts as $loc => $label ) : ?>
                                                <?php $pid = aim_t_elementor_part_id( $loc ); ?>
                                                <?php if ( $pid ) : ?><a class="btn btn-light" href="<?php echo esc_url( $edit( $pid ) ); ?>" target="_blank" rel="noopener"><?php echo aim_icon( 'edit-line' ); // phpcs:ignore ?>ویرایش <?php echo esc_html( $label ); ?></a><?php endif; ?>
                                        <?php endforeach; ?>
                                        <button type="button" class="btn btn-light" data-action="site/rebuild-home" data-confirm="یک صفحهٔ اصلی تازه با محتوای پیش‌فرض قالب ساخته و صفحهٔ اول سایت می‌شود. صفحهٔ فعلی پاک نمی‌شود. ادامه می‌دهید؟"><?php echo aim_icon( 'refresh-line' ); // phpcs:ignore ?>ساخت دوبارهٔ صفحهٔ اصلی</button>
                                </div>
                                <div class="form-grid" style="margin-top:18px">
                                        <?php foreach ( $parts as $loc => $label ) : ?>
                                                <div class="form-row">
                                                        <label class="form-label" for="set-el_<?php echo esc_attr( $loc ); ?>">قالب <?php echo esc_html( $label ); ?></label>
                                                        <select id="set-el_<?php echo esc_attr( $loc ); ?>" name="el_<?php echo esc_attr( $loc ); ?>">
                                                                <option value="0" <?php selected( (int) $s[ 'el_' . $loc ], 0 ); ?>>قالب ساخته‌شدهٔ هوش‌مارکت (پیش‌فرض)</option>
                                                                <option value="-1" <?php selected( (int) $s[ 'el_' . $loc ], -1 ); ?>>نسخهٔ ثابت قالب (بدون المنتور)</option>
                                                                <?php foreach ( $templates as $t ) : ?>
                                                                        <?php $tl = get_post_meta( $t->ID, '_aim_location', true ); ?>
                                                                        <?php if ( $tl && $tl !== $loc ) { continue; } // the theme's footer template is not a header, and vice versa ?>
                                                                        <option value="<?php echo esc_attr( $t->ID ); ?>" <?php selected( (int) $s[ 'el_' . $loc ], $t->ID ); ?>><?php echo esc_html( $t->post_title ); ?></option>
                                                                <?php endforeach; ?>
                                                        </select>
                                                        <p class="form-help">قالب‌های المنتور را در پنل ← محتوا ← قالب‌های المنتور مدیریت کنید.</p>
                                                </div>
                                        <?php endforeach; ?>
                                </div>
                        <?php endif; ?>
                </div>
        </section>

        <section class="card" data-tab-panel="speed" hidden>
                <div class="card-head"><h2>بهینه‌سازی سرعت سایت</h2></div>
                <div class="card-body form-grid">
                        <?php
                        $switch( 'opt_disable_emojis', 'غیرفعال کردن اسکریپت ایموجی وردپرس', 'ایموجی‌ها همچنان با فونت دستگاه کاربر نمایش داده می‌شوند.' );
                        $switch( 'opt_disable_embeds', 'غیرفعال کردن Embed وردپرس', 'اگر لینک نوشته‌های سایت‌های دیگر را به‌صورت کارت در مقاله‌ها نمی‌گذارید، خاموشش کنید.' );
                        $switch( 'opt_jquery_migrate', 'حذف jQuery Migrate از سایت', 'اگر افزونهٔ قدیمی دارید و بعد از فعال‌سازی چیزی خراب شد، دوباره خاموشش کنید.' );
                        $switch( 'opt_remove_ver', 'حذف نسخهٔ وردپرس از آدرس فایل‌ها', 'نسخهٔ وردپرس شما در کد صفحه دیده نمی‌شود.' );
                        ?>
                </div>
        </section>

        <div class="save-bar" data-save-bar>
                <button class="btn btn-primary btn-lg" type="submit"><?php echo aim_icon( 'save-3-line' ); // phpcs:ignore ?>ذخیرهٔ تغییرات</button>
        </div>
</form>
<form id="sms-test-form" data-api="sms/test" hidden></form>

<?php $hidden = AIM_Panel_Menu::hidden(); ?>
<form data-api="settings/menu" data-tab-panel="menu" data-reload hidden>
        <section class="card">
                <div class="card-head"><h2>بخش‌های منوی پنل</h2></div>
                <div class="card-body">
                        <p class="text-2" style="margin-top:0">بخش‌هایی را که لازم ندارید خاموش کنید تا از منو برداشته شوند. خود قابلیت پاک نمی‌شود: هر وقت خواستید همین‌جا دوباره روشنش کنید؛ صفحه‌های وردپرسی (مثل کدهای تخفیف و برگه‌ها) از «همهٔ بخش‌های وردپرس» هم باز می‌شوند. پیشخوان، تنظیمات سایت، راه‌اندازی سریع و همهٔ بخش‌های وردپرس همیشه روشن‌اند.</p>
                        <div class="menu-switches">
                                <?php foreach ( AIM_Panel_Menu::admin( true, true ) as $group ) : ?>
                                        <div class="menu-group">
                                                <h3><?php echo esc_html( $group['title'] ); ?></h3>
                                                <?php foreach ( $group['items'] as $item ) : ?>
                                                        <?php foreach ( array_merge( [ $item ], $item['children'] ?? [] ) as $k => $it ) : ?>
                                                                <?php $locked = in_array( $it['key'], AIM_Panel_Menu::LOCKED, true ); ?>
                                                                <label class="switch menu-switch<?php echo $k ? ' is-child' : ''; ?>">
                                                                        <input type="checkbox" name="visible[]" value="<?php echo esc_attr( $it['key'] ); ?>" <?php checked( ! in_array( $it['key'], $hidden, true ) ); ?> <?php disabled( $locked ); ?>>
                                                                        <?php if ( ! $k ) : ?><?php echo aim_icon( $it['icon'] ?? 'circle-line' ); // phpcs:ignore ?><?php endif; ?>
                                                                        <?php echo esc_html( $it['label'] ); ?>
                                                                        <?php if ( ! empty( $it['soon'] ) ) : ?><span class="nav-soon">به‌زودی</span><?php endif; ?>
                                                                        <?php if ( ! $k && ! empty( $it['wc'] ) && ! class_exists( 'WooCommerce' ) ) : ?><span class="nav-soon">با ووکامرس</span><?php endif; ?>
                                                                        <?php if ( $locked ) : ?><input type="hidden" name="visible[]" value="<?php echo esc_attr( $it['key'] ); ?>"><?php endif; ?>
                                                                </label>
                                                        <?php endforeach; ?>
                                                <?php endforeach; ?>
                                        </div>
                                <?php endforeach; ?>
                        </div>
                </div>
        </section>
        <div class="save-bar">
                <button class="btn btn-primary btn-lg" type="submit"><?php echo aim_icon( 'save-3-line' ); // phpcs:ignore ?>ذخیرهٔ منو</button>
        </div>
</form>

<?php if ( $can_code ) : ?>
<form data-api="settings/code" data-tab-panel="code" class="code-form" hidden>
        <section class="card">
                <div class="card-head"><h2>کدهای سفارشی</h2></div>
                <div class="card-body">
                        <div class="notice inline" style="margin-top:0"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>کدهای اینجا روی همهٔ صفحه‌های فروشگاه (به‌جز صفحهٔ ورود و پنل‌ها) اضافه می‌شوند، جدا از فایل‌های قالب؛ پس با به‌روزرسانی قالب پاک نمی‌شوند. کد CSS و JS را بدون تگ <code>&lt;style&gt;</code> و <code>&lt;script&gt;</code> بنویسید.</p></div>
                        <?php
                        $boxes = [
                                'css'    => [ 'CSS سفارشی', 'بعد از استایل قالب بارگذاری می‌شود.' ],
                                'js'     => [ 'JavaScript سفارشی', 'در انتهای صفحه اجرا می‌شود.' ],
                                'head'   => [ 'کد داخل &lt;head&gt;', 'برای تگ‌های تأیید سایت، آمار و پیکسل‌ها.' ],
                                'footer' => [ 'کد پیش از &lt;/body&gt;', 'برای ابزارک گفت‌وگوی آنلاین و اسکریپت‌های بیرونی.' ],
                        ];
                        foreach ( $boxes as $k => [ $label, $help ] ) :
                                ?>
                                <div class="form-row">
                                        <label class="form-label" for="code-<?php echo esc_attr( $k ); ?>"><?php echo wp_kses( $label, [] ); ?></label>
                                        <textarea id="code-<?php echo esc_attr( $k ); ?>" class="field ltr code-box" name="<?php echo esc_attr( $k ); ?>" rows="8" data-code-editor="<?php echo esc_attr( $k ); ?>" spellcheck="false"><?php echo esc_textarea( $code[ $k ] ); ?></textarea>
                                        <p class="form-help"><?php echo wp_kses( $help, [] ); ?></p>
                                </div>
                        <?php endforeach; ?>
                </div>
        </section>
        <div class="save-bar">
                <button class="btn btn-primary btn-lg" type="submit"><?php echo aim_icon( 'save-3-line' ); // phpcs:ignore ?>ذخیرهٔ کدها</button>
        </div>
</form>
<?php endif; ?>
</div>
