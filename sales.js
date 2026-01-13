let sales = [];
let products = [];

window.addEventListener('DOMContentLoaded', () => {
    loadSales();
    loadProducts();
    
    document.getElementById('periodFilter').addEventListener('change', loadSales);
    document.getElementById('saleQuantity').addEventListener('input', calculateTotal);
    document.getElementById('saleUnitPrice').addEventListener('input', calculateTotal);
    document.getElementById('saleProduct').addEventListener('change', onProductSelect);
});

async function loadSales() {
    try {
        const period = document.getElementById('periodFilter').value;
        const res = await fetch(`api/sales.php?period=${period}`);
        const data = await res.json();
        
        if (data.success) {
            sales = data.data;
            renderSales(sales);
        }
    } catch (err) {
        console.error('Failed to load sales:', err);
    }
}

async function loadProducts() {
    try {
        const res = await fetch('api/products.php');
        const data = await res.json();
        
        if (data.success) {
            products = data.data;
            
            const select = document.getElementById('saleProduct');
            select.innerHTML = '<option value="">Select Product</option>' + 
                products.map(p => 
                    `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.stock_quantity}">
                        ${p.name} (${p.sku}) - Stock: ${p.stock_quantity}
                    </option>`
                ).join('');
        }
    } catch (err) {
        console.error('Failed to load products:', err);
    }
}

function renderSales(data) {
    const tbody = document.getElementById('salesTable');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No sales found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.map(s => `
        <tr>
            <td>${s.product_name}</td>
            <td>${s.customer_name || '-'}</td>
            <td>${s.quantity}</td>
            <td>$${parseFloat(s.unit_price).toFixed(2)}</td>
            <td><strong>$${parseFloat(s.total_amount).toFixed(2)}</strong></td>
            <td>${new Date(s.sale_date).toLocaleString()}</td>
            <td>
                <button class="action-btn delete" onclick="deleteSale(${s.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

function onProductSelect(e) {
    const select = e.target;
    const option = select.options[select.selectedIndex];
    const price = option.getAttribute('data-price');
    const stock = option.getAttribute('data-stock');
    
    if (price) {
        document.getElementById('saleUnitPrice').value = price;
        
        const info = document.getElementById('productInfo');
        info.textContent = `Available Stock: ${stock} units`;
        info.style.display = 'block';
        
        calculateTotal();
    }
}

function calculateTotal() {
    const quantity = parseFloat(document.getElementById('saleQuantity').value) || 0;
    const price = parseFloat(document.getElementById('saleUnitPrice').value) || 0;
    const total = quantity * price;
    
    document.getElementById('totalAmount').textContent = `$${total.toFixed(2)}`;
}

function openSaleModal() {
    const modal = document.getElementById('saleModal');
    const form = document.getElementById('saleForm');
    
    form.reset();
    document.getElementById('totalAmount').textContent = '$0.00';
    document.getElementById('productInfo').style.display = 'none';
    
    modal.style.display = 'block';
}

function closeSaleModal() {
    document.getElementById('saleModal').style.display = 'none';
}

async function deleteSale(id) {
    if (!confirm('Are you sure you want to delete this sale? Stock will be restored.')) return;
    
    try {
        const res = await fetch(`api/sales.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert('Sale deleted successfully');
            loadSales();
            loadProducts();
        } else {
            alert('Failed to delete sale: ' + data.message);
        }
    } catch (err) {
        console.error('Delete failed:', err);
        alert('Failed to delete sale');
    }
}

document.getElementById('saleForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = document.getElementById('saveSaleBtn');
    const alert = document.getElementById('saleAlert');
    
    btn.disabled = true;
    btn.textContent = 'Processing...';
    alert.style.display = 'none';
    
    const saleData = {
        product_id: document.getElementById('saleProduct').value,
        quantity: document.getElementById('saleQuantity').value,
        unit_price: document.getElementById('saleUnitPrice').value,
        customer_name: document.getElementById('customerName').value
    };
    
    try {
        const res = await fetch('api/sales.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(saleData)
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert.className = 'alert alert-success';
            alert.textContent = 'Sale recorded successfully!';
            alert.style.display = 'block';
            
            setTimeout(() => {
                closeSaleModal();
                loadSales();
                loadProducts();
            }, 1500);
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        alert.className = 'alert alert-error';
        alert.textContent = err.message || 'Failed to record sale';
        alert.style.display = 'block';
        
        btn.disabled = false;
        btn.textContent = 'Record Sale';
    }
});

window.onclick = (e) => {
    const modal = document.getElementById('saleModal');
    if (e.target === modal) {
        closeSaleModal();
    }
};