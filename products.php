<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$user_id = validateSession();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getProduct($_GET['id']);
        } elseif (isset($_GET['action']) && $_GET['action'] === 'low-stock') {
            getLowStockProducts();
        } else {
            getProducts();
        }
        break;
    case 'POST':
        createProduct($user_id);
        break;
    case 'PUT':
        updateProduct($user_id);
        break;
    case 'DELETE':
        deleteProduct($_GET['id'] ?? null);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getProducts() {
    $conn = getDBConnection();
    
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    
    $sql = "SELECT p.*, c.name as category_name, s.name as supplier_name,
            CASE 
                WHEN p.stock_quantity = 0 THEN 'Out of Stock'
                WHEN p.stock_quantity <= p.min_stock_level THEN 'Low Stock'
                ELSE 'In Stock'
            END as stock_status
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE 1=1";
    
    if ($search) {
        $sql .= " AND (p.name LIKE '%$search%' OR p.sku LIKE '%$search%' OR p.barcode LIKE '%$search%')";
    }
    
    if ($category) {
        $sql .= " AND p.category_id = $category";
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $result = $conn->query($sql);
    $products = $result->fetch_all(MYSQLI_ASSOC);
    
    sendSuccess('Products retrieved', $products);
}

function getProduct($id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT p.*, c.name as category_name, s.name as supplier_name 
                           FROM products p
                           LEFT JOIN categories c ON p.category_id = c.id
                           LEFT JOIN suppliers s ON p.supplier_id = s.id
                           WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Product not found', 404);
    }
    
    sendSuccess('Product retrieved', $result->fetch_assoc());
}

function getLowStockProducts() {
    $conn = getDBConnection();
    
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.stock_quantity <= p.min_stock_level
            ORDER BY p.stock_quantity ASC";
    
    $result = $conn->query($sql);
    $products = $result->fetch_all(MYSQLI_ASSOC);
    
    sendSuccess('Low stock products retrieved', $products);
}

function createProduct($user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'sku', 'cost_price', 'selling_price'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            sendError("$field is required");
        }
    }
    
    $conn = getDBConnection();
    
    // Check if SKU exists
    $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ?");
    $stmt->bind_param("s", $data['sku']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendError('SKU already exists');
    }
    
    $name = sanitizeInput($data['name']);
    $sku = sanitizeInput($data['sku']);
    $barcode = isset($data['barcode']) ? sanitizeInput($data['barcode']) : null;
    $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
    $supplier_id = isset($data['supplier_id']) ? (int)$data['supplier_id'] : null;
    $cost_price = (float)$data['cost_price'];
    $selling_price = (float)$data['selling_price'];
    $stock_quantity = isset($data['stock_quantity']) ? (int)$data['stock_quantity'] : 0;
    $min_stock_level = isset($data['min_stock_level']) ? (int)$data['min_stock_level'] : 10;
    $description = isset($data['description']) ? sanitizeInput($data['description']) : null;
    
    $stmt = $conn->prepare("INSERT INTO products (name, sku, barcode, category_id, supplier_id, cost_price, selling_price, stock_quantity, min_stock_level, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiddiis", $name, $sku, $barcode, $category_id, $supplier_id, $cost_price, $selling_price, $stock_quantity, $min_stock_level, $description);
    
    if ($stmt->execute()) {
        $product_id = $conn->insert_id;
        sendSuccess('Product created successfully', ['id' => $product_id]);
    } else {
        sendError('Failed to create product');
    }
}

function updateProduct($user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        sendError('Product ID is required');
    }
    
    $conn = getDBConnection();
    
    $id = (int)$data['id'];
    $name = sanitizeInput($data['name']);
    $sku = sanitizeInput($data['sku']);
    $barcode = isset($data['barcode']) ? sanitizeInput($data['barcode']) : null;
    $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
    $supplier_id = isset($data['supplier_id']) ? (int)$data['supplier_id'] : null;
    $cost_price = (float)$data['cost_price'];
    $selling_price = (float)$data['selling_price'];
    $stock_quantity = (int)$data['stock_quantity'];
    $min_stock_level = (int)$data['min_stock_level'];
    $description = isset($data['description']) ? sanitizeInput($data['description']) : null;
    
    $stmt = $conn->prepare("UPDATE products SET name=?, sku=?, barcode=?, category_id=?, supplier_id=?, cost_price=?, selling_price=?, stock_quantity=?, min_stock_level=?, description=? WHERE id=?");
    $stmt->bind_param("sssiiddiixi", $name, $sku, $barcode, $category_id, $supplier_id, $cost_price, $selling_price, $stock_quantity, $min_stock_level, $description, $id);
    
    if ($stmt->execute()) {
        sendSuccess('Product updated successfully');
    } else {
        sendError('Failed to update product');
    }
}

function deleteProduct($id) {
    if (!$id) {
        sendError('Product ID is required');
    }
    
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        sendSuccess('Product deleted successfully');
    } else {
        sendError('Failed to delete product');
    }
}
?>