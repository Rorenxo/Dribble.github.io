<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'connection.php';


$user_id = $_SESSION['user_id'];


$userCourtsRef = $database->getReference('user_courts/' . $user_id);
$userCourts = $userCourtsRef->getValue();

if ($userCourts === null || !isset($userCourts['setup_completed']) || $userCourts['setup_completed'] !== true) {

    header("Location: setup.php");
    exit();
}

$userRef = $database->getReference('users/' . $user_id);
$userData = $userRef->getValue();


$courts = $userCourts['courts'] ?? [];


$piggyBankRef = $database->getReference('piggy_bank/' . $user_id);
$piggyBankData = $piggyBankRef->getValue() ?? [
    'current_amount' => 0,
    'goal_amount' => 0,
    'transactions' => []
];

$settingsRef = $database->getReference('settings/' . $user_id);
$settings = $settingsRef->getValue() ?? [];
$piggyBankEnabled = $settings['piggy_bank_enabled'] ?? true;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['set_goal'])) {
        $goalAmount = floatval($_POST['goal_amount']);
        if ($goalAmount > 0) {
            $piggyBankRef->update([
                'goal_amount' => $goalAmount
            ]);
            $piggyBankData['goal_amount'] = $goalAmount;
        }
    } 
    // Add money
    elseif (isset($_POST['add_money'])) {
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        
        if ($amount > 0) {
            $newAmount = ($piggyBankData['current_amount'] ?? 0) + $amount;
            $transaction = [
                'type' => 'deposit',
                'amount' => $amount,
                'description' => $description,
                'date' => date('Y-m-d H:i:s')
            ];
            

            $transactions = $piggyBankData['transactions'] ?? [];
            array_unshift($transactions, $transaction);
            
            $piggyBankRef->update([
                'current_amount' => $newAmount,
                'transactions' => $transactions
            ]);
            
            $piggyBankData['current_amount'] = $newAmount;
            $piggyBankData['transactions'] = $transactions;
        }
    } 

    elseif (isset($_POST['withdraw_money'])) {
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        
        if ($amount > 0 && $amount <= $piggyBankData['current_amount']) {
            $newAmount = $piggyBankData['current_amount'] - $amount;
            $transaction = [
                'type' => 'withdrawal',
                'amount' => $amount,
                'description' => $description,
                'date' => date('Y-m-d H:i:s')
            ];

            $transactions = $piggyBankData['transactions'] ?? [];
            array_unshift($transactions, $transaction);
            
            $piggyBankRef->update([
                'current_amount' => $newAmount,
                'transactions' => $transactions
            ]);
            
            $piggyBankData['current_amount'] = $newAmount;
            $piggyBankData['transactions'] = $transactions;
        }
    }
}


$bookingsRef = $database->getReference('bookings/' . $user_id);
$allBookings = $bookingsRef->getValue() ?: [];


$totalCourts = count($courts);


$today = date('Y-m-d');
$todayBookings = 0;
foreach ($allBookings as $booking) {
    $bookingDate = substr($booking['start_time'], 0, 10);
    if ($bookingDate === $today) {
        $todayBookings++;
    }
}



$totalBookings = count($allBookings);


$confirmedBookings = 0;
$pendingBookings = 0;
$cancelledBookings = 0;

foreach ($allBookings as $booking) {
    $status = strtolower($booking['status'] ?? 'confirmed');
    if ($status === 'confirmed') {
        $confirmedBookings++;
    } elseif ($status === 'pending') {
        $pendingBookings++;
    } elseif ($status === 'cancelled') {
        $cancelledBookings++;
    }
}


function getDailyBookingData($allBookings) {
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $confirmedData = array_fill(0, 7, 0);
    $pendingData = array_fill(0, 7, 0);
    $cancelledData = array_fill(0, 7, 0);
    
    $today = new DateTime();
    $startOfWeek = (clone $today)->modify('monday this week');
    
    foreach ($allBookings as $booking) {
        $bookingDate = new DateTime(substr($booking['start_time'], 0, 10));

        $diff = $startOfWeek->diff($bookingDate);
        if ($diff->days < 7 && $diff->invert === 0) {
            $dayOfWeek = intval($bookingDate->format('N')) - 1; // 0-based index (Monday = 0)
            
            $status = strtolower($booking['status'] ?? 'confirmed');
            if ($status === 'confirmed') {
                $confirmedData[$dayOfWeek]++;
            } elseif ($status === 'pending') {
                $pendingData[$dayOfWeek]++;
            } elseif ($status === 'cancelled') {
                $cancelledData[$dayOfWeek]++;
            }
        }
    } 
    
    return [
        'labels' => $days,
        'confirmed' => $confirmedData,
        'pending' => $pendingData,
        'cancelled' => $cancelledData
    ];

}
$dailyData = getDailyBookingData($allBookings);

