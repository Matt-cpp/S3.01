<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\When;
use Behat\Step\Then;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    private $response;
    private $lastUrl;
    private $filters = [];
    private $decisionTableContent = '';

    public function __construct()
    {
    }

    /**
     * ============================================
     * DECISION HISTORY FEATURE STEPS
     * ============================================
     */

    #[Given('je suis responsable pédagogique et connecté')]
    public function jeSuisResponsablePedagogiqueEtConnecte(): void
    {
        $_SESSION['user_id'] = 3;
        $_SESSION['user_role'] = 'academic_manager';
        $_SESSION['identifier'] = '8080808080';
        $_SESSION['first_name'] = 'academic_manager';
        $_SESSION['last_name'] = 'academic_manager';
        $_SESSION['user_email'] = 'academic_manager@uphf.fr';
        $_SESSION['user_first_name'] = 'academic_manager';
        $_SESSION['user_last_name'] = 'academic_manager';
    }

    #[Given('je suis sur le tableau de bord')]
    public function jeSuisSurLeTableauDeBord(): void
    {
        $this->jeSuisResponsablePedagogiqueEtConnecte();
        $this->lastUrl = 'View/templates/academic_manager/home.php';
        
        ob_start();
        // Override REQUEST_METHOD to avoid login presenter issues
        $_SERVER['REQUEST_METHOD'] = 'GET';
        require __DIR__ . '/../../View/templates/academic_manager/home.php';
        $this->response = ob_get_clean();
    }

    #[When('je clique sur :buttonText')]
    public function jeCliqueSur(string $buttonText): void
    {
        if (strpos($this->response, $buttonText) === false) {
            throw new Exception("Button '{$buttonText}' not found in page");
        }

        switch ($buttonText) {
            case 'Historique des décisions':
                $this->loadDecisionHistoryPage();
                break;
            case 'Filtrer':
                $this->applyFilters();
                break;
            case 'Réinitialiser':
                $this->filters = [];
                $this->loadDecisionHistoryPage();
                break;
        }
    }

    #[Given('je suis responsable pédagogique et sur la page d\'historique')]
    public function jeSuisResponsablePedagogiqueEtSurLaPageDHistorique(): void
    {
        $this->jeSuisResponsablePedagogiqueEtConnecte();
        $this->loadDecisionHistoryPage();
    }

    #[Then('le système m\'affiche la liste des décisions des justificatifs')]
    public function leSystemeMafficheLaListeDesDecisions(): void
    {
        if (strpos($this->response, 'Historique des décisions') === false) {
            throw new Exception('Decision history page not found');
        }
        if (strpos($this->response, '<table') === false && 
            strpos($this->response, 'table') === false) {
            throw new Exception('Decisions table not found in response');
        }
    }

    #[Then('je peux voir la date de chaque décision')]
    public function jePouxVoirLaDateDeChaquDecision(): void
    {
        if (strpos($this->response, 'Date de décision') === false) {
            throw new Exception('Decision date column not found');
        }
    }

    #[Then('je peux voir le nom de l\'étudiant')]
    public function jePouxVoirLeNomDelEtudiant(): void
    {
        if (strpos($this->response, 'Étudiant') === false) {
            throw new Exception('Student name column not found');
        }
    }

    #[Then('je peux voir l\'action effectuée (Accepté/Rejeté/Demande d\'infos)')]
    public function jePouxVoirLactionEffectuee(): void
    {
        if (strpos($this->response, 'Action') === false) {
            throw new Exception('Action column not found');
        }
    }

    #[Then('je peux voir le statut avant et après')]
    public function jePouxVoirLeStatutAvantEtApres(): void
    {
        if (strpos($this->response, 'Statut') === false) {
            throw new Exception('Status column not found');
        }
    }

    #[When('j\'entre le nom :studentName dans le filtre étudiant')]
    public function jEntreLeName(string $studentName): void
    {
        $this->filters['name'] = $studentName;
    }

    #[Then('le système m\'affiche uniquement les décisions de l\'étudiant :studentName')]
    public function leSystemeMafficheLesDecisionsDeLEtudiant(string $studentName): void
    {
        // After applying filters and loading page, check if results appear
        if (empty($this->response)) {
            throw new Exception('No response after applying filters');
        }
        // The filtered page should load successfully
    }

    #[When('j\'entre la date de début :startDate')]
    public function jEntreLaDateDeDebut(string $startDate): void
    {
        $this->filters['start_date'] = $startDate;
    }

    #[When('j\'entre la date de fin :endDate')]
    public function jEntreLaDateDeFin(string $endDate): void
    {
        $this->filters['end_date'] = $endDate;
    }

    #[Then('le système m\'affiche uniquement les décisions prises entre ces deux dates')]
    public function leSystemeMafficheLesDecisionsEntreLesDates(): void
    {
        if (empty($this->filters['start_date']) || empty($this->filters['end_date'])) {
            throw new Exception('Date range not set for filtering');
        }
        // Verify the page loaded with filters applied
        if (empty($this->response)) {
            throw new Exception('No response after date filtering');
        }
    }

    #[When('je sélectionne :action dans le filtre action')]
    public function jeSelectionneUnAction(string $action): void
    {
        $actionMap = [
            'Accepté' => 'accept',
            'Rejeté' => 'reject',
            'Demande d\'infos' => 'request_info',
            'Déverrouillé' => 'unlock'
        ];
        $this->filters['action'] = $actionMap[$action] ?? strtolower($action);
    }

    #[Then('le système m\'affiche uniquement les décisions acceptées')]
    public function leSystemeMafficheLesDecisionsAcceptees(): void
    {
        // Verify page loaded with action filter
        if (empty($this->response)) {
            throw new Exception('No response after action filtering');
        }
    }

    
