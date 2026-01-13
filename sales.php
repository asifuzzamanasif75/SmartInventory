<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$user_id = validateSession();

switch ($method) {
    case 'GET':
        if (isset($_GET['action']) && $_GET['action'] === 'stats') {
            getSalesStats();
        } else {
            getSales();
        }
        break;
    case 'POST':
        createSale($user_id);
        break;
    case 'DELETE':
        deleteSale($_GET['id'] ?? null);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getSales() {
    $conn = getDBConnection();
    
    $period = isset($_GET['period']) ? sanitizeInput($_GET['period']) : 'all';
    
    $sql = "SELECT s.*, p.name as product_name, p.sku, u.full_name as user_name
            FROM sales s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE 1=1";
    
    switch ($period) {
        case 'today':
            $sql .= " AND DATE(s.sale_date) = CURDATE()";
            break;
        case 'week':
            $sql .= " AND YEARWEEK(s.sale_date) = YEARWEEK(NOW())";
            break;
        case 'month':
            $sql .= " AND MONTH(s.sale_date) = MONTH(NOW()) AND YEAR(s.sale_date) = YEAR(NOW())";
            break;
    }
    
    $sql .= " ORDER BY s.sale_date DESC LIMIT 100";
    
    $result = $conn->query($sql);
    $sales = $result->fetch_all(MYSQLI_ASSOC);
    
    sendSuccess('Sales retrieved', $sales);
}

function getSalesStats() {
    $conn = getDBConnection();
    
    // Today's sales
    $today = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc();
    
    // This week's sales
    $week = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE YEARWEEK(sale_date) = YEARWEEK(NOW())")->fetch_assoc();
    
    // This month's sales
    $month = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE MONTH(sale_date) = MONTH(NOW()) AND YEAR(sale_date) = YEAR(NOW())")->fetch_assoc();
    
    // Sales trend (last 7 days)
    $trend = $conn->query("SELECT DATE(sale_date) as date, SUM(total_amount) as total 
                          FROM sales 
                          WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                          GROUP BY DATE(sale_date)
                          ORDER BY date ASC")->fetch_all(MYSQLI_ASSOC);
    
    // Best selling products
    $best_sellers = $conn->query("SELECT p.name, SUM(s.quantity) as total_sold, SUM(s.total_amount) as revenue
                                  FROM sales s
                                  LEFT JOIN products p ON s.product_id = p.id
                                  WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                  GROUP BY s.product_id
                                  ORDER BY total_sold DESC
                                  LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    
    sendSuccess('Sales statistics retrieved', [
        'today' => $today['total'],
        'week' => $week['total'],
        'month' => $month['total'],
        'trend' => $trend,
        'best_sellers' => $best_sellers
    ]);
}

function createSale($user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['product_id', 'quantity'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            sendError("$field is required");
        }
    }
    
    $conn = getDBConnection();
    $conn->begin_transaction();
    
    try {
        $product_id = (int)$data['product_id'];
        $quantity = (int)$data['quantity'];
        $customer_name = isset($data['customer_name']) ? sanitizeInput($data['customer_name']) : null;
        
        // Get product details
        $stmt = $conn->prepare("SELECT stock_quantity, selling_price FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        if ($product['stock_quantity'] < $quantity) {
            throw new Exception('Insufficient stock');
        }
        
        $unit_price = isset($data['unit_price']) ? (float)$data['unit_price'] : $product['selling_price'];
        $total_amount = $unit_price * $quantity;
        
        // Insert sale
        $stmt = $conn->prepare("INSERT INTO sales (product_id, quantity, unit_price, total_amount, customer_name, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiddsi", $product_id, $quantity, $unit_price, $total_amount, $customer_name, $user_id);
        $stmt->execute();
        $sale_id = $conn->insert_id;
        
        // Update stock
        $new_stock = $product['stock_quantity'] - $quantity;
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_stock, $product_id);
        $stmt->execute();
        
        // Log stock change
        $stmt = $conn->prepare("INSERT INTO stock_logs (product_id, type, quantity, previous_stock, new_stock, reference_id, user_id) VALUES (?, 'sale', ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiii", $product_id, $quantity, $product['stock_quantity'], $new_stock, $sale_id, $user_id);
        $stmt->execute();
        
        $conn->commit();
        sendSuccess('Sale recorded successfully', ['id' => $sale_id]);
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError($e->getMessage());
    }
}

function deleteSale($id) {
    if (!$id) {
        sendError('Sale ID is required');
    }
    
    $conn = getDBConnection();
    $conn->begin_transaction();
    
    try {
        // Get sale details
        $stmt = $conn->prepare("SELECT product_id, quantity FROM sales WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $sale = $stmt->get_result()->fetch_assoc();
        
        if (!$sale) {
            throw new Exception('Sale not found');
        }
        
        // Restore stock
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $stmt->bind_param("ii", $sale['quantity'], $sale['product_id']);
        $stmt->execute();
        
        // Delete sale
        $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $conn->commit();
        sendSuccess('Sale deleted successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError($e->getMessage());
    }
}
?>