<?php

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    /** @var array<int, array<string, mixed>> */
    private array $historiqueJustifications = [];
    private bool $notification = false;
    private string $commentaireRejet = '';
    private bool $studentConnected = false;

    /** @var array<int, array<string, mixed>> */
    private array $absences = [];

    private ?int $selectedAbsenceIndex = null;

    /** @var array<string, mixed> */
    private array $justificationForm = [
        'description' => '',
        'fileName' => null,
    ];

    private bool $justificationSaved = false;
    private ?string $lastErrorMessage = null;
    private ?string $lastConfirmationMessage = null;
    private bool $canSubmitForm = false;

    /** @var string[] */
    private array $acceptedFormats = ['PDF', 'JPG', 'PNG', 'DOC', 'DOCX'];

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /**
     * @Given /^un étudiant connecté à l'application$/
     */
    public function unEtudiantConnecteALapplication(): void
    {
        $this->studentConnected = true;
    }

    /**
     * @Given /^cet étudiant a des absences non justifiées$/
     */
    public function cetEtudiantADesAbsencesNonJustifiees(): void
    {
        $this->absences = [
            [
                'date' => '2026-03-20',
                'cours' => 'Mathématiques',
                'heures' => 2,
                'status' => 'Non justifiée',
            ],
            [
                'date' => '2026-03-22',
                'cours' => 'Programmation PHP',
                'heures' => 3,
                'status' => 'Non justifiée',
            ],
        ];
    }

    /**
     * @When /^je consulte mon tableau de bord$/
     */
    public function jeConsulteMonTableauDeBord(): void
    {
        Assert::assertTrue($this->studentConnected, 'L\'étudiant doit être connecté pour accéder au tableau de bord.');
    }

    /**
     * @Then /^je vois la liste de mes absences non justifiées$/
     */
    public function jeVoisLaListeDeMesAbsencesNonJustifiees(): void
    {
        Assert::assertNotEmpty($this->absences, 'Aucune absence non justifiée trouvée.');

        foreach ($this->absences as $absence) {
            Assert::assertSame('Non justifiée', $absence['status']);
        }
    }

    /**
     * @Then /^chaque absence affiche la date, le cours et le nombre d'heures$/
     */
    public function chaqueAbsenceAfficheLaDateLeCoursEtLeNombreDHeures(): void
    {
        foreach ($this->absences as $absence) {
            Assert::assertArrayHasKey('date', $absence);
            Assert::assertArrayHasKey('cours', $absence);
            Assert::assertArrayHasKey('heures', $absence);
            Assert::assertNotSame('', (string) $absence['date']);
            Assert::assertNotSame('', (string) $absence['cours']);
            Assert::assertGreaterThan(0, (int) $absence['heures']);
        }
    }

    /**
     * @When /^je sélectionne une absence$/
     */
    public function jeSelectionneUneAbsence(): void
    {
        Assert::assertNotEmpty($this->absences, 'Impossible de sélectionner une absence: la liste est vide.');

        $this->selectedAbsenceIndex = 0;
        $this->lastErrorMessage = null;
        $this->lastConfirmationMessage = null;
        $this->justificationSaved = false;
    }

    /**
     * @When /^je remplis le formulaire de justification$/
     */
    public function jeRemplisLeFormulaireDeJustification(): void
    {
        Assert::assertNotNull($this->selectedAbsenceIndex, 'Aucune absence sélectionnée.');

        $this->justificationForm['description'] = 'Absence justifiée avec document de preuve.';
    }

    /**
     * @When /^j'ajoute un fichier de preuve$/
     */
    public function jajouteUnFichierDePreuve(): void
    {
        $this->justificationForm['fileName'] = 'preuve.pdf';
        $this->lastErrorMessage = null;
        $this->canSubmitForm = true;
    }



    /**
     * @Then /^ma justification est enregistrée$/
     */
    public function maJustificationEstEnregistree(): void
    {
        Assert::assertTrue($this->justificationSaved, 'La justification aurait dû être enregistrée.');
    }

    /**
     * @Then /^le statut de l'absence passe à "([^"]*)"$/
     */
    public function leStatutDeLabsencePasseA(string $expectedStatus): void
    {
        Assert::assertNotNull($this->selectedAbsenceIndex, 'Aucune absence sélectionnée.');
        Assert::assertSame($expectedStatus, $this->absences[$this->selectedAbsenceIndex]['status']);
    }

    /**
     * @Then /^je reçois une confirmation de soumission$/
     */
    public function jeRecoisUneConfirmationDeSoumission(): void
    {
        Assert::assertNotNull($this->lastConfirmationMessage, 'Aucun message de confirmation reçu.');
    }

    /**
     * @When /^je tente de soumettre une justification$/
     */
    public function jeTenteDeSoumettreUneJustification(): void
    {
        $this->jeSelectionneUneAbsence();
        $this->jeRemplisLeFormulaireDeJustification();
    }

    /**
     * @When /^j'ajoute un fichier dans un format non accepté$/
     */
    public function jajouteUnFichierDansUnFormatNonAccepte(): void
    {
        $this->justificationForm['fileName'] = 'preuve.exe';
        $this->canSubmitForm = false;
        $this->lastErrorMessage = 'Format de fichier non accepté.';
    }

    /**
     * @Then /^un message d'erreur s'affiche$/
     */
    public function unMessageDerreurSaffiche(): void
    {
        Assert::assertNotNull($this->lastErrorMessage, 'Un message d\'erreur était attendu.');
    }

    /**
     * @Then /^je ne peux pas soumettre le formulaire$/
     */
    public function jeNePeuxPasSoumettreLeFormulaire(): void
    {
        Assert::assertFalse($this->canSubmitForm, 'Le formulaire ne devrait pas être soumissible.');
    }

    /**
     * @Then /^les formats acceptés sont indiqués \(PDF, JPG, PNG, DOC, DOCX\)$/
     */
    public function lesFormatsAcceptesSontIndiques(): void
    {
        Assert::assertSame(['PDF', 'JPG', 'PNG', 'DOC', 'DOCX'], $this->acceptedFormats);
    }

    private function soumettreJustification(): void
    {
        $this->lastErrorMessage = null;
        $this->lastConfirmationMessage = null;
        $this->justificationSaved = false;

        Assert::assertNotNull($this->selectedAbsenceIndex, 'Aucune absence sélectionnée.');

        $fileName = (string) ($this->justificationForm['fileName'] ?? '');
        if ($fileName === '') {
            $this->lastErrorMessage = 'Aucun fichier de preuve ajouté.';
            $this->canSubmitForm = false;

            return;
        }

        $extension = strtoupper((string) pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->acceptedFormats, true)) {
            $this->lastErrorMessage = 'Format de fichier non accepté.';
            $this->canSubmitForm = false;

            return;
        }

        $this->canSubmitForm = true;
        $this->justificationSaved = true;
        $this->absences[$this->selectedAbsenceIndex]['status'] = 'En attente de validation';
        $this->lastConfirmationMessage = 'Votre justification a bien été soumise.';
    }
    /**
     * @When /^je consulte une absence$/
     */
    public function jeConsulteUneAbsence(): void
    {
        Assert::assertNotEmpty($this->absences, 'Aucune absence à consulter.');
        $this->selectedAbsenceIndex = 0;
    }

    /**
     * @Then /^je vois la date limite pour justifier cette absence$/
     */
    public function jeVoisLaDateLimitePourJustifierCetteAbsence(): void
    {
        // Supposons une date limite 7 jours après l'absence
        $absence = $this->absences[$this->selectedAbsenceIndex];
        $dateLimite = date('Y-m-d', strtotime($absence['date'] . ' +7 days'));
        Assert::assertNotEmpty($dateLimite, 'La date limite doit être affichée.');
    }

    /**
     * @Then /^un délai de justification est respecté$/
     */
    public function unDelaiDeJustificationEstRespecte(): void
    {
        $absence = $this->absences[$this->selectedAbsenceIndex];
        $dateLimite = date('Y-m-d', strtotime($absence['date'] . ' +7 days'));
        $aujourdhui = date('Y-m-d');
        Assert::assertLessThanOrEqual($dateLimite, $aujourdhui, 'Le délai de justification est dépassé.');
    }

    /**
     * @When /^je sélectionne une justification en attente de validation$/
     */
    public function jeSelectionneUneJustificationEnAttenteDeValidation(): void
    {
        // On suppose que la première absence est en attente de validation
        $this->selectedAbsenceIndex = 0;
        $this->absences[$this->selectedAbsenceIndex]['status'] = 'En attente de validation';
    }

    /**
     * @When /^je modifie le fichier de preuve ou la description$/
     */
    public function jeModifieLeFichierDePreuveOuLaDescription(): void
    {
        $this->justificationForm['fileName'] = 'preuve_modifiee.pdf';
        $this->justificationForm['description'] = 'Description modifiée.';
    }

    // La méthode spécifique "je clique sur 'Mettre à jour'" est supprimée pour éviter l'ambiguïté.
    /**
     * @When /^je clique sur "([^"]*)"$/
     */
    public function jeCliqueSur(string $action): void
    {
        if ($action === 'Soumettre') {
            $this->soumettreJustification();
        } elseif ($action === 'Mettre à jour') {
            $this->justificationSaved = true;
            // Le statut reste "En attente de validation"
        } else {
            Assert::fail('Action inconnue : ' . $action);
        }
    }
    /**
     * @Then /^la justification est mise à jour$/
     */
    public function laJustificationEstMiseAJour(): void
    {
        Assert::assertTrue($this->justificationSaved, 'La justification aurait dû être mise à jour.');
    }

    /**
     * @Then /^le statut reste "En attente de validation"$/
     */
    public function leStatutResteEnAttenteDeValidation(): void
    {
        Assert::assertSame('En attente de validation', $this->absences[$this->selectedAbsenceIndex]['status']);
    }

    /**
     * @When /^je consulte la section "Historique des justifications"$/
     */
    public function jeConsulteLaSectionHistoriqueDesJustifications(): void
    {
        $this->historiqueJustifications = [
            [
                'date' => '2026-03-01',
                'statut' => 'Excusée',
                'commentaire' => 'Acceptée',
            ],
            [
                'date' => '2026-03-05',
                'statut' => 'Rejetée',
                'commentaire' => 'Document illisible',
            ],
        ];
    }

    /**
     * @Then /^je vois la liste de toutes mes justifications précédentes$/
     */
    public function jeVoisLaListeDeToutesMesJustificationsPrecedentes(): void
    {
        Assert::assertNotEmpty($this->historiqueJustifications, 'Aucune justification précédente trouvée.');
    }

    /**
     * @Then /^pour chaque justification je vois : date de soumission, statut, commentaire du validateur$/
     */
    public function pourChaqueJustificationJeVoisDateStatutCommentaire(): void
    {
        foreach ($this->historiqueJustifications as $justification) {
            Assert::assertArrayHasKey('date', $justification);
            Assert::assertArrayHasKey('statut', $justification);
            Assert::assertArrayHasKey('commentaire', $justification);
        }
    }

    /**
     * @When /^une justification est validée ou rejetée$/
     */
    public function uneJustificationEstValideeOuRejetee(): void
    {
        // Simuler la validation ou le rejet
        $this->notification = true;
        // Pour le test, on alterne entre "Excusée" et "Rejetée"
        if (!isset($this->absences[$this->selectedAbsenceIndex]['status']) || $this->absences[$this->selectedAbsenceIndex]['status'] !== 'Rejetée') {
            $this->absences[$this->selectedAbsenceIndex]['status'] = 'Excusée';
            $this->commentaireRejet = '';
        } else {
            $this->absences[$this->selectedAbsenceIndex]['status'] = 'Rejetée';
            $this->commentaireRejet = 'Absence non justifiée.';
        }
    }

    /**
     * @Then /^je reçois une notification$/
     */
    public function jeRecoisUneNotification(): void
    {
        Assert::assertTrue($this->notification ?? false, 'Aucune notification reçue.');
    }

    /**
     * @Then /^le statut de l'absence devient "Excusée" ou "Rejetée"$/
     */
    public function leStatutDeLabsenceDevientExcuseeOuRejetee(): void
    {
        $status = $this->absences[$this->selectedAbsenceIndex]['status'];
        Assert::assertTrue(in_array($status, ['Excusée', 'Rejetée'], true), 'Le statut doit être "Excusée" ou "Rejetée".');
    }

    /**
     * @Then /^un commentaire explicatif est fourni en cas de rejet$/
     */
    public function unCommentaireExplicatifEstFourniEnCasDeRejet(): void
    {
        if (($this->absences[$this->selectedAbsenceIndex]['status'] ?? '') === 'Rejetée') {
            Assert::assertNotEmpty($this->commentaireRejet, 'Un commentaire explicatif doit être fourni en cas de rejet.');
        }
    }// ===== ConsulterHistorique =====

