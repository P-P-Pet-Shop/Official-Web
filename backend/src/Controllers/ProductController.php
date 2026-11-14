<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProductRepository;

class ProductController
{
    private ProductRepository $repo;

    public function __construct()
    {
        $this->repo = new ProductRepository();
    }

    public function index(Request $request): Response
    {
        $category = $request->getQuery('category');
        $search = $request->getQuery('search');
        $sort = $request->getQuery('sort', 'latest');
        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = min(50, max(1, (int)$request->getQuery('per_page', 12)));

        $filters = array_filter([
            'category' => $category,
            'search' => $search,
            'sort' => $sort,
        ]);

        $result = $this->repo->findPaginated($filters, $page, $perPage);

        return Response::success($result['items'], 'Products retrieved successfully', 200, $result['pagination']);
    }

    public function show(Request $request, array $params): Response
    {
        $slug = $params['slug'] ?? '';
        $product = $this->repo->findBySlug($slug);

        if (!$product) {
            return Response::error('Product not found or inactive', 404, null, 'PRODUCT_NOT_FOUND');
        }

        return Response::success($product, 'Product details retrieved successfully');
    }

    public function search(Request $request): Response
    {
        $query = trim((string)$request->getQuery('q', ''));
        if (strlen($query) < 2) {
            return Response::success([], 'Please enter at least 2 characters');
        }

        $result = $this->repo->findPaginated(['search' => $query], 1, 10);
        return Response::success($result['items'], 'Search results');
    }

    public function featured(Request $request): Response
    {
        $result = $this->repo->findPaginated(['featured' => 1], 1, 8);
        return Response::success($result['items'], 'Featured products');
    }
}

