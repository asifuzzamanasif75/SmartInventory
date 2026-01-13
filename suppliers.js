let suppliers = [];

window.addEventListener('DOMContentLoaded', () => {
    loadSuppliers();
});

async function loadSuppliers() {
    try {
        const res = await fetch('api/suppliers.php');
        const data = await res.json();
        
        if (data.success) {
            suppliers = data.data;
            renderSuppliers(suppliers);
        }
    } catch (err) {
        console.error('Failed to load suppliers:', err);
    }
}

function renderSuppliers(data) {
    const tbody = document.getElementById('suppliersTable');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No suppliers found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.map(s => `
        <tr>
            <td><strong>${s.name}</strong></td>
            <td>${s.contact_person || '-'}</td>
            <td>${s.phone || '-'}</td>
            <td>${s.email || '-'}</td>
            <td>${s.product_count || 0}</td>
            <td>
                <button class="action-btn edit" onclick="editSupplier(${s.id})">Edit</button>
                <button class="action-btn delete" onclick="deleteSupplier(${s.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

function openSupplierModal(id = null) {
    const modal = document.getElementById('supplierModal');
    const form = document.getElementById('supplierForm');
    const title = document.getElementById('modalTitle');
    
    form.reset();
    document.getElementById('supplierId').value = '';
    title.textContent = 'Add Supplier';
    
    if (id) {
        const supplier = suppliers.find(s => s.id == id);
        if (supplier) {
            document.getElementById('supplierId').value = supplier.id;
            document.getElementById('supplierName').value = supplier.name;
            document.getElementById('contactPerson').value = supplier.contact_person || '';
            document.getElementById('phone').value = supplier.phone || '';
            document.getElementById('email').value = supplier.email || '';
            document.getElementById('address').value = supplier.address || '';
            
            title.textContent = 'Edit Supplier';
        }
    }
    
    modal.style.display = 'block';
}

function closeSupplierModal() {
    document.getElementById('supplierModal').style.display = 'none';
}

function editSupplier(id) {
    openSupplierModal(id);
}

async function deleteSupplier(id) {
    if (!confirm('Are you sure you want to delete this supplier?')) return;
    
    try {
        const res = await fetch(`api/suppliers.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert('Supplier deleted successfully');
            loadSuppliers();
        } else {
            alert('Failed to delete supplier: ' + data.message);
        }
    } catch (err) {
        console.error('Delete failed:', err);
        alert('Failed to delete supplier');
    }
}

document.getElementById('supplierForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = document.getElementById('saveSupplierBtn');
    const alert = document.getElementById('supplierAlert');
    
    btn.disabled = true;
    btn.textContent = 'Saving...';
    alert.style.display = 'none';
    
    const id = document.getElementById('supplierId').value;
    const supplierData = {
        name: document.getElementById('supplierName').value,
        contact_person: document.getElementById('contactPerson').value,
        phone: document.getElementById('phone').value,
        email: document.getElementById('email').value,
        address: document.getElementById('address').value
    };
    
    try {
        const method = id ? 'PUT' : 'POST';
        
        if (id) {
            supplierData.id = id;
        }
        
        const res = await fetch('api/suppliers.php', {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(supplierData)
        });
        
        const data = await res.json();
        
        if (data.success) {
            alert.className = 'alert alert-success';
            alert.textContent = id ? 'Supplier updated successfully!' : 'Supplier created successfully!';
            alert.style.display = 'block';
            
            setTimeout(() => {
                closeSupplierModal();
                loadSuppliers();
            }, 1500);
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        alert.className = 'alert alert-error';
        alert.textContent = err.message || 'Failed to save supplier';
        alert.style.display = 'block';
        
        btn.disabled = false;
        btn.textContent = id ? 'Update Supplier' : 'Save Supplier';
    }
});

window.onclick = (e) => {
    const modal = document.getElementById('supplierModal');
    if (e.target === modal) {
        closeSupplierModal();
    }
};