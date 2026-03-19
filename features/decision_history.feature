Feature: Historique des décisions
  En tant que responsable pédagogique
  Je veux accéder à l'historique des décisions
  Afin de voir si les absences ont été justifiées

  Scenario: Accès à l'historique des décisions depuis le tableau de bord
    Given je suis responsable pédagogique et connecté
    And je suis sur le tableau de bord
    When je clique sur "Historique des décisions"
    Then le système m'affiche la liste des décisions des justificatifs
    And je peux voir la date de chaque décision
    And je peux voir le nom de l'étudiant
    And je peux voir l'action effectuée (Accepté/Rejeté/Demande d'infos)
    And je peux voir le statut avant et après

  Scenario: Filtrer l'historique par étudiant
    Given je suis responsable pédagogique et sur la page d'historique
    When j'entre le nom "Dupont" dans le filtre étudiant
    And je clique sur "Filtrer"
    Then le système m'affiche uniquement les décisions de l'étudiant Dupont

  Scenario: Filtrer l'historique par date
    Given je suis responsable pédagogique et sur la page d'historique
    When j'entre la date de début "2026-01-01"
    And j'entre la date de fin "2026-03-19"
    And je clique sur "Filtrer"
    Then le système m'affiche uniquement les décisions prises entre ces deux dates

  Scenario: Filtrer l'historique par action
    Given je suis responsable pédagogique et sur la page d'historique
    When je sélectionne "Accepté" dans le filtre action
    And je clique sur "Filtrer"
    Then le système m'affiche uniquement les décisions acceptées

  Scenario: Afficher les détails d'une décision
    Given je suis responsable pédagogique et sur la page d'historique
    When je regarde une décision dans la liste
    Then je peux voir le motif de rejet (si applicable)
    And je peux voir le commentaire du responsable
    And je peux voir le nom du responsable qui a pris la décision
    And je peux voir la période d'absence justifiée/rejetée

  Scenario: Réinitialiser les filtres
    Given je suis responsable pédagogique et sur la page d'historique
    And j'ai appliqué des filtres
    When je clique sur "Réinitialiser"
    Then tous les filtres sont vidés
    And le système m'affiche toutes les décisions
