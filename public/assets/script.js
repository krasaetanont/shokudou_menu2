/**
 * Shokudou Menu Management Script
 * Handles real-time status updates for menu items
 */
document.addEventListener('DOMContentLoaded', function() {
    // Select all toggle buttons
    const toggleButtons = document.querySelectorAll('.toggle-status');
    
    // Add click event listeners to all toggle buttons
    toggleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get item data from button attributes
            const itemId = this.getAttribute('data-id');
            const currentStatus = this.getAttribute('data-status') === 'true';
            const newStatus = !currentStatus;
            
            // Disable the button temporarily to prevent multiple clicks
            this.disabled = true;
            
            // Update the UI to show processing state
            this.textContent = 'Updating...';
            
            // Send the update request
            updateMenuItemStatus(itemId, newStatus, this);
        });
    });
    
    /**
     * Send AJAX request to update menu item status
     * 
     * @param {number} itemId - ID of the menu item to update
     * @param {boolean} newStatus - New availability status
     * @param {HTMLElement} buttonElement - Button that was clicked
     */
    function updateMenuItemStatus(itemId, newStatus, buttonElement) {
        // Create form data for the request
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('available', newStatus ? 1 : 0);
        
        // Send AJAX request to the API
        fetch('api/update_status.php', {
            method: 'POST',
            body: formData
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
                // Get the table row and status elements
                const row = buttonElement.closest('tr');
                const statusCell = row.querySelector('.status');
                const statusIndicator = statusCell.querySelector('.status-indicator');
                
                // Update the status text and class
                statusIndicator.textContent = newStatus ? 'Available' : 'Unavailable';
                statusIndicator.classList.remove(newStatus ? 'unavailable' : 'available');
                statusIndicator.classList.add(newStatus ? 'available' : 'unavailable');
                
                // Update the button's data-status attribute
                buttonElement.setAttribute('data-status', newStatus.toString());
                
                // Add highlight animation
                row.classList.add('updated');
                setTimeout(() => {
                    row.classList.remove('updated');
                }, 1500);
                
                // Show a success notification
                showNotification('Success!', `Item status updated to ${newStatus ? 'available' : 'unavailable'}.`, 'success');
            } else {
                showNotification('Update Failed', data.message || 'Unknown error occurred', 'error');
            }
        })
        .catch(error => {
            console.error('Error updating menu item:', error);
            showNotification('Error', error.message || 'Failed to update status', 'error');
        })
        .finally(() => {
            // Re-enable the button and restore text
            buttonElement.disabled = false;
            buttonElement.textContent = 'Toggle Status';
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