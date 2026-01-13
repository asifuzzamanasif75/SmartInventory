<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$user_id = validateSession();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getSupplier($_GET['id']);
        } else {
            getSuppliers();
        }
        break;
    case 'POST':
        createSupplier();
        break;
    case 'PUT':
        updateSupplier();
        break;
    case 'DELETE':
        deleteSupplier($_GET['id'] ?? null);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getSuppliers() {
    $conn = getDBConnection();
    
    $sql = "SELECT s.*, COUNT(p.id) as product_count
            FROM suppliers s
            LEFT JOIN products p ON s.id = p.supplier_id
            GROUP BY s.id
            ORDER BY s.created_at DESC";
    
    $result = $conn->query($sql);
    $suppliers = $result->fetch_all(MYSQLI_ASSOC);
    
    sendSuccess('Suppliers retrieved', $suppliers);
}

function getSupplier($id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT s.*, COUNT(p.id) as product_count
                           FROM suppliers s
                           LEFT JOIN products p ON s.id = p.supplier_id
                           WHERE s.id = ?
                           GROUP BY s.id");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Supplier not found', 404);
    }
    
    sendSuccess('Supplier retrieved', $result->fetch_assoc());
}

function createSupplier() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || empty($data['name'])) {
        sendError('Supplier name is required');
    }
    
    $conn = getDBConnection();
    
    $name = sanitizeInput($data['name']);
    $contact_person = isset($data['contact_person']) ? sanitizeInput($data['contact_person']) : null;
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : null;
    $email = isset($data['email']) ? sanitizeInput($data['email']) : null;
    $address = isset($data['address']) ? sanitizeInput($data['address']) : null;
    
    $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $contact_person, $phone, $email, $address);
    
    if ($stmt->execute()) {
        $supplier_id = $conn->insert_id;
        sendSuccess('Supplier created successfully', ['id' => $supplier_id]);
    } else {
        sendError('Failed to create supplier');
    }
}

function updateSupplier() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        sendError('Supplier ID is required');
    }
    
    $conn = getDBConnection();
    
    $id = (int)$data['id'];
    $name = sanitizeInput($data['name']);
    $contact_person = isset($data['contact_person']) ? sanitizeInput($data['contact_person']) : null;
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : null;
    $email = isset($data['email']) ? sanitizeInput($data['email']) : null;
    $address = isset($data['address']) ? sanitizeInput($data['address']) : null;
    
    $stmt = $conn->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?");
    $stmt->bind_param("sssssi", $name, $contact_person, $phone, $email, $address, $id);
    
    if ($stmt->execute()) {
        sendSuccess('Supplier updated successfully');
    } else {
        sendError('Failed to update supplier');
    }
}

function deleteSupplier($id) {
    if (!$id) {
        sendError('Supplier ID is required');
    }
    
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        sendSuccess('Supplier deleted successfully');
    } else {
        sendError('Failed to delete supplier');
    }
}
?>