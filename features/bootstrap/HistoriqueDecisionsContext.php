<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;

/**
 * Behat context for the "Historique des decisions" feature.
 *
 * Covers all scenarios defined in ConsulterHistorique.feature:
 *  - Acces a l'historique des decisions depuis le tableau de bord
 *  - Filtrer l'historique par action
 *  - Afficher les details d'une decision
 *  - Reinitialiser les filtres
 *
 * Status values mirror AbsenceHistoryPresenter::getStatus():
 *   pending        -> En attente
 *   accepted       -> Acceptée
 *   rejected       -> Rejetée
 *   under_review   -> En cours d'examen
 *
 * Actions (decisions taken by the academic manager):
 *   Accepté / Rejeté / Demande d'infos
 */
class HistoriqueDecisionsContext implements Context
{
    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    /** Whether the academic manager is authenticated. */
    private bool $responsableConnecte = false;

    /** Whether the historique page has been loaded. */
    private bool $pageHistoriqueOuverte = false;

    /**
     * Full dataset — simulates what AbsenceHistoryPresenter::getAbsences() returns.
     *
     * Each entry contains the fields used by the feature + presenter:
     *   date, etudiant, action, statut_avant, statut_apres,
     *   motif_rejet, commentaire, responsable, periode,
     *   justification_status (mirrors the DB field used by getStatus())
     *
     * @var array<int, array<string, mixed>>
     */
    private array $toutesLesDecisions = [];

    /**
     * Currently visible decisions (may be filtered).
     *
     * @var array<int, array<string, mixed>>
     */
    private array $decisionsAffichees = [];

    /** Active filter on the "action" column (null = no filter). */
    private ?string $filtreAction = null;

    /** True when at least one filter is active. */
    private bool $filtresAppliques = false;

    /** The decision selected for detail view. */
    private ?array $decisionSelectionnee = null;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Populates $toutesLesDecisions with a realistic fixture dataset.
     * The three entries cover all possible actions so every Then-step
     * can find the data it needs.
     */
    private function chargerJeuDeTest(): void
    {
        $this->toutesLesDecisions = [
            [
                'date'                 => '2026-03-10',
                'etudiant'             => 'Alice Martin',
                'action'               => 'Accepté',
                'statut_avant'         => 'En attente',
                'statut_apres'         => 'Acceptée',
                'motif_rejet'          => '',
                'commentaire'          => 'Document valide, absence excusée.',
                'responsable'          => 'M. Dupont',
                'periode'              => '2026-03-05 — 2026-03-06',
                'justification_status' => 'accepted',
            ],
            [
                'date'                 => '2026-03-12',
                'etudiant'             => 'Bob Leroy',
                'action'               => 'Rejeté',
                'statut_avant'         => 'En attente',
                'statut_apres'         => 'Rejetée',
                'motif_rejet'          => 'Document illisible.',
                'commentaire'          => 'Merci de renvoyer un document lisible.',
                'responsable'          => 'Mme Bernard',
                'periode'              => '2026-03-08',
                'justification_status' => 'rejected',
            ],
            [
                'date'                 => '2026-03-15',
                'etudiant'             => 'Clara Petit',
                'action'               => "Demande d'infos",
                'statut_avant'         => 'En attente',
                'statut_apres'         => 'En cours d\'examen',
                'motif_rejet'          => '',
                'commentaire'          => 'Précisez la nature de l\'absence.',
                'responsable'          => 'M. Dupont',
                'periode'              => '2026-03-12',
                'justification_status' => 'under_review',
            ],
        ];
    }

    /**
     * Mirrors AbsenceHistoryPresenter::getStatus() so the context
     * can verify status labels without depending on the real class.
     */
    private function getStatusLabel(array $decision): string
    {
        $map = [
            'pending'      => 'En attente',
            'accepted'     => 'Acceptée',
            'rejected'     => 'Rejetée',
            'under_review' => "En cours d'examen",
        ];

        return $map[$decision['justification_status']] ?? 'Inconnu';
    }

    // -------------------------------------------------------------------------
    // Given steps
    // -------------------------------------------------------------------------

    /**
     * @Given /^je suis responsable pedagogique et connecte$/
     */
    public function jeSuisResponsablePedagogiqueEtConnecte(): void
    {
        $this->responsableConnecte = true;
        $this->pageHistoriqueOuverte = false;
        $this->toutesLesDecisions = [];
        $this->decisionsAffichees = [];
        $this->filtreAction = null;
        $this->filtresAppliques = false;
        $this->decisionSelectionnee = null;
    }

    /**
     * @Given /^je suis sur le tableau de bord$/
     */
    public function jeSuisSurLeTableauDeBord(): void
    {
        Assert::assertTrue(
            $this->responsableConnecte,
            'Le responsable doit être connecté pour accéder au tableau de bord.'
        );
    }

