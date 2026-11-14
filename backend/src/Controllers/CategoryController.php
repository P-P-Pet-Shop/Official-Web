<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class CategoryController
{
    public function index(Request $request): Response
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT c.*, 
                   COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        $categories = $stmt->fetchAll();

        return Response::success($categories, 'Categories retrieved successfully');
    }
}

