<?php

require_once __DIR__ . '/../Model/database.php';

$db = getDatabase();

if ($db->testConnection()) {
    echo "Database connection successful!\n";
} else {
    echo "Database connection failed!\n";
}
