Feature: Historique des decisions
  En tant que responsable pedagogique
  Je veux acceder a l'historique des decisions
  Afin de voir si les absences ont ete justifiees

  Scenario: Acces a l'historique des decisions depuis le tableau de bord
    Given je suis responsable pedagogique et connecte
    And je suis sur le tableau de bord
    When je clique sur 'Historique des decisions'
    Then le systeme m'affiche la liste des decisions des justificatifs
    And je peux voir la date de chaque decision
    And je peux voir le nom de l'etudiant
    And je peux voir l'action effectuee (Accepte/Rejete/Demande d'infos)
    And je peux voir le statut avant et apres

  Scenario: Filtrer l'historique par action
    Given je suis responsable pedagogique et sur la page d'historique
    When je selectionne 'Accepte' dans le filtre action
    And je clique sur 'Filtrer'
    Then le systeme m'affiche uniquement les decisions acceptees

  Scenario: Afficher les details d'une decision
    Given je suis responsable pedagogique et sur la page d'historique
    When je regarde une decision dans la liste
    Then je peux voir le motif de rejet (si applicable)
    And je peux voir le commentaire du responsable
    And je peux voir le nom du responsable qui a pris la decision
    And je peux voir la periode d'absence justifiee/rejetee

  Scenario: Reinitialiser les filtres
    Given je suis responsable pedagogique et sur la page d'historique
    And j'ai applique des filtres
    When je clique sur 'Reinitialiser'
    Then tous les filtres sont vides
    And le systeme m'affiche toutes les decisions