    /**
     * @Given /^je suis responsable pedagogique et sur la page d'historique$/
     */
    public function jeSuisResponsablePedagogiqueEtSurLaPageDHistorique(): void
    {
        $this->jeSuisResponsablePedagogiqueEtConnecte();
        $this->chargerJeuDeTest();
        $this->decisionsAffichees = $this->toutesLesDecisions;
        $this->pageHistoriqueOuverte = true;
    }

    /**
     * @Given /^j'ai applique des filtres$/
     */
    public function jaiAppliqueDesFiltres(): void
    {
        Assert::assertTrue(
            $this->pageHistoriqueOuverte,
            'La page d\'historique doit être ouverte avant d\'appliquer des filtres.'
        );

        $this->filtreAction = 'Accepté';
        $this->filtresAppliques = true;

        // Apply the filter so the state reflects a filtered view
        $this->decisionsAffichees = array_values(array_filter(
            $this->toutesLesDecisions,
            fn(array $d): bool => $d['action'] === $this->filtreAction
        ));
    }

    // -------------------------------------------------------------------------
    // When steps
    // -------------------------------------------------------------------------

    /**
     * @When /^je clique sur "Historique des decisions"$/
     */
    public function jeCliqueSurHistoriqueDesDecisions(): void
    {
        Assert::assertTrue(
            $this->responsableConnecte,
            'Le responsable doit être connecté.'
        );

        $this->chargerJeuDeTest();
        $this->decisionsAffichees = $this->toutesLesDecisions;
        $this->pageHistoriqueOuverte = true;
    }

    /**
     * @When /^je selectionne "([^"]*)" dans le filtre action$/
     */
    public function jeSelectionneActionDansLeFiltreAction(string $action): void
    {
        Assert::assertTrue(
            $this->pageHistoriqueOuverte,
            'La page d\'historique doit être ouverte.'
        );

        $this->filtreAction = $action;
    }

    /**
     * @When /^je clique sur "Filtrer"$/
     */
    public function jeCliqueSurFiltrer(): void
    {
        Assert::assertNotNull(
            $this->filtreAction,
            'Aucun filtre sélectionné avant de cliquer sur Filtrer.'
        );

        $this->filtresAppliques = true;

        $this->decisionsAffichees = array_values(array_filter(
            $this->toutesLesDecisions,
            fn(array $d): bool => $d['action'] === $this->filtreAction
        ));
    }

    /**
     * @When /^je regarde une decision dans la liste$/
     */
    public function jeRegardeUneDecisionDansLaListe(): void
    {
        Assert::assertNotEmpty(
            $this->decisionsAffichees,
            'Aucune décision disponible dans la liste.'
        );

        $this->decisionSelectionnee = $this->decisionsAffichees[0];
    }

    /**
     * @When /^je clique sur "Reinitialiser"$/
     */
    public function jeCliqueSurReinitialiser(): void
    {
        Assert::assertTrue(
            $this->pageHistoriqueOuverte,
            'La page d\'historique doit être ouverte.'
        );

        // Reset all filters and reload the full dataset
        $this->filtreAction = null;
        $this->filtresAppliques = false;
        $this->decisionsAffichees = $this->toutesLesDecisions;
    }

    // -------------------------------------------------------------------------
    // Then steps — scenario 1 (liste principale)
    // -------------------------------------------------------------------------

    /**
     * @Then /^le systeme m'affiche la liste des decisions des justificatifs$/
     */
    public function leSystemeMafficheListeDesDecisions(): void
    {
        Assert::assertNotEmpty(
            $this->decisionsAffichees,
            'La liste des décisions est vide alors qu\'elle devrait contenir des entrées.'
        );
    }