$totalRevenue = 0;
$todayRevenue = 0;


$defaultPrice = 500; 

foreach ($allBookings as $booking) {
    $price = $booking['price'] ?? $defaultPrice;
    $totalRevenue += $price;
    
    $bookingDate = substr($booking['start_time'], 0, 10);
    if ($bookingDate === $today) {
        $todayRevenue += $price;
    }
}

$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Dribble</title>
    <link rel="stylesheet" href="asset/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/stats.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
    .goal-info-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
    }
    
    .change-goal-btn {
        background-color: transparent;
        border: none;
        color: var(--primary);
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 2px 5px;
        border-radius: 3px;
    }
    
    .change-goal-btn:hover {
        background-color: rgba(255, 107, 0, 0.1);
    }
    </style>
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
        <a href="stats.php" class="nav-link active">
            <i class="fa-solid fa-chart-simple"></i>
            <span>Stats</span>
        </a>
    </div>
    <div class="nav-item">
        <a href="calendar.php" class="nav-link">
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
    <div class="container">
        <!-- DASHBOARD LAYOUT -->
        <div class="dashboard-layout <?php echo $piggyBankEnabled ? '' : 'full-width'; ?>">
            <div class="dashboard-main">
                <!-- STATS CARDS -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-card-icon blue">
                                <i class="fas fa-basketball-ball"></i>
                            </div>
                            <h3 class="stat-card-title">Total Courts</h3>
                        </div>
                        <h2 class="stat-card-value"><?php echo $totalCourts; ?></h2>
                        <p class="stat-card-subtitle">Available for booking</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-card-icon purple">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <h3 class="stat-card-title">Today's Bookings</h3>
                        </div>
                        <h2 class="stat-card-value"><?php echo $todayBookings; ?></h2>
                        <p class="stat-card-subtitle">Bookings for today</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-card-icon teal">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3 class="stat-card-title">Total Bookings</h3>
                        </div>
                        <h2 class="stat-card-value"><?php echo $totalBookings; ?></h2>
                        <p class="stat-card-subtitle">All-time bookings</p>
                    </div>
                    
                </div>
                
                <!-- BOOKING STATUS CHART -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h2>Booking Status</h2>
                        <div class="chart-tabs">
                            <button class="chart-tab active" data-period="daily">Daily</button>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container">
                            <canvas id="bookingStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($piggyBankEnabled): ?>
            <!-- PIGGY BANK CARD -->
            <div class="dashboard-sidebar">
                <div class="piggy-bank-card">
                    <div class="piggy-bank-header">
                        <h2><i class="fas fa-piggy-bank"></i> Piggy Bank</h2>
                    </div>
                    <div class="piggy-bank-body">
                        <div class="balance-info">
                            <div class="balance-label">Current Balance</div>
                            <div class="balance-amount">₱<?php echo number_format($piggyBankData['current_amount'], 0); ?></div>
                        </div>
                        
                        <?php if ($piggyBankData['goal_amount'] > 0): ?>
                        <div class="goal-progress">
                            <div class="goal-info-container">
                                <div class="goal-info">
                                    <span>Savings Goal</span>
                                </div>
                                <button type="button" class="change-goal-btn" onclick="openSetGoalModal()">
                                    <i class="fas fa-edit"></i> Change Goal
                                </button>
                            </div>
                            <div class="goal-info">
                                <span>₱<?php echo number_format($piggyBankData['current_amount'], 0); ?> / ₱<?php echo number_format($piggyBankData['goal_amount'], 0); ?></span>
                            </div>
                            <?php 
                                $goalPercentage = min(100, ($piggyBankData['current_amount'] / $piggyBankData['goal_amount']) * 100);
                            ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $goalPercentage; ?>%"></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="goal-progress">
                            <div class="goal-info">
                                <span>No savings goal set</span>
                                <button type="button" class="change-goal-btn" onclick="openSetGoalModal()">
                                    <i class="fas fa-plus-circle"></i> Set Goal
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="piggy-actions">
                            <button class="btn btn-success" onclick="openAddMoneyModal()">Add Money</button>
                            <button class="btn btn-danger" onclick="openWithdrawModal()" <?php echo $piggyBankData['current_amount'] <= 0 ? 'disabled' : ''; ?>>Withdraw</button>
                        </div>
                        
                        <?php if (!empty($piggyBankData['transactions'])): ?>
                        <div class="transactions-list">
                            <h3>Recent Transactions</h3>
                            <?php 
                                $transactions = array_slice($piggyBankData['transactions'], 0, 5);
                                foreach ($transactions as $transaction): 
                            ?>
                            <div class="transaction">
                                <div class="transaction-info">
                                    <div class="transaction-desc"><?php echo htmlspecialchars($transaction['description']); ?></div>
                                    <div class="transaction-date"><?php echo date('M d, Y', strtotime($transaction['date'])); ?></div>
                                </div>
                                <div class="transaction-amount amount-<?php echo $transaction['type']; ?>">
                                    <?php echo $transaction['type'] === 'deposit' ? '+' : '-'; ?>₱<?php echo number_format($transaction['amount'], 0); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <input type="hidden" id="user_id" value="<?php echo $user_id; ?>">
