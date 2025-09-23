<?php
$db = getDatabase();
$query = "SELECT nom,prenom,cours,date,motif,statut FROM absence " ;
$statement = $db->getConnection()->prepare($query);
$statement->execute();
$absences = $statement->fetchAll(PDO::FETCH_ASSOC);

?>