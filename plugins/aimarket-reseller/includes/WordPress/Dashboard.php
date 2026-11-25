<?php
/**
 * Storefront reseller desk, under My Account and the shortcode.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\ApiException;
use HooshMarket\Reseller\Domain\Money;

final class Dashboard
{
    public function __construct(private readonly Services $services)
    {
    }

    public static function registerEndpoint(): void
    {
        add_rewrite_endpoint('reseller', EP_ROOT | EP_PAGES);
    }

    public function register(): void
    {
        add_action('init', [self::class, 'registerEndpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'menu']);
        add_action('woocommerce_account_reseller_endpoint', [$this, 'render']);
        add_shortcode('hm_reseller_dashboard', [$this, 'shortcode']);
        add_shortcode('hm_reseller_api_docs', [$this, 'docsShortcode']);
        add_action('admin_post_hmr_front', [$this, 'handle']);
        add_action('wp', [$this, 'assets']);
    }

    /**
     * @param array<string, string> $items
     * @return array<string, string>
     */
    public function menu(array $items): array
    {
        $out = [];
        foreach ($items as $key => $label) {
            $out[$key] = $label;
            if ($key === 'orders') {
                $out['reseller'] = 'همکاری در فروش';
            }
        }
        if (!isset($out['reseller'])) {
            $out['reseller'] = 'همکاری در فروش';
        }
        return $out;
    }

    public function assets(): void
    {
        if (is_admin()) {
            return;
        }
        $load = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('reseller');
        $post = get_post();
        if ($post instanceof \WP_Post) {
            $load = $load
                || has_shortcode($post->post_content, 'hm_reseller_dashboard')
                || has_shortcode($post->post_content, 'hm_reseller_api_docs');
        }
        if (!$load) {
            return;
        }
        wp_enqueue_style('hmr-dashboard', plugins_url('assets/css/dashboard.css', HMR_FILE), [], HMR_VERSION);
        wp_enqueue_script('hmr-dashboard', plugins_url('assets/js/dashboard.js', HMR_FILE), [], HMR_VERSION, true);
    }

    public function shortcode(): string
    {
        ob_start();
        $this->render();
        return (string) ob_get_clean();
    }

    public function docsShortcode(): string
    {
        $file = HMR_DIR . '/docs/api-fa.html';
        if (!is_file($file)) {
            return '';
        }
        $html = (string) file_get_contents($file);
        if (preg_match('/<body[^>]*>(.*)<\/body>/s', $html, $match) !== 1) {
            return '';
        }
        return '<div class="hmr-docs" dir="rtl" lang="fa">' . $match[1] . '</div>';
    }

    public function render(): void
    {
        echo '<div class="hmr-dash" dir="rtl" lang="fa">';
        if (!is_user_logged_in()) {
            echo '<p>برای درخواست همکاری ابتدا وارد حساب کاربری شوید.</p>';
            echo '</div>';
            return;
        }
        $this->flash();
        $reseller = $this->services->crm->findByUser(get_current_user_id());
        if ($reseller === null) {
            $this->applyForm();
            echo '</div>';
            return;
        }
        if ($reseller['status'] === 'pending') {
            echo '<div class="hmr-card"><h2>درخواست شما ثبت شد</h2><p>پس از تأیید مدیر، کیف پول و کلید API در همین صفحه فعال می‌شود.</p></div>';
            echo '</div>';
            return;
        }
        if ($reseller['status'] === 'rejected') {
            echo '<div class="hmr-card"><h2>درخواست همکاری رد شد</h2><p>برای پیگیری با پشتیبانی فروشگاه تماس بگیرید.</p></div>';
            echo '</div>';
            return;
        }
        if ($reseller['status'] === 'suspended') {
            echo '<div class="hmr-card hmr-card-warn"><h2>حساب همکاری مسدود است</h2><p>خرید API و برداشت از کیف پول تا رفع مسدودی ممکن نیست. موجودی حفظ شده است.</p></div>';
        }
        $this->desk($reseller);
        echo '</div>';
    }

    public function handle(): void
    {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('وارد حساب شوید.', 'aimarket-reseller'), '', ['response' => 401]);
        }
        check_admin_referer('hmr_front');
        $op = sanitize_key((string) wp_unslash($_POST['hmr_op'] ?? ''));
        $back = wp_get_referer() ?: home_url('/');
        try {
            $reseller = $this->services->crm->findByUser(get_current_user_id());
            if ($op === 'apply') {
                $this->services->crm->apply(
                    get_current_user_id(),
                    sanitize_text_field(wp_unslash((string) ($_POST['display_name'] ?? ''))),
                    sanitize_text_field(wp_unslash((string) ($_POST['phone'] ?? ''))),
                    sanitize_text_field(wp_unslash((string) ($_POST['company'] ?? ''))),
                    sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? '')))
                );
            } elseif ($reseller === null || $reseller['status'] !== 'approved') {
                throw new ApiException('reseller_not_approved', 'این حساب هنوز همکار فعال نیست.', 403);
            } elseif ($op === 'create_key') {
                $ips = preg_split('/\s+/', (string) wp_unslash($_POST['ips'] ?? '')) ?: [];
                $token = $this->services->auth->issue(
                    (int) $reseller['id'],
                    sanitize_text_field(wp_unslash((string) ($_POST['label'] ?? 'اصلی'))),
                    array_map('strval', $ips),
                    (int) ($_POST['rate_limit'] ?? 60)
                );
                set_transient('hmr_reveal_' . get_current_user_id(), $token, 60);
            } elseif ($op === 'revoke_key') {
                $this->services->auth->revoke((int) $reseller['id'], (int) ($_POST['key_id'] ?? 0));
            } elseif ($op === 'webhook') {
                $this->services->crm->setWebhook((int) $reseller['id'], esc_url_raw(wp_unslash((string) ($_POST['webhook_url'] ?? ''))));
            } elseif ($op === 'rotate_secret') {
                $this->services->crm->rotateWebhookSecret((int) $reseller['id'], $this->services->crypto);
            } else {
                throw new ApiException('unknown_action', 'عملیات شناخته نشد.', 400);
            }
            $back = add_query_arg('hmr_notice', 'saved', $back);
        } catch (ApiException $e) {
            $back = add_query_arg(['hmr_notice' => 'error', 'hmr_message' => rawurlencode($e->getMessage())], $back);
        }
        wp_safe_redirect($back);
        exit;
    }

    /**
     * @param array<string, mixed> $reseller
     */
    private function desk(array $reseller): void
    {
        $wallet = $this->services->wallet->balance((int) $reseller['id']);
        $usage = $this->services->crm->usage((int) $reseller['id']);
        $balance = $wallet === null ? 0 : (int) $wallet['balance_minor'];
        $currency = $wallet === null ? $this->services->currency : (string) $wallet['currency'];
        $tierName = '—';
        foreach ($this->services->crm->tiers() as $tier) {
            if ((int) $tier['id'] === (int) $reseller['tier_id']) {
                $tierName = (string) $tier['name'];
            }
        }
        echo '<header class="hmr-head"><div><p class="hmr-kicker">پنل همکاری در فروش</p><h2>' . esc_html((string) $reseller['display_name']) . '</h2></div>';
        echo '<span class="hmr-pill">' . esc_html($tierName) . '</span></header>';
        echo '<div class="hmr-stats">';
        $this->stat('موجودی', Money::format($balance, $this->services->decimals, $currency));
        $this->stat('سفارش امروز', (string) $usage['orders_today']);
        $this->stat('درخواست API امروز', (string) $usage['requests_today']);
        $this->stat('خرید امروز', Money::format($usage['spend_today'], $this->services->decimals, $currency));
        echo '</div>';

        echo '<section class="hmr-card" id="hmr-orders"><h3>سفارش‌ها</h3>';
        $orders = $this->services->orders->list((int) $reseller['id'], 1, 20);
        if ($orders['items'] === []) {
            echo '<p class="hmr-empty">هنوز سفارشی از API ثبت نشده است.</p>';
        } else {
            echo '<div class="hmr-table-wrap"><table><thead><tr><th>شماره</th><th>محصول</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead><tbody>';
            foreach ($orders['items'] as $order) {
                $full = $this->services->orders->get((int) $reseller['id'], (int) $order['id']);
                echo '<tr><td>' . esc_html((string) $order['id']) . '</td><td>' . esc_html((string) $order['product_id']) . '</td>';
                echo '<td>' . esc_html(Money::format((int) $order['total'], $this->services->decimals, (string) $order['currency'])) . '</td>';
                echo '<td>' . esc_html($this->orderStatus((string) $order['status'])) . '</td>';
                echo '<td>' . esc_html((string) $order['created_at']) . '</td></tr>';
                if ($full['credentials'] !== []) {
                    echo '<tr><td colspan="5"><details><summary>اطلاعات اکانت تحویل‌شده</summary><ul class="hmr-creds">';
                    foreach ($full['credentials'] as $cred) {
                        echo '<li><span>شناسه</span> ' . esc_html($cred['username']) . '<br><span>رمز</span> <code>' . esc_html($cred['password']) . '</code>';
                        if ($cred['extra'] !== '') {
                            echo '<br><span>توضیح</span> ' . esc_html($cred['extra']);
                        }
                        echo '</li>';
                    }
                    echo '</ul></details></td></tr>';
                }
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';

        echo '<section class="hmr-card" id="hmr-keys"><h3>کلیدهای API</h3>';
        $reveal = get_transient('hmr_reveal_' . get_current_user_id());
        if (is_string($reveal) && $reveal !== '') {
            delete_transient('hmr_reveal_' . get_current_user_id());
            echo '<div class="hmr-reveal"><p>این کلید فقط یک‌بار نشان داده می‌شود. آن را جایی امن نگه دارید.</p>';
            echo '<input readonly value="' . esc_attr($reveal) . '" id="hmr-new-key" /> <button type="button" class="hmr-copy" data-copy="hmr-new-key">کپی</button></div>';
        }
        $keys = $this->services->auth->listKeys((int) $reseller['id']);
        if ($keys === []) {
            echo '<p class="hmr-empty">کلیدی نساخته‌اید. با کلید می‌توانید از REST API خرید کنید.</p>';
        } else {
            echo '<ul class="hmr-keys">';
            foreach ($keys as $key) {
                $state = $key['revoked_at'] ? 'باطل‌شده' : 'فعال';
                echo '<li><div><strong>' . esc_html((string) ($key['label'] ?: 'بدون نام')) . '</strong> <code>' . esc_html((string) $key['key_prefix']) . '…</code>';
                echo '<br><span class="hmr-muted">' . esc_html($state) . ' · سقف ' . esc_html((string) $key['rate_limit']) . ' در دقیقه</span></div>';
                if (!$key['revoked_at']) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    $this->frontFields('revoke_key');
                    echo '<input type="hidden" name="key_id" value="' . esc_attr((string) $key['id']) . '" />';
                    echo '<button class="hmr-btn hmr-btn-quiet" data-confirm="این کلید باطل شود؟">باطل کردن</button></form>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hmr-form">';
        $this->frontFields('create_key');
        echo '<label>نام کلید <input name="label" placeholder="سرور فروش" /></label>';
        echo '<label>سقف در دقیقه <input name="rate_limit" type="number" min="1" max="10000" value="60" /></label>';
        echo '<label>IP مجاز، هر کدام در یک خط <textarea name="ips" rows="3" placeholder="خالی = همه IPها"></textarea></label>';
        echo '<button class="hmr-btn">ساخت کلید جدید</button></form></section>';

        $secret = $this->services->crm->revealSecret($reseller, $this->services->crypto);
        echo '<section class="hmr-card" id="hmr-hook"><h3>وب‌هوک</h3>';
        echo '<p>بعد از تحویل یا شکست سفارش، یک JSON امضاشده به این آدرس پست می‌شود. امضا در هدر <code>X-HooshMarket-Signature</code> با مقدار <code>sha256=</code> و HMAC بدنه است.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hmr-form">';
        $this->frontFields('webhook');
        echo '<label>آدرس <input name="webhook_url" type="url" value="' . esc_attr((string) ($reseller['webhook_url'] ?? '')) . '" placeholder="https://example.com/hooks/hooshmarket" /></label>';
        echo '<button class="hmr-btn">ذخیره آدرس</button></form>';
        if ($secret !== '') {
            echo '<p class="hmr-muted">رمز امضا</p><input readonly value="' . esc_attr($secret) . '" id="hmr-secret" /> <button type="button" class="hmr-copy" data-copy="hmr-secret">کپی</button>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $this->frontFields('rotate_secret');
        echo '<button class="hmr-btn hmr-btn-quiet" data-confirm="رمز قبلی از این لحظه باطل می‌شود.">تعویض رمز امضا</button></form></section>';

        echo '<section class="hmr-card" id="hmr-usage"><h3>مصرف هفت روز اخیر</h3>';
        if ($usage['requests_by_day'] === []) {
            echo '<p class="hmr-empty">هنوز درخواستی از کلیدهای شما نرسیده است.</p>';
        } else {
            echo '<ul class="hmr-usage">';
            foreach ($usage['requests_by_day'] as $day) {
                echo '<li><span>' . esc_html($day['day']) . '</span><strong>' . esc_html((string) $day['count']) . '</strong></li>';
            }
            echo '</ul>';
        }
        echo '</section>';
    }

    private function applyForm(): void
    {
        $user = wp_get_current_user();
        echo '<section class="hmr-card"><h2>درخواست همکاری در فروش</h2>';
        echo '<p>بعد از تأیید، با موجودی کیف پول و قیمت سطح خودتان از API خرید می‌کنید و اکانت همان لحظه تحویل می‌شود.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hmr-form">';
        $this->frontFields('apply');
        echo '<label>نام <input name="display_name" required value="' . esc_attr($user->display_name) . '" /></label>';
        echo '<label>موبایل <input name="phone" required /></label>';
        echo '<label>نام مجموعه <input name="company" /></label>';
        echo '<label>توضیح <textarea name="note" rows="4" placeholder="حجم تقریبی خرید و محصولات مورد نیاز"></textarea></label>';
        echo '<button class="hmr-btn">ثبت درخواست</button></form></section>';
    }

    private function frontFields(string $op): void
    {
        echo '<input type="hidden" name="action" value="hmr_front" />';
        echo '<input type="hidden" name="hmr_op" value="' . esc_attr($op) . '" />';
        wp_nonce_field('hmr_front');
    }

    private function flash(): void
    {
        $notice = sanitize_key((string) ($_GET['hmr_notice'] ?? ''));
        if ($notice === 'saved') {
            echo '<p class="hmr-flash">انجام شد.</p>';
        } elseif ($notice === 'error') {
            $message = isset($_GET['hmr_message']) ? sanitize_text_field(rawurldecode((string) $_GET['hmr_message'])) : 'خطا';
            echo '<p class="hmr-flash hmr-flash-bad">' . esc_html($message) . '</p>';
        }
    }

    private function stat(string $label, string $value): void
    {
        echo '<article class="hmr-stat"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></article>';
    }

    private function orderStatus(string $status): string
    {
        return match ($status) {
            'delivered' => 'تحویل شد',
            'failed' => 'ناموفق',
            'delivering' => 'در حال تحویل',
            default => $status,
        };
    }
}