</div>

<!-- ADD MONEY MODAL -->
<div id="addMoneyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Money to Piggy Bank</h2>
            <span class="close" onclick="closeAddMoneyModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="amount">Amount</label>
                <input type="number" id="amount" name="amount" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_money" class="btn btn-primary">Add Money</button>
            </div>
        </form>
    </div>
</div>

<div id="withdrawModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Withdraw Money</h2>
            <span class="close" onclick="closeWithdrawModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="withdraw_amount">Amount</label>
                <input type="number" id="withdraw_amount" name="amount" min="1" max="<?php echo $piggyBankData['current_amount']; ?>" step="1" required>
            </div>
            <div class="form-group">
                <label for="withdraw_description">Description</label>
                <input type="text" id="withdraw_description" name="description" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="withdraw_money" class="btn btn-primary">Withdraw Money</button>
            </div>
        </form>
    </div>
</div>

<div id="setGoalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo $piggyBankData['goal_amount'] > 0 ? 'Update' : 'Set'; ?> Savings Goal</h2>
            <span class="close" onclick="closeSetGoalModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="goal_amount">Goal Amount</label>
                <input type="number" id="goal_amount" name="goal_amount" min="1" step="1" required value="<?php echo $piggyBankData['goal_amount']; ?>">
            </div>
            <div class="form-actions">
                <button type="submit" name="set_goal" class="btn btn-primary">
                    <?php echo $piggyBankData['goal_amount'] > 0 ? 'Update Goal' : 'Set Goal'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="asset/theme.js?v=<?php echo time(); ?>"></script>