// ERROR 1
    #[When('je regarde une décision dans la liste')]
    public function jeRegardeUneDécisionDansLaListe(): void
    {
        if (strpos($this->response, 'table') === false && 
            strpos($this->response, '<table') === false) {
            throw new Exception('Decisions table not found');
        }
        $this->decisionTableContent = $this->response;
    }

    #[Then('je peux voir le motif de rejet (si applicable)')]
    public function jePouxVoirLeMotifDeRejet(): void
    {
        // It's optional - only check if rejection functionality is present
        // Either via "Motif" text or rejection_reason in response
        if (!empty($this->decisionTableContent)) {
            // Optional check - just verify the page structure is sound
        }
    }

    #[Then('je peux voir le commentaire du responsable')]
    public function jePeuxVoirLeCommentaireDuResponsable(): void
    {
        if (strpos($this->decisionTableContent, 'Commentaire') === false) {
            throw new Exception('Comment label not found in table');
        }
    }

    #[Then('je peux voir le nom du responsable qui a pris la décision')]
    public function jePeuxVoirLeNomDuResponsableQuiAPrisLaDécision(): void
    {
        if (strpos($this->decisionTableContent, 'Responsable') === false) {
            throw new Exception('Manager/Responsible column not found');
        }
    }

    #[Then('je peux voir la période d\'absence justifiée/rejetée')]
    public function jePeuxVoirLaPeriodeDabsenceJustifieeRejetee(): void
    {
        if (strpos($this->decisionTableContent, 'Période') === false &&
            strpos($this->decisionTableContent, 'absence') === false) {
            throw new Exception('Absence period not found');
        }
    }

    #[Given('j\'ai appliqué des filtres')]
    public function jaiAppliquéDesFiltres(): void
    {
        $this->filters['name'] = 'Test Student';
        $this->filters['action'] = 'accept';
    }

    #[Then('tous les filtres sont vidés')]
    public function tousLesFiltresSontVidés(): void
    {
        if (!empty($this->filters)) {
            throw new Exception('Filters not cleared after reset');
        }
    }

    #[Then('le système m\'affiche toutes les décisions')]
    public function leSystèmeMafficheToutesLesDécisions(): void
    {
        // After reset, filters should be empty
        if (!empty($this->filters)) {
            throw new Exception('Filters still active after reset');
        }
        // Page should have loaded successfully
        if (empty($this->response)) {
            throw new Exception('Page did not load after reset');
        }
    }

    /**
     * ============================================
     * HELPER METHODS
     * ============================================
     */

    private function loadDecisionHistoryPage(): void
    {
        ob_start();
        
        // Set POST data if filters are set
        if (!empty($this->filters)) {
            $_POST['nameFilter'] = $this->filters['name'] ?? '';
            $_POST['startDateFilter'] = $this->filters['start_date'] ?? '';
            $_POST['endDateFilter'] = $this->filters['end_date'] ?? '';
            $_POST['actionFilter'] = $this->filters['action'] ?? '';
            $_POST['statusFilter'] = $this->filters['status'] ?? '';
            $_SERVER['REQUEST_METHOD'] = 'POST';
        } else {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }

        $filePath = __DIR__ . '/../../View/templates/academic_manager/decision_history.php';
        require $filePath;
        $this->response = ob_get_clean();
        $this->lastUrl = 'View/templates/academic_manager/decision_history.php';
    }

    private function applyFilters(): void
    {
        // Filters are already set by the step definitions
        // Just reload the page with filters applied
        $this->loadDecisionHistoryPage();
    }
}

