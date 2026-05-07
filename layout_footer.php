<!-- Global UI Enhancements -->
<div id="toast-container"></div>

<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <h3 id="modalTitle">Confirm Action</h3>
        <p id="modalMsg" style="margin: 1rem 0; color: var(--text-light);">Are you sure you want to proceed?</p>
        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
            <button id="modalConfirmBtn" class="btn-confirm">Yes, Delete</button>
            <button onclick="closeConfirm()" class="btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<div class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark/Light Mode">
    🌓
</div>

<script>
    // Dark Mode Logic
    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    }

    // Load Saved Theme
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }

    // Toast Logic
    function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = msg;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    // Global Confirmation
    let confirmCallback = null;
    function confirmAction(title, msg, callback) {
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('modalMsg').innerText = msg;
        document.getElementById('confirmModal').style.display = 'flex';
        confirmCallback = callback;
    }

    function closeConfirm() {
        document.getElementById('confirmModal').style.display = 'none';
    }

    document.getElementById('modalConfirmBtn').onclick = function() {
        if (typeof confirmCallback === 'function') {
            confirmCallback();
        } else if (typeof confirmCallback === 'string') {
            window.location.href = confirmCallback;
        }
        closeConfirm();
    };

    // Auto-show messages from URL
    window.onload = () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) showToast(urlParams.get('msg'));
        if (urlParams.has('err')) showToast(urlParams.get('err'), 'error');
    };
</script>
