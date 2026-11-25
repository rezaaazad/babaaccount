<?php
/**
 * Admin screens. They attach to the AIM Panel menu when that menu exists.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\ApiException;
use HooshMarket\Reseller\Domain\Money;

final class AdminPage
{
    public function __construct(private readonly Services $services)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 30);
        add_filter('aim_admin_menu', [$this, 'panelMenu'], 20, 2);
        add_filter('aim_template_file', [$this, 'panelTemplate'], 10, 2);
        add_action('admin_post_hmr_admin', [$this, 'handle']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function assets(string $hook): void
    {
        if (!str_contains($hook, 'hm-reseller')) {
            return;
        }
        wp_enqueue_style('hmr-admin', plugins_url('assets/css/admin.css', HMR_FILE), [], HMR_VERSION);
    }

    /**
     * @param array<int, array<string, mixed>> $menu
     * @param array<string, int> $counts
     * @return array<int, array<string, mixed>>
     */
    public function panelMenu(array $menu, array $counts): array
    {
        unset($counts);
        if (!class_exists('AIM_Panel_Menu')) {
            return $menu;
        }
        \AIM_Panel_Menu::add_group($menu, [
            'id' => 'reseller',
            'title' => 'همکاری در فروش',
            'wc' => true,
            'items' => [
                ['key' => 'hm-reseller', 'label' => 'همکاران', 'icon' => 'user-star-line'],
                ['key' => 'hm-reseller-tiers', 'label' => 'سطح و قیمت', 'icon' => 'price-tag-3-line'],
                ['key' => 'hm-reseller-wallet', 'label' => 'کیف پول', 'icon' => 'wallet-3-line'],
                ['key' => 'hm-reseller-logs', 'label' => 'لاگ API', 'icon' => 'file-list-3-line'],
                ['key' => 'hm-reseller-profit', 'label' => 'سود', 'icon' => 'line-chart-line'],
            ],
        ], 'accounts');
        return $menu;
    }

    public function panelTemplate(string $file, string $name): string
    {
        $own = HMR_DIR . '/templates/' . $name . '.php';
        return is_file($own) ? $own : $file;
    }

    public function menu(): void
    {
        if (class_exists('AIM_WP_Admin')) {
            return;
        }
        $cap = 'manage_hm_resellers';
        $parent = $this->parentSlug();
        if ($parent === '') {
            add_menu_page('همکاری در فروش', 'همکاری در فروش', $cap, 'hm-reseller', [$this, 'renderResellers'], 'dashicons-groups', 56);
            $parent = 'hm-reseller';
        } else {
            add_submenu_page($parent, 'همکاران فروش', 'همکاران فروش', $cap, 'hm-reseller', [$this, 'renderResellers']);
        }
        add_submenu_page($parent, 'سطح و قیمت همکاری', 'سطح و قیمت', $cap, 'hm-reseller-tiers', [$this, 'renderTiers']);
        add_submenu_page($parent, 'کیف پول همکاران', 'کیف پول', $cap, 'hm-reseller-wallet', [$this, 'renderWallet']);
        add_submenu_page($parent, 'لاگ API همکاری', 'لاگ API', $cap, 'hm-reseller-logs', [$this, 'renderLogs']);
        add_submenu_page($parent, 'سود همکاری', 'سود', $cap, 'hm-reseller-profit', [$this, 'renderProfit']);
        add_submenu_page($parent, 'تنظیمات همکاری', 'تنظیمات همکاری', $cap, 'hm-reseller-settings', [$this, 'renderSettings']);
    }

    public function handle(): void
    {
        if (!current_user_can('manage_hm_resellers') && !current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('اجازه این کار را ندارید.', 'aimarket-reseller'), '', ['response' => 403]);
        }
        check_admin_referer('hmr_admin');
        $op = sanitize_key((string) wp_unslash($_POST['hmr_op'] ?? ''));
        $section = sanitize_key((string) wp_unslash($_POST['hmr_back'] ?? 'hm-reseller'));
        if (!empty($_POST['hmr_panel']) && class_exists('AIM_WP_Admin')) {
            $back = \AIM_WP_Admin::url($section);
        } else {
            $back = admin_url('admin.php?page=' . $section);
        }
        try {
            $this->dispatch($op);
            $back = add_query_arg('hmr_notice', 'saved', $back);
        } catch (ApiException $e) {
            $back = add_query_arg(['hmr_notice' => 'error', 'hmr_message' => rawurlencode($e->getMessage())], $back);
        } catch (\Throwable $e) {
            error_log('hm-reseller admin ' . $e->getMessage());
            $back = add_query_arg(['hmr_notice' => 'error', 'hmr_message' => rawurlencode('ذخیره انجام نشد.')], $back);
        }
        wp_safe_redirect($back);
        exit;
    }

    private function dispatch(string $op): void
    {
        $id = (int) ($_POST['reseller_id'] ?? 0);
        if ($op === 'approve') {
            $this->services->crm->approve($id, (int) ($_POST['tier_id'] ?? 0), $this->services->currency, $this->services->crypto);
            return;
        }
        if ($op === 'suspend') {
            $this->services->crm->setStatus($id, 'suspended');
            return;
        }
        if ($op === 'unsuspend') {
            $this->services->crm->setStatus($id, 'approved');
            return;
        }
        if ($op === 'reject') {
            $this->services->crm->setStatus($id, 'rejected');
            return;
        }
        if ($op === 'wallet') {
            $amount = Money::majorToMinor(sanitize_text_field(wp_unslash((string) ($_POST['amount'] ?? ''))), $this->services->decimals);
            $direction = ($_POST['direction'] ?? '') === 'debit' ? 'debit' : 'credit';
            $note = sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? '')));
            if ($note === '') {
                throw new ApiException('invalid_note', 'برای تغییر دستی موجودی نوشتن توضیح الزامی است.', 422);
            }
            $this->services->wallet->apply($id, $direction, $amount, $direction === 'credit' ? 'adjustment_credit' : 'adjustment_debit', 'admin', (string) get_current_user_id(), $note, get_current_user_id());
            return;
        }
        if ($op === 'tier') {
            $this->services->crm->updateTier((int) ($_POST['tier_id'] ?? 0), sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))), (float) ($_POST['discount'] ?? 0));
            return;
        }
        if ($op === 'prices') {
            $tierId = (int) ($_POST['tier_id'] ?? 0);
            $prices = isset($_POST['price']) && is_array($_POST['price']) ? wp_unslash($_POST['price']) : [];
            $discounts = isset($_POST['discount']) && is_array($_POST['discount']) ? wp_unslash($_POST['discount']) : [];
            $ids = array_unique(array_merge(array_keys($prices), array_keys($discounts)));
            foreach ($ids as $productId) {
                $productId = (int) $productId;
                $priceRaw = trim((string) ($prices[$productId] ?? $prices[(string) $productId] ?? ''));
                $discountRaw = trim((string) ($discounts[$productId] ?? $discounts[(string) $productId] ?? ''));
                $price = $priceRaw === '' ? null : Money::majorToMinor($priceRaw, $this->services->decimals);
                $discount = $discountRaw === '' ? null : (float) $discountRaw;
                $this->services->crm->savePrice($tierId, $productId, $price, $discount);
            }
            return;
        }
        if ($op === 'settings') {
            $current = get_option('hmr_settings', []);
            if (!is_array($current)) {
                $current = [];
            }
            $table = sanitize_key((string) wp_unslash($_POST['accounts_table'] ?? ''));
            $current['accounts_table'] = $table;
            $current['available_status'] = sanitize_key((string) wp_unslash($_POST['available_status'] ?? 'available')) ?: 'available';
            $current['sold_status'] = sanitize_key((string) wp_unslash($_POST['sold_status'] ?? 'sold')) ?: 'sold';
            update_option('hmr_settings', $current, false);
            return;
        }
        if ($op === 'resend_webhook') {
            $this->services->webhooks->resend((int) ($_POST['webhook_id'] ?? 0));
            return;
        }
        throw new ApiException('unknown_action', 'عملیات شناخته نشد.', 400);
    }

    public function renderResellers(): void
    {
        $this->open('همکاران فروش');
        $status = sanitize_key((string) ($_GET['status'] ?? ''));
        $listed = $this->services->crm->list($status, max(1, (int) ($_GET['paged'] ?? 1)), 50);
        $tiers = $this->services->crm->tiers();
        echo '<p class="hmr-filters">';
        foreach (['' => 'همه', 'pending' => 'در انتظار', 'approved' => 'فعال', 'suspended' => 'مسدود', 'rejected' => 'رد شده'] as $key => $label) {
            $url = add_query_arg(['page' => 'hm-reseller', 'status' => $key], admin_url('admin.php'));
            echo '<a class="button' . ($status === $key ? ' button-primary' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
        }
        echo '</p>';
        echo '<table class="widefat striped"><thead><tr><th>همکار</th><th>کاربر</th><th>سطح</th><th>وضعیت</th><th>موجودی</th><th>عملیات</th></tr></thead><tbody>';
        if ($listed['items'] === []) {
            echo '<tr><td colspan="6">هنوز همکاری ثبت نشده است.</td></tr>';
        }
        foreach ($listed['items'] as $row) {
            $user = get_userdata((int) $row['user_id']);
            $balance = $row['balance_minor'] === null ? '—' : Money::format((int) $row['balance_minor'], $this->services->decimals, (string) ($row['currency'] ?: $this->services->currency));
            echo '<tr>';
            echo '<td><strong>' . esc_html((string) $row['display_name']) . '</strong><br><span class="hmr-muted">' . esc_html((string) $row['phone']) . ' ' . esc_html((string) $row['company']) . '</span>';
            if (!empty($row['note'])) {
                echo '<br><span class="hmr-muted">' . esc_html((string) $row['note']) . '</span>';
            }
            echo '</td>';
            echo '<td>' . esc_html($user instanceof \WP_User ? $user->user_login : '#' . $row['user_id']) . '</td>';
            echo '<td>' . esc_html((string) ($row['tier_name'] ?: '—')) . '</td>';
            echo '<td>' . esc_html($this->statusLabel((string) $row['status'])) . '</td>';
            echo '<td>' . esc_html($balance) . '</td><td>';
            if ($row['status'] !== 'approved') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                $this->fields('approve', 'hm-reseller');
                echo '<input type="hidden" name="reseller_id" value="' . esc_attr((string) $row['id']) . '" />';
                echo '<select name="tier_id">';
                foreach ($tiers as $tier) {
                    echo '<option value="' . esc_attr((string) $tier['id']) . '">' . esc_html((string) $tier['name']) . '</option>';
                }
                echo '</select> <button class="button button-primary">تأیید</button></form>';
            }
            if ($row['status'] === 'approved') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                $this->fields('suspend', 'hm-reseller');
                echo '<input type="hidden" name="reseller_id" value="' . esc_attr((string) $row['id']) . '" />';
                echo '<button class="button">مسدود کردن</button></form>';
            }
            if ($row['status'] === 'suspended') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                $this->fields('unsuspend', 'hm-reseller');
                echo '<input type="hidden" name="reseller_id" value="' . esc_attr((string) $row['id']) . '" />';
                echo '<button class="button">رفع مسدودی</button></form>';
            }
            if ($row['status'] === 'pending') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                $this->fields('reject', 'hm-reseller');
                echo '<input type="hidden" name="reseller_id" value="' . esc_attr((string) $row['id']) . '" />';
                echo '<button class="button">رد</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        $this->close();
    }

    public function renderTiers(): void
    {
        $this->open('سطح و قیمت عمده');
        echo '<p>درصد سطح روی قیمت خرده‌فروشی اعمال می‌شود. قیمت ثابت هر محصول، اگر وارد شود، بر درصد اولویت دارد.</p>';
        foreach ($this->services->crm->tiers() as $tier) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hmr-card-form">';
            $this->fields('tier', 'hm-reseller-tiers');
            echo '<input type="hidden" name="tier_id" value="' . esc_attr((string) $tier['id']) . '" />';
            echo '<h2>' . esc_html((string) $tier['name']) . ' <code>' . esc_html((string) $tier['slug']) . '</code></h2>';
            echo '<label>نام <input name="name" value="' . esc_attr((string) $tier['name']) . '" /></label> ';
            echo '<label>درصد تخفیف <input name="discount" type="number" min="0" max="100" step="0.01" value="' . esc_attr((string) $tier['discount_percent']) . '" /></label> ';
            echo '<button class="button button-primary">ذخیره سطح</button></form>';
        }
        $tierId = (int) ($_GET['tier'] ?? 0);
        $tiers = $this->services->crm->tiers();
        if ($tierId <= 0 && $tiers !== []) {
            $tierId = (int) $tiers[0]['id'];
        }
        echo '<h2>قیمت هر محصول</h2>';
        echo '<form method="get"><input type="hidden" name="page" value="hm-reseller-tiers" /><select name="tier" onchange="this.form.submit()">';
        foreach ($tiers as $tier) {
            echo '<option value="' . esc_attr((string) $tier['id']) . '" ' . selected($tierId, (int) $tier['id'], false) . '>' . esc_html((string) $tier['name']) . '</option>';
        }
        echo '</select></form>';
        $prices = $tierId > 0 ? $this->services->crm->pricesForTier($tierId) : [];
        $products = $this->services->catalog->list(1, 100);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $this->fields('prices', 'hm-reseller-tiers');
        echo '<input type="hidden" name="tier_id" value="' . esc_attr((string) $tierId) . '" />';
        echo '<table class="widefat striped"><thead><tr><th>محصول</th><th>قیمت خرده</th><th>قیمت ثابت عمده</th><th>درصد اختصاصی</th></tr></thead><tbody>';
        if ($products['items'] === []) {
            echo '<tr><td colspan="4">محصول منتشرشده‌ای برای قیمت‌گذاری نیست.</td></tr>';
        }
        foreach ($products['items'] as $product) {
            $override = $prices[$product->id] ?? null;
            $fixed = $override && $override['price_minor'] !== null ? (string) (int) $override['price_minor'] : '';
            if ($fixed !== '' && $this->services->decimals === 0) {
                $fixed = (string) (int) $override['price_minor'];
            }
            $disc = $override && $override['discount_percent'] !== null ? (string) $override['discount_percent'] : '';
            echo '<tr><td>' . esc_html($product->name) . '</td><td>' . esc_html(Money::format($product->retailMinor, $product->decimals, $product->currency)) . '</td>';
            echo '<td><input name="price[' . esc_attr((string) $product->id) . ']" value="' . esc_attr($fixed) . '" placeholder="خالی = بدون قیمت ثابت" /></td>';
            echo '<td><input name="discount[' . esc_attr((string) $product->id) . ']" value="' . esc_attr($disc) . '" placeholder="خالی = درصد سطح" /></td></tr>';
        }
        echo '</tbody></table><p><button class="button button-primary">ذخیره قیمت‌ها</button></p></form>';
        echo '<p class="hmr-muted">قیمت ثابت را به واحد خرد ارز فروشگاه وارد کنید. برای تومان بدون اعشار، همان عدد تومان است.</p>';
        $this->close();
    }

    public function renderWallet(): void
    {
        $this->open('اصلاح کیف پول');
        $listed = $this->services->crm->list('', 1, 100);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hmr-card-form">';
        $this->fields('wallet', 'hm-reseller-wallet');
        echo '<p><label>همکار <select name="reseller_id">';
        foreach ($listed['items'] as $row) {
            if ($row['status'] !== 'approved') {
                continue;
            }
            echo '<option value="' . esc_attr((string) $row['id']) . '">' . esc_html((string) $row['display_name']) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>جهت <select name="direction"><option value="credit">افزایش موجودی</option><option value="debit">کاهش موجودی</option></select></label></p>';
        echo '<p><label>مبلغ <input name="amount" required /></label></p>';
        echo '<p><label>توضیح <textarea name="note" required rows="3"></textarea></label></p>';
        echo '<button class="button button-primary">ثبت در دفتر کل</button></form>';
        $this->close();
    }

    public function renderLogs(): void
    {
        $this->open('لاگ درخواست‌های API');
        $listed = $this->services->log->recent(max(1, (int) ($_GET['paged'] ?? 1)), 50);
        echo '<table class="widefat striped"><thead><tr><th>زمان</th><th>همکار</th><th>روش</th><th>مسیر</th><th>وضعیت</th><th>خطا</th><th>IP</th><th>ms</th><th>شناسه</th></tr></thead><tbody>';
        if ($listed['items'] === []) {
            echo '<tr><td colspan="9">درخواستی ثبت نشده است.</td></tr>';
        }
        foreach ($listed['items'] as $row) {
            echo '<tr><td>' . esc_html((string) $row['created_at']) . '</td><td>' . esc_html((string) ($row['reseller_id'] ?? '—')) . '</td>';
            echo '<td>' . esc_html((string) $row['method']) . '</td><td><code>' . esc_html((string) $row['route']) . '</code></td>';
            echo '<td>' . esc_html((string) $row['status_code']) . '</td><td>' . esc_html((string) $row['error_code']) . '</td>';
            echo '<td>' . esc_html((string) $row['ip']) . '</td><td>' . esc_html((string) $row['duration_ms']) . '</td>';
            echo '<td><code>' . esc_html((string) $row['request_id']) . '</code></td></tr>';
        }
        echo '</tbody></table>';
        $this->close();
    }

    public function renderProfit(): void
    {
        $this->open('سود هر همکار');
        echo '<p>درآمد، مبلغی است که از کیف پول همکار کم شده. بهای تمام‌شده از متای محصول در لحظهٔ خرید ذخیره می‌شود.</p>';
        $rows = $this->services->crm->profit();
        echo '<table class="widefat striped"><thead><tr><th>همکار</th><th>سفارش تحویل‌شده</th><th>درآمد</th><th>بهای تمام‌شده</th><th>سود</th><th>بدون بها</th></tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="6">هنوز فروشی ثبت نشده است.</td></tr>';
        }
        foreach ($rows as $row) {
            $revenue = (int) $row['revenue_minor'];
            $cost = (int) $row['cost_minor'];
            echo '<tr><td>' . esc_html((string) $row['display_name']) . '</td>';
            echo '<td>' . esc_html((string) $row['orders']) . '</td>';
            echo '<td>' . esc_html(Money::format($revenue, $this->services->decimals, $this->services->currency)) . '</td>';
            echo '<td>' . esc_html(Money::format($cost, $this->services->decimals, $this->services->currency)) . '</td>';
            echo '<td>' . esc_html(Money::format($revenue - $cost, $this->services->decimals, $this->services->currency)) . '</td>';
            echo '<td>' . esc_html((string) $row['unknown_cost_rows']) . '</td></tr>';
        }
        echo '</tbody></table>';
        $this->close();
    }

    public function renderSettings(): void
    {
        $this->open('اتصال به انبار اکانت');
        $table = class_exists('AIM_Install') ? \AIM_Install::table('accounts') : '(aimarket-core فعال نیست)';
        echo '<p>خرید همکاری از همان انبار aimarket-accounts انجام می‌شود. جدول <code>' . esc_html($table) . '</code> است. هر ردیف یک اکانت است و متن ورود در ستون <code>secret</code> با <code>AIM_Crypto</code> (پیشوند <code>v1:</code>) رمز شده است. وضعیت آماده <code>available</code> و بعد از تحویل <code>assigned</code> است و به سفارش ووکامرس وصل می‌شود.</p>';
        echo '<p>این افزونه رمز را دوباره رمز نمی‌کند. موقع نمایش برای همکار، همان <code>AIM_Inventory::secret()</code> رمزگشایی می‌کند.</p>';
        $this->close();
    }

    private function open(string $title): void
    {
        if (!current_user_can('manage_hm_resellers') && !current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('اجازه این کار را ندارید.', 'aimarket-reseller'), '', ['response' => 403]);
        }
        $inside = class_exists('AIM_WP_Admin') && \AIM_WP_Admin::is_panel();
        echo $inside
            ? '<div class="hmr-admin" dir="rtl" lang="fa"><h2>' . esc_html($title) . '</h2>'
            : '<div class="wrap hmr-admin" dir="rtl" lang="fa"><h1>' . esc_html($title) . '</h1>';
        $notice = sanitize_key((string) ($_GET['hmr_notice'] ?? ''));
        if ($notice === 'saved') {
            echo '<div class="notice notice-success"><p>ذخیره شد.</p></div>';
        } elseif ($notice === 'error') {
            $message = isset($_GET['hmr_message']) ? rawurldecode((string) $_GET['hmr_message']) : 'خطا';
            echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field($message)) . '</p></div>';
        }
    }

    private function close(): void
    {
        echo '</div>';
    }

    private function fields(string $op, string $back): void
    {
        echo '<input type="hidden" name="action" value="hmr_admin" />';
        echo '<input type="hidden" name="hmr_op" value="' . esc_attr($op) . '" />';
        echo '<input type="hidden" name="hmr_back" value="' . esc_attr($back) . '" />';
        if (class_exists('AIM_WP_Admin') && \AIM_WP_Admin::is_panel()) {
            echo '<input type="hidden" name="hmr_panel" value="1" />';
        }
        wp_nonce_field('hmr_admin');
    }

    private function parentSlug(): string
    {
        $filtered = apply_filters('hm_reseller_admin_parent', '');
        if (is_string($filtered) && $filtered !== '') {
            return $filtered;
        }
        global $menu;
        if (!is_array($menu)) {
            return '';
        }
        $known = ['aimarket', 'aim-panel', 'aimarket-panel', 'aimarket-core', 'aimarket_panel'];
        foreach ($menu as $item) {
            if (is_array($item) && isset($item[2]) && in_array((string) $item[2], $known, true)) {
                return (string) $item[2];
            }
        }
        return '';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'در انتظار',
            'approved' => 'فعال',
            'suspended' => 'مسدود',
            'rejected' => 'رد شده',
            default => $status,
        };
    }
}
