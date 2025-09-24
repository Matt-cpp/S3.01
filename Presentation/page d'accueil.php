<?php
require_once '../Model/database.php';
$db = getDatabase();
$db->select("SELECT nom,prenom,cours,date,motif,statut FROM absence ");

?>