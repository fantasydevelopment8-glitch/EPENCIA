-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : ven. 26 juin 2026 à 11:43
-- Version du serveur : 9.1.0
-- Version de PHP : 8.4.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `epencia_sgi`
--

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE IF NOT EXISTS `clients` (
  `client_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nom_prenom` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `lieu_naissance` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sexe` enum('masculin','feminin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nationalite` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `groupe_sanguin` enum('AB+','AB-','A+','A-','B+','B-','O+','O-') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solde` decimal(10,5) DEFAULT NULL,
  `pays` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ville` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adresse` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `profession` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nom_prenom_urgence` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone_urgence` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email_urgence` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `photo` longblob,
  `type_photo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`client_id`, `nom_prenom`, `date_naissance`, `lieu_naissance`, `sexe`, `nationalite`, `groupe_sanguin`, `telephone`, `email`, `solde`, `pays`, `ville`, `adresse`, `profession`, `nom_prenom_urgence`, `telephone_urgence`, `email_urgence`, `description`, `photo`, `type_photo`, `statut`) VALUES
('cli_6a3a4410de9c1', 'Mardochée niamien Kouakou', '2026-06-26', 'yop', 'masculin', 'ivoirienne', 'A+', '0717525439', 'mardoknt07@gmail.com', 0.00000, 'cote d\'ivoire', 'Bouaké', NULL, 'ingénieur informatique', NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
('CLT_mandigo_793897', 'Mardochée niamien Kouakou', NULL, 'yopp', 'masculin', NULL, 'A+', '0717525439', 'mardoknt07@gmail.com', 0.00000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
CREATE TABLE IF NOT EXISTS `commandes` (
  `commande_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `produit_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `facture_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `utilisateur_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prix_initial` decimal(10,5) DEFAULT NULL,
  `prix` decimal(10,5) DEFAULT NULL,
  `quantite` int DEFAULT NULL,
  `montant` decimal(10,5) DEFAULT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`commande_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `connexions_log`
--

DROP TABLE IF EXISTS `connexions_log`;
CREATE TABLE IF NOT EXISTS `connexions_log` (
  `log_id` varchar(100) NOT NULL,
  `client_id` varchar(100) DEFAULT NULL,
  `date_connexion` datetime DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text,
  PRIMARY KEY (`log_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `connexions_log`
--

INSERT INTO `connexions_log` (`log_id`, `client_id`, `date_connexion`, `type`, `status`, `ip_address`, `user_agent`) VALUES
('LOG_20260622221953_9869', 'CLT_MANDIGO_CMU_581434', '2026-06-22 22:19:53', 'recherche_manuelle', 'succes', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260622223226_7871', 'CLT_MANDIGO_CMU_581434', '2026-06-22 22:32:26', 'recherche_manuelle', 'succes', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623083606_7564', 'CLT_mandigo_793897', '2026-06-23 08:36:06', 'recherche_manuelle', 'succes', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623092959_9445', 'CLT_mandigo_793897', '2026-06-23 09:29:59', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623093128_6525', 'CLT_mandigo_793897', '2026-06-23 09:31:28', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623093353_9483', 'CLT_mandigo_793897', '2026-06-23 09:33:53', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623093529_6776', 'CLT_mandigo_793897', '2026-06-23 09:35:29', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623093818_7533', 'CLT_mandigo_793897', '2026-06-23 09:38:18', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623094531_6536', 'CLT_mandigo_793897', '2026-06-23 09:45:31', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623094533_4918', 'CLT_mandigo_793897', '2026-06-23 09:45:33', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623094659_6177', 'CLT_mandigo_793897', '2026-06-23 09:46:59', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623094933_5480', 'CLT_mandigo_793897', '2026-06-23 09:49:33', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623095304_6832', 'CLT_mandigo_793897', '2026-06-23 09:53:04', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623095426_9857', 'CLT_mandigo_793897', '2026-06-23 09:54:26', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623100925_1934', 'CLT_mandigo_793897', '2026-06-23 10:09:25', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623102250_6136', 'CLT_mandigo_793897', '2026-06-23 10:22:50', 'recherche_manuelle', 'succes', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623102251_1792', 'CLT_mandigo_793897', '2026-06-23 10:22:51', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623102931_8879', 'CLT_mandigo_793897', '2026-06-23 10:29:31', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623103310_2444', 'CLT_mandigo_793897', '2026-06-23 10:33:10', 'affichage_profil', 'succes', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623123108_3843', '100002', '2026-06-23 12:31:08', 'scan_qr', 'echec_client_introuv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623123142_7306', 'CLT_MANDIGO_CMU_240053', '2026-06-23 12:31:42', 'scan_qr', 'echec_client_introuv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36'),
('LOG_20260623123311_6403', 'https://qr.wave.com/iVVNfY2lfNG55WkNGMGZ2Rmkt/BqOnz7/An3yO9?t=0&o=1.556&d=1782217978', '2026-06-23 12:33:11', 'scan_qr', 'echec_client_introuv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Structure de la table `diagnostics`
--

DROP TABLE IF EXISTS `diagnostics`;
CREATE TABLE IF NOT EXISTS `diagnostics` (
  `diagnostic_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `facture_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nom` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('constante','symptome','maladie') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`diagnostic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `district`
--

DROP TABLE IF EXISTS `district`;
CREATE TABLE IF NOT EXISTS `district` (
  `id_district` varchar(100) NOT NULL,
  `titre_district` varchar(100) NOT NULL,
  `ville` varchar(100) NOT NULL,
  `id_region` varchar(200) NOT NULL,
  `etat_district` varchar(100) NOT NULL,
  PRIMARY KEY (`id_district`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `domaine`
--

DROP TABLE IF EXISTS `domaine`;
CREATE TABLE IF NOT EXISTS `domaine` (
  `id_domaine` varchar(100) NOT NULL,
  `titre_domaine` varchar(100) NOT NULL,
  `etat_domaine` varchar(20) NOT NULL,
  PRIMARY KEY (`id_domaine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `donnee`
--

DROP TABLE IF EXISTS `donnee`;
CREATE TABLE IF NOT EXISTS `donnee` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_projet` varchar(70) NOT NULL,
  `id_district` varchar(70) NOT NULL,
  `id_site` varchar(70) NOT NULL,
  `id_domaine` varchar(200) NOT NULL,
  `id_indicateur` varchar(100) NOT NULL,
  `id_tranche` varchar(70) NOT NULL,
  `sexe` varchar(100) NOT NULL,
  `valeur` varchar(100) NOT NULL,
  `mois` varchar(200) NOT NULL,
  `annee` varchar(200) NOT NULL,
  `date_enregistrement` date NOT NULL,
  `saisi_par` varchar(200) NOT NULL,
  `etat_donnee` varchar(25) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

DROP TABLE IF EXISTS `factures`;
CREATE TABLE IF NOT EXISTS `factures` (
  `facture_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `client_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `organisme_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `utilisateur_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date DEFAULT NULL,
  `heure` time DEFAULT NULL,
  `statut` enum('en attente','payé','impayé','annulé','partiel') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`facture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `factures`
--

INSERT INTO `factures` (`facture_id`, `client_id`, `organisme_id`, `utilisateur_id`, `date`, `heure`, `statut`) VALUES
('FACT-6449F6E056', 'CLT_mandigo_793897', 'ORG-001', '1', '2026-06-23', '09:54:19', 'en attente');

-- --------------------------------------------------------

--
-- Structure de la table `indicateur`
--

DROP TABLE IF EXISTS `indicateur`;
CREATE TABLE IF NOT EXISTS `indicateur` (
  `id_indicateur` int NOT NULL AUTO_INCREMENT,
  `numero` varchar(200) NOT NULL,
  `titre_indicateur` varchar(100) NOT NULL,
  `id_domaine` varchar(20) NOT NULL,
  `etat_indicateur` varchar(100) NOT NULL,
  PRIMARY KEY (`id_indicateur`),
  UNIQUE KEY `numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `objet` varchar(255) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `text` longtext NOT NULL,
  `date` datetime NOT NULL,
  `user` int NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `organismes`
--

DROP TABLE IF EXISTS `organismes`;
CREATE TABLE IF NOT EXISTS `organismes` (
  `organisme_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nom` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('clinique','pharmacie','laboratoire') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pays` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ville` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adresse` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `photo` longblob,
  `type_photo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`organisme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `prestations`
--

DROP TABLE IF EXISTS `prestations`;
CREATE TABLE IF NOT EXISTS `prestations` (
  `prestation_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `produit_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `organisme_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prix` decimal(10,5) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`prestation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

DROP TABLE IF EXISTS `produits`;
CREATE TABLE IF NOT EXISTS `produits` (
  `produit_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nom` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('soins','examen','médicament') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`produit_id`, `nom`, `type`, `description`, `statut`) VALUES
('1', 'bla', 'médicament', 'palue', 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `projet`
--

DROP TABLE IF EXISTS `projet`;
CREATE TABLE IF NOT EXISTS `projet` (
  `id_projet` varchar(100) NOT NULL,
  `titre_projet` varchar(100) NOT NULL,
  `type_projet` varchar(100) NOT NULL,
  `details_projet` varchar(70) NOT NULL,
  `bailleur` varchar(70) NOT NULL,
  `etat_projet` varchar(100) NOT NULL,
  PRIMARY KEY (`id_projet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `projet_district`
--

DROP TABLE IF EXISTS `projet_district`;
CREATE TABLE IF NOT EXISTS `projet_district` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_projet` varchar(200) NOT NULL,
  `id_district` varchar(200) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `projet_domaine`
--

DROP TABLE IF EXISTS `projet_domaine`;
CREATE TABLE IF NOT EXISTS `projet_domaine` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_projet` varchar(70) NOT NULL,
  `id_domaine` varchar(70) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `projet_tranche`
--

DROP TABLE IF EXISTS `projet_tranche`;
CREATE TABLE IF NOT EXISTS `projet_tranche` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_projet` varchar(100) NOT NULL,
  `id_tranche` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `region`
--

DROP TABLE IF EXISTS `region`;
CREATE TABLE IF NOT EXISTS `region` (
  `id_region` varchar(200) NOT NULL,
  `titre_region` varchar(200) NOT NULL,
  `etat_region` varchar(100) NOT NULL,
  PRIMARY KEY (`id_region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `site`
--

DROP TABLE IF EXISTS `site`;
CREATE TABLE IF NOT EXISTS `site` (
  `id_site` varchar(25) NOT NULL,
  `titre_site` varchar(100) NOT NULL,
  `id_district` varchar(100) NOT NULL,
  `etat_site` varchar(20) NOT NULL,
  PRIMARY KEY (`id_site`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `tranche_age`
--

DROP TABLE IF EXISTS `tranche_age`;
CREATE TABLE IF NOT EXISTS `tranche_age` (
  `id_tranche` varchar(100) NOT NULL,
  `age_debut` varchar(10) NOT NULL,
  `age_fin` varchar(10) NOT NULL,
  `titre_debut` varchar(100) NOT NULL,
  `titre_fin` varchar(100) NOT NULL,
  `etat_tranche_age` varchar(30) NOT NULL,
  PRIMARY KEY (`id_tranche`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `transaction_id` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date DEFAULT NULL,
  `heure` time DEFAULT NULL,
  `montant` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `frais` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `montant_total` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('entrée','sortie') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `motif` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mode_reglement` enum('espèce','virement bancaire','carte bancaire','wave','orange money','MTN money','moov money') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero_reglement` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference_reglement` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `utilisateur_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `facture_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `statut` enum('succes','echec','en attente') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `utilisateur_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nom_prenom` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `login` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mdp` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('Superviseur','Administrateur','Pharmacien','Medecin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `organisme_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_saisie` date DEFAULT NULL,
  `photo` longblob,
  `type_photo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `etat` enum('actif','inactif','en attente') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`utilisateur_id`),
  UNIQUE KEY `login` (`login`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`utilisateur_id`, `nom_prenom`, `login`, `mdp`, `telephone`, `email`, `role`, `organisme_id`, `date_saisie`, `photo`, `type_photo`, `etat`) VALUES
('1', 'admin', 'systeme', 'AZERTY', '123456', 'mardoknt07@gmail.com', 'Administrateur', 'koko', '2017-06-14', NULL, NULL, 'actif'),
('2', 'Mardochée niamien Kouakou', '1', '$2y$12$YPGRC3OzJw/g4oZ0z2KZh.8ex6Okae7zVcJUnEN/3FzpiwbiscqBe', '0717525439', '001@gmail.com', 'Medecin', 'ORG-001', '2026-06-22', NULL, NULL, 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur_projet`
--

DROP TABLE IF EXISTS `utilisateur_projet`;
CREATE TABLE IF NOT EXISTS `utilisateur_projet` (
  `id` varchar(200) NOT NULL,
  `utilisateur` varchar(200) NOT NULL,
  `projet` varchar(200) NOT NULL,
  `zone` varchar(200) NOT NULL,
  `localite` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `visiteur`
--

DROP TABLE IF EXISTS `visiteur`;
CREATE TABLE IF NOT EXISTS `visiteur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_connexion` date NOT NULL,
  `heure_connexion` time NOT NULL,
  `date_deconnexion` date NOT NULL,
  `heure_deconnexion` time NOT NULL,
  `duree_date` varchar(250) NOT NULL,
  `duree_heure` varchar(250) NOT NULL,
  `reference` varchar(250) NOT NULL,
  `etat_connexion` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Structure de la table `vue`
--

DROP TABLE IF EXISTS `vue`;
CREATE TABLE IF NOT EXISTS `vue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notification` int NOT NULL,
  `user` varchar(11) NOT NULL,
  `lecture` tinyint(1) NOT NULL DEFAULT '0',
  `affichage` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
