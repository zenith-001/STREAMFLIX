<?php
// db.php

// Database configuration
$host = 'localhost'; // Database host
$username = 'root'; // Database username
$password = ''; // Database password
$database = 'streamflix'; // Database name

// Create a connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check the connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Optional: Set the character set to UTF-8
mysqli_set_charset($conn, 'utf8');

// You can also define a function to close the connection if needed
function closeConnection($conn) {
    mysqli_close($conn);
}
?>
