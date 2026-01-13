let salesChart, productsChart;

// Load dashboard data
window.addEventListener('DOMContentLoaded', () => {
    loadDashboardData();
    updateCurrentDate();
});

function updateCurrentDate() {
    const el = document.getElementById('currentDate');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }
}

async function loadDashboardData() {
    try {
        const res = await fetch('api/dashboard.php');
        const data = await res.json();
        
        if (data.success) {
            updateStats(data.data.stats);
            renderSalesChart(data.data.sales_trend);
            renderProductsChart(data.data.best_sellers);
            renderLowStockTable(data.data.low_stock_products);
            renderRecentSales(data.data.recent_sales);
            renderRecentPurchases(data.data.recent_purchases);
        }
    } catch (err) {
        console.error('Failed to load dashboard:', err);
    }
}

function updateStats(stats) {
    document.getElementById('totalProducts').textContent = stats.total_products;
    document.getElementById('totalSuppliers').textContent = stats.total_suppliers;
    document.getElementById('totalSales').textContent = `$${parseFloat(stats.total_sales).toFixed(2)}`;
    document.getElementById('totalPurchases').textContent = `$${parseFloat(stats.total_purchases).toFixed(2)}`;
}

function renderSalesChart(trend) {
    const ctx = document.getElementById('salesChart');
    
    if (salesChart) {
        salesChart.destroy();
    }
    
    const labels = trend.map(item => {
        const d = new Date(item.date);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    const values = trend.map(item => parseFloat(item.total));
    
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sales Amount',
                data: values,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => '$' + value
                    }
                }
            }
        }
    });
}

function renderProductsChart(bestSellers) {
    const ctx = document.getElementById('productsChart');
    
    if (productsChart) {
        productsChart.destroy();
    }
    
    const labels = bestSellers.map(item => item.name);
    const values = bestSellers.map(item => parseInt(item.total_sold));
    
    productsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Units Sold',
                data: values,
                backgroundColor: [
                    '#4f46e5',
                    '#7c3aed',
                    '#10b981',
                    '#f59e0b',
                    '#ef4444'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function renderLowStockTable(products) {
    const tbody = document.getElementById('lowStockTable');
    
    if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No low stock products</td></tr>';
        return;
    }
    
    tbody.innerHTML = products.map(p => `
        <tr>
            <td>${p.name}</td>
            <td>${p.sku}</td>
            <td>${p.stock_quantity}</td>
            <td>${p.min_stock_level}</td>
            <td>
                <span class="badge ${p.stock_quantity === 0 ? 'badge-danger' : 'badge-warning'}">
                    ${p.stock_quantity === 0 ? 'Out of Stock' : 'Low Stock'}
                </span>
            </td>
        </tr>
    `).join('');
}

function renderRecentSales(sales) {
    const tbody = document.getElementById('recentSalesTable');
    
    if (sales.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No recent sales</td></tr>';
        return;
    }
    
    tbody.innerHTML = sales.map(s => `
        <tr>
            <td>${s.product_name}</td>
            <td>${s.quantity}</td>
            <td>$${parseFloat(s.total_amount).toFixed(2)}</td>
            <td>${new Date(s.sale_date).toLocaleString()}</td>
        </tr>
    `).join('');
}

function renderRecentPurchases(purchases) {
    const tbody = document.getElementById('recentPurchasesTable');
    
    if (purchases.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No recent purchases</td></tr>';
        return;
    }
    
    tbody.innerHTML = purchases.map(p => `
        <tr>
            <td>${p.product_name}</td>
            <td>${p.quantity}</td>
            <td>$${parseFloat(p.total_cost).toFixed(2)}</td>
            <td>${new Date(p.purchase_date).toLocaleString()}</td>
        </tr>
    `).join('');
}