/**
 * AWD-WebUI Global JavaScript
 * Automatically handles loading states for forms and buttons
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Auto-Loading on Form Submission
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Find the submit button inside the form
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"], .btn-start, .btn-p, .btn-s');
        
        if (submitBtn) {
            submitBtn.classList.add('is-loading');
            
            // Disable button slightly after to allow form to submit
            setTimeout(function() {
                submitBtn.style.pointerEvents = 'none';
            }, 10);
        }
    });

    // Automatically remove loading state when jQuery AJAX requests complete
    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(document).ajaxComplete(function() {
            document.querySelectorAll('.is-loading').forEach(btn => {
                btn.classList.remove('is-loading');
                btn.style.pointerEvents = '';
            });
        });
    }

    // 2. Auto-Loading on regular buttons that trigger page reloads/actions via href or onclick
    // We target common classes used for actions
    const actionButtons = document.querySelectorAll('.btn-p, .btn-d, .btn-kill, .btn-w, .btn-s, .btn-start, .refresh');
    
    actionButtons.forEach(btn => {
        // Skip if it's already a submit button inside a form (handled above)
        if (btn.type === 'submit' && btn.closest('form')) return;

        btn.addEventListener('click', function(e) {
            // Check if it's a link or has an onclick attribute
            if (this.tagName === 'A' || this.hasAttribute('onclick')) {
                // If it's a link with target="_blank" or just a hash "#", skip
                if (this.getAttribute('target') === '_blank' || this.getAttribute('href') === '#') return;
                
                // Add loading state
                this.classList.add('is-loading');
                
                // If it's an A tag without onclick, it will navigate, but if it fails we remove loading after 5s
                setTimeout(() => {
                    this.classList.remove('is-loading');
                }, 5000);
            }
        });
    });

    // 3. Auto-Convert existing static alerts to Toasts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alertEl => {
        // Hide the original alert
        alertEl.style.display = 'none';
        
        let msg = alertEl.innerText || alertEl.textContent;
        msg = msg.trim();
        
        if (!msg) return;

        let type = 'info';
        if (alertEl.classList.contains('error') || alertEl.classList.contains('err') || alertEl.classList.contains('danger') || msg.toLowerCase().includes('failed') || msg.toLowerCase().includes('gagal') || msg.toLowerCase().includes('error')) {
            type = 'error';
        } else if (alertEl.classList.contains('success') || alertEl.classList.contains('suc') || msg.toLowerCase().includes('success') || msg.toLowerCase().includes('sukses') || msg.toLowerCase().includes('berhasil')) {
            type = 'success';
        }

        // Show as toast after a tiny delay for animation effect
        setTimeout(() => {
            window.showToast(msg, type);
        }, 100);
    });
});

// ================== GLOBAL TOAST FUNCTION ==================
window.showToast = function(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    // SVG Icons
    const icons = {
        success: `<svg class="toast-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>`,
        error: `<svg class="toast-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>`,
        info: `<svg class="toast-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>`
    };

    toast.innerHTML = `${icons[type] || icons.info} <span>${message}</span>`;
    container.appendChild(toast);

    // Auto remove after 4 seconds
    setTimeout(() => {
        toast.classList.add('toast-hiding');
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 400); // Wait for transition
    }, 4000);
};

