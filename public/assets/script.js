document.addEventListener('DOMContentLoaded', function() {
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
        fetch('/shokudouMenu2/src/api/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${itemId}&available=${isAvailable ? 1 : 0}`
        })
        .then(response => response.json())
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
            console.log(askAI("fix this" + error));
            showNotification('Error', 'An unexpected error occurred', 'error');
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
        // return loginButton && loginButton.textContent.trim() === 'Logout';
        return true; // For testing purposes, always return true
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

async function askAI(prompt) {
    // Initialize chat history with the user's prompt.
    // The Gemini API expects the input in a specific 'contents' format.
    let chatHistory = [];
    chatHistory.push({ role: "user", parts: [{ text: prompt }] });

    // Construct the payload for the API request.
    const payload = {
        contents: chatHistory
    };

    // The API key is left as an empty string. In the Canvas environment,
    // this will be automatically provided at runtime.
    const apiKey = "AIzaSyC8h3EZROLsxWYF1TyBkVwInAbaDWuonvY";
    // Define the API endpoint for the gemini-2.0-flash model.
    const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${apiKey}`;

    try {
        // Make the POST request to the Gemini API.
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        // Check if the response was successful.
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(`API request failed with status ${response.status}: ${JSON.stringify(errorData)}`);
        }

        // Parse the JSON response.
        const result = await response.json();

        // Extract the generated text from the response.
        // The structure of the response needs to be carefully navigated.
        if (result.candidates && result.candidates.length > 0 &&
            result.candidates[0].content && result.candidates[0].content.parts &&
            result.candidates[0].content.parts.length > 0) {
            const text = result.candidates[0].content.parts[0].text;
            return text;
        } else {
            // Handle cases where the response structure is unexpected or content is missing.
            throw new Error("Unexpected API response structure or missing content.");
        }
    } catch (error) {
        // Log and re-throw any errors that occur during the fetch operation.
        console.error("Error calling Gemini API:", error);
        throw error; // Re-throw to allow the calling code to handle it.
    }
}
