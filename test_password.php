<?php
$password = 'admin123';
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

echo "Testing password verification:<br>";
echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br>";
echo "Result: " . (password_verify($password, $hash) ? "VERIFIED" : "FAILED") . "<br>";
?>
