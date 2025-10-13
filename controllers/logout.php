<?php
session_start();

// Déconnexion simple
session_unset();
session_destroy();
header("Location: /View/templates/login.php");
exit;