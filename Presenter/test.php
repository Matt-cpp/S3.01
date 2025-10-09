<?php

require_once __DIR__ . '/../Model/database.php';

$db = getDatabase();
$sql ="SELECT identifier, id, last_name, first_name
FROM users
WHERE identifier = 'S12345'";
if ($db->testConnection()) {
    echo "Database connection successful!\n";
} else {
    echo "Database connection failed!\n";
}

?>
