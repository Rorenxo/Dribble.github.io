<?php
session_start();
/*
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
    */
require_once 'connection.php';
require_once 'theme.php'; 

$user_id = $_SESSION['user_id'];

$userCourtsRef = $database->getReference('user_courts/' . $user_id);
$userCourts = $userCourtsRef->getValue();

if ($userCourts !== null) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $numCourts = intval($_POST['numCourts']);
    $courts = [];

    $isValid = true;
    for ($i = 1; $i <= $numCourts; $i++) {
        if (empty($_POST["court_name_$i"]) || empty($_POST["court_address_$i"])) {
            $isValid = false;
            $error = "All court details are required";
            break;
        }
    }

    if ($isValid) {
        // Process each court
        for ($i = 1; $i <= $numCourts; $i++) {
            $courtData = [
                'name' => $_POST["court_name_$i"],
                'address' => $_POST["court_address_$i"],
                'image_url' => '' 
            ];

            // Handle image upload if present
            if (isset($_FILES["court_image_$i"]) && $_FILES["court_image_$i"]['error'] == 0) {
                require_once 'upload.php';
                
                // Debug information
                error_log("Uploading image for court $i");
                error_log("File info: " . print_r($_FILES["court_image_$i"], true));
                
                $image_url = uploadImageToFirebase($_FILES["court_image_$i"], $user_id, "court_$i");
                if ($image_url) {
                    error_log("Image uploaded successfully: " . $image_url);
                    $courtData['image_url'] = $image_url;
                } else {
                    error_log("Failed to upload image for court $i");
                }
            }

            $courts[] = $courtData;
        }

        // Save to Firebase Realtime Database
        $userCourtsRef->set([
            'count' => $numCourts,
            'courts' => $courts,
            'setup_completed' => true,
            'setup_date' => date('Y-m-d H:i:s')
        ]);

        // Redirect to dashboard
        header("Location: dashboard.php");
        exit();
    }
}
// Get current theme
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Add Courts</title>
    <link rel="icon" href="asset/imoticon.png" type="image/png">
    <link rel="stylesheet" href="asset/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/setup.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="asset/image-styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <img src="asset/nobg.png" alt="KORTambayan Logo" class="logo">
            <h1>Welcome to Dribble!</h1>
            <p>Let's set up your courts to get started with your scheduling system</p>
        </div>

        <div class="progress-indicator">
            <div class="progress-step">
                <div class="step-number active" id="step1Number">1</div>
                <div class="step-text active" id="step1Text">Number of Courts</div>
            </div>
            <div class="progress-step">
                <div class="step-number" id="step2Number">2</div>
                <div class="step-text" id="step2Text">Court Details</div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="setup-step" id="step1">
            <h2><i class="fas fa-basketball-ball"></i> How many courts do you want to register?</h2>
            <div class="court-counter">
                <button type="button" id="decreaseCourts" class="counter-btn"><i class="fas fa-minus"></i></button>
                <input type="number" id="numCourts" value="1" min="1" max="10">
                <button type="button" id="increaseCourts" class="counter-btn"><i class="fas fa-plus"></i></button>
            </div>
            <button type="button" id="nextStep" class="btn-primary">Continue <i class="fas fa-arrow-right"></i></button>
        </div>

        <div class="setup-step" id="step2" style="display: none;">
            <h2><i class="fas fa-info-circle"></i> Enter Court Details</h2>
            <form method="POST" action="" enctype="multipart/form-data" id="courtForm">
                <input type="hidden" name="numCourts" id="numCourtsHidden" value="1">
                
                <div id="courtFields"></div>
                
                <div class="form-buttons">
                    <button type="button" id="backStep" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="submit" class="btn-primary">Complete Setup <i class="fas fa-check"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentNumCourts = 1;
        
        // DOM elements
        const numCourtsInput = document.getElementById('numCourts');
        const numCourtsHidden = document.getElementById('numCourtsHidden');
        const decreaseBtn = document.getElementById('decreaseCourts');
        const increaseBtn = document.getElementById('increaseCourts');
        const nextStepBtn = document.getElementById('nextStep');
        const backStepBtn = document.getElementById('backStep');
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const courtFields = document.getElementById('courtFields');
        const step1Number = document.getElementById('step1Number');
        const step2Number = document.getElementById('step2Number');
        const step1Text = document.getElementById('step1Text');
        const step2Text = document.getElementById('step2Text');
        
        // Update court count
        function updateCourtCount(value) {
            currentNumCourts = parseInt(value);
            numCourtsInput.value = currentNumCourts;
            numCourtsHidden.value = currentNumCourts;
            
            decreaseBtn.disabled = currentNumCourts <= 1;
            increaseBtn.disabled = currentNumCourts >= 10;
        }
        
        // Generate court fields
        function generateCourtFields() {
            courtFields.innerHTML = '';
            
            for (let i = 1; i <= currentNumCourts; i++) {
                const courtField = document.createElement('div');
                courtField.className = 'court-field';
                courtField.innerHTML = `
                    <h3>Court ${i}</h3>
                    <div class="form-group">
                        <label for="court_name_${i}">Court Name</label>
                        <input type="text" id="court_name_${i}" name="court_name_${i}" placeholder="Enter court name" required>
                    </div>
                    <div class="form-group">
                        <label for="court_address_${i}">Court Address</label>
                        <input type="text" id="court_address_${i}" name="court_address_${i}" placeholder="Enter court address" required>
                    </div>
                    <div class="form-group">
                        <label for="court_image_${i}">Court Image (Optional)</label>
                        <div class="image-upload">
                            <div class="image-preview" id="imagePreview_${i}">
                                <i class="fas fa-image"></i>
                                <span>No image selected</span>
                            </div>
                            <label for="court_image_${i}" class="upload-btn">
                                <i class="fas fa-upload"></i> Choose Image
                            </label>
                            <input type="file" id="court_image_${i}" name="court_image_${i}" accept="image/*" style="display: none;">
                        </div>
                    </div>
                `;
                courtFields.appendChild(courtField);
                
                // Add image preview functionality
                const imageInput = document.getElementById(`court_image_${i}`);
                const imagePreview = document.getElementById(`imagePreview_${i}`);
                
                imageInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.innerHTML = `<img src="${e.target.result}" alt="Court Image Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                        };
                        reader.readAsDataURL(this.files[0]);
                    } else {
                        imagePreview.innerHTML = `
                            <i class="fas fa-image"></i>
                            <span>No image selected</span>
                        `;
                    }
                });
            }
        }
        
        // Update progress indicator
        function updateProgressIndicator(step) {
            if (step === 1) {
                step1Number.classList.add('active');
                step1Text.classList.add('active');
                step2Number.classList.remove('active');
                step2Text.classList.remove('active');
            } else {
                step1Number.classList.add('active');
                step1Text.classList.add('active');
                step2Number.classList.add('active');
                step2Text.classList.add('active');
            }
        }
        
        // Event listeners
        decreaseBtn.addEventListener('click', function() {
            if (currentNumCourts > 1) {
                updateCourtCount(currentNumCourts - 1);
            }
        });
        
        increaseBtn.addEventListener('click', function() {
            if (currentNumCourts < 10) {
                updateCourtCount(currentNumCourts + 1);
            }
        });
        
        numCourtsInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value) || value < 1) value = 1;
            if (value > 10) value = 10;
            updateCourtCount(value);
        });
        
        nextStepBtn.addEventListener('click', function() {
            step1.style.display = 'none';
            step2.style.display = 'block';
            updateProgressIndicator(2);
            generateCourtFields();

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        backStepBtn.addEventListener('click', function() {
            step2.style.display = 'none';
            step1.style.display = 'block';
            updateProgressIndicator(1);

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Initialize
        updateCourtCount(currentNumCourts);
        updateProgressIndicator(1);
    </script>
</body>
</html>