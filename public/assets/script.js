document.addEventListener('DOMContentLoaded', function() {
    const inputFields = document.getElementById('inputField');
    const uploadButton = document.getElementById('uploadButton');
    const uploadPopupButton = document.getElementById('uploadMenuButton');
    uploadPopupButton.addEventListener('click', function(event) {
        const cancelButton = document.getElementById('cancelUpload');
        event.preventDefault();
        // Show the upload menu popup
        const uploadPopup = document.getElementById('uploadPopup');
        uploadPopup.style.display = 'flex';
        cancelButton.addEventListener('click', function(event) {
            uploadPopup.style.display = 'none';
        });
        // Add event listener to the upload button
        uploadButton.addEventListener('click', function(event) {
            event.preventDefault();
            // Check if a file is selected
            if (inputFields.files.length === 0) {
                alert('Please select a file to upload.');
                return;
            }
            
            // Create FormData object to send the file
            const formData = new FormData();
            formData.append('file', inputFields.files[0]);
            
            // Send AJAX request to upload the file
            fetch('/team1/toyouke/src/api/upload_menu.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success notification
                    showNotification('Success', 'File uploaded successfully', 'success');
                    // Close the popup
                    uploadPopup.style.display = 'none';
                } else {
                    // Show error notification
                    showNotification('Error', data.message || 'Failed to upload file', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error', `An unexpected error occurred: ${error.message}`, 'error');
            });
        });
    });


    // Get all available and unavailable buttons
    const availableButtons = document.querySelectorAll('.btn-available');
    const unavailableButtons = document.querySelectorAll('.btn-unavailable');
    
    // Add event listeners to all available buttons
    availableButtons.forEach(button => {
        button.addEventListener('click', function() {
            updateAvailability(this.dataset.id, true);
        });
    });
    
    // Add event listeners to all unavailable buttons
    unavailableButtons.forEach(button => {
        button.addEventListener('click', function() {
            updateAvailability(this.dataset.id, false);
        });
    });
    
    /**
     * Update the availability status of a menu item
     * @param {string} itemId - The ID of the menu item
     * @param {boolean} isAvailable - Whether the item should be available or not
     */
    
    function updateAvailability(itemId, isAvailable) {
        // Show login popup if not logged in
        if (!isUserLoggedIn()) {
            showLoginPopup();
            return;
        }
        
        // Send AJAX request to update status
        fetch('/team1/toyouke/src/api/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${itemId}&available=${isAvailable ? 1 : 0}`
        })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response has content
            return response.text().then(text => {
                if (!text.trim()) {
                    throw new Error('Empty response from server');
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Server response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                // Update UI
                updateItemUI(itemId, isAvailable);
                // Show success notification
                showNotification('Success', 'Menu item updated successfully', 'success');
            } else {
                // Show error notification
                showNotification('Error', data.message || 'Failed to update menu item', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error', `An unexpected error occurred: ${error.message}`, 'error');
        });
    }
    
    /**
     * Update the UI for a menu item after status change
     * @param {string} itemId - The ID of the menu item
     * @param {boolean} isAvailable - Whether the item is now available or not
     */
    function updateItemUI(itemId, isAvailable) {
        // Get the row for this item
        const row = document.querySelector(`tr[data-id="${itemId}"]`);
        if (!row) return;
        
        // Update status indicator
        const statusCell = row.querySelector('.status');
        const statusIndicator = statusCell.querySelector('.status-indicator');
        
        // Update status text and class
        statusIndicator.textContent = isAvailable ? 'Available' : 'Unavailable';
        statusIndicator.className = 'status-indicator ' + (isAvailable ? 'available' : 'unavailable');
        
        // Update buttons
        const availableBtn = row.querySelector('.btn-available');
        const unavailableBtn = row.querySelector('.btn-unavailable');
        
        // Set active class and disabled state
        availableBtn.classList.toggle('active', isAvailable);
        unavailableBtn.classList.toggle('active', !isAvailable);
        availableBtn.disabled = isAvailable;
        unavailableBtn.disabled = !isAvailable;
        
        // Highlight the row to indicate it was updated
        row.classList.add('updated');
        setTimeout(() => {
            row.classList.remove('updated');
        }, 1500);
    }
    
    /**
     * Show a notification to the user
     * @param {string} title - The notification title
     * @param {string} message - The notification message
     * @param {string} type - The type of notification (success, error, info)
     */
    function showNotification(title, message, type = 'info') {
        // Create notification container if it doesn't exist
        let container = document.querySelector('.notification-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'notification-container';
            document.body.appendChild(container);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <h4>${title}</h4>
            <p>${message}</p>
            <button class="close-notification">&times;</button>
        `;
        
        // Add notification to container
        container.appendChild(notification);
        
        // Add event listener to close button
        const closeBtn = notification.querySelector('.close-notification');
        closeBtn.addEventListener('click', function() {
            closeNotification(notification);
        });
        
        // Auto-close after 5 seconds
        setTimeout(() => {
            closeNotification(notification);
        }, 5000);
    }
    
    /**
     * Close and remove a notification
     * @param {HTMLElement} notification - The notification element to close
     */
    function closeNotification(notification) {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
    
    /**
     * Check if the user is logged in
     * @returns {boolean} - Whether the user is logged in
     */
    function isUserLoggedIn() {
        const loginButton = document.querySelector('.loginButton a');
        // If the button text is "Logout", the user is logged in
        return loginButton && loginButton.textContent.trim() === 'Logout';
    }
    
    /**
     * Show the login popup
     */
    function showLoginPopup() {
        const loginPopup = document.getElementById('loginPopup');
        if (loginPopup) {
            loginPopup.style.display = 'flex';
            
            // Add event listener to cancel button
            const cancelBtn = document.getElementById('cancelLogin');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    loginPopup.style.display = 'none';
                });
            }
        }
    }
});