<?php
require __DIR__ . '/vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Auth;
use Kreait\Firebase\ServiceAccount;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
try {
    $options = [
        'timeout' => 30.0, 
        'connectTimeout' => 30.0
    ];

    // Updated database URL with the correct format
    $factory = (new Factory)
         ->withServiceAccount($_ENV['FIREBASE_CREDENTIALS_PATH'])
    ->withDatabaseUri($_ENV['FIREBASE_DB_URL']); 

    $database = $factory->createDatabase();
    $auth = $factory->createAuth();
    $storage = $factory->createStorage();
    
    $reference = $database->getReference('testConnection');
    $reference->set([
        'message' => 'Connected to Firebase!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    error_log("Firebase connection established successfully");
} catch (FirebaseException $e) {
    error_log("Firebase connection failed: " . $e->getMessage());
    // Don't exit, try to continue with degraded functionality
} catch (Exception $e) {
    error_log("General error in connection.php: " . $e->getMessage());
    // Don't exit, try to continue with degraded functionality
}

function app($service) {
    global $factory, $database, $auth, $storage;
    
    switch ($service) {
        case 'firebase.database':
            return $database;
        case 'firebase.auth':
            return $auth;
        case 'firebase.storage':
            return $storage;
        default:
            return null;
    }
}

function isFirebaseWorking() {
    global $database;
    
    try {
        $testRef = $database->getReference('test_connection');
        $testRef->set(['timestamp' => date('Y-m-d H:i:s')]);
        return true;
    } catch (Exception $e) {
        error_log("Firebase check failed: " . $e->getMessage());
        return false;
    }
}
?>
