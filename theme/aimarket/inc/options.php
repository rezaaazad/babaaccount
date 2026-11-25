<?php
/**
 * Theme options: texts and lists of the pages the free Elementor cannot
 * build (product page, product cards, shop, cart, checkout, thank-you, 404,
 * mini cart). Edited in panel → «تنظیمات قالب»; the core plugin renders the
 * form from aim_t_option_schema() and saves to the same option.
 *
 * Templates read a value with aim_t_opt( 'key' ); a title with *stars*
 * marks its accent-coloured part, rendered by aim_t_mark().
 */
defined( 'ABSPATH' ) || exit;

const AIM_T_OPTIONS = 'aim_theme_options';

/** Saved value of a theme option, or its default. */
function aim_t_opt( $key ) {
        static $saved = null;
        $saved ??= (array) get_option( AIM_T_OPTIONS, [] );
        foreach ( aim_t_option_schema() as $group ) {
                if ( isset( $group['fields'][ $key ] ) ) {
                        $field = $group['fields'][ $key ];
                        if ( array_key_exists( $key, $saved ) && ( ( '' !== $saved[ $key ] && [] !== $saved[ $key ] ) || ( [] === $saved[ $key ] && ! empty( $field['allow_empty'] ) ) ) ) {
                                return $saved[ $key ];
                        }
                        return $field['default'];
                }
        }
        if ( array_key_exists( $key, $saved ) && '' !== $saved[ $key ] && [] !== $saved[ $key ] ) {
                return $saved[ $key ];
        }
        return '';
}

/** "بعد از خرید *چه اتفاقی* می‌افتد" → escaped HTML with the starred part in <em>. */
function aim_t_mark( $text ) {
        return preg_replace( '/\*([^*]+)\*/u', '<em>$1</em>', esc_html( (string) $text ) );
}

/**
 * Groups of fields. Field types: text, textarea, list (rows of sub-fields).
 * Every field has a label and a default; "help" is optional.
 */
