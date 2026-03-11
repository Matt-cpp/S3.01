<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../../Model/database.php';

class SettingsPresenter
{
    private UserModel $userModel;
    private ?array $currentUser;

    public function __construct()
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->userModel = new UserModel();

        $userId = $_SESSION['user_id'] ?? null;
        $this->currentUser = $this->userModel->getUserById($userId);
    }

    public function getCurrentUser(): ?array
    {
        return $this->currentUser;
    }

    public function getUserFullName(): string
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

    public function getUserRole(): string
    {
        if (!$this->currentUser) {
            return 'Non défini';
        }

        return $this->userModel->getRoleLabel($this->currentUser['role']);
    }

    public function getFormattedBirthDate(): string
    {
        if (!$this->currentUser || !$this->currentUser['birth_date']) {
            return 'Non renseignée';
        }

        $timezone = new DateTimeZone('Europe/Paris');
        $date = new DateTime($this->currentUser['birth_date'], $timezone);
        return $date->format('d/m/Y');
    }

    public function getAccountCreationDate(): string
    {
        if (!$this->currentUser || !$this->currentUser['created_at']) {
            return 'Inconnue';
        }

        $timezone = new DateTimeZone('Europe/Paris');
        $date = new DateTime($this->currentUser['created_at'], $timezone);
        return $date->format('d/m/Y à H:i');
    }

    public function getUserStatistics(): ?array
    {
        if (!$this->currentUser || $this->currentUser['role'] !== 'student') {
            return null;
        }

        return $this->userModel->getUserStatistics($this->currentUser['identifier']);
    }

    public function getThemePreference(): string
    {
        return $_COOKIE['theme'] ?? 'light';
    }

    public function isDarkMode(): bool
    {
        return $this->getThemePreference() === 'dark';
    }
}
