<?php
// Fichier: controllers/utilisateur.php
session_start();
require_once 'config/authentification.php';

class Utilisateur {

    public function menu() {
        // checkAccessConditions();
        include "config/menu.php";
    }

    public function profil(){

        include "views/utilisateur/profil.php";
    }
    
    public function gestion() {
        // Vérifier les conditions d'accès
        
        include "views/utilisateur/gestion-utilisateur.php";
    }

    public function dashboard() {
        // Vérifier les conditions d'accès
        
        include "views/utilisateur/dashboard.php";
    }

    public function connexion() {
       
        include "views/utilisateur/connexion.php";
    }
    
    public function deconnexion() {
        session_start();
        $_SESSION = array();
        session_destroy();
        
        // Supprimer le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
                  . $_SERVER['HTTP_HOST']
                  . (dirname($_SERVER['PHP_SELF']) === '/' ? '' : rtrim(dirname($_SERVER['PHP_SELF']), '/\\'));
        header("Location: " . $base_url . "/utilisateur/connexion");
        exit;
    }
}
?>