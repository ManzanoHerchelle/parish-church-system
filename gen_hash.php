<?php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo "New bcrypt hash for 'admin123':<br>";
echo $hash . "<br><br>";
echo "Update command:<br>";
echo "UPDATE users SET password = '" . $hash . "' WHERE email = 'admin@parishchurch.com';";
?>
