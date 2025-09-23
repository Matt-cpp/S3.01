<?php
$db = getDatabase();
$query = "SELECT nom,prenom,cours,date,motif,statut FROM absence " ;
$statement = $db->getConnection()->prepare($query);
$statement->execute();
$absences = $statement->fetchAll(PDO::FETCH_ASSOC);
//             error_log("Erreur lors de l'exécution de la requête SELECT: " . $e->getMessage());
//             throw new Exception("Erreur lors de la récupération des données");
//         }
//     }
// }
?>