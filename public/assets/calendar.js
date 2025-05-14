/**
 * Shokudou Calendar Popup
 * Handles calendar view for date selection
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get calendar link and create modal container
    const calendarLink = document.querySelector('.calendar a');
    let calendarModal = null;
    
    // Add click event listener to calendar link
    if (calendarLink) {
        calendarLink.addEventListener('click', function(e) {
            e.preventDefault();
            showCalendarModal();
        });
    }
    
    /**
     * Create and show the calendar modal
     */
    function showCalendarModal() {
        // Create modal if it doesn't exist
        if (!calendarModal) {
            calendarModal = document.createElement('div');
            calendarModal.className = 'calendar-modal';
            calendarModal.innerHTML = `
                <div class="calendar-content">
                    <div class="calendar-header">
                        <button class="prev-month">&lt;</button>
                        <h3 class="month-year"></h3>
                        <button class="next-month">&gt;</button>
                    </div>
                    <div class="weekdays">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>
                    <div class="days"></div>
                    <div class="calendar-footer">
                        <button class="today-btn">Today</button>
                        <button class="close-btn">Close</button>
                    </div>
                </div>
            `;
            document.body.appendChild(calendarModal);
            
            // Add event listeners to calendar buttons
            const closeBtn = calendarModal.querySelector('.close-btn');
            const prevMonthBtn = calendarModal.querySelector('.prev-month');
            const nextMonthBtn = calendarModal.querySelector('.next-month');
            const todayBtn = calendarModal.querySelector('.today-btn');
            
            closeBtn.addEventListener('click', hideCalendarModal);
            prevMonthBtn.addEventListener('click', () => navigateMonth(-1));
            nextMonthBtn.addEventListener('click', () => navigateMonth(1));
            todayBtn.addEventListener('click', () => {
                currentMonth = new Date();
                renderCalendar();
            });
            
            // Close modal when clicking outside the calendar
            calendarModal.addEventListener('click', function(e) {
                if (e.target === calendarModal) {
                    hideCalendarModal();
                }
            });
        }
        
        // Show modal and render calendar
        calendarModal.style.display = 'flex';
        currentMonth = new Date();
        renderCalendar();
    }
    
    /**
     * Hide the calendar modal
     */
    function hideCalendarModal() {
        if (calendarModal) {
            calendarModal.style.display = 'none';
        }
    }
    
    // Current month being displayed
    let currentMonth = new Date();
    
    /**
     * Navigate to previous or next month
     * @param {number} direction -1 for previous, 1 for next
     */
    function navigateMonth(direction) {
        currentMonth.setMonth(currentMonth.getMonth() + direction);
        renderCalendar();
    }
    
    /**
     * Render the calendar for the current month
     */
    function renderCalendar() {
        const monthYear = calendarModal.querySelector('.month-year');
        const daysContainer = calendarModal.querySelector('.days');
        
        // Set month and year in header
        monthYear.textContent = currentMonth.toLocaleDateString('en-US', { 
            month: 'long', 
            year: 'numeric' 
        });
        
        // Clear previous days
        daysContainer.innerHTML = '';
        
        // Get first day of month and total days
        const firstDay = new Date(
            currentMonth.getFullYear(),
            currentMonth.getMonth(),
            1
        );
        const lastDay = new Date(
            currentMonth.getFullYear(),
            currentMonth.getMonth() + 1,
            0
        );
        
        // Get day of week for first day (0 = Sunday, 6 = Saturday)
        const firstWeekday = firstDay.getDay();
        
        // Create empty cells for days before the first day of month
        for (let i = 0; i < firstWeekday; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'day empty';
            daysContainer.appendChild(emptyDay);
        }
        
        // Create cells for each day in the month
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (let i = 1; i <= lastDay.getDate(); i++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'day';
            dayCell.textContent = i;
            
            // Check if this day is today
            const cellDate = new Date(
                currentMonth.getFullYear(),
                currentMonth.getMonth(),
                i
            );
            
            if (cellDate.getTime() === today.getTime()) {
                dayCell.classList.add('today');
            }
            
            // Add click event to select date
            dayCell.addEventListener('click', function() {
                const selectedDate = new Date(
                    currentMonth.getFullYear(),
                    currentMonth.getMonth(),
                    i
                );
                
                // Format date as YYYY-MM-DD
                const formattedDate = formatDate(selectedDate);
                
                // Redirect to index.php with selected date
                window.location.href = `index.php?date=${formattedDate}`;
                
                // Hide modal
                hideCalendarModal();
            });
            
            daysContainer.appendChild(dayCell);
        }
    }
    
    /**
     * Format date as YYYY-MM-DD
     * @param {Date} date 
     * @returns {string} Formatted date
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
});