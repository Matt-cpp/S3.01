<!DOCTYPE html>
<html lang="fr">
<?php
/**
 * Fichier: info.php
 * 
 * Template d'information pour les étudiants - Gère l'affichage des informations et procédures relatives à la justification des absences.
 * Contient des sections détaillées sur:
 * - L'importance de l'assiduité
 * - Le délai de justification des absences
 * - Les conséquences des absences non justifiées
 * - Les motifs acceptables de justification
 * - La procédure de soumission d'un justificatif
 * - Le suivi des justificatifs soumis
 * - Les contacts pour assistance
 * Utilisé par la page d'information des étudiants.
 */
require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$user = requireRole('student');

// Use the authenticated user's ID
if (!isset($_SESSION['id_student'])) {
    $_SESSION['id_student'] = $user['id'];
}
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <title>Informations et Procédure</title>

    <link rel="stylesheet" href="../../assets/css/student/info.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>

    <main>
        <h1 class="page-title">Informations et Procédure de Justification</h1>

        <div class="info-container">
            <!-- Section 1: Importance de l'assiduité et règlement intérieur -->
            <section class="info-section important-section">
                <div class="section-header">
                    <span class="section-icon">⚠️</span>
                    <h2>Assiduité Obligatoire</h2>
                </div>
                <div class="section-content">
                    <p class="highlight-text">
                        <strong>L'assiduité aux cours est OBLIGATOIRE</strong> pour tous les étudiants inscrits à l'IUT.
                    </p>
                    <p>
                        Conformément au <strong>règlement intérieur de l'établissement</strong>, la présence à
                        l'ensemble
                        des cours (CM, TD, TP) et évaluations est impérative. Toute absence doit être justifiée selon
                        les modalités décrites ci-dessous.
                    </p>
                    <div class="link-box">
                        <span class="link-icon">📄</span>
                        <div>
                            <strong>Règlement intérieur :</strong><br>
                            <a href="https://recueildesactes.uphf.fr/download/f3c230cc-c68b-45b0-b1b9-b7e60868b6ce"
                                target="_blank" class="external-link">
                                Consultez le règlement intérieur complet ici (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 2: Explication du délai de justification avec exemples -->
            <section class="info-section">
                <div class="section-header">
                    <span class="section-icon">⏱️</span>
                    <h2>Délai de Justification</h2>
                </div>
                <div class="section-content">
                    <div class="deadline-box">
                        <h3>📅 Délai impératif : 48 heures ouvrées</h3>
                        <p>
                            Vous disposez de <strong>48 heures ouvrées (2 jours ouvrés)</strong> après votre retour en
                            cours
                            pour soumettre votre justificatif d'absence.
                        </p>
                    </div>

                    <div class="example-box">
                        <h4>💡 Exemple de calcul du délai :</h4>
                        <ul>
                            <li>
                                <strong>Absence :</strong> Lundi 10h - 12h
                            </li>
                            <li>
                                <strong>Retour en cours :</strong> Mardi 8h
                            </li>
                            <li>
                                <strong>Délai limite de soumission :</strong> Jeudi 8h
                                <span class="note">(2 jours ouvrés après le retour)</span>
                            </li>
                        </ul>
                    </div>

                    <div class="example-box">
                        <h4>💡 Exemple avec week-end :</h4>
                        <ul>
                            <li>
                                <strong>Absence :</strong> Vendredi 14h - 17h
                            </li>
                            <li>
                                <strong>Retour en cours :</strong> Lundi 8h
                            </li>
                            <li>
                                <strong>Délai limite de soumission :</strong> Mercredi 8h
                                <span class="note">(les week-ends ne sont pas comptés)</span>
                            </li>
                        </ul>
                    </div>

                    <div class="warning-box">
                        <span class="warning-icon">⚠️</span>
                        <div>
                            <strong>Important :</strong> Au-delà de ce délai, votre justificatif risque fortement
                            de ne pas être pris en compte. Un message d'avertissement vous sera affiché lors
                            de la soumission si le délai est dépassé.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 3: Conséquences d'une absence non justifiée -->
            <section class="info-section alert-section">
                <div class="section-header">
                    <span class="section-icon">❌</span>
                    <h2>Conséquences d'une Absence Non Justifiée</h2>
                </div>
                <div class="section-content">
                    <div class="consequence-box">
                        <h3>Impact sur la moyenne selon le règlement du BUT Informatique</h3>
                        <p class="highlight-text">
                            <strong>Selon le règlement de la formation du BUT Informatique :</strong><br>
                            <span style="font-size: 1.1em; color: #dc2626;">
                                5 demi-journées d'absences non justifiées = -0,5 point sur la moyenne
                            </span>
                        </p>
                        <p>
                            Ce système de pénalité s'applique de manière cumulative. Plus vous accumulez d'absences
                            non justifiées, plus l'impact sur votre moyenne sera important.
                        </p>
                    </div>

                    <div class="consequence-box">
                        <h3>Remplacement de la mention "ABS" par un "0"</h3>
                        <p>
                            Si vous êtes absent(e) à une <strong>évaluation notée</strong> (contrôle continu, examen, TP
                            noté, etc.)
                            et que votre absence n'est pas justifiée dans les délais :
                        </p>
                        <ul>
                            <li>La mention "ABS" (Absent) sera <strong>automatiquement remplacée par la note
                                    0/20</strong></li>
                            <li>Cette note de 0 sera comptabilisée dans votre moyenne</li>
                            <li>Cela peut avoir un impact significatif sur votre moyenne générale et celle du module
                                concerné</li>
                        </ul>
                    </div>

                    <div class="warning-box danger">
                        <span class="warning-icon">🚨</span>
                        <div>
                            <strong>Attention :</strong> Une absence justifiée après le délai de 48h peut également
                            être considérée comme non justifiée et entraîner un 0 selon l'appréciation de l'équipe
                            pédagogique.
                        </div>
                    </div>

                    <div class="info-box">
                        <span class="info-icon">ℹ️</span>
                        <div>
                            <strong>Bon à savoir :</strong> Si votre justificatif est accepté, la mention "ABS"
                            sera conservée sans pénalité. La note ne sera pas comptabilisée dans votre moyenne
                            (absence justifiée neutralisée).
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 4: Motifs acceptables -->
            <section class="info-section">
                <div class="section-header">
                    <span class="section-icon">✅</span>
                    <h2>Motifs Acceptables de Justification</h2>
                </div>
                <div class="section-content">
                    <p>Les motifs suivants sont généralement acceptés avec les pièces justificatives appropriées :</p>

                    <div class="motifs-grid">
                        <div class="motif-card">
                            <h4>Maladie</h4>
                            <p>Certificat médical obligatoire précisant les dates d'arrêt</p>
                        </div>

                        <div class="motif-card">
                            <h4>Décès dans la famille</h4>
                            <p>Acte de décès ou faire-part avec justificatif de lien de parenté</p>
                        </div>

                        <div class="motif-card">
                            <h4>Obligations familiales</h4>
                            <p>Justificatif approprié selon la situation</p>
                        </div>

                        <div class="motif-card">
                            <h4>Rendez-vous médical</h4>
                            <p>Convocation ou attestation du praticien avec date et horaire</p>
                        </div>

                        <div class="motif-card">
                            <h4>Convocation officielle</h4>
                            <p>Convocation pour permis de conduire, TOIC, tribunal, etc.</p>
                        </div>

                        <div class="motif-card">
                            <h4>Problème de transport</h4>
                            <p>Attestation de retard ou incident de transport en commun</p>
                        </div>
                    </div>

                    <div class="info-box">
                        <span class="info-icon">📄</span>
                        <div>
                            <strong>Documents requis :</strong> Tous les justificatifs doivent être des documents
                            officiels (certificats, attestations, convocations, etc.) mentionnant clairement
                            les dates et heures concernées.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 5: Procédure de soumission -->
            <section class="info-section">
                <div class="section-header">
                    <span class="section-icon">📤</span>
                    <h2>Procédure de Soumission d'un Justificatif</h2>
                </div>
                <div class="section-content">
                    <div class="steps-container">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Accéder au formulaire</h4>
                                <p>Cliquez sur "Soumettre justificatif" dans le menu de navigation</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Sélectionner les dates de début et de fin de votre absence</h4>
                                <p>Indiquez la période d'absence que vous souhaitez justifier</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Indiquer le motif</h4>
                                <p>Sélectionnez le motif d'absence dans la liste proposée</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4>Joindre le document</h4>
                                <p>Téléchargez votre justificatif au format PDF, JPG, PNG ou JPEG (max 5 Mo)</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">5</div>
                            <div class="step-content">
                                <h4>Ajouter un commentaire (facultatif)</h4>
                                <p>Vous pouvez ajouter des informations complémentaires si nécessaire</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">6</div>
                            <div class="step-content">
                                <h4>Valider la soumission</h4>
                                <p>Vérifiez les informations et soumettez votre justificatif</p>
                            </div>
                        </div>
                    </div>

                    <div class="action-box">
                        <a href="proof_submit.php" class="submit-button">
                            <span>➕</span>
                            Soumettre un justificatif maintenant
                        </a>
                    </div>
                </div>
            </section>

            <!-- Section 6: Suivi de votre justificatif -->
            <section class="info-section">
                <div class="section-header">
                    <span class="section-icon">🔍</span>
                    <h2>Suivi de Votre Justificatif</h2>
                </div>
                <div class="section-content">
                    <p>Une fois votre justificatif soumis, vous pouvez suivre son traitement dans la section "Mes
                        justificatifs".</p>

                    <div class="status-grid">
                        <div class="status-card status-pending">
                            <span class="status-badge">🕐</span>
                            <h4>En attente</h4>
                            <p>Votre justificatif a été soumis et attend d'être examiné par l'équipe pédagogique</p>
                        </div>

                        <div class="status-card status-review">
                            <span class="status-badge">⚠️</span>
                            <h4>En révision</h4>
                            <p>Votre justificatif est en cours d'examen. Des informations complémentaires peuvent être
                                demandées</p>
                        </div>

                        <div class="status-card status-accepted">
                            <span class="status-badge">✅</span>
                            <h4>Accepté</h4>
                            <p>Votre justificatif a été validé. Vos absences sont justifiées</p>
                        </div>

                        <div class="status-card status-rejected">
                            <span class="status-badge">❌</span>
                            <h4>Refusé</h4>
                            <p>Votre justificatif n'a pas été accepté. Consultez le commentaire pour plus d'informations
                            </p>
                        </div>
                    </div>

                    <div class="action-box">
                        <a href="proofs.php" class="secondary-button">
                            <span>📄</span>
                            Consulter mes justificatifs
                        </a>
                    </div>
                </div>
            </section>

            <!-- Section 7: Contacts et aide -->
            <section class="info-section">
                <div class="section-header">
                    <span class="section-icon">💬</span>
                    <h2>Besoin d'Aide ?</h2>
                </div>
                <div class="section-content">
                    <p>Pour toute question concernant les absences et justificatifs, vous pouvez contacter :</p>

                    <div class="contact-grid">
                        <div class="contact-card">
                            <h4>Votre responsable de formation</h4>
                            <p>Pour les questions pédagogiques et situations particulières</p>
                        </div>

                        <div class="contact-card">
                            <h4>Service scolarité</h4>
                            <p>Pour les questions administratives</p>
                            <a href="mailto:scolarite@uphf.fr">scolarite@uphf.fr</a>
                        </div>
                    </div>

                    <div class="info-box success">
                        <span class="info-icon">💡</span>
                        <div>
                            <strong>Conseil :</strong> En cas de doute sur l'acceptabilité d'un motif ou sur
                            la procédure à suivre, n'hésitez pas à contacter votre responsable de formation
                            <strong>avant</strong> l'expiration du délai de 48h.
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>