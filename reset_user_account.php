<?php

/**
 * Fichier: reset_user_account.php
 * 
 * Script pour réinitialiser un compte utilisateur.
 * Supprime toutes les données associées (absences, justificatifs, notifications, etc.)
 * tout en conservant le compte utilisateur.
 */

require_once __DIR__ . '/Model/database.php';

// Check if running from command line or web
$isCLI = php_sapi_name() === 'cli';

/**
 * Display message based on execution context
 */
function displayMessage(string $message, string $type = 'info', bool $isCLI = false): void
{
    if ($isCLI) {
        $prefix = match ($type) {
            'success' => "[✓] ",
            'error' => "[✗] ",
            'warning' => "[!] ",
            default => "[i] "
        };
        echo $prefix . $message . PHP_EOL;
    } else {
        $class = match ($type) {
            'success' => 'color: green;',
            'error' => 'color: red;',
            'warning' => 'color: orange;',
            default => 'color: blue;'
        };
        echo "<p style='$class'>$message</p>";
    }
}

/**
 * Reset user account - deletes all associated data while keeping the user
 */
function resetUserAccount(int $userId): array
{
    $db = getDatabase();
    $results = [
        'success' => false,
        'message' => '',
        'deleted' => []
    ];

    try {
        // First, verify the user exists
        $user = $db->selectOne("SELECT id, identifier, first_name, last_name, email, role FROM users WHERE id = :id", ['id' => $userId]);

        if (!$user) {
            $results['message'] = "User with ID $userId not found.";
            return $results;
        }

        $db->beginTransaction();

        $studentIdentifier = $user['identifier'];

        // 1. Delete from decision_history (references proof)
        $proofIds = $db->select(
            "SELECT id FROM proof WHERE student_identifier = :identifier",
            ['identifier' => $studentIdentifier]
        );

        if (!empty($proofIds)) {
            $proofIdList = array_column($proofIds, 'id');
            $placeholders = implode(',', array_fill(0, count($proofIdList), '?'));
            $count = $db->execute(
                "DELETE FROM decision_history WHERE justification_id IN ($placeholders)",
                $proofIdList
            );
            $results['deleted']['decision_history'] = $count;
        }

        // 2. Delete from proof_absences (references proof and absences)
        if (!empty($proofIds)) {
            $proofIdList = array_column($proofIds, 'id');
            $placeholders = implode(',', array_fill(0, count($proofIdList), '?'));
            $count = $db->execute(
                "DELETE FROM proof_absences WHERE proof_id IN ($placeholders)",
                $proofIdList
            );
            $results['deleted']['proof_absences'] = $count;
        }

        // 3. Delete from proof table
        $count = $db->execute(
            "DELETE FROM proof WHERE student_identifier = :identifier",
            ['identifier' => $studentIdentifier]
        );
        $results['deleted']['proof'] = $count;

        // 4. Delete from makeups (references absences)
        $count = $db->execute(
            "DELETE FROM makeups WHERE student_identifier = :identifier",
            ['identifier' => $studentIdentifier]
        );
        $results['deleted']['makeups'] = $count;

        // 5. Delete from notifications
        $count = $db->execute(
            "DELETE FROM notifications WHERE student_identifier = :identifier",
            ['identifier' => $studentIdentifier]
        );
        $results['deleted']['notifications'] = $count;

        // 6. Delete from absence_monitoring
        $count = $db->execute(
            "DELETE FROM absence_monitoring WHERE student_id = :user_id OR student_identifier = :identifier",
            ['user_id' => $userId, 'identifier' => $studentIdentifier]
        );
        $results['deleted']['absence_monitoring'] = $count;

        // 7. Delete from absences
        $count = $db->execute(
            "DELETE FROM absences WHERE student_identifier = :identifier",
            ['identifier' => $studentIdentifier]
        );
        $results['deleted']['absences'] = $count;

        // 8. Delete from user_groups
        $count = $db->execute(
            "DELETE FROM user_groups WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        $results['deleted']['user_groups'] = $count;

        // 9. Delete from email_verifications (by email)
        if (!empty($user['email'])) {
            $count = $db->execute(
                "DELETE FROM email_verifications WHERE email = :email",
                ['email' => $user['email']]
            );
            $results['deleted']['email_verifications'] = $count;
        }

        $db->commit();

        $results['success'] = true;
        $results['message'] = "Account reset successful for user: {$user['first_name']} {$user['last_name']} (ID: $userId)";
        $results['user'] = $user;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $results['message'] = "Error resetting account: " . $e->getMessage();
        error_log("Reset account error: " . $e->getMessage());
    }

    return $results;
}

// ============================================
// MAIN EXECUTION
// ============================================

if ($isCLI) {
    // Command line execution
    echo "========================================" . PHP_EOL;
    echo "   USER ACCOUNT RESET TOOL" . PHP_EOL;
    echo "========================================" . PHP_EOL . PHP_EOL;

    // Get user ID from command line argument or prompt
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $userId = (int) $argv[1];
    } else {
        echo "Enter the user ID to reset: ";
        $userId = (int) trim(fgets(STDIN));
    }

    if ($userId <= 0) {
        displayMessage("Invalid user ID. Please provide a positive integer.", 'error', true);
        exit(1);
    }

    // Confirm action
    echo PHP_EOL . "WARNING: This will delete ALL data associated with user ID $userId:" . PHP_EOL;
    echo "  - Absences" . PHP_EOL;
    echo "  - Proofs/Justifications" . PHP_EOL;
    echo "  - Decision history" . PHP_EOL;
    echo "  - Notifications" . PHP_EOL;
    echo "  - Makeups" . PHP_EOL;
    echo "  - Group memberships" . PHP_EOL;
    echo "  - Absence monitoring records" . PHP_EOL;
    echo "  - Email verifications" . PHP_EOL;
    echo PHP_EOL . "The user account itself will be kept." . PHP_EOL;
    echo PHP_EOL . "Are you sure you want to continue? (yes/no): ";

    $confirmation = trim(fgets(STDIN));

    if (strtolower($confirmation) !== 'yes') {
        displayMessage("Operation cancelled.", 'warning', true);
        exit(0);
    }

    echo PHP_EOL;
    $result = resetUserAccount($userId);

    if ($result['success']) {
        displayMessage($result['message'], 'success', true);
        echo PHP_EOL . "Deleted records:" . PHP_EOL;
        foreach ($result['deleted'] as $table => $count) {
            echo "  - $table: $count record(s)" . PHP_EOL;
        }
    } else {
        displayMessage($result['message'], 'error', true);
        exit(1);
    }

} else {
    // Web execution
    ?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset User Account</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background-color: #f5f5f5;
            }

            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            h1 {
                color: #333;
                margin-bottom: 20px;
            }

            .warning {
                background-color: #fff3cd;
                border: 1px solid #ffc107;
                color: #856404;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }

            input[type="number"] {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
            }

            button {
                background-color: #dc3545;
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
            }

            button:hover {
                background-color: #c82333;
            }

            .result {
                margin-top: 20px;
                padding: 15px;
                border-radius: 5px;
            }

            .result.success {
                background-color: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }

            .result.error {
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }

            .deleted-list {
                margin-top: 10px;
                padding-left: 20px;
            }

            .deleted-list li {
                margin: 5px 0;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <h1>🔄 Reset User Account</h1>

            <div class="warning">
                <strong>⚠️ Warning:</strong> This action will permanently delete all data associated with the user account:
                <ul>
                    <li>Absences</li>
                    <li>Proofs/Justifications</li>
                    <li>Decision history</li>
                    <li>Notifications</li>
                    <li>Makeups</li>
                    <li>Group memberships</li>
                    <li>Absence monitoring records</li>
                    <li>Email verifications</li>
                </ul>
                <strong>The user account itself will be preserved.</strong>
            </div>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
                $userId = (int) $_POST['user_id'];

                if ($userId <= 0) {
                    echo '<div class="result error">Invalid user ID. Please provide a positive integer.</div>';
                } else {
                    $result = resetUserAccount($userId);

                    if ($result['success']) {
                        echo '<div class="result success">';
                        echo '<strong>✓ ' . htmlspecialchars($result['message']) . '</strong>';
                        echo '<ul class="deleted-list">';
                        foreach ($result['deleted'] as $table => $count) {
                            echo '<li><strong>' . htmlspecialchars($table) . ':</strong> ' . $count . ' record(s) deleted</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    } else {
                        echo '<div class="result error">';
                        echo '<strong>✗ Error:</strong> ' . htmlspecialchars($result['message']);
                        echo '</div>';
                    }
                }
            }
            ?>

            <form method="POST"
                onsubmit="return confirm('Are you sure you want to reset this user account? This action cannot be undone.');">
                <div class="form-group">
                    <label for="user_id">User ID:</label>
                    <input type="number" id="user_id" name="user_id" min="1" required
                        placeholder="Enter the user ID to reset"
                        value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>">
                </div>
                <button type="submit">Reset Account</button>
            </form>
        </div>
    </body>

    </html>
    <?php
}
