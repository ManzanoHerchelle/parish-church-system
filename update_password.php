<?php
require_once __DIR__ . '/config/database.php';

try {
    $conn = getDBConnection();
    $newHash = '$2y$10$4rS7ph7pt4w3R0mH5nRywOdS5RDp8vR45SMjdOZU72ke4qvtEhybK';
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = 'admin@parishchurch.com'");
    $stmt->bind_param("s", $newHash);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        echo "Password updated successfully!<br>";
        
        // Verify
        $verify = $conn->query("SELECT email, password FROM users WHERE email = 'admin@parishchurch.com'");
        $user = $verify->fetch_assoc();
        echo "Admin user: " . htmlspecialchars($user['email']) . "<br>";
        echo "New hash: " . htmlspecialchars($user['password']) . "<br>";
        echo "You can now log in with: admin@parishchurch.com / admin123";
    } else {
        echo "Failed to update password";
    }
    
    closeDBConnection($conn);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
