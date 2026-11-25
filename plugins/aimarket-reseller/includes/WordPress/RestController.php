<?php
/**
 * /wp-json/hooshmarket/v1
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\ApiException;
use HooshMarket\Reseller\Domain\AuthContext;
use HooshMarket\Reseller\Domain\Clock;
use HooshMarket\Reseller\Domain\Money;
use WP_REST_Request;
use WP_REST_Response;

final class RestController
{
    public function __construct(private readonly Services $services)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $routes = [
                ['/products', 'GET', 'products'],
                ['/orders', 'GET', 'orders'],
                ['/orders', 'POST', 'createOrder'],
                ['/orders/(?P<id>\d+)', 'GET', 'order'],
                ['/wallet', 'GET', 'wallet'],
                ['/wallet/transactions', 'GET', 'transactions'],
                ['/me', 'GET', 'me'],
                ['/me/webhook', 'PUT', 'webhook'],
            ];
            foreach ($routes as [$path, $method, $callback]) {
                register_rest_route('hooshmarket/v1', $path, [
                    'methods' => $method,
                    'callback' => function (WP_REST_Request $request) use ($callback) {
                        return $this->guard($request, $callback);
                    },
                    'permission_callback' => '__return_true',
                ]);
            }
        });
    }

    private function guard(WP_REST_Request $request, string $callback): WP_REST_Response
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        $resellerId = null;
        $keyId = null;
        $status = 500;
        $errorCode = '';
        $context = null;
        try {
            $context = $this->services->auth->authenticate($this->bearer($request), $this->clientIp($request));
            $resellerId = $context->resellerId;
            $keyId = $context->keyId;
            $response = $this->{$callback}($request, $context, $requestId);
            $status = $response->get_status();
            $data = $response->get_data();
            if (is_array($data) && isset($data['error']['code'])) {
                $errorCode = (string) $data['error']['code'];
            }
        } catch (ApiException $e) {
            $status = $e->httpStatus;
            $errorCode = $e->errorCode;
            $response = $this->error($e->errorCode, $e->getMessage(), $e->httpStatus, $requestId);
        } catch (\Throwable $e) {
            $status = 500;
            $errorCode = 'internal_error';
            error_log('hm-reseller ' . $requestId . ' ' . $e->getMessage());
            $response = $this->error('internal_error', 'خطای داخلی. شناسه درخواست را به پشتیبانی بدهید.', 500, $requestId);
        }
        if ($context instanceof AuthContext) {
            $response->header('X-RateLimit-Limit', (string) $context->rateLimit);
            $response->header(
                'X-RateLimit-Remaining',
                (string) $this->services->auth->remaining($context->keyId, $context->rateLimit)
            );
        }
        $response->header('X-Request-Id', $requestId);
        try {
            $this->services->log->write(
                $requestId,
                $resellerId,
                $keyId,
                $request->get_method(),
                (string) $request->get_route(),
                $status,
                $this->clientIp($request),
                (int) round((microtime(true) - $started) * 1000),
                $errorCode
            );
        } catch (\Throwable $e) {
            error_log('hm-reseller log ' . $e->getMessage());
        }
        return $response;
    }

    private function products(WP_REST_Request $request, AuthContext $context, string $requestId): WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $perPage = (int) $request->get_param('per_page');
        if ($perPage < 1) {
            $perPage = 20;
        }
        $search = sanitize_text_field((string) $request->get_param('search'));
        $listed = $this->services->catalog->list($page, $perPage, $search);
        $tierId = (int) ($context->reseller['tier_id'] ?? 0);
        $items = [];
        foreach ($listed['items'] as $product) {
            $quote = $this->services->pricing->quote($tierId > 0 ? $tierId : null, $product);
            $stock = null;
            try {
                $stock = $this->services->fulfillment->stock($product->id);
            } catch (ApiException) {
                $stock = null;
            }
            $items[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'currency' => $product->currency,
                'retail_price' => $quote->retailMinor,
                'price' => $quote->priceMinor,
                'discount_percent' => $quote->discountPercent,
                'stock' => $stock,
                'in_stock' => $stock !== null && $stock > 0,
            ];
        }
        return $this->ok($items, [
            'page' => $page,
            'per_page' => min(100, $perPage),
            'total' => $listed['total'],
            'request_id' => $requestId,
        ]);
    }

    private function createOrder(WP_REST_Request $request, AuthContext $context, string $requestId): WP_REST_Response
    {
        $key = (string) $request->get_header('idempotency_key');
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        $productId = (int) ($body['product_id'] ?? 0);
        $quantity = (int) ($body['quantity'] ?? 1);
        $reference = sanitize_text_field((string) ($body['reference'] ?? ''));
        if ($productId <= 0) {
            throw new ApiException('invalid_product', 'شناسه محصول الزامی است.', 422);
        }
        $this->services->fulfillment->setCustomer((int) ($context->reseller['user_id'] ?? 0));
        $result = $this->services->orders->place($context->resellerId, $productId, $quantity, $reference, $key);
        if (empty($result['replayed']) && !empty($result['event'])) {
            $this->services->webhooks->enqueue(
                $context->resellerId,
                (string) $result['event'],
                (int) $result['body']['id'],
                $result['body']
            );
        }
        $response = $this->ok($result['body'], ['request_id' => $requestId], (int) $result['http_status']);
        if (!empty($result['replayed'])) {
            $response->header('Idempotent-Replayed', 'true');
        }
        return $response;
    }

    private function order(WP_REST_Request $request, AuthContext $context, string $requestId): WP_REST_Response
    {
        $order = $this->services->orders->get($context->resellerId, (int) $request->get_param('id'));
        return $this->ok($order, ['request_id' => $requestId]);
    }

    private function orders(WP_REST_Request $request, AuthContext $context, string $requestId): WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $perPage = (int) $request->get_param('per_page');
        if ($perPage < 1) {
            $perPage = 20;
        }
        $status = sanitize_key((string) $request->get_param('status'));
        $listed = $this->services->orders->list($context->resellerId, $page, $perPage, $status);
        return $this->ok($listed['items'], [
            'page' => $page,
            'per_page' => min(100, $perPage),
            'total' => $listed['total'],
            'request_id' => $requestId,
        ]);
    }

    private function wallet(WP_REST_Request $request, AuthContext $context, string $requestId): WP_REST_Response
    {
        unset($request);
        $row = $this->services->wallet->balance($context->resellerId);
        if ($row === null) {
            throw new ApiException('wallet_missing', 'کیف پول این همکار ساخته نشده است.', 409);
        }
        return $this->ok([
            'balance' => (int) $row['balance_minor'],
            'currency' => (string) $row['currency'],
            'formatted' => Money::format((int) $row['balance_minor'], $this->services->decimals, (string) $row['currency']),
            'updated_at' => Clock::iso((string) $row['updated_at']),
        ], ['request_id' => $requestId]);
    }

    private function transactions(WP_REST_Request $request, AuthContext $context, string $requestId): WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $perPage = (int) $request->get_param('per_page');
        if ($perPage < 1) {
            $perPage = 20;
        }
        $listed = $this->services->wallet->transactions($context->resellerId, $page, $perPage);
        $items = [];
        foreach ($listed['items'] as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'direction' => (string) $row['direction'],
                'type' => (string) $row['entry_type'],
                'amount' => (int) $row['amount_minor'],
                'balance_after' => (int) $row['balance_after'],
                'reference_type' => (string) $row['reference_type'],
                'reference_id' => (string) $row['reference_id'],
                'note' => (string) ($row['note'] ?? ''),
                'created_at' => Clock::iso((string) $row['created_at']),
            ];
        }
        return $this->ok($items, [
            'page' => $page,
            'per_page' => min(100, $perPage),
            'total' => $listed['total'],
            'request_id' => $requestId,
        ]);
    }

    private function me(WP_REST_Request $request, AuthContext $context, string $requestId): WP_REST_Response
    {
        unset($request);
        $reseller = $context->reseller;
        $tier = null;
        if (!empty($reseller['tier_id'])) {
            foreach ($this->services->crm->tiers() as $row) {
                if ((int) $row['id'] === (int) $reseller['tier_id']) {
                    $tier = [
                        'id' => (int) $row['id'],
                        'slug' => (string) $row['slug'],
                        'name' => (string) $row['name'],
                        'discount_percent' => (float) $row['discount_percent'],
                    ];
                }
            }
        }
        return $this->ok([
            'id' => $context->resellerId,
            'name' => (string) $reseller['display_name'],
            'status' => (string) $reseller['status'],
            'tier' => $tier,
            'webhook_url' => (string) ($reseller['webhook_url'] ?? ''),
            'webhook_secret' => $this->services->crm->revealSecret($reseller, $this->services->crypto),
        ], ['request_id' => $requestId]);
    }

    private function webhook(WP_REST_Request $request, AuthContext $context, string $requestId): WP_REST_Response
    {
        $body = $request->get_json_params();
        $url = is_array($body) ? (string) ($body['url'] ?? '') : '';
        $this->services->crm->setWebhook($context->resellerId, esc_url_raw($url));
        return $this->ok(['webhook_url' => $url], ['request_id' => $requestId]);
    }

    private function bearer(WP_REST_Request $request): string
    {
        $header = (string) $request->get_header('authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $match)) {
            throw new ApiException('invalid_api_key', 'هدر Authorization با Bearer token الزامی است.', 401);
        }
        return $match[1];
    }

    private function clientIp(WP_REST_Request $request): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $filtered = apply_filters('hm_reseller_client_ip', $ip, $request);
        return is_string($filtered) ? $filtered : $ip;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function ok(mixed $data, array $meta, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response(['data' => $data, 'meta' => $meta], $status);
    }

    private function error(string $code, string $message, int $status, string $requestId): WP_REST_Response
    {
        return new WP_REST_Response([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $requestId,
            ],
        ], $status);
    }
}
