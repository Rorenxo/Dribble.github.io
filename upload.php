<?php
require_once 'connection.php';

/**
 * Upload an image to Firebase Storage
 * 
 * @param array $file The $_FILES array element
 * @param string $user_id The user ID
 * @param string $court_id A unique identifier for the court
 * @return string|false The download URL if successful, false otherwise
 */
function uploadImageToFirebase($file, $user_id, $court_id) {
    // Check if file is valid
    if (!isset($file) || $file['error'] != 0) {
        error_log("File upload error: " . $file['error']);
        return false;
    }
    
    // Get file info
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_size = $file['size'];
    
    // Check file size (limit to 5MB)
    if ($file_size > 5 * 1024 * 1024) {
        error_log("File too large: " . $file_size);
        return false;
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate file extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($extension, $allowed_extensions)) {
        error_log("Invalid file extension: " . $extension);
        return false;
    }
    
    // Generate a unique filename
    $filename = 'court_' . $court_id . '_' . time() . '.' . $extension;
    
    // Local upload path (as a fallback)
    $upload_dir = 'asset/images/courts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $local_path = $upload_dir . $filename;
    
    // First try to upload locally as a fallback
    if (move_uploaded_file($file_tmp, $local_path)) {
        error_log("Image uploaded locally: " . $local_path);
        
        // Try to upload to Firebase Storage
        try {
            // Firebase Storage path
            $storage_path = 'courts/' . $user_id . '/' . $filename;
            
            // Get storage bucket
            $storage = app('firebase.storage');
            $bucket = $storage->getBucket();
            
            // Upload file to Firebase Storage
            $object = $bucket->upload(
                fopen($local_path, 'r'),
                [
                    'name' => $storage_path,
                    'predefinedAcl' => 'publicRead'
                ]
            );
            
            // Get the download URL
            $download_url = 'https://firebasestorage.googleapis.com/v0/b/' . $bucket->name() . '/o/' . urlencode($storage_path) . '?alt=media';
            
            error_log("Image uploaded to Firebase: " . $download_url);
            return $download_url;
        } catch (Exception $e) {
            error_log('Firebase Storage upload error: ' . $e->getMessage());
            // Return the local URL as a fallback
            return $local_path;
        }
    } else {
        error_log("Failed to upload image locally");
        return false;
    }
}

/**
 * Delete an image from Firebase Storage
 * 
 * @param string $image_url The image URL to delete
 * @return bool True if successful, false otherwise
 */
function deleteImageFromFirebase($image_url) {
    if (empty($image_url)) {
        return false;
    }
    
    // If it's a local path, just delete the file
    if (strpos($image_url, 'http') !== 0) {
        if (file_exists($image_url)) {
            unlink($image_url);
            error_log("Local image deleted: " . $image_url);
            return true;
        }
        return false;
    }
    
    try {
        // Extract the path from the URL
        $url_parts = parse_url($image_url);
        if (!isset($url_parts['path'])) {
            return false;
        }
        
        $path = $url_parts['path'];
        
        // Extract the object name
        preg_match('/\/o\/(.+)/', $path, $matches);
        if (isset($matches[1])) {
            $object_name = urldecode($matches[1]);
            
            // Create a storage reference
            $storage = app('firebase.storage');
            $bucket = $storage->getBucket();
            
            // Delete the object
            $object = $bucket->object($object_name);
            if ($object->exists()) {
                $object->delete();
                error_log("Image deleted from Firebase: " . $image_url);
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log('Firebase Storage delete error: ' . $e->getMessage());
        return false;
    }
}
?>
