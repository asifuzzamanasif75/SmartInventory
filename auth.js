const API_URL = 'api/auth.php';

// Check if already logged in
window.addEventListener('DOMContentLoaded', () => {
    checkAuth();
});

async function checkAuth() {
    try {
        const res = await fetch('api/auth.php?action=check');
        const data = await res.json();
        
        if (data.success && window.location.pathname.includes('login.html')) {
            window.location.href = 'dashboard.html';
        }
    } catch (err) {
        console.log('Not authenticated');
    }
}

// Login form handler
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const btn = document.getElementById('loginBtn');
        const alert = document.getElementById('loginAlert');
        
        btn.disabled = true;
        btn.textContent = 'Logging in...';
        alert.style.display = 'none';
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        try {
            const res = await fetch(API_URL + '?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            
            const data = await res.json();
            
            if (data.success) {
                alert.className = 'alert alert-success';
                alert.textContent = 'Login successful! Redirecting...';
                alert.style.display = 'block';
                
                setTimeout(() => {
                    window.location.href = 'dashboard.html';
                }, 1000);
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            alert.className = 'alert alert-error';
            alert.textContent = err.message || 'Login failed. Please try again.';
            alert.style.display = 'block';
            
            btn.disabled = false;
            btn.textContent = 'Login';
        }
    });
}

// Logout function
async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await fetch('api/auth.php?action=logout');
            window.location.href = 'login.html';
        } catch (err) {
            console.error('Logout failed:', err);
        }
    }
}

// Protect pages
async function protectPage() {
    try {
        const res = await fetch('api/auth.php?action=check');
        const data = await res.json();
        
        if (!data.success) {
            window.location.href = 'login.html';
        } else {
            // Update user info in sidebar
            const userInfo = document.getElementById('userInfo');
            if (userInfo) {
                userInfo.innerHTML = `<strong>${data.data.full_name}</strong><br><small>${data.data.role}</small>`;
            }
        }
    } catch (err) {
        window.location.href = 'login.html';
    }
}

// Call protectPage on protected pages
if (!window.location.pathname.includes('login.html')) {
    protectPage();
}