<script>
    const bookingStatusCtx = document.getElementById('bookingStatusChart').getContext('2d');

    // Get current theme
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const isDarkMode = currentTheme === 'dark';

    const textColor = isDarkMode ? '#ffffff' : '#212529';
    const gridColor = isDarkMode ? '#ffffff' : '#000000';

    const bookingStatusChart = new Chart(bookingStatusCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($dailyData['labels']); ?>,
            datasets: [
                {
                    label: 'Confirmed',
                    data: <?php echo json_encode($dailyData['confirmed']); ?>,
                    backgroundColor: '#4361ee',
                    borderRadius: 4,
                    stack: 'Stack 0'
                },
                {
                    label: 'Pending',
                    data: <?php echo json_encode($dailyData['pending']); ?>,
                    backgroundColor: '#f9c74f',
                    borderRadius: 4,
                    stack: 'Stack 0'
                },
                {
                    label: 'Cancelled',
                    data: <?php echo json_encode($dailyData['cancelled']); ?>,
                    backgroundColor: '#e63946',
                    borderRadius: 4,
                    stack: 'Stack 0'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: 11
                        },
                        color: textColor
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        font: {
                            size: 10
                        },
                        color: textColor
                    },
                    grid: {
                        color: isDarkMode ? 'rgba(255, 255, 255, 0.5)' : 'rgba(0, 0, 0, 0.1)',
                        borderColor: isDarkMode ? 'rgba(255, 255, 255, 0.8)' : 'rgba(0, 0, 0, 0.5)'
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        font: {
                            size: 10
                        },
                        color: textColor
                    },
                    grid: {
                        color: isDarkMode ? 'rgba(255, 255, 255, 0.5)' : 'rgba(0, 0, 0, 0.1)',
                        borderColor: isDarkMode ? 'rgba(255, 255, 255, 0.8)' : 'rgba(0, 0, 0, 0.5)'
                    }
                }
            }
        }
    });

    window.bookingStatusChart = bookingStatusChart;

    function updateChartColors() {
        const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDarkMode ? '#ffffff' : '#212529';
        
        if (window.bookingStatusChart) {

            window.bookingStatusChart.options.plugins.legend.labels.color = textColor;
            
            window.bookingStatusChart.options.scales.x.ticks.color = textColor;
            window.bookingStatusChart.options.scales.y.ticks.color = textColor;
            
            window.bookingStatusChart.options.scales.x.grid.color = isDarkMode ? 'rgba(255, 255, 255, 0.5)' : 'rgba(0, 0, 0, 0.1)';
            window.bookingStatusChart.options.scales.y.grid.color = isDarkMode ? 'rgba(255, 255, 255, 0.5)' : 'rgba(0, 0, 0, 0.1)';
            window.bookingStatusChart.options.scales.x.grid.borderColor = isDarkMode ? 'rgba(255, 255, 255, 0.8)' : 'rgba(0, 0, 0, 0.5)';
            window.bookingStatusChart.options.scales.y.grid.borderColor = isDarkMode ? 'rgba(255, 255, 255, 0.8)' : 'rgba(0, 0, 0, 0.5)';
            
            window.bookingStatusChart.update();
        }
    }


    document.addEventListener('themeChanged', updateChartColors);
    
    // Modal functions
    function openAddMoneyModal() {
        document.getElementById('addMoneyModal').style.display = 'block';
    }
    
    function closeAddMoneyModal() {
        document.getElementById('addMoneyModal').style.display = 'none';
    }
    
    function openWithdrawModal() {
        document.getElementById('withdrawModal').style.display = 'block';
    }
    
    function closeWithdrawModal() {
        document.getElementById('withdrawModal').style.display = 'none';
    }
    
    function openSetGoalModal() {
        document.getElementById('setGoalModal').style.display = 'block';
    }
    
    function closeSetGoalModal() {
        document.getElementById('setGoalModal').style.display = 'none';
    }
    
    window.addEventListener('click', function(event) {
        if (event.target == document.getElementById('addMoneyModal')) {
            closeAddMoneyModal();
        }
        if (event.target == document.getElementById('withdrawModal')) {
            closeWithdrawModal();
        }
        if (event.target == document.getElementById('setGoalModal')) {
            closeSetGoalModal();
        }
    });
    
    document.addEventListener('DOMContentLoaded', function () {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarNav = document.getElementById('sidebarNav');
        const mainContent = document.getElementById('mainContent');
        
        sidebarToggle.addEventListener('click', function() {
            sidebarNav.classList.toggle('expanded');
            mainContent.classList.toggle('expanded');
        });

/*
        function fetchBotpressStats() {
            fetch('fetch-botpress-data.php?type=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.stats) {
                        // Update booking status chart if Botpress has daily data
                        if (data.stats.dailyData && window.bookingStatusChart) {
                            window.bookingStatusChart.data.datasets[0].data = data.stats.dailyData.confirmed || window.bookingStatusChart.data.datasets[0].data;
                            window.bookingStatusChart.data.datasets[1].data = data.stats.dailyData.pending || window.bookingStatusChart.data.datasets[1].data;
                            window.bookingStatusChart.data.datasets[2].data = data.stats.dailyData.cancelled || window.bookingStatusChart.data.datasets[2].data;
                            window.bookingStatusChart.update();
                        }
                        
                        // Update stat cards if Botpress has booking counts
                        if (data.stats.totalBookings) {
                            const totalBookingsElement = document.querySelector('.stat-card:nth-child(3) .stat-card-value');
                            if (totalBookingsElement) {
                                totalBookingsElement.textContent = data.stats.totalBookings;
                            }
                        }
                        
                        if (data.stats.todayBookings) {
                            const todayBookingsElement = document.querySelector('.stat-card:nth-child(2) .stat-card-value');
                            if (todayBookingsElement) {
                                todayBookingsElement.textContent = data.stats.todayBookings;
                            }
                        }
                    }
                })
                .catch(error => console.error('Error fetching Botpress stats:', error));
        }

        // Fetch Botpress stats
        fetchBotpressStats();
        */
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
