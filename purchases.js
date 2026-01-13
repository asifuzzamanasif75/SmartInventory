let purchases = [];
let products = [];
let suppliers = [];

window.addEventListener('DOMContentLoaded', () => {
    loadPurchases();
    loadProducts();
    loadSuppliers();
    
    document.getElementById('purchaseQuantity').addEventListener('input', calculateTotalCost);
    document.getElementById('purchaseUnitCost').addEventListener('input', calculateTotalCost);
});

async function loadPurchases() {
    try {
        const res = await fetch('api/purchases.php');
        const data = await res.json();
        
        if (data.success) {
            purchases = data.data;
            renderPurchases(purchases);
        }
    } catch (err) {
        console.error('Failed to load purchases:', err);
    }
}

async function loadProducts() {
    try {
        const res = await fetch('api/products.php');
        const data = await res.json();
        
        if (data.success) {
            products = data.data;
            
            const select = document.getElementById('purchaseProduct');
            select.innerHTML = '<option value="">Select Product</option>' + 
                products.map(p => `<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');
        }
    } catch (err) {
        console.error('Failed to load products:', err);
    }
}

async function loadSuppliers() {
    try {
        const res = await fetch('api/suppliers.php');
        const data = await res.json();
        
        if (data.success) {
            suppliers = data.data;
            
            const select = document.getElementById('purchaseSupplier');
            select.innerHTML = '<option value="">Select Supplier</option>' + 
                suppliers.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
        }
    } catch (err) {
        console.error('Failed to load suppliers:', err);
    }
}

function renderPurchases(data) {
    const tbody = document.getElementById('purchasesTable');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No purchases found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.map(p => `
        <tr>
            <td>${p.product_name}</td>
            <td>${p.supplier_name || '-'}</td>
            <td>${p.quantity}</td>
            <td>$${parseFloat(p.unit_cost).toFixed(2)}</td>
            <td><strong>$${parseFloat(p.total_cost).toFixed(2)}</strong></td>
            <td>${new Date(p.purchase_date).toLocaleString()}</td>
            <td>
                <button class="action-btn delete" onclick="deletePurchase(${p.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

function calculateTotalCost() {
    const quantity = parseFloat(document.getElementById('purchaseQuantity').value) || 0;
    const cost = parseFloat(document.getElementById('purchaseUnitCost').value) || 0;
    const total = quantity * cost;
    
    document.getElementById('totalCost').textContent = `$${total.toFixed(2)}`;
}

function openPurchaseModal() {
    const modal = document.getElementById('purchaseModal');
    const form = document.getElementById('purchaseForm');
    
    form.reset();
    document.getElementById('totalCost').textContent = '$0.00';
    
    modal.style.display = 'block';
}

function closePurchaseModal() {
    document.getElementById('purchaseModal').style.display = 'none';
}

async function deletePurchase(id) {
    if (!confirm('Are you sure you want to delete this purchase? Stock will be reduced.')) return;
    
    try {
        const res = await fetch(`api/purchases.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert('Purchase deleted successfully');
            loadPurchases();
            loadProducts();
        } else {
            alert('Failed to delete purchase: ' + data.message);
        }
    } catch (err) {
        console.error('Delete failed:', err);
        alert('Failed to delete purchase');
    }
}

document.getElementById('purchaseForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = document.getElementById('savePurchaseBtn');
    const alert = document.getElementById('purchaseAlert');
    
    btn.disabled = true;
    btn.textContent = 'Processing...';
    alert.style.display = 'none';
    
    const purchaseData = {
        product_id: document.getElementById('purchaseProduct').value,
        supplier_id: document.getElementById('purchaseSupplier').value || null,
        quantity: document.getElementById('purchaseQuantity').value,
        unit_cost: document.getElementById('purchaseUnitCost').value
    };
    
    try {
        const res = await fetch('api/purchases.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(purchaseData)
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert.className = 'alert alert-success';
            alert.textContent = 'Purchase recorded successfully!';
            alert.style.display = 'block';
            
            setTimeout(() => {
                closePurchaseModal();
                loadPurchases();
                loadProducts();
            }, 1500);
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        alert.className = 'alert alert-error';
        alert.textContent = err.message || 'Failed to record purchase';
        alert.style.display = 'block';
        
        btn.disabled = false;
        btn.textContent = 'Record Purchase';
    }
});

window.onclick = (e) => {
    const modal = document.getElementById('purchaseModal');
    if (e.target === modal) {
        closePurchaseModal();
    }
};