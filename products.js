let products = [];
let categories = [];
let suppliers = [];

window.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    loadCategories();
    loadSuppliers();
    
    document.getElementById('searchInput').addEventListener('input', filterProducts);
    document.getElementById('categoryFilter').addEventListener('change', filterProducts);
});

async function loadProducts() {
    try {
        const res = await fetch('api/products.php');
        const data = await res.json();
        
        if (data.success) {
            products = data.data;
            renderProducts(products);
        }
    } catch (err) {
        console.error('Failed to load products:', err);
    }
}

async function loadCategories() {
    try {
        const res = await fetch('api/dashboard.php');
        const data = await res.json();
        
        if (data.success) {
            categories = data.data.categories;
            
            const selects = document.querySelectorAll('#categoryFilter, #productCategory');
            selects.forEach(select => {
                const options = categories.map(c => 
                    `<option value="${c.id}">${c.name}</option>`
                ).join('');
                
                if (select.id === 'categoryFilter') {
                    select.innerHTML = '<option value="">All Categories</option>' + options;
                } else {
                    select.innerHTML = '<option value="">Select Category</option>' + options;
                }
            });
        }
    } catch (err) {
        console.error('Failed to load categories:', err);
    }
}

async function loadSuppliers() {
    try {
        const res = await fetch('api/suppliers.php');
        const data = await res.json();
        
        if (data.success) {
            suppliers = data.data;
            
            const select = document.getElementById('productSupplier');
            select.innerHTML = '<option value="">Select Supplier</option>' + 
                suppliers.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
        }
    } catch (err) {
        console.error('Failed to load suppliers:', err);
    }
}

function renderProducts(data) {
    const tbody = document.getElementById('productsTable');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No products found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.map(p => `
        <tr>
            <td>${p.sku}</td>
            <td>${p.name}</td>
            <td>${p.category_name || '-'}</td>
            <td>$${parseFloat(p.cost_price).toFixed(2)}</td>
            <td>$${parseFloat(p.selling_price).toFixed(2)}</td>
            <td>${p.stock_quantity}</td>
            <td>
                <span class="badge ${getStockStatusBadge(p.stock_status)}">
                    ${p.stock_status}
                </span>
            </td>
            <td>
                <button class="action-btn edit" onclick="editProduct(${p.id})">Edit</button>
                <button class="action-btn delete" onclick="deleteProduct(${p.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

function getStockStatusBadge(status) {
    switch (status) {
        case 'In Stock': return 'badge-success';
        case 'Low Stock': return 'badge-warning';
        case 'Out of Stock': return 'badge-danger';
        default: return 'badge-success';
    }
}

function filterProducts() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    
    let filtered = products;
    
    if (search) {
        filtered = filtered.filter(p => 
            p.name.toLowerCase().includes(search) || 
            p.sku.toLowerCase().includes(search) ||
            (p.barcode && p.barcode.toLowerCase().includes(search))
        );
    }
    
    if (category) {
        filtered = filtered.filter(p => p.category_id == category);
    }
    
    renderProducts(filtered);
}

function openProductModal(id = null) {
    const modal = document.getElementById('productModal');
    const form = document.getElementById('productForm');
    const title = document.getElementById('modalTitle');
    
    form.reset();
    document.getElementById('productId').value = '';
    title.textContent = 'Add Product';
    
    if (id) {
        const product = products.find(p => p.id == id);
        if (product) {
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productSku').value = product.sku;
            document.getElementById('productBarcode').value = product.barcode || '';
            document.getElementById('productCategory').value = product.category_id || '';
            document.getElementById('productSupplier').value = product.supplier_id || '';
            document.getElementById('productCostPrice').value = product.cost_price;
            document.getElementById('productSellingPrice').value = product.selling_price;
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('productMinStock').value = product.min_stock_level;
            document.getElementById('productDescription').value = product.description || '';
            
            title.textContent = 'Edit Product';
        }
    }
    
    modal.style.display = 'block';
}

function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
}

function editProduct(id) {
    openProductModal(id);
}

async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    
    try {
        const res = await fetch(`api/products.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert('Product deleted successfully');
            loadProducts();
        } else {
            alert('Failed to delete product: ' + data.message);
        }
    } catch (err) {
        console.error('Delete failed:', err);
        alert('Failed to delete product');
    }
}

document.getElementById('productForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = document.getElementById('saveProductBtn');
    const alert = document.getElementById('productAlert');
    
    btn.disabled = true;
    btn.textContent = 'Saving...';
    alert.style.display = 'none';
    
    const id = document.getElementById('productId').value;
    const productData = {
        name: document.getElementById('productName').value,
        sku: document.getElementById('productSku').value,
        barcode: document.getElementById('productBarcode').value,
        category_id: document.getElementById('productCategory').value || null,
        supplier_id: document.getElementById('productSupplier').value || null,
        cost_price: document.getElementById('productCostPrice').value,
        selling_price: document.getElementById('productSellingPrice').value,
        stock_quantity: document.getElementById('productStock').value,
        min_stock_level: document.getElementById('productMinStock').value,
        description: document.getElementById('productDescription').value
    };
    
    try {
        const url = id ? 'api/products.php' : 'api/products.php';
        const method = id ? 'PUT' : 'POST';
        
        if (id) {
            productData.id = id;
        }
        
        const res = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(productData)
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert.className = 'alert alert-success';
            alert.textContent = id ? 'Product updated successfully!' : 'Product created successfully!';
            alert.style.display = 'block';
            
            setTimeout(() => {
                closeProductModal();
                loadProducts();
            }, 1500);
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        alert.className = 'alert alert-error';
        alert.textContent = err.message || 'Failed to save product';
        alert.style.display = 'block';
        
        btn.disabled = false;
        btn.textContent = id ? 'Update Product' : 'Save Product';
    }
});

window.onclick = (e) => {
    const modal = document.getElementById('productModal');
    if (e.target === modal) {
        closeProductModal();
    }
};