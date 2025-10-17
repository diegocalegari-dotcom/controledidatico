<?php

$project_root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
$database_path = $project_root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

echo "Project root: " . $project_root . "\n";
echo "Resolved database path: " . $database_path . "\n";

if (!file_exists($database_path)) {
    die("Error: database.php not found at expected path: " . $database_path . "\n");
}

require_once $database_path;

$username = 'admin';


$password = 'admin123'; // CHANGE THIS TO A STRONG PASSWORD IN PRODUCTION!
$role = 'admin';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$conn = connect_db();

// Check if user already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "User '{$username}' already exists.\n";
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashed_password, $role);
    if ($stmt->execute()) {
        echo "Admin user '{$username}' created successfully.\n";
    } else {
        echo "Error creating user: " . $conn->error . "\n";
    }
}

$stmt->close();
$conn->close();
?>