/** @var array<int, array<string, mixed>> */
private array $historiqueDecisions = [];

private ?string $filtreAction = null;
private bool $filtresAppliques = false;

/** @var array<string, mixed>|null */
private array $decisionSelectionnee = [];

/**
 * @Given /^je suis responsable pedagogique et connecte$/
 */
public function jeSuisResponsablePedagogiqueEtConnecte(): void
{
    $this->studentConnected = false; // On n'est pas étudiant ici
    // On simule un rôle responsable pédagogique
    $this->historiqueDecisions = [];
    $this->filtreAction = null;
    $this->filtresAppliques = false;
}

/**
 * @Given /^je suis sur le tableau de bord$/
 */
public function jeSuisSurLeTableauDeBord(): void
{
    // Rien à faire : le tableau de bord est accessible au responsable connecté
}

/**
 * @Given /^je suis responsable pedagogique et sur la page d'historique$/
 */
public function jeSuisResponsablePedagogiqueEtSurLaPageDHistorique(): void
{
    $this->jeSuisResponsablePedagogiqueEtConnecte();
    $this->chargerHistoriqueDecisions();
}

/**
 * @When /^je clique sur "Historique des decisions"$/
 */
public function jeCliqueSurHistoriqueDesDecisions(): void
{
    $this->chargerHistoriqueDecisions();
}

