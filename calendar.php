<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'connection.php';

$user_id = $_SESSION['user_id'];

$userRef = $database->getReference('users/' . $user_id);
$userData = $userRef->getValue();


$settingsRef = $database->getReference('settings/' . $user_id);
$settings = $settingsRef->getValue() ?? [];


$userCourtsRef = $database->getReference('user_courts/' . $user_id);
$userCourts = $userCourtsRef->getValue();
$courts = $userCourts['courts'] ?? [];

function getBookings($database, $user_id) {
    $bookingsRef = $database->getReference('bookings/' . $user_id);
    $bookings = $bookingsRef->getValue() ?: [];
    
    $formattedBookings = [];
    foreach ($bookings as $key => $booking) {

        $courtName = isset($booking['court']) ? $booking['court'] : 'Unknown Court';
        
        $formattedBookings[] = [
            'id' => $key,
            'title' => $booking['customer_name'],
            'start' => $booking['start_time'],
            'end' => $booking['end_time'],
            'color' => getStatusColor($booking['status']),
            'extendedProps' => [
                'court_name' => $courtName, // Use the court name from booking data
                'court_id' => $booking['court_id'],
                'customer_name' => $booking['customer_name'],
                'status' => $booking['status'],
            ]
        ];
    }
    
    return $formattedBookings;
}


function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'confirmed':
            return '#4361ee';
        case 'pending':
            return '#f9c74f';
        case 'cancelled':
            return '#e63946';
        default:
            return '#2ec4b6';
    }
}

// Handle booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_booking'])) {
    $bookingDate = $_POST['booking_date'];
    $courtIndex = $_POST['court_index'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $customerName = $_POST['customer_name'];
    $status = 'Confirmed'; 

    if (!empty($bookingDate) && isset($courts[$courtIndex]) && !empty($startTime) && !empty($endTime) && !empty($customerName)) {

        $courtName = $courts[$courtIndex]['name'];

        $courtId = isset($courts[$courtIndex]['id']) ? $courts[$courtIndex]['id'] : $courtIndex;

        $startDateTime = $bookingDate . 'T' . $startTime . ':00';
        $endDateTime = $bookingDate . 'T' . $endTime . ':00';
        
        $bookingData = [
            'court_id' => $courtId,
            'court' => $courtName, 
            'customer_name' => $customerName,
            'start_time' => $startDateTime,
            'end_time' => $endDateTime,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $bookingsRef = $database->getReference('bookings/' . $user_id);
        $bookingsRef->push($bookingData);

        updateCourtAvailability($database, $user_id, $bookingDate);
        
        $success = true;
    }
}

// deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    $bookingId = $_POST['booking_id'];
    $bookingDate = $_POST['booking_date'];
    
    if (!empty($bookingId)) {
        $bookingRef = $database->getReference('bookings/' . $user_id . '/' . $bookingId);
        $bookingRef->remove();
        
 
        updateCourtAvailability($database, $user_id, $bookingDate);
        
        $success = true;
    }
}

