<?php
// In your existing calendar.php file, replace the JavaScript part that loads events with this:
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize FullCalendar
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        events: function(info, successCallback, failureCallback) {
            // Fetch events from the server
            fetch('fetch-bookings.php')
                .then(response => response.json())
                .then(data => {
                    successCallback(data);
                    // After calendar is loaded, update today's bookings
                    loadTodayBookings();
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    failureCallback(error);
                });
        },
        // Rest of your calendar configuration...
    });
    
    calendar.render();
    
    // Update the loadTodayBookings function to fetch from the server
    function loadTodayBookings() {
        const todayBookings = document.getElementById('todayBookings');
        const today = new Date().toISOString().split('T')[0];
        
        // Fetch today's bookings from the server
        fetch('fetch-bookings.php?date=' + today)
            .then(response => response.json())
            .then(todayEvents => {
                if (todayEvents.length > 0) {
                    let html = '<div class="booking-count">' + todayEvents.length + ' bookings today</div>';
                    html += '<ul class="booking-list">';
                    
                    todayEvents.forEach(function(event) {
                        const startTime = new Date(event.start).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        const endTime = new Date(event.end).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        
                        html += '<li class="booking-item">';
                        html += '<div class="booking-time">' + startTime + ' - ' + endTime + '</div>';
                        html += '<div class="booking-details">';
                        html += '<div class="booking-court">' + event.extendedProps.court_name + '</div>';
                        html += '<div class="booking-customer">' + event.extendedProps.customer_name + '</div>';
                        html += '</div>';
                        html += '<div class="booking-status status-' + event.extendedProps.status.toLowerCase() + '">' + event.extendedProps.status + '</div>';
                        html += '</li>';
                    });
                    
                    html += '</ul>';
                    todayBookings.innerHTML = html;
                } else {
                    todayBookings.innerHTML = '<p class="no-bookings">No bookings for today.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching today\'s bookings:', error);
                todayBookings.innerHTML = '<p class="error-text">Error loading bookings.</p>';
            });
    }
    
    // Rest of your calendar JavaScript...
});
</script>