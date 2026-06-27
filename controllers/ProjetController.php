<?php




class projet {
    
    // projet
    public function gestion() {
        include "views/projet/enregistrement_projet.php";
    }
    public function recherche() {
        include "views/projet/recherche_projet.php";
    }

    // district
    public function dgestion() {
        include "views/projet/enregistrement_district.php";
    }
    public function drecherche() {
        include "views/projet/recherche_district.php";
    }
    // domaine
    public function dmgestion() {
        include "views/projet/enregistrement_domaine.php";
    }
    public function dmrecherche() {
        include "views/projet/recherche_domaine.php";
    }

    // tranche
    public function tgestion() {
        include "views/projet/enregistrement_tranche.php";
    }
    public function trecherche() {
        include "views/projet/recherche_tranche.php";
    }
    
    // utilisateur
    public function ugestion() {
        include "views/projet/enregistrement_utilisateur.php";
    }
    public function urecherche() {
        include "views/projet/recherche_utilisateur.php";
    }
}