// update sa courts
function updateCourtAvailability($database, $user_id, $date) {

    $userCourtsRef = $database->getReference('user_courts/' . $user_id);
    $userCourts = $userCourtsRef->getValue();
    $courts = $userCourts['courts'] ?? [];
    $totalCourts = count($courts);

    $bookingsRef = $database->getReference('bookings/' . $user_id);
    $allBookings = $bookingsRef->getValue() ?: [];
    
    $dateBookings = [];
    foreach ($allBookings as $key => $booking) {
        $bookingDate = substr($booking['start_time'], 0, 10);
        if ($bookingDate === $date) {
            $dateBookings[] = $booking;
        }
    }
    
    $bookedCourts = [];
    foreach ($dateBookings as $booking) {
        if (!in_array($booking['court_id'], $bookedCourts)) {
            $bookedCourts[] = $booking['court_id'];
        }
    }
    
    $bookedCount = count($bookedCourts);
    $availableCount = $totalCourts - $bookedCount;
    
    $availabilityRef = $database->getReference('availability/' . $user_id . '/' . $date);
    $availabilityRef->set([
        'total' => $totalCourts,
        'booked' => $bookedCount,
        'available' => $availableCount,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
    return [
        'total' => $totalCourts,
        'booked' => $bookedCount,
        'available' => $availableCount
    ];
}


$bookings = getBookings($database, $user_id);


$today = date('Y-m-d');


$todayAvailability = updateCourtAvailability($database, $user_id, $today);


$hideManualBooking = $settings['hide_manual_booking'] ?? false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Dribble</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="asset/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/calendar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
</head>
<body>

<!-- NAVIGATION BAR -->
<div class="navbar-container">
    <div class="navbar-left">
        <button class="navbar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <div class="navbar-right">
        <div class="navbar-logo">
            <img src="asset/alt.png" alt="Logo">
        </div>
        <div class="navbar-right">
           <button id="themeToggle" class="theme-toggle">
               <i id="themeIcon" class="fas fa-moon"></i>
           </button>
       </div>
    </div>
</div>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar-nav" id="sidebarNav">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="nav-item">
        <a href="Dashboard.php" class="nav-link">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
    </div>
    <div class="nav-item">
        <a href="stats.php" class="nav-link">
            <i class="fa-solid fa-chart-simple"></i>
            <span>Stats</span>
        </a>
    </div>
    <div class="nav-item">
        <a href="calendar.php" class="nav-link active">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Calendar</span>
        </a>
    </div>
    <div class="nav-item">
           <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
               <i class="fas fa-cog"></i>
               <span>Settings</span>
           </a>
       </div>
    <div class="nav-item">
        <a href="#" onclick="confirmLogout()" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content" id="mainContent">
    <div class="calendar-layout <?php echo $hideManualBooking ? 'full-width' : ''; ?>">
        <div class="calendar-sidebar">
            <div class="availability-panel">
                <h2>Today's Availability</h2>
                <div class="availability-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $todayAvailability['total']; ?></span>
                        <span class="stat-label">Total Courts</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $todayAvailability['booked']; ?></span>
                        <span class="stat-label">Booked</span>
                    </div>
                    <div class="stat-item highlight">
                        <span class="stat-value"><?php echo $todayAvailability['available']; ?></span>
                        <span class="stat-label">Available</span>
                    </div>
                </div>
                <div class="availability-message">
                    <?php if ($todayAvailability['available'] > 0): ?>
                        <p class="available-message"><?php echo $todayAvailability['available']; ?> courts available today</p>
                    <?php else: ?>
                        <p class="fully-booked-message">All courts are booked today</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$hideManualBooking): ?>
            <div class="add-booking-form">
                <h2>Add New Booking</h2>
                <?php if (isset($success) && $success): ?>
                    <div class="success-message">Booking successfully saved!</div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="booking_date">Date:</label>
                        <input type="date" id="booking_date" name="booking_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="court_index">Court:</label>
                        <select id="court_index" name="court_index" required>
                            <?php foreach ($courts as $index => $court): ?>
                                <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($court['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">Start Time:</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">End Time:</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_name">Customer Name:</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>
                    
                    <button type="submit" name="add_booking" value="1" class="btn-primary">Add Booking</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="calendar-wrapper">
            <div id="calendar"></div>
        </div>
    </div>
    <input type="hidden" id="user_id" value="<?php echo $user_id; ?>">
</div>

<!-- BOOKING DETAILS -->
<div id="bookingModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalTitle"></h2>
        <div id="modalDetails">
            <p><strong>Court:</strong> <span id="modalCourt"></span></p>
            <p><strong>Customer:</strong> <span id="modalCustomer"></span></p>
            <p><strong>Time:</strong> <span id="modalTime"></span></p>
            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
        </div>
        <div class="modal-actions">
            <form method="POST" action="">
                <input type="hidden" id="delete_booking_id" name="booking_id">
                <input type="hidden" id="delete_booking_date" name="booking_date">
                <button type="submit" name="delete_booking" value="1" class="btn-delete" onclick="return confirm('Are you sure you want to delete this booking?')">Delete Booking</button>
            </form>
        </div>
    </div>
</div>
<script src="asset/theme.js?v=<?php echo time(); ?>"></script>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarNav = document.getElementById('sidebarNav');
        const mainContent = document.getElementById('mainContent');
        const success = document.getElementById('successMessage');
        const error = document.getElementById('errorMessage');
        
        sidebarToggle.addEventListener('click', function() {
            sidebarNav.classList.toggle('expanded');
            mainContent.classList.toggle('expanded');
        });
        
        if (success) {
            setTimeout(() => {
                success.style.display = 'none';
            }, 500); 
        }
        if (error) {
            setTimeout(() => {
                error.style.display = 'none';
            }, 500); 
        }
        
        var calendarEl = document.getElementById('calendar');
        
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: <?php echo json_encode($bookings); ?>,
            height: '100%',
            contentHeight: 'auto',
            aspectRatio: 1.35,
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            allDaySlot: false,
            slotDuration: '01:00:00',
            slotLabelFormat: {
                hour: 'numeric',
                minute: '2-digit',
                omitZeroMinute: true,
                meridiem: 'short'
            },
            eventClick: function(info) {

                document.getElementById('modalTitle').textContent = info.event.title;
                

                const courtName = info.event.extendedProps.court_name || "Unknown Court";
                document.getElementById('modalCourt').textContent = courtName;
                
                document.getElementById('modalCustomer').textContent = info.event.extendedProps.customer_name;
                

                const start = new Date(info.event.start);
                const end = new Date(info.event.end);
                const timeString = start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) + 
                                  ' - ' + 
                                  end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                document.getElementById('modalTime').textContent = timeString;
                

                const statusSpan = document.getElementById('modalStatus');
                statusSpan.textContent = info.event.extendedProps.status;
                statusSpan.className = 'status-' + info.event.extendedProps.status.toLowerCase();
            
                document.getElementById('delete_booking_id').value = info.event.id;
                document.getElementById('delete_booking_date').value = start.toISOString().split('T')[0];
                l
                document.getElementById('bookingModal').style.display = 'block';
            },
            dateClick: function(info) {
       
                const bookingDateInput = document.getElementById('booking_date');
                if (bookingDateInput) {
                    bookingDateInput.value = info.dateStr.split('T')[0];
                    
                    if (info.view.type !== 'dayGridMonth') {
                     
                        const startTimeInput = document.getElementById('start_time');
                        const endTimeInput = document.getElementById('end_time');
                        
                        if (startTimeInput && endTimeInput) {
                            const clickedTime = info.dateStr.split('T')[1].substring(0, 5);
                            startTimeInput.value = clickedTime;
                            
                     
                            const startHour = parseInt(clickedTime.split(':')[0]);
                            const startMinute = clickedTime.split(':')[1];
                            const endHour = (startHour + 1) % 24;
                            endTimeInput.value = 
                                endHour.toString().padStart(2, '0') + ':' + startMinute;
                        }
                    }
                }
            }
        });
        
        calendar.render();


        window.calendar = calendar;
