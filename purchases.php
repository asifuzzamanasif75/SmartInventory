<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$user_id = validateSession();

switch ($method) {
    case 'GET':
        getPurchases();
        break;
    case 'POST':
        createPurchase($user_id);
        break;
    case 'DELETE':
        deletePurchase($_GET['id'] ?? null);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getPurchases() {
    $conn = getDBConnection();
    
    $sql = "SELECT pu.*, p.name as product_name, p.sku, s.name as supplier_name, u.full_name as user_name
            FROM purchases pu
            LEFT JOIN products p ON pu.product_id = p.id
            LEFT JOIN suppliers s ON pu.supplier_id = s.id
            LEFT JOIN users u ON pu.user_id = u.id
            ORDER BY pu.purchase_date DESC
            LIMIT 100";
    
    $result = $conn->query($sql);
    $purchases = $result->fetch_all(MYSQLI_ASSOC);
    
    sendSuccess('Purchases retrieved', $purchases);
}

function createPurchase($user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['product_id', 'quantity', 'unit_cost'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            sendError("$field is required");
        }
    }
    
    $conn = getDBConnection();
    $conn->begin_transaction();
    
    try {
        $product_id = (int)$data['product_id'];
        $supplier_id = isset($data['supplier_id']) ? (int)$data['supplier_id'] : null;
        $quantity = (int)$data['quantity'];
        $unit_cost = (float)$data['unit_cost'];
        $total_cost = $unit_cost * $quantity;
        
        // Get current stock
        $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        // Insert purchase
        $stmt = $conn->prepare("INSERT INTO purchases (product_id, supplier_id, quantity, unit_cost, total_cost, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiddi", $product_id, $supplier_id, $quantity, $unit_cost, $total_cost, $user_id);
        $stmt->execute();
        $purchase_id = $conn->insert_id;
        
        // Update stock
        $new_stock = $product['stock_quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_stock, $product_id);
        $stmt->execute();
        
        // Log stock change
        $stmt = $conn->prepare("INSERT INTO stock_logs (product_id, type, quantity, previous_stock, new_stock, reference_id, user_id) VALUES (?, 'purchase', ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiii", $product_id, $quantity, $product['stock_quantity'], $new_stock, $purchase_id, $user_id);
        $stmt->execute();
        
        $conn->commit();
        sendSuccess('Purchase recorded successfully', ['id' => $purchase_id]);
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError($e->getMessage());
    }
}

function deletePurchase($id) {
    if (!$id) {
        sendError('Purchase ID is required');
    }
    
    $conn = getDBConnection();
    $conn->begin_transaction();
    
    try {
        // Get purchase details
        $stmt = $conn->prepare("SELECT product_id, quantity FROM purchases WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        
        if (!$purchase) {
            throw new Exception('Purchase not found');
        }
        
        // Reduce stock
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $stmt->bind_param("ii", $purchase['quantity'], $purchase['product_id']);
        $stmt->execute();
        
        // Delete purchase
        $stmt = $conn->prepare("DELETE FROM purchases WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $conn->commit();
        sendSuccess('Purchase deleted successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError($e->getMessage());
    }
}
?>