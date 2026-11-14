<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\OrderController;
use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\AdminController;
use App\Controllers\WebhookController;

class App
{
    private Router $router;

    public function __construct()
    {
        // Load configuration and set timezone
        Config::load();
        date_default_timezone_set(Config::get('timezone', 'Asia/Colombo'));

        $this->router = new Router();
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $prefix = Config::get('api_prefix', '/api/v1');

        $this->router->group($prefix, [], function (Router $r) {
            // Health check
            $r->get('/health', function () {
                return Response::success([
                    'status' => 'operational',
                    'timestamp' => gmdate('Y-m-d H:i:s') . ' UTC',
                    'php_version' => PHP_VERSION,
                    'app' => 'P and P Pet Shop API'
                ], 'System operational');
            });

            // PUBLIC ENDPOINTS
            $r->get('/products', [ProductController::class, 'index']);
            $r->get('/products/{slug}', [ProductController::class, 'show']);
            $r->get('/categories', [CategoryController::class, 'index']);
            $r->get('/search', [ProductController::class, 'search']);
            $r->get('/products/featured', [ProductController::class, 'featured']);
            $r->post('/cart/validate', [CartController::class, 'validateCart']);
            $r->post('/checkout', [CheckoutController::class, 'process']);
            $r->get('/orders/{orderNumber}/tracking', [OrderController::class, 'track']);

            // CUSTOMER AUTH & ACCOUNT ENDPOINTS
            $r->post('/auth/register', [AuthController::class, 'register']);
            $r->post('/auth/login', [AuthController::class, 'customerLogin']);
            $r->post('/auth/logout', [AuthController::class, 'logout']);
            $r->get('/customer/profile', [CustomerController::class, 'profile'], [\App\Middleware\AuthMiddleware::class]);
            $r->patch('/customer/profile', [CustomerController::class, 'updateProfile'], [\App\Middleware\AuthMiddleware::class]);
            $r->get('/customer/orders', [CustomerController::class, 'orders'], [\App\Middleware\AuthMiddleware::class]);
            $r->get('/customer/addresses', [CustomerController::class, 'addresses'], [\App\Middleware\AuthMiddleware::class]);
            $r->post('/customer/addresses', [CustomerController::class, 'addAddress'], [\App\Middleware\AuthMiddleware::class]);

            // ADMIN AUTH & DASHBOARD ENDPOINTS
            $r->post('/admin/auth/login', [AuthController::class, 'adminLogin']);
            $r->post('/admin/auth/logout', [AuthController::class, 'adminLogout']);
            
            $r->get('/admin/dashboard', [AdminController::class, 'dashboard'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/orders', [AdminController::class, 'getOrders'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/orders/{id}', [AdminController::class, 'getOrderDetails'], [\App\Middleware\AdminMiddleware::class]);
            $r->patch('/admin/orders/{id}', [AdminController::class, 'updateOrderStatus'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/products', [AdminController::class, 'getProducts'], [\App\Middleware\AdminMiddleware::class]);
            $r->post('/admin/products', [AdminController::class, 'createProduct'], [\App\Middleware\AdminMiddleware::class]);
            $r->patch('/admin/products/{id}', [AdminController::class, 'updateProduct'], [\App\Middleware\AdminMiddleware::class]);
            $r->delete('/admin/products/{id}', [AdminController::class, 'deleteProduct'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/categories', [AdminController::class, 'getCategories'], [\App\Middleware\AdminMiddleware::class]);
            $r->post('/admin/categories', [AdminController::class, 'createCategory'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/customers', [AdminController::class, 'getCustomers'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/inventory', [AdminController::class, 'getInventory'], [\App\Middleware\AdminMiddleware::class]);
            $r->patch('/admin/inventory/{id}', [AdminController::class, 'updateInventory'], [\App\Middleware\AdminMiddleware::class]);
            
            // ADMIN UBER EATS INTEGRATION ENDPOINTS
            $r->get('/admin/uber/status', [AdminController::class, 'uberStatus'], [\App\Middleware\AdminMiddleware::class]);
            $r->post('/admin/uber/sync', [AdminController::class, 'uberSync'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/uber/sync-logs', [AdminController::class, 'uberSyncLogs'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/uber/mappings', [AdminController::class, 'uberMappings'], [\App\Middleware\AdminMiddleware::class]);
            $r->get('/admin/settings', [AdminController::class, 'getSettings'], [\App\Middleware\AdminMiddleware::class]);
            $r->post('/admin/settings', [AdminController::class, 'saveSettings'], [\App\Middleware\AdminMiddleware::class]);

            // UBER WEBHOOK ENDPOINTS
            $r->post('/webhooks/uber', [WebhookController::class, 'handleUberWebhook']);
        });
    }

    public function run(): void
    {
        $startTime = microtime(true);
        $request = new Request();

        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $e) {
            Logger::error("Unhandled exception: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = Config::isDebug() ? $e->getMessage() : 'An internal server error occurred';
            $response = Response::error($message, 500);
        }

        $durationMs = (int)round((microtime(true) - $startTime) * 1000);
        $this->logApiCall($request, $response, $durationMs);

        $response->send();
    }

    private function logApiCall(Request $request, Response $response, int $durationMs): void
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO api_logs (client_ip, method, path, request_headers, request_body, response_status, execution_time_ms)
                VALUES (:ip, :method, :path, :headers, :body, :status, :time)
            ");
            
            // Mask sensitive data in request body before logging
            $body = $request->getBody();
            if (isset($body['password'])) $body['password'] = '***';
            if (isset($body['password_hash'])) $body['password_hash'] = '***';

            $stmt->execute([
                'ip' => $request->getClientIp(),
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
                'headers' => json_encode(['User-Agent' => $request->getHeader('user-agent')]),
                'body' => json_encode($body),
                'status' => http_response_code(),
                'time' => $durationMs
            ]);
        } catch (Throwable) {
            // Never fail API response if logging table fails
        }
    }
}