function aim_t_option_schema() {
        static $schema = null;
        if ( null !== $schema ) {
                return $schema;
        }
        $mark = 'بخشی که بین دو ستاره (*) بنویسید رنگی نمایش داده می‌شود.';
        $home = aim_t_home();

        $schema = [
                'product'  => [
                        'title'  => 'صفحهٔ محصول',
                        'icon'   => 'shopping-basket-2-line',
                        'fields' => [
                                'p_guarantee'     => [ 'type' => 'text', 'label' => 'برچسب ضمانت (بالای صفحه)', 'default' => 'ضمانت تا پایان اشتراک' ],
                                'p_cta'           => [ 'type' => 'text', 'label' => 'دکمهٔ بالای صفحه', 'default' => 'مشاهدهٔ پلن‌ها و خرید' ],
                                'p_plans_title'   => [ 'type' => 'text', 'label' => 'عنوان بخش پلن‌ها', 'default' => 'پلن *مناسب* را انتخاب کن', 'help' => $mark ],
                                'p_plans_text'    => [ 'type' => 'text', 'label' => 'توضیح بخش پلن‌ها', 'default' => 'تفاوت پلن‌ها در مدت، نوع اکانت و زمان تحویل است.' ],
                                'p_buy'           => [ 'type' => 'text', 'label' => 'دکمهٔ خرید', 'default' => 'خرید و تحویل' ],
                                'p_soldout_title' => [ 'type' => 'text', 'label' => 'عنوان کادر «ناموجود»', 'default' => 'این سرویس فعلاً ناموجود است' ],
                                'p_soldout_text'  => [ 'type' => 'text', 'label' => 'متن کادر «ناموجود»', 'default' => 'موجودی همهٔ پلن‌ها تمام شده و به‌زودی شارژ می‌شود.' ],
                                'p_after_title'   => [ 'type' => 'text', 'label' => 'عنوان «بعد از خرید»', 'default' => 'بعد از خرید *چه اتفاقی* می‌افتد؟', 'help' => $mark ],
                                'p_after_auto'    => [
                                        'type'    => 'list',
                                        'label'   => 'مراحل بعد از خرید — تحویل خودکار',
                                        'fields'  => [ 'title' => 'عنوان', 'text' => 'توضیح' ],
                                        'default' => [
                                                [ 'title' => 'پرداخت', 'text' => 'سفارش در حساب کاربری شما ثبت و همان لحظه پردازش می‌شود.' ],
                                                [ 'title' => 'تحویل آنی', 'text' => 'اطلاعات ورود اکانت در «اکانت‌های من» نمایش داده و با پیامک اطلاع داده می‌شود.' ],
                                                [ 'title' => 'استفاده', 'text' => 'از اکانت استفاده کنید؛ پیش از پایان اشتراک یادآوری تمدید می‌گیرید.' ],
                                        ],
                                ],
                                'p_after_manual'  => [
                                        'type'    => 'list',
                                        'label'   => 'مراحل بعد از خرید — فعال‌سازی روی ایمیل',
                                        'fields'  => [ 'title' => 'عنوان', 'text' => 'توضیح' ],
                                        'default' => [
                                                [ 'title' => 'پرداخت', 'text' => 'سفارش در حساب کاربری شما ثبت می‌شود.' ],
                                                [ 'title' => 'ایمیل فعال‌سازی', 'text' => 'ایمیلی که در صفحهٔ پرداخت وارد کرده‌اید برای فعال‌سازی استفاده می‌شود.' ],
                                                [ 'title' => 'فعال‌سازی', 'text' => 'پس از فعال شدن اشتراک، پیامک می‌گیرید و جزئیات در «اکانت‌های من» نمایش داده می‌شود.' ],
                                        ],
                                ],
                                'p_faq_title'     => [ 'type' => 'text', 'label' => 'عنوان سؤال‌ها', 'default' => 'سؤال‌های *متداول*', 'help' => $mark ],
                                'p_faq_text'      => [ 'type' => 'text', 'label' => 'توضیح سؤال‌ها', 'default' => 'جواب مهم‌ترین سؤال‌ها دربارهٔ نوع اکانت، تحویل و ضمانت.' ],
                                'p_faq'           => [ 'type' => 'list', 'label' => 'سؤال‌های همهٔ محصولات', 'fields' => [ 'q' => 'سؤال', 'a' => 'پاسخ' ], 'default' => $home['faq'] ],
                                'p_reviews_title' => [ 'type' => 'text', 'label' => 'عنوان نظرات', 'default' => 'نظر *خریداران*', 'help' => $mark ],
                                'p_reviews_text'  => [ 'type' => 'text', 'label' => 'توضیح نظرات', 'default' => 'تجربهٔ خریداران واقعی را بخوان و تجربهٔ خودت را ثبت کن.' ],
                                'p_related_title' => [ 'type' => 'text', 'label' => 'عنوان محصولات مشابه', 'default' => 'ابزارهای *مشابه*', 'help' => $mark ],
                                'p_related_text'  => [ 'type' => 'text', 'label' => 'توضیح محصولات مشابه', 'default' => 'گزینه‌های دیگر را هم ببین و مقایسه کن.' ],
                        ],
                ],
                'cards'    => [
                        'title'  => 'کارت محصول',
                        'icon'   => 'apps-2-line',
                        'fields' => [
                                'card_from'    => [ 'type' => 'text', 'label' => 'متن بالای قیمت', 'default' => 'شروع قیمت از' ],
                                'card_button'  => [ 'type' => 'text', 'label' => 'دکمه (محصول موجود)', 'default' => 'انتخاب پلن' ],
                                'card_soldout' => [ 'type' => 'text', 'label' => 'دکمه (محصول ناموجود)', 'default' => 'مشاهدهٔ محصول' ],
                        ],
                ],
                'shop'     => [
                        'title'  => 'فروشگاه و دسته‌ها',
                        'icon'   => 'store-2-line',
                        'fields' => [
                                's_title'       => [ 'type' => 'text', 'label' => 'عنوان صفحهٔ فروشگاه', 'default' => 'فروشگاه' ],
                                's_text'        => [ 'type' => 'textarea', 'label' => 'توضیح صفحهٔ فروشگاه', 'default' => 'اکانت و اشتراک سرویس‌های هوش مصنوعی. قیمت هر محصول، قیمت ارزان‌ترین پلن موجود آن است.', 'help' => 'صفحهٔ هر دسته، توضیح خود آن دسته را نشان می‌دهد.' ],
                                's_empty_title' => [ 'type' => 'text', 'label' => 'عنوان «محصولی پیدا نشد»', 'default' => 'محصولی پیدا نشد' ],
                                's_empty_text'  => [ 'type' => 'text', 'label' => 'متن «محصولی پیدا نشد»', 'default' => 'فیلترها یا عبارت جست‌وجو را تغییر بده.' ],
                        ],
                ],
                'cart'     => [
                        'title'  => 'سبد خرید و پرداخت',
                        'icon'   => 'shopping-cart-2-line',
                        'fields' => [
                                'c_empty_title'    => [ 'type' => 'text', 'label' => 'عنوان سبد خالی', 'default' => 'سبد خریدت خالی است' ],
                                'c_empty_text'     => [ 'type' => 'text', 'label' => 'متن سبد خالی', 'default' => 'سرویس موردنظرت را انتخاب کن؛ بعد از پرداخت، اکانت در حسابت آماده است.' ],
                                'c_empty_button'   => [ 'type' => 'text', 'label' => 'دکمهٔ سبد خالی', 'default' => 'مشاهدهٔ سرویس‌ها' ],
                                'c_go'             => [ 'type' => 'text', 'label' => 'دکمهٔ «ادامه و پرداخت»', 'default' => 'ادامه و پرداخت' ],
                                'co_login_title'   => [ 'type' => 'text', 'label' => 'پرداخت — عنوان «وارد شو»', 'default' => 'برای ادامه، با شمارهٔ موبایل وارد شو' ],
                                'co_login_text'    => [ 'type' => 'textarea', 'label' => 'پرداخت — متن «وارد شو»', 'default' => 'اطلاعات اکانت بعد از پرداخت در حساب کاربری‌ات نمایش داده می‌شود. ورود و ثبت‌نام یکی است و فقط یک کد پیامکی لازم دارد.' ],
                                'co_login_button'  => [ 'type' => 'text', 'label' => 'پرداخت — دکمهٔ ورود', 'default' => 'ورود یا ثبت‌نام و ادامهٔ خرید' ],
                                'co_buyer'         => [ 'type' => 'text', 'label' => 'پرداخت — عنوان اطلاعات خریدار', 'default' => 'اطلاعات خریدار' ],
                                'co_payment'       => [ 'type' => 'text', 'label' => 'پرداخت — عنوان روش پرداخت', 'default' => 'روش پرداخت را انتخاب کنید' ],
                                'co_place_order'   => [ 'type' => 'text', 'label' => 'پرداخت — دکمهٔ نهایی', 'default' => 'ادامه و پرداخت امن' ],
                                'co_title'         => [ 'type' => 'text', 'label' => 'پرداخت — عنوان صفحه', 'default' => 'تکمیل سفارش و *پرداخت*', 'help' => $mark ],
                                'co_fee_label'     => [ 'type' => 'text', 'label' => 'پرداخت — ردیف هزینهٔ تحویل', 'default' => 'هزینهٔ پردازش و تحویل' ],
                                'co_account_note'  => [ 'type' => 'textarea', 'label' => 'پرداخت — یادداشت حساب کاربری', 'default' => 'پیشنهاد می‌کنیم برای فعال‌سازی سریع با همین شمارهٔ موبایل ثبت‌نام کنید؛ سفارش و اطلاعات اکانت‌ها به همین حساب متصل می‌شود.' ],
                                'mc_empty_title'   => [ 'type' => 'text', 'label' => 'سبد کشویی — عنوان خالی', 'default' => 'سبد خریدت خالی است' ],
                                'mc_empty_text'    => [ 'type' => 'text', 'label' => 'سبد کشویی — متن خالی', 'default' => 'سرویس موردنظرت را انتخاب کن.' ],
                        ],
                ],
                'menu'     => [
                        'title'  => 'منوی سایت',
                        'icon'   => 'menu-line',
                        'fields' => [
                                'm_title'        => [ 'type' => 'text', 'label' => 'عنوان منوی بزرگ (دسته‌ها)', 'default' => 'هوش مصنوعی‌ها', 'help' => 'دسته‌هایی که زیرمجموعه ندارند زیر این عنوان می‌آیند؛ هر دستهٔ والد منوی بزرگ خودش را دارد.' ],
                                'm_popular'      => [ 'type' => 'text', 'label' => 'عنوان تب پرفروش‌ها', 'default' => 'پرفروش‌ترین‌ها' ],
                                'm_support'      => [ 'type' => 'text', 'label' => 'عنوان منوی پشتیبانی', 'default' => 'پشتیبانی' ],
                                'm_all'          => [ 'type' => 'text', 'label' => 'دکمهٔ «همهٔ محصولات»', 'default' => 'همهٔ محصولات فروشگاه' ],
                                'm_promo'        => [ 'type' => 'select', 'label' => 'کارت تبلیغ منوی بزرگ', 'default' => 'on', 'options' => [ 'on' => 'نمایش', 'off' => 'پنهان' ] ],
                                'm_promo_kicker' => [ 'type' => 'text', 'label' => 'کارت تبلیغ — برچسب', 'default' => 'تحویل فوری' ],
                                'm_promo_title'  => [ 'type' => 'text', 'label' => 'کارت تبلیغ — عنوان', 'default' => 'اکانت آماده، *چند ثانیه* بعد از پرداخت', 'help' => $mark ],
                                'm_promo_text'   => [ 'type' => 'textarea', 'label' => 'کارت تبلیغ — متن', 'default' => 'پلن را انتخاب کن، پرداخت کن و اطلاعات اکانت را همان لحظه در حساب کاربری‌ات ببین.' ],
                                'm_promo_button' => [ 'type' => 'text', 'label' => 'کارت تبلیغ — دکمه', 'default' => 'شروع خرید' ],
                                'm_promo_url'    => [ 'type' => 'url', 'label' => 'کارت تبلیغ — لینک', 'default' => '', 'help' => 'خالی = صفحهٔ فروشگاه' ],
                        ],
                ],
                'footer'   => [
                        'title'  => 'فوتر',
                        'icon'   => 'layout-masonry-line',
                        'fields' => [
                                'f_h_top'          => [ 'type' => 'heading', 'label' => 'نوار بالای فوتر' ],
                                'f_logo'           => [ 'type' => 'image', 'label' => 'لوگوی فوتر', 'default' => '', 'help' => 'خالی = نشان پیش‌فرض و نام سایت.' ],
                                'f_brand_sub'      => [ 'type' => 'text', 'label' => 'زیرنویس نام سایت', 'default' => 'HOOSH MARKET' ],
                                'f_ticket_label'   => [ 'type' => 'text', 'label' => 'دکمهٔ اصلی — برچسب', 'default' => 'پشتیبانی آنلاین' ],
                                'f_ticket_text'    => [ 'type' => 'text', 'label' => 'دکمهٔ اصلی — متن', 'default' => 'ارسال تیکت' ],
                                'f_ticket_url'     => [ 'type' => 'url', 'label' => 'دکمهٔ اصلی — لینک', 'default' => '', 'help' => 'خالی = تیکت‌های حساب کاربری. نشانی‌های کوتاه ستون‌های لینک مثل {contact} هم کار می‌کنند.' ],
                                'f_telegram_label' => [ 'type' => 'text', 'label' => 'تلگرام — برچسب', 'default' => 'پشتیبانی در تلگرام' ],
                                'f_telegram'       => [ 'type' => 'text', 'label' => 'آیدی تلگرام پشتیبانی', 'default' => '', 'help' => 'مثل @support یا t.me/support — خالی بگذارید تا نمایش داده نشود.' ],
                                'f_phone_label'    => [ 'type' => 'text', 'label' => 'تلفن — برچسب', 'default' => 'تلفن' ],
                                'f_phone'          => [ 'type' => 'text', 'label' => 'شمارهٔ تلفن', 'default' => '', 'help' => 'خالی = تلفن پشتیبانی تنظیمات سایت.' ],
                                'f_email'          => [ 'type' => 'text', 'label' => 'ایمیل', 'default' => '', 'help' => 'زیر کپی‌رایت نمایش داده می‌شود؛ خالی = ایمیل تنظیمات سایت.' ],
                                'f_backtop'        => [ 'type' => 'select', 'label' => 'دکمهٔ «بازگشت به بالا»', 'default' => 'on', 'options' => [ 'on' => 'نمایش', 'off' => 'پنهان' ] ],

                                'f_h_features'     => [ 'type' => 'heading', 'label' => 'ویژگی‌ها' ],
                                'f_features'       => [
                                        'type'    => 'list',
                                        'allow_empty' => true, // a saved empty list hides this part instead of bringing the defaults back
                                        'label'   => 'ردیف ویژگی‌ها',
                                        'fields'  => [ 'icon' => [ 'label' => 'آیکن', 'type' => 'select', 'options' => aim_t_footer_icon_options() ], 'title' => 'عنوان', 'text' => 'توضیح' ],
                                        'default' => [
                                                [ 'icon' => 'flashlight-line', 'title' => 'تحویل سریع', 'text' => 'زمان تحویل هر پلن در صفحه‌اش آمده' ],
                                                [ 'icon' => 'shield-check-line', 'title' => 'ضمانت تا پایان اشتراک', 'text' => 'تعویض یا بازگشت وجه طبق شرایط پلن' ],
                                                [ 'icon' => 'customer-service-2-line', 'title' => 'پشتیبانی واقعی', 'text' => 'تیکت، تلگرام و تلفن' ],
                                                [ 'icon' => 'file-list-3-line', 'title' => 'پیگیری سفارش', 'text' => 'همیشه در حساب کاربری شما' ],
                                        ],
                                ],

                                'f_h_cols'         => [ 'type' => 'heading', 'label' => 'ستون‌های لینک' ],
                                'f_columns'        => [
                                        'type'    => 'list',
                                        'allow_empty' => true, // a saved empty list hides this part instead of bringing the defaults back
                                        'label'   => 'ستون‌ها',
                                        'help'    => 'هر خط یک لینک: «متن | آدرس». خط [products] = محصولات پرفروش و [categories] = دسته‌ها (خودکار). در آدرس‌ها می‌توانید بنویسید: {shop} فروشگاه، {account} حساب کاربری، {orders} سفارش‌ها، {tickets} تیکت‌ها، {contact} تماس، {faq} سؤالات، {steps} راهنمای خرید، {blog} مجله، {privacy} حریم خصوصی. {site} = نام سایت.',
                                        'fields'  => [ 'title' => 'عنوان ستون', 'links' => [ 'label' => 'لینک‌ها (هر خط: متن | آدرس)', 'type' => 'textarea' ] ],
                                        'default' => [
                                                [ 'title' => 'محصولات پرفروش', 'links' => '[products]' ],
                                                [ 'title' => 'دسته‌بندی‌ها', 'links' => '[categories]' ],
                                                [ 'title' => 'خدمات مشتریان', 'links' => "پیگیری سفارش | {orders}\nحساب کاربری | {account}\nراهنمای خرید | {steps}\nسؤالات متداول | {faq}\nارسال تیکت | {tickets}" ],
                                                [ 'title' => '{site}', 'links' => "فروشگاه | {shop}\nمجله و آموزش | {blog}\nتماس با ما | {contact}\nحریم خصوصی | {privacy}" ],
                                        ],
                                ],

                                'f_h_news'         => [ 'type' => 'heading', 'label' => 'کارت کد تخفیف و شبکه‌های اجتماعی' ],
                                'f_news'           => [ 'type' => 'select', 'label' => 'فرم دریافت کد تخفیف', 'default' => 'on', 'options' => [ 'on' => 'نمایش', 'off' => 'پنهان' ] ],
                                'f_news_title'     => [ 'type' => 'text', 'label' => 'عنوان', 'default' => 'کد تخفیف می‌خوای؟' ],
                                'f_news_text'      => [ 'type' => 'textarea', 'label' => 'توضیح', 'default' => 'شمارهٔ موبایلت را بنویس تا کدهای تخفیف و پیشنهادهای {site} پیامک شود.' ],
                                'f_news_placeholder' => [ 'type' => 'text', 'label' => 'متن داخل کادر', 'default' => '۰۹۱۲ ۱۲۳ ۴۵۶۷' ],
                                'f_news_button'    => [ 'type' => 'text', 'label' => 'دکمه', 'default' => 'دریافت کد' ],
                                'f_social_title'   => [ 'type' => 'text', 'label' => 'عنوان شبکه‌های اجتماعی', 'default' => '{site} در شبکه‌های اجتماعی' ],
                                'f_socials'        => [
                                        'type'    => 'list',
                                        'allow_empty' => true, // a saved empty list hides this part instead of bringing the defaults back
                                        'label'   => 'شبکه‌های اجتماعی',
                                        'help'    => 'ردیفی که آدرس ندارد نمایش داده نمی‌شود.',
                                        'fields'  => [ 'net' => [ 'label' => 'شبکه', 'type' => 'select', 'options' => array_map( fn( $n ) => $n[0], aim_t_socials() ) ], 'label' => 'نام نمایشی (خالی = نام شبکه)', 'url' => [ 'label' => 'آدرس', 'type' => 'url' ] ],
                                        'default' => [],
                                ],

                                'f_h_bottom'       => [ 'type' => 'heading', 'label' => 'پایین فوتر' ],
                                'f_about'          => [ 'type' => 'textarea', 'label' => 'متن معرفی', 'default' => '{site} اشتراک سرویس‌های هوش مصنوعی را با پرداخت ریالی و تحویل سریع ارائه می‌کند. جزئیات هر پلن، از مدت و نوع اکانت تا شرایط ضمانت، در صفحهٔ همان محصول آمده است.' ],
                                'f_copyright'      => [ 'type' => 'text', 'label' => 'کپی‌رایت', 'default' => '© {year} {site} — همهٔ حقوق محفوظ است.', 'help' => '{year} = سال شمسی، {site} = نام سایت' ],
                        ],
                ],
                'badges'   => [
                        'title'  => 'نمادهای اعتماد',
                        'icon'   => 'shield-check-line',
                        'fields' => [
                                'f_badges' => [
                                        'type'    => 'list',
                                        'allow_empty' => true, // a saved empty list hides this part instead of bringing the defaults back
                                        'label'   => 'نمادها (اینماد، ساماندهی، اتحادیه و…)',
                                        'help'    => 'برای اینماد و ساماندهی همان کد HTML که سایتشان می‌دهد را در «کد نماد» بگذارید (لینک استعلام درست کار می‌کند). یا فقط تصویر و لینک بدهید. نمادها پایین فوتر در کارت‌های سفید نمایش داده می‌شوند.',
                                        'fields'  => [
                                                'title' => 'عنوان زیر نماد',
                                                'image' => [ 'label' => 'تصویر', 'type' => 'image' ],
                                                'url'   => [ 'label' => 'لینک (اختیاری)', 'type' => 'url' ],
                                                'code'  => [ 'label' => 'کد نماد (HTML)', 'type' => 'code' ],
                                        ],
                                        'default' => [],
                                ],
                        ],
                ],
                'thankyou' => [
                        'title'  => 'صفحهٔ تشکر از خرید',
                        'icon'   => 'checkbox-circle-line',
                        'fields' => [
                                'ty_paid'         => [ 'type' => 'text', 'label' => 'عنوان پرداخت موفق', 'default' => 'پرداخت موفق بود؛ سفارشت ثبت شد' ],
                                'ty_unpaid'       => [ 'type' => 'text', 'label' => 'عنوان سفارش ثبت‌شده (پرداخت بعدی)', 'default' => 'سفارشت ثبت شد' ],
                                'ty_failed_title' => [ 'type' => 'text', 'label' => 'عنوان پرداخت ناموفق', 'default' => 'پرداخت ناموفق بود' ],
                                'ty_failed_text'  => [ 'type' => 'textarea', 'label' => 'متن پرداخت ناموفق', 'default' => 'اگر مبلغی از حسابت کم شده، معمولاً تا ۷۲ ساعت برمی‌گردد. می‌توانی دوباره پرداخت کنی.' ],
                                'ty_accounts'     => [ 'type' => 'text', 'label' => 'دکمهٔ اکانت‌ها', 'default' => 'مشاهدهٔ اکانت‌های من' ],
                        ],
                ],
                'notfound' => [
                        'title'  => 'صفحهٔ ۴۰۴',
                        'icon'   => 'compass-3-line',
                        'fields' => [
                                'nf_title'   => [ 'type' => 'text', 'label' => 'عنوان', 'default' => 'این صفحه پیدا نشد' ],
                                'nf_text'    => [ 'type' => 'textarea', 'label' => 'متن', 'default' => 'شاید آدرس اشتباه تایپ شده یا صفحه جابه‌جا شده باشد. از جست‌وجو استفاده کن یا سراغ سرویس‌ها برو.' ],
                                'nf_related' => [ 'type' => 'text', 'label' => 'عنوان پیشنهادها', 'default' => 'شاید دنبال این‌ها بودی' ],
                        ],
                ],
        ];
        return $schema;
}

// The core plugin's panel builds the «تنظیمات قالب» form from this schema.
add_filter( 'aim_theme_options_schema', fn() => [ 'option' => AIM_T_OPTIONS, 'groups' => aim_t_option_schema() ] );