/*
        function fetchBotpressBookings() {
            fetch('fetch-bookings.php?type=bookings')
                .then(response => response.json())
                .then(data => {
                    if (data.bookings && data.bookings.length > 0) {
                        data.bookings.forEach(booking => {
                            calendar.addEvent({
                                id: booking.id || 'bp-' + Math.random().toString(36).substr(2, 9),
                                title: booking.title || `${booking.customer_name}`,
                                start: booking.start_time,
                                end: booking.end_time,
                                color: getStatusColor(booking.status),
                                extendedProps: {
                                    court_name: booking.court || booking.court_name || "Unknown Court", // Use court or court_name
                                    court_id: booking.court_id,
                                    customer_name: booking.customer_name,
                                    status: booking.status,
                                }
                            });
                        });
                    }
                })
                .catch(error => console.error('Error fetching Botpress bookings:', error));
        }

        // Fetch Botpress bookings after calendar is rendered
        fetchBotpressBookings();
 */       
        document.querySelector('.close').addEventListener('click', function() {
            document.getElementById('bookingModal').style.display = 'none';
        });

        window.addEventListener('click', function(event) {
            if (event.target == document.getElementById('bookingModal')) {
                document.getElementById('bookingModal').style.display = 'none';
            }
        });
        
        const bookingDateInput = document.getElementById('booking_date');
        if (bookingDateInput) {
            bookingDateInput.addEventListener('change', function() {
                const selectedDate = this.value;
                
                fetch('get_availability.php?date=' + selectedDate)
                    .then(response => response.json())
                    .then(data => {

                        const availabilityMessage = document.querySelector('.availability-message');
                        if (data.available > 0) {
                            availabilityMessage.innerHTML = `<p class="available-message">${data.available} courts available on selected date</p>`;
                        } else {
                            availabilityMessage.innerHTML = `<p class="fully-booked-message">All courts are booked on selected date</p>`;
                        }
                    })
                    .catch(error => console.error('Error fetching availability:', error));
            });
        }
    });
    
    function confirmLogout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    }
</script>

<script src="botpress-integration.js?v=<?php echo time(); ?>"></script>
</body>
</html>
