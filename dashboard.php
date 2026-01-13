<?php
require_once 'config.php';

$user_id = validateSession();

getDashboardStats();

function getDashboardStats() {
    $conn = getDBConnection();
    
    // Total Products
    $total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
    
    // Total Suppliers
    $total_suppliers = $conn->query("SELECT COUNT(*) as count FROM suppliers")->fetch_assoc()['count'];
    
    // Total Sales (this month)
    $total_sales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE MONTH(sale_date) = MONTH(NOW()) AND YEAR(sale_date) = YEAR(NOW())")->fetch_assoc()['total'];
    
    // Total Purchases (this month)
    $total_purchases = $conn->query("SELECT COALESCE(SUM(total_cost), 0) as total FROM purchases WHERE MONTH(purchase_date) = MONTH(NOW()) AND YEAR(purchase_date) = YEAR(NOW())")->fetch_assoc()['total'];
    
    // Low Stock Products Count
    $low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level")->fetch_assoc()['count'];
    
    // Out of Stock Products Count
    $out_of_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0")->fetch_assoc()['count'];
    
    // Sales Trend (last 7 days)
    $sales_trend = $conn->query("SELECT DATE(sale_date) as date, COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count
                                FROM sales 
                                WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                GROUP BY DATE(sale_date)
                                ORDER BY date ASC")->fetch_all(MYSQLI_ASSOC);
    
    // Fill missing dates
    $filled_trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $found = false;
        foreach ($sales_trend as $item) {
            if ($item['date'] === $date) {
                $filled_trend[] = $item;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $filled_trend[] = ['date' => $date, 'total' => 0, 'count' => 0];
        }
    }
    
    // Best Selling Products (last 30 days)
    $best_sellers = $conn->query("SELECT p.name, p.sku, SUM(s.quantity) as total_sold, SUM(s.total_amount) as revenue
                                  FROM sales s
                                  LEFT JOIN products p ON s.product_id = p.id
                                  WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                  GROUP BY s.product_id
                                  ORDER BY total_sold DESC
                                  LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    
    // Low Stock Products
    $low_stock_products = $conn->query("SELECT p.*, c.name as category_name
                                       FROM products p
                                       LEFT JOIN categories c ON p.category_id = c.id
                                       WHERE p.stock_quantity <= p.min_stock_level
                                       ORDER BY p.stock_quantity ASC
                                       LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    
    // Recent Sales
    $recent_sales = $conn->query("SELECT s.*, p.name as product_name
                                 FROM sales s
                                 LEFT JOIN products p ON s.product_id = p.id
                                 ORDER BY s.sale_date DESC
                                 LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    
    // Recent Purchases
    $recent_purchases = $conn->query("SELECT pu.*, p.name as product_name, s.name as supplier_name
                                     FROM purchases pu
                                     LEFT JOIN products p ON pu.product_id = p.id
                                     LEFT JOIN suppliers s ON pu.supplier_id = s.id
                                     ORDER BY pu.purchase_date DESC
                                     LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    
    // Categories
    $categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    
    sendSuccess('Dashboard data retrieved', [
        'stats' => [
            'total_products' => $total_products,
            'total_suppliers' => $total_suppliers,
            'total_sales' => $total_sales,
            'total_purchases' => $total_purchases,
            'low_stock_count' => $low_stock_count,
            'out_of_stock_count' => $out_of_stock_count
        ],
        'sales_trend' => $filled_trend,
        'best_sellers' => $best_sellers,
        'low_stock_products' => $low_stock_products,
        'recent_sales' => $recent_sales,
        'recent_purchases' => $recent_purchases,
        'categories' => $categories
    ]);
}
?>