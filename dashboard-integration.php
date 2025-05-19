<?php
$user_id = $_SESSION['user_id'];


$userCourtsRef = $database->getReference('user_courts/' . $user_id);
$userCourts = $userCourtsRef->getValue();
$courts = $userCourts['courts'] ?? [];

$courtsForJs = [];
foreach ($courts as $index => $court) {
    $courtsForJs[] = [
        'id' => $index,
        'name' => $court['name'],
        'address' => $court['address'],
        'status' => $court['status'] ?? 'available'
    ];
}
$courtsJson = json_encode($courtsForJs);
?>

<script>

const userId = '<?php echo $user_id; ?>';
const userCourts = <?php echo $courtsJson; ?>;


function initBotpress() {
    window.botpress.init({
        "botId": "038fd471-3da2-4b8f-a471-3bd98e666424",
        "configuration": {
            "botName": "Bmax",
            "website": {},
            "email": {},
            "phone": {},
            "termsOfService": {},
            "privacyPolicy": {},
            "color": "#68181F",
            "variant": "solid",
            "themeMode": "light",
            "fontFamily": "rubik",
            "radius": 1,
            "hideWidget": true,
            "disableAnimations": true,
            "embedded": true
        },
        "clientId": "fcedd2a2-bfa2-474c-9f03-be7b2b656caf",
        "selector": "#webchat"
    });
    
  
    setTimeout(function() {

        if (window.botpress) {

            window.botpress.sendMessage({
                type: 'session_reset',
                payload: {
                    user_id: userId,
                    courts: userCourts
                }
            });
        }
    }, 1000);
}

document.addEventListener('DOMContentLoaded', function() {
    initBotpress();
});
</script>

<script src="botpress-integration.js?v=<?php echo time(); ?>"></script>
