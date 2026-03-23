<?php

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
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
                'date' => '2026-03-10',
                'cours' => 'Mathématiques',
                'heures' => 2,
                'status' => 'Non justifiée',
            ],
            [
                'date' => '2026-03-15',
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
     * @When /^je clique sur "([^"]*)"$/
     */
    public function jeCliqueSur(string $action): void
    {
        Assert::assertSame('Soumettre', $action, 'Cette étape est prévue pour le bouton "Soumettre".');

        $this->soumettreJustification();
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
}
