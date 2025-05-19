/**
 * Shokudou Menu Management Script
 * Handles real-time status updates for menu items
 */
document.addEventListener('DOMContentLoaded', function() {
    // Select all status buttons
    const availableButtons = document.querySelectorAll('.btn-available');
    const unavailableButtons = document.querySelectorAll('.btn-unavailable');
    
    // Get the login popup
    const loginPopup = document.getElementById('loginPopup');
    
    // Set up cancel button for login popup
    const cancelLoginBtn = document.getElementById('cancelLogin');
    if (cancelLoginBtn) {
        cancelLoginBtn.addEventListener('click', function() {
            loginPopup.style.display = 'none';
        });
    }
    
    // Click outside to close login popup
    window.addEventListener('click', function(event) {
        if (event.target === loginPopup) {
            loginPopup.style.display = 'none';
        }
    });
    
    // Check login status from cookie directly
    function isUserLoggedIn() {
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i].trim();
            if (cookie.startsWith('user_logged_in=')) {
                return cookie.substring('user_logged_in='.length) === 'true';
            }
        }
        return false;
    }
    
    // Add click event listeners to all available buttons
    availableButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            handleStatusChange(this, true);
        });
    });
    
    // Add click event listeners to all unavailable buttons
    unavailableButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            handleStatusChange(this, false);
        });
    });
    
    /**
     * Handle status change for menu items
     * 
     * @param {HTMLElement} buttonElement - Button that was clicked
     * @param {boolean} newStatus - New availability status to set
     */
    function handleStatusChange(buttonElement, newStatus) {
        // Check login status in real-time from cookie
        const loggedIn = isUserLoggedIn();
        
        if (!loggedIn) {
            // Show login popup for non-logged in users
            loginPopup.style.display = 'flex';
            return;
        }
        
        // Get item data from button attributes
        const itemId = buttonElement.getAttribute('data-id');
        const currentStatus = buttonElement.closest('tr').querySelector('.status-indicator').classList.contains('available');
        
        // If item already has the requested status, do nothing
        if (newStatus === currentStatus) {
            return;
        }
        
        // Disable both buttons temporarily to prevent multiple clicks
        const row = buttonElement.closest('tr');
        const availableBtn = row.querySelector('.btn-available');
        const unavailableBtn = row.querySelector('.btn-unavailable');
        
        availableBtn.disabled = true;
        unavailableBtn.disabled = true;
        
        // Show updating state on the clicked button
        buttonElement.textContent = 'Updating...';
        
        // Send the update request
        updateMenuItemStatus(itemId, newStatus, row);
    }
    
    /**
     * Send AJAX request to update menu item status
     * 
     * @param {number} itemId - ID of the menu item to update
     * @param {boolean} newStatus - New availability status
     * @param {HTMLElement} row - Table row containing the item
     */
    function updateMenuItemStatus(itemId, newStatus, row) {
        // Create form data for the request
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('available', newStatus ? 1 : 0);
        
        // Send AJAX request to the API
        fetch('api/update_status.php', {
            method: 'POST',
            body: formData,
            credentials: 'include' // Include cookies with the request
        })
        .then(response => {
            // Check if the response is OK
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || `Server returned ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Get the status elements
                const statusCell = row.querySelector('.status');
                const statusIndicator = statusCell.querySelector('.status-indicator');
                const availableBtn = row.querySelector('.btn-available');
                const unavailableBtn = row.querySelector('.btn-unavailable');
                
                // Update the status text and class
                statusIndicator.textContent = newStatus ? 'Available' : 'Unavailable';
                statusIndicator.classList.remove(newStatus ? 'unavailable' : 'available');
                statusIndicator.classList.add(newStatus ? 'available' : 'unavailable');
                
                // Update button states
                availableBtn.disabled = newStatus;
                availableBtn.classList.toggle('active', newStatus);
                availableBtn.textContent = 'Available';
                
                unavailableBtn.disabled = !newStatus;
                unavailableBtn.classList.toggle('active', !newStatus);
                unavailableBtn.textContent = 'Unavailable';
                
                // Add highlight animation
                row.classList.add('updated');
                setTimeout(() => {
                    row.classList.remove('updated');
                }, 1500);
                
                // Show a success notification
                showNotification('Success!', `Item status updated to ${newStatus ? 'available' : 'unavailable'}.`, 'success');
            } else {
                // Re-enable buttons on error
                const availableBtn = row.querySelector('.btn-available');
                const unavailableBtn = row.querySelector('.btn-unavailable');
                
                availableBtn.disabled = false;
                availableBtn.textContent = 'Available';
                
                unavailableBtn.disabled = false;
                unavailableBtn.textContent = 'Unavailable';
                
                showNotification('Update Failed', data.message || 'Unknown error occurred', 'error');
            }
        })
        .catch(error => {
            console.error('Error updating menu item:', error);
            
            // Re-enable buttons on error
            const availableBtn = row.querySelector('.btn-available');
            const unavailableBtn = row.querySelector('.btn-unavailable');
            
            availableBtn.disabled = false;
            availableBtn.textContent = 'Available';
            
            unavailableBtn.disabled = false;
            unavailableBtn.textContent = 'Unavailable';
            
            showNotification('Error', error.message || 'Failed to update status', 'error');
        });
    }
    
    /**
     * Display a temporary notification message
     * 
     * @param {string} title - Notification title
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, error, info)
     */
    function showNotification(title, message, type = 'info') {
        // Create notification container if it doesn't exist
        let notificationContainer = document.querySelector('.notification-container');
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.className = 'notification-container';
            document.body.appendChild(notificationContainer);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        // Create notification content
        notification.innerHTML = `
            <h4>${title}</h4>
            <p>${message}</p>
            <button class="close-notification">×</button>
        `;
        
        // Add to container
        notificationContainer.appendChild(notification);
        
        // Add close button functionality
        notification.querySelector('.close-notification').addEventListener('click', function() {
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.remove();
            }, 300);
        });
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
});