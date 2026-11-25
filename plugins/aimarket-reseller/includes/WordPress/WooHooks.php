<?php
/**
 * Wallet top-up products and the account-product checkboxes.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\Money;

final class WooHooks
{
    public function __construct(private readonly Services $services)
    {
    }

    public function register(): void
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'tab']);
        add_action('woocommerce_product_data_panels', [$this, 'panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save']);
        add_action('woocommerce_payment_complete', [$this, 'credit']);
        add_action('woocommerce_order_status_completed', [$this, 'credit']);
        add_action('woocommerce_order_status_processing', [$this, 'credit']);
    }

    /**
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function tab(array $tabs): array
    {
        $tabs['hmr'] = [
            'label' => 'همکاری در فروش',
            'target' => 'hmr_product_data',
            'class' => [],
            'priority' => 80,
        ];
        return $tabs;
    }

    public function panel(): void
    {
        global $post;
        $topup = $post instanceof \WP_Post ? get_post_meta($post->ID, '_hmr_wallet_topup', true) : '';
        $hidden = $post instanceof \WP_Post ? get_post_meta($post->ID, '_hmr_api_hidden', true) : '';
        $cost = $post instanceof \WP_Post ? get_post_meta($post->ID, '_hmr_cost', true) : '';
        echo '<div id="hmr_product_data" class="panel woocommerce_options_panel" dir="rtl">';
        echo '<div class="options_group">';
        echo '<p class="form-field"><label for="_hmr_wallet_topup">شارژ کیف پول</label>';
        echo '<input type="checkbox" id="_hmr_wallet_topup" name="_hmr_wallet_topup" value="yes" ' . checked($topup, 'yes', false) . ' /> ';
        echo '<span class="description">با پرداخت این محصول، مبلغ پرداخت‌شده به کیف پول همکار اضافه می‌شود.</span></p>';
        echo '<p class="form-field"><label for="_hmr_api_hidden">مخفی در API</label>';
        echo '<input type="checkbox" id="_hmr_api_hidden" name="_hmr_api_hidden" value="yes" ' . checked($hidden, 'yes', false) . ' /> ';
        echo '<span class="description">این محصول در فهرست و سفارش همکاری نشان داده نمی‌شود.</span></p>';
        echo '<p class="form-field"><label for="_hmr_cost">بهای تمام‌شده</label>';
        echo '<input type="text" class="short" id="_hmr_cost" name="_hmr_cost" value="' . esc_attr((string) $cost) . '" />';
        echo '<span class="description">برای گزارش سود هر همکار. اگر خالی باشد و متای _aimarket_cost وجود داشته باشد، همان خوانده می‌شود.</span></p>';
        echo '</div></div>';
    }

    public function save(\WC_Product $product): void
    {
        if (!current_user_can('edit_products')) {
            return;
        }
        $product->update_meta_data('_hmr_wallet_topup', isset($_POST['_hmr_wallet_topup']) ? 'yes' : 'no');
        $product->update_meta_data('_hmr_api_hidden', isset($_POST['_hmr_api_hidden']) ? 'yes' : 'no');
        if (isset($_POST['_hmr_cost'])) {
            $product->update_meta_data('_hmr_cost', wc_clean(wp_unslash((string) $_POST['_hmr_cost'])));
        }
    }

    public function credit(int $orderId): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            return;
        }
        if ($order->get_meta('_hmr_topup_ledger_id') !== '') {
            return;
        }
        $amountMajor = 0.0;
        $hasTopup = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product instanceof \WC_Product) {
                continue;
            }
            if ((string) $product->get_meta('_hmr_wallet_topup') !== 'yes') {
                continue;
            }
            $hasTopup = true;
            $amountMajor += (float) $item->get_total();
        }
        if (!$hasTopup || $amountMajor <= 0) {
            return;
        }
        $userId = (int) $order->get_user_id();
        $reseller = $userId > 0 ? $this->services->crm->findByUser($userId) : null;
        if ($reseller === null || $reseller['status'] !== 'approved') {
            $order->add_order_note('شارژ کیف پول انجام نشد: خریدار همکار تأییدشده نیست.');
            $order->save();
            return;
        }
        try {
            $minor = Money::majorToMinor((string) $amountMajor, $this->services->decimals);
            if ($minor <= 0) {
                return;
            }
            $moved = $this->services->wallet->apply(
                (int) $reseller['id'],
                'credit',
                $minor,
                'topup_online',
                'wc_order',
                (string) $orderId,
                'شارژ آنلاین از سفارش ووکامرس #' . $orderId,
                $userId
            );
            $order->update_meta_data('_hmr_topup_ledger_id', (string) $moved['ledger_id']);
            $order->add_order_note('کیف پول همکاری به مبلغ ' . Money::format($minor, $this->services->decimals, $this->services->currency) . ' شارژ شد.');
            $order->save();
        } catch (\Throwable $e) {
            $order->add_order_note('شارژ کیف پول ناموفق بود: ' . $e->getMessage());
            $order->save();
        }
    }
}
