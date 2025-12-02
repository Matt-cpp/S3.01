<?php

require_once __DIR__ . '/../Model/UserModel.php';
require_once __DIR__ . '/../Model/database.php';

class SettingsPresenter
{
    private $userModel;
    private $currentUser;

    public function __construct()
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->userModel = new UserModel();



        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        } else {
            $userId = null;
        }
        $this->currentUser = $this->userModel->getUserById($userId);
    }

    //Get current user information
    public function getCurrentUser()
    {
        return $this->currentUser;
    }

    //Get user's full name
    public function getUserFullName()
    {
        if (!$this->currentUser) {
            return 'Utilisateur inconnu';
        }

        $parts = array_filter([
            $this->currentUser['first_name'],
            $this->currentUser['middle_name'] ?? '',
            $this->currentUser['last_name']
        ]);

        return implode(' ', $parts);
    }

    //Get user's role 
    public function getUserRole()
    {
        if (!$this->currentUser) {
            return 'Non défini';
        }

        return $this->userModel->getRoleLabel($this->currentUser['role']);
    }

    //Get formatted birth date
    public function getFormattedBirthDate()
    {
        if (!$this->currentUser || !$this->currentUser['birth_date']) {
            return 'Non renseignée';
        }

        $timezone = new DateTimeZone('Europe/Paris');
        $date = new DateTime($this->currentUser['birth_date'], $timezone);
        return $date->format('d/m/Y');
    }

    //Get account creation date
    public function getAccountCreationDate()
    {
        if (!$this->currentUser || !$this->currentUser['created_at']) {
            return 'Inconnue';
        }

        $timezone = new DateTimeZone('Europe/Paris');
        $date = new DateTime($this->currentUser['created_at'], $timezone);
        return $date->format('d/m/Y à H:i');
    }

    //Get user statistics (for students)
    public function getUserStatistics()
    {
        if (!$this->currentUser || $this->currentUser['role'] !== 'student') {
            return null;
        }

        return $this->userModel->getUserStatistics($this->currentUser['identifier']);
    }

    //Get theme preference from cookie
    public function getThemePreference()
    {
        return $_COOKIE['theme'] ?? 'light';
    }

    //Check if dark mode is enabled
    public function isDarkMode()
    {
        return $this->getThemePreference() === 'dark';
    }
}