/**
 * @Then /^le systeme m'affiche la liste des decisions des justificatifs$/
 */
public function leSystemeMafficheListeDesDecisions(): void
{
    Assert::assertNotEmpty($this->historiqueDecisions, 'La liste des décisions est vide.');
}

/**
 * @Then /^je peux voir la date de chaque decision$/
 */
public function jePeuxVoirLaDateDeChaquDecision(): void
{
    foreach ($this->historiqueDecisions as $decision) {
        Assert::assertArrayHasKey('date', $decision);
        Assert::assertNotEmpty($decision['date']);
    }
}

/**
 * @Then /^je peux voir le nom de l'etudiant$/
 */
public function jePeuxVoirLeNomDeLEtudiant(): void
{
    foreach ($this->historiqueDecisions as $decision) {
        Assert::assertArrayHasKey('etudiant', $decision);
        Assert::assertNotEmpty($decision['etudiant']);
    }
}

/**
 * @Then /^je peux voir l'action effectuee \(Accepte\/Rejete\/Demande d'infos\)$/
 */
public function jePeuxVoirLactionEffectuee(): void
{
    $actionsValides = ['Accepté', 'Rejeté', "Demande d'infos"];
    foreach ($this->historiqueDecisions as $decision) {
        Assert::assertArrayHasKey('action', $decision);
        Assert::assertContains($decision['action'], $actionsValides, 'Action invalide : ' . $decision['action']);
    }
}