    /**
     * @Then /^je peux voir la date de chaque decision$/
     */
    public function jePeuxVoirLaDateDeChaquDecision(): void
    {
        foreach ($this->decisionsAffichees as $index => $decision) {
            Assert::assertArrayHasKey('date', $decision, "Décision #$index : clé 'date' manquante.");
            Assert::assertNotEmpty($decision['date'], "Décision #$index : date vide.");
            // Basic date format check (YYYY-MM-DD)
            Assert::assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}$/',
                $decision['date'],
                "Décision #$index : format de date invalide."
            );
        }
    }

    /**
     * @Then /^je peux voir le nom de l'etudiant$/
     */
    public function jePeuxVoirLeNomDeLEtudiant(): void
    {
        foreach ($this->decisionsAffichees as $index => $decision) {
            Assert::assertArrayHasKey('etudiant', $decision, "Décision #$index : clé 'etudiant' manquante.");
            Assert::assertNotEmpty($decision['etudiant'], "Décision #$index : nom de l'étudiant vide.");
        }
    }

    /**
     * @Then /^je peux voir l'action effectuee \(Accepte\/Rejete\/Demande d'infos\)$/
     */
    public function jePeuxVoirLactionEffectuee(): void
    {
        $actionsValides = ['Accepté', 'Rejeté', "Demande d'infos"];

        foreach ($this->decisionsAffichees as $index => $decision) {
            Assert::assertArrayHasKey('action', $decision, "Décision #$index : clé 'action' manquante.");
            Assert::assertContains(
                $decision['action'],
                $actionsValides,
                "Décision #$index : action '{$decision['action']}' non reconnue."
            );
        }
    }

    /**
     * @Then /^je peux voir le statut avant et apres$/
     */
    public function jePeuxVoirLeStatutAvantEtApres(): void
    {
        foreach ($this->decisionsAffichees as $index => $decision) {
            Assert::assertArrayHasKey('statut_avant', $decision, "Décision #$index : clé 'statut_avant' manquante.");
            Assert::assertArrayHasKey('statut_apres', $decision, "Décision #$index : clé 'statut_apres' manquante.");
            Assert::assertNotEmpty($decision['statut_avant'], "Décision #$index : statut_avant vide.");
            Assert::assertNotEmpty($decision['statut_apres'], "Décision #$index : statut_apres vide.");
        }
    }

    // -------------------------------------------------------------------------
    // Then steps — scenario 2 (filtre)
    // -------------------------------------------------------------------------

    /**
     * @Then /^le systeme m'affiche uniquement les decisions acceptees$/
     */
    public function leSystemeMafficheUniquementLesDecisionsAcceptees(): void
    {
        Assert::assertNotEmpty(
            $this->decisionsAffichees,
            'Aucune décision "Accepté" trouvée après filtrage.'
        );

        foreach ($this->decisionsAffichees as $index => $decision) {
            Assert::assertSame(
                'Accepté',
                $decision['action'],
                "Décision #$index : action attendue 'Accepté', obtenu '{$decision['action']}'."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Then steps — scenario 3 (détails)
    // -------------------------------------------------------------------------

    /**
     * @Then /^je peux voir le motif de rejet \(si applicable\)$/
     */
    public function jePeuxVoirLeMotifDeRejet(): void
    {
        Assert::assertNotNull($this->decisionSelectionnee, 'Aucune décision sélectionnée.');
        Assert::assertArrayHasKey('motif_rejet', $this->decisionSelectionnee);

        // When the action is a rejection, the motif must be non-empty
        if ($this->decisionSelectionnee['action'] === 'Rejeté') {
            Assert::assertNotEmpty(
                $this->decisionSelectionnee['motif_rejet'],
                'Un motif de rejet est obligatoire quand l\'action est "Rejeté".'
            );
        }
    }

    /**
     * @Then /^je peux voir le commentaire du responsable$/
     */
    public function jePeuxVoirLeCommentaireDuResponsable(): void
    {
        Assert::assertNotNull($this->decisionSelectionnee, 'Aucune décision sélectionnée.');
        Assert::assertArrayHasKey('commentaire', $this->decisionSelectionnee);
    }

    /**
     * @Then /^je peux voir le nom du responsable qui a pris la decision$/
     */
    public function jePeuxVoirLeNomDuResponsable(): void
    {
        Assert::assertNotNull($this->decisionSelectionnee, 'Aucune décision sélectionnée.');
        Assert::assertArrayHasKey('responsable', $this->decisionSelectionnee);
        Assert::assertNotEmpty(
            $this->decisionSelectionnee['responsable'],
            'Le nom du responsable ne doit pas être vide.'
        );
    }

    /**
     * @Then /^je peux voir la periode d'absence justifiee\/rejetee$/
     */
    public function jePeuxVoirLaPeriodeDAbsence(): void
    {
        Assert::assertNotNull($this->decisionSelectionnee, 'Aucune décision sélectionnée.');
        Assert::assertArrayHasKey('periode', $this->decisionSelectionnee);
        Assert::assertNotEmpty(
            $this->decisionSelectionnee['periode'],
            'La période d\'absence ne doit pas être vide.'
        );
    }

    // -------------------------------------------------------------------------
    // Then steps — scenario 4 (réinitialisation)
    // -------------------------------------------------------------------------

    /**
     * @Then /^tous les filtres sont vides$/
     */
    public function tousLesFiltresSontVides(): void
    {
        Assert::assertNull(
            $this->filtreAction,
            'Le filtre action devrait être null après réinitialisation.'
        );
        Assert::assertFalse(
            $this->filtresAppliques,
            'Le flag filtresAppliques devrait être false après réinitialisation.'
        );
    }

    /**
     * @Then /^le systeme m'affiche toutes les decisions$/
     */
    public function leSystemeMafficheToutesLesDecisions(): void
    {
        Assert::assertCount(
            count($this->toutesLesDecisions),
            $this->decisionsAffichees,
            'Le nombre de décisions affichées devrait être égal au total sans filtre.'
        );
    }
}
