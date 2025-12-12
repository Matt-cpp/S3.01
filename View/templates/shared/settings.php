<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - UPHF</title>
    <?php include __DIR__ . '/../../includes/theme-helper.php'; renderThemeSupport(); ?>
    <link rel="stylesheet" href="/View/assets/css/shared/navbar.css">
    <link rel="stylesheet" href="/View/assets/css/shared/settings.css">
</head>
<body>
    <?php
    require_once __DIR__ . '/../../../controllers/auth_guard.php';
    $authUser = requireAuth(); // Available to all authenticated users
    
    require_once __DIR__ . '/../../../Presenter/shared/settings-presenter.php';
    $presenter = new SettingsPresenter();
    $user = $presenter->getCurrentUser();
    $statistics = $presenter->getUserStatistics();
    ?>
    
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="main-content">
        <div class="settings-container">
            <h1 class="page-title">Paramètres</h1>
            <p class="page-subtitle">Gérez vos informations personnelles et vos préférences</p>

            <!-- User Information Section -->
            <div class="settings-section">
                <div class="section-header">
                    <h2 class="section-title">Informations personnelles</h2>
                    <p class="section-description">Vos informations de compte</p>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label class="info-label">Nom complet</label>
                        <div class="info-value"><?php echo htmlspecialchars($presenter->getUserFullName()); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <label class="info-label">Identifiant</label>
                        <div class="info-value"><?php echo htmlspecialchars($user['identifier'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <label class="info-label">Rôle</label>
                        <div class="info-value">
                            <span class="role-badge"><?php echo htmlspecialchars($presenter->getUserRole()); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <label class="info-label">Email</label>
                        <div class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'Non renseigné'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <label class="info-label">Date de naissance</label>
                        <div class="info-value"><?php echo htmlspecialchars($presenter->getFormattedBirthDate()); ?></div>
                    </div>
                    
                    <?php if (!empty($user['department'])): ?>
                    <div class="info-item">
                        <label class="info-label">Département</label>
                        <div class="info-value"><?php echo htmlspecialchars($user['department']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($user['degrees'])): ?>
                    <div class="info-item">
                        <label class="info-label">Formation</label>
                        <div class="info-value"><?php echo htmlspecialchars($user['degrees']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <label class="info-label">Compte créé le</label>
                        <div class="info-value"><?php echo htmlspecialchars($presenter->getAccountCreationDate()); ?></div>
                    </div>
                </div>
            </div>

            <!-- Statistics Section (for students) -->
            <?php if ($statistics): ?>
            <div class="settings-section">
                <div class="section-header">
                    <h2 class="section-title">Statistiques</h2>
                    <p class="section-description">Vos absences en un coup d'œil</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-number"><?php echo $statistics['total_absences']; ?></div>
                        <div class="stat-label">Absences totales</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-icon"></div>
                        <div class="stat-number"><?php echo $statistics['justified_absences']; ?></div>
                        <div class="stat-label">Absences justifiées</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-icon"></div>
                        <div class="stat-number"><?php echo $statistics['unjustified_absences']; ?></div>
                        <div class="stat-label">Absences non justifiées</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Password Change Section -->
            <div class="settings-section">
                <div class="section-header">
                    <h2 class="section-title">Sécurité</h2>
                    <p class="section-description">Modifiez votre mot de passe</p>
                </div>
                
                <form id="password-form" class="settings-form">
                    <div class="form-group">
                        <label for="current-password" class="form-label">Mot de passe actuel</label>
                        <input 
                            type="password" 
                            id="current-password" 
                            name="current_password" 
                            class="form-input"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="new-password" class="form-label">Nouveau mot de passe</label>
                        <input 
                            type="password" 
                            id="new-password" 
                            name="new_password" 
                            class="form-input"
                            minlength="8"
                            required
                        >
                        <small class="form-hint">Le mot de passe doit contenir au moins 8 caractères</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm-password" class="form-label">Confirmer le nouveau mot de passe</label>
                        <input 
                            type="password" 
                            id="confirm-password" 
                            name="confirm_password" 
                            class="form-input"
                            required
                        >
                    </div>
                    
                    <div id="password-message" class="message" style="display: none;"></div>
                    
                    <button type="submit" class="btn btn-primary">
                        Modifier le mot de passe
                    </button>
                </form>
            </div>

            <!-- Appearance Section -->
            <div class="settings-section">
                <div class="section-header">
                    <h2 class="section-title">Apparence</h2>
                    <p class="section-description">Personnalisez l'interface</p>
                </div>
                
                <div class="theme-selector">
                    <label class="theme-option">
                        <input 
                            type="radio" 
                            name="theme" 
                            value="light" 
                            <?php echo $presenter->isDarkMode() ? '' : 'checked'; ?>
                        >
                        <div class="theme-card">
                            <div class="theme-preview light-preview">
                                <div class="preview-header"></div>
                                <div class="preview-content">
                                    <div class="preview-line"></div>
                                    <div class="preview-line"></div>
                                    <div class="preview-line short"></div>
                                </div>
                            </div>
                            <div class="theme-name">Mode clair</div>
                        </div>
                    </label>
                    
                    <label class="theme-option">
                        <input 
                            type="radio" 
                            name="theme" 
                            value="dark"
                            <?php echo $presenter->isDarkMode() ? 'checked' : ''; ?>
                        >
                        <div class="theme-card">
                            <div class="theme-preview dark-preview">
                                <div class="preview-header"></div>
                                <div class="preview-content">
                                    <div class="preview-line"></div>
                                    <div class="preview-line"></div>
                                    <div class="preview-line short"></div>
                                </div>
                            </div>
                            <div class="theme-name">Mode sombre</div>
                        </div>
                    </label>
                </div>
            </div>

        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script src="/View/assets/js/shared/settings.js"></script>
</body>
</html>