/**
 * @Then /^je peux voir le statut avant et apres$/
 */
public function jePeuxVoirLeStatutAvantEtApres(): void
{
    foreach ($this->historiqueDecisions as $decision) {
        Assert::assertArrayHasKey('statut_avant', $decision);
        Assert::assertArrayHasKey('statut_apres', $decision);
        Assert::assertNotEmpty($decision['statut_avant']);
        Assert::assertNotEmpty($decision['statut_apres']);
    }
}

/**
 * @When /^je selectionne "([^"]*)" dans le filtre action$/
 */
public function jeSelectionneActionDansLeFiltreAction(string $action): void
{
    $this->filtreAction = $action;
}

/**
 * @When /^je clique sur "Filtrer"$/
 */
public function jeCliqueSurFiltrer(): void
{
    Assert::assertNotNull($this->filtreAction, 'Aucun filtre sélectionné.');
    $this->filtresAppliques = true;

    $this->historiqueDecisions = array_values(array_filter(
        $this->historiqueDecisions,
        fn(array $d) => $d['action'] === $this->filtreAction
    ));
}

/**
 * @Then /^le systeme m'affiche uniquement les decisions acceptees$/
 */
public function leSystemeMafficheUniquementLesDecisionsAcceptees(): void
{
    Assert::assertNotEmpty($this->historiqueDecisions, 'Aucune décision acceptée trouvée.');
    foreach ($this->historiqueDecisions as $decision) {
        Assert::assertSame('Accepté', $decision['action']);
    }
}

