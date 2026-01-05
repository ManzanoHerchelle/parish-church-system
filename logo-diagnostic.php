<?php
require_once __DIR__ . '/config/database.php';

$conn = getDBConnection();

echo "<h2>Logo Management Diagnostic</h2>";

// Check if table exists
$tableCheck = $conn->query("SELECT 1 FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA='parish_church_system' AND TABLE_NAME='system_logos'");

if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p style='color: green;'><strong>✓ system_logos table EXISTS</strong></p>";
    
    // Count logos
    $countResult = $conn->query("SELECT COUNT(*) as total FROM system_logos");
    $count = $countResult->fetch_assoc()['total'];
    echo "<p>Total logos in database: <strong>$count</strong></p>";
    
    if ($count > 0) {
        // Show all logos
        echo "<h3>All Logos:</h3>";
        echo "<table border='1' cellpadding='10' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Name</th><th>File Path</th><th>Active</th><th>Archived</th></tr>";
        
        $logosResult = $conn->query("SELECT id, name, file_path, is_active, is_archived FROM system_logos");
        while ($logo = $logosResult->fetch_assoc()) {
            $active = $logo['is_active'] ? '✓ YES' : 'NO';
            $archived = $logo['is_archived'] ? 'YES' : '✓ NO';
            echo "<tr>";
            echo "<td>{$logo['id']}</td>";
            echo "<td>{$logo['name']}</td>";
            echo "<td>{$logo['file_path']}</td>";
            echo "<td style='color: " . ($logo['is_active'] ? 'green' : 'red') . ";'>{$active}</td>";
            echo "<td style='color: " . ($logo['is_archived'] ? 'red' : 'green') . ";'>{$archived}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check active logo
        $activeResult = $conn->query("SELECT * FROM system_logos WHERE is_active = 1 AND is_archived = 0");
        if ($activeResult->num_rows > 0) {
            $activeLogo = $activeResult->fetch_assoc();
            echo "<h3>Active Logo:</h3>";
            echo "<p><strong>Name:</strong> {$activeLogo['name']}</p>";
            echo "<p><strong>File Path:</strong> {$activeLogo['file_path']}</p>";
            echo "<p><strong>Full URL:</strong> /documentSystem/{$activeLogo['file_path']}</p>";
            
            // Check if file exists
            $filePath = __DIR__ . '/' . $activeLogo['file_path'];
            if (file_exists($filePath)) {
                echo "<p style='color: green;'><strong>✓ File EXISTS on server</strong></p>";
                echo "<p><strong>Preview:</strong></p>";
                echo "<img src='/documentSystem/{$activeLogo['file_path']}' style='max-width: 100px; max-height: 100px; border: 1px solid #ccc;' alt='Active Logo'>";
            } else {
                echo "<p style='color: red;'><strong>✗ File NOT FOUND on server</strong></p>";
                echo "<p>Expected location: $filePath</p>";
            }
        } else {
            echo "<p style='color: orange;'><strong>⚠ No active logo set</strong></p>";
            echo "<p>Please go to Admin → Manage Logos and set a logo as active</p>";
        }
    } else {
        echo "<p style='color: orange;'><strong>⚠ No logos uploaded yet</strong></p>";
        echo "<p>Please go to Admin → Manage Logos and upload a logo</p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ system_logos table DOES NOT EXIST</strong></p>";
    echo "<p>Please visit the Manage Logos page first to create the table</p>";
}

$conn->close();
?>
