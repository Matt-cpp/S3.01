# language: fr
Fonctionnalité: Justifier ses absences
  En tant qu'étudiant
  Je veux justifier mes absences
  Afin d'être excusé

  Contexte:
    Étant donné un étudiant connecté à l'application
    Et cet étudiant a des absences non justifiées

  Scénario: Consulter la liste de mes absences
    Quand je consulte mon tableau de bord
    Alors je vois la liste de mes absences non justifiées
    Et chaque absence affiche la date, le cours et le nombre d'heures

  Scénario: Soumettre une justification avec un fichier de preuve
    Quand je sélectionne une absence
    Et je remplis le formulaire de justification
    Et j'ajoute un fichier de preuve
    Et je clique sur "Soumettre"
    Alors ma justification est enregistrée
    Et le statut de l'absence passe à "En attente de validation"
    Et je reçois une confirmation de soumission

  Scénario: Valider le format du fichier de preuve
    Quand je tente de soumettre une justification
    Et j'ajoute un fichier dans un format non accepté
    Alors un message d'erreur s'affiche
    Et je ne peux pas soumettre le formulaire
    Et les formats acceptés sont indiqués (PDF, JPG, PNG, DOC, DOCX)

  Scénario: Vérifier la date limite de soumission
    Quand je consulte une absence
    Alors je vois la date limite pour justifier cette absence
    Et un délai de justification est respecté

  Scénario: Modifier une justification non encore validée
    Quand je sélectionne une justification en attente de validation
    Et je modifie le fichier de preuve ou la description
    Et je clique sur "Mettre à jour"
    Alors la justification est mise à jour
    Et le statut reste "En attente de validation"

  Scénario: Consulter l'historique de mes justifications
    Quand je consulte la section "Historique des justifications"
    Alors je vois la liste de toutes mes justifications précédentes
    Et pour chaque justification je vois : date de soumission, statut, commentaire du validateur

  Scénario: Recevoir une notification sur le statut de validation
    Quand une justification est validée ou rejetée
    Alors je reçois une notification
    Et le statut de l'absence devient "Excusée" ou "Rejetée"
    Et un commentaire explicatif est fourni en cas de rejet