/**
 * @When /^je regarde une decision dans la liste$/
 */
public function jeRegardeUneDecisionDansLaListe(): void
{
    Assert::assertNotEmpty($this->historiqueDecisions, 'Aucune décision disponible.');
    $this->decisionSelectionnee = $this->historiqueDecisions[0];
}

/**
 * @Then /^je peux voir le motif de rejet \(si applicable\)$/
 */
public function jePeuxVoirLeMotifDeRejet(): void
{
    Assert::assertArrayHasKey('motif_rejet', $this->decisionSelectionnee);
    // Le motif peut être vide si l'action n'est pas un rejet
    if ($this->decisionSelectionnee['action'] === 'Rejeté') {
        Assert::assertNotEmpty($this->decisionSelectionnee['motif_rejet']);
    }
}

/**
 * @Then /^je peux voir le commentaire du responsable$/
 */
public function jePeuxVoirLeCommentaireDuResponsable(): void
{
    Assert::assertArrayHasKey('commentaire', $this->decisionSelectionnee);
}

/**
 * @Then /^je peux voir le nom du responsable qui a pris la decision$/
 */
public function jePeuxVoirLeNomDuResponsable(): void
{
    Assert::assertArrayHasKey('responsable', $this->decisionSelectionnee);
    Assert::assertNotEmpty($this->decisionSelectionnee['responsable']);
}

/**
 * @Then /^je peux voir la periode d'absence justifiee\/rejetee$/
 */
public function jePeuxVoirLaPeriodeDAbsence(): void
{
    Assert::assertArrayHasKey('periode', $this->decisionSelectionnee);
    Assert::assertNotEmpty($this->decisionSelectionnee['periode']);
}

/**
 * @Given /^j'ai applique des filtres$/
 */
public function jaiAppliquDesFiltres(): void
{
    $this->filtreAction = 'Accepté';
    $this->filtresAppliques = true;
}

/**
 * @When /^je clique sur "Reinitialiser"$/
 */
public function jeCliqueSurReinitialiser(): void
{
    $this->filtreAction = null;
    $this->filtresAppliques = false;
    $this->chargerHistoriqueDecisions(); // Recharge tout sans filtre
}

/**
 * @Then /^tous les filtres sont vides$/
 */
public function tousLesFiltresSontVides(): void
{
    Assert::assertNull($this->filtreAction, 'Le filtre action devrait être vide.');
    Assert::assertFalse($this->filtresAppliques, 'Les filtres devraient être désactivés.');
}

/**
 * @Then /^le systeme m'affiche toutes les decisions$/
 */


/**
 * Charge un jeu de données fictif représentant l'historique des décisions.
 */
private function chargerHistoriqueDecisions(): void
{
    $this->historiqueDecisions = [
        [
            'date'         => '2026-03-10',
            'etudiant'     => 'Alice Martin',
            'action'       => 'Accepté',
            'statut_avant' => 'En attente de validation',
            'statut_apres' => 'Excusée',
            'motif_rejet'  => '',
            'commentaire'  => 'Document valide.',
            'responsable'  => 'M. Dupont',
            'periode'      => '2026-03-05 — 2026-03-06',
        ],
        [
            'date'         => '2026-03-12',
            'etudiant'     => 'Bob Leroy',
            'action'       => 'Rejeté',
            'statut_avant' => 'En attente de validation',
            'statut_apres' => 'Rejetée',
            'motif_rejet'  => 'Document illisible.',
            'commentaire'  => 'Merci de renvoyer un document lisible.',
            'responsable'  => 'Mme Bernard',
            'periode'      => '2026-03-08',
        ],
        [
            'date'         => '2026-03-15',
            'etudiant'     => 'Clara Petit',
            'action'       => "Demande d'infos",
            'statut_avant' => 'En attente de validation',
            'statut_apres' => 'En attente de validation',
            'motif_rejet'  => '',
            'commentaire'  => 'Précisez la nature de l\'absence.',
            'responsable'  => 'M. Dupont',
            'periode'      => '2026-03-12',
        ],
    ];
}
    

}
