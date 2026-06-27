<?php
// ========================================
// CONTROLEUR CLIENT
// ========================================

class client {
    
    public function gestion() {
        include "views/client/gestion-client.php";
    }
    
    public function scan() {
        include "views/client/qrcode.php";
    }
}
?>