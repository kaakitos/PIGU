-- PIGU - Azure MySQL 8.0 compatible dump
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table `utilisateur`
-- --------------------------------------------------------
CREATE TABLE `utilisateur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `login` varchar(80) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `role` enum('ADMIN_SAMU','GESTIONNAIRE_HOPITAL','AMBULANCIER') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `utilisateur` (`id`, `nom`, `prenom`, `login`, `mot_de_passe`, `telephone`, `role`, `created_at`) VALUES
(1, 'Diallo', 'Moussa', 'admin', '0192023a7bbd73250516f069df18b500', '771234567', 'ADMIN_SAMU', '2026-04-07 10:03:39'),
(3, 'Ndiaye', 'Abdou', 'ambulancier1', '0192023a7bbd73250516f069df18b500', '771234567', 'AMBULANCIER', '2026-04-07 12:06:21'),
(4, 'Fall', 'Fatou', 'docFatou', '0192023a7bbd73250516f069df18b500', '771234567', 'GESTIONNAIRE_HOPITAL', '2026-04-07 12:09:22'),
(5, 'Sarr', 'Ibrahima', 'ambulancier2', '0192023a7bbd73250516f069df18b500', '771234568', 'AMBULANCIER', '2026-04-08 00:00:00'),
(6, 'Diop', 'Cheikh', 'ambulancier3', '0192023a7bbd73250516f069df18b500', '771234569', 'AMBULANCIER', '2026-04-08 00:00:00'),
(7, 'Ba', 'Mariama', 'hopital2', '0192023a7bbd73250516f069df18b500', '771234570', 'GESTIONNAIRE_HOPITAL', '2026-04-08 00:00:00');

-- --------------------------------------------------------
-- Table `ambulance`
-- --------------------------------------------------------
CREATE TABLE `ambulance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `immatriculation` varchar(20) NOT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `statut` enum('DISPONIBLE','EN_MISSION','HORS_SERVICE') NOT NULL DEFAULT 'DISPONIBLE',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `utilisateur_id` (`utilisateur_id`),
  UNIQUE KEY `immatriculation` (`immatriculation`),
  CONSTRAINT `ambulance_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ambulance` (`id`, `utilisateur_id`, `immatriculation`, `latitude`, `longitude`, `statut`, `updated_at`) VALUES
(3, 3, 'DK-1234-AB', 14.692778, -17.446667, 'EN_MISSION', '2026-04-07 16:33:59'),
(4, 5, 'DK-5678-BC', 14.720000, -17.450000, 'DISPONIBLE', '2026-04-08 00:00:00'),
(5, 6, 'DK-9012-CD', 14.680000, -17.430000, 'DISPONIBLE', '2026-04-08 00:00:00');

-- --------------------------------------------------------
-- Table `hopital`
-- --------------------------------------------------------
CREATE TABLE `hopital` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `nom` varchar(150) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  CONSTRAINT `hopital_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `hopital` (`id`, `utilisateur_id`, `nom`, `adresse`, `latitude`, `longitude`, `telephone`, `created_at`) VALUES
(1, 4, 'Hôpital Principal de Dakar', 'Avenue Nelson Mandela, Dakar', 14.693425, -17.447938, '338390000', '2026-04-07 12:10:15'),
(2, 7, 'Hôpital Général de Grand Yoff', 'Grand Yoff, Dakar', 14.735000, -17.460000, '338450000', '2026-04-08 00:00:00');

-- --------------------------------------------------------
-- Table `incident`
-- --------------------------------------------------------
CREATE TABLE `incident` (
  `id` int NOT NULL AUTO_INCREMENT,
  `description` text DEFAULT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `adresse_texte` varchar(255) DEFAULT NULL,
  `priorite` enum('NON_URGENT','URGENT','TRES_URGENT') NOT NULL DEFAULT 'URGENT',
  `statut` enum('NOUVEAU','EN_TRAITEMENT','RESOLU') NOT NULL DEFAULT 'NOUVEAU',
  `date_heure` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `incident` (`id`, `description`, `latitude`, `longitude`, `adresse_texte`, `priorite`, `statut`, `date_heure`) VALUES
(1, '2 blessees', 14.754000, -17.390000, 'Route de Rufisque, Pikine', 'NON_URGENT', 'EN_TRAITEMENT', '2026-04-07 13:50:20'),
(2, 'vegicule', 14.710000, -17.470000, 'Cité Keur Gorgui, Dakar', 'URGENT', 'EN_TRAITEMENT', '2026-04-07 16:33:33'),
(3, 'Accident de voiture, 3 blessés', 14.740000, -17.410000, 'Autoroute Dakar-Diamniadio', 'TRES_URGENT', 'NOUVEAU', '2026-04-08 00:00:00'),
(4, 'Malaise cardiaque', 14.690000, -17.440000, 'Plateau, Dakar', 'URGENT', 'NOUVEAU', '2026-04-08 00:00:00'),
(5, 'Chute de moto', 14.710000, -17.480000, 'Parcelles Assainies, Dakar', 'NON_URGENT', 'NOUVEAU', '2026-04-08 00:00:00');

-- --------------------------------------------------------
-- Table `mission`
-- --------------------------------------------------------
CREATE TABLE `mission` (
  `id` int NOT NULL AUTO_INCREMENT,
  `incident_id` int NOT NULL,
  `ambulance_id` int NOT NULL,
  `hopital_dest_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `statut` enum('EN_ATTENTE','EN_COURS','TERMINEE','ANNULEE') NOT NULL DEFAULT 'EN_ATTENTE',
  `date_debut` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_fin` timestamp NULL DEFAULT NULL,
  `distance_km` decimal(6,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `incident_id` (`incident_id`),
  KEY `ambulance_id` (`ambulance_id`),
  KEY `hopital_dest_id` (`hopital_dest_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `mission_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incident` (`id`),
  CONSTRAINT `mission_ibfk_2` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulance` (`id`),
  CONSTRAINT `mission_ibfk_3` FOREIGN KEY (`hopital_dest_id`) REFERENCES `hopital` (`id`),
  CONSTRAINT `mission_ibfk_4` FOREIGN KEY (`admin_id`) REFERENCES `utilisateur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `mission` (`id`, `incident_id`, `ambulance_id`, `hopital_dest_id`, `admin_id`, `statut`, `date_debut`, `date_fin`, `distance_km`) VALUES
(1, 1, 3, 1, 1, 'TERMINEE', '2026-04-07 16:25:41', '2026-04-07 16:33:06', 89.09),
(2, 2, 3, 1, 1, 'EN_COURS', '2026-04-07 16:33:59', '2026-04-07 16:33:59', 89.09);

-- --------------------------------------------------------
-- Table `place`
-- --------------------------------------------------------
CREATE TABLE `place` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hopital_id` int NOT NULL,
  `numero` varchar(20) NOT NULL,
  `type` enum('CHAMBRE','LIT','BOX') NOT NULL DEFAULT 'LIT',
  `specialite` varchar(100) DEFAULT NULL,
  `statut` enum('DISPONIBLE','OCCUPE','HORS_SERVICE') NOT NULL DEFAULT 'DISPONIBLE',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hopital_id` (`hopital_id`),
  CONSTRAINT `place_ibfk_1` FOREIGN KEY (`hopital_id`) REFERENCES `hopital` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `place` (`id`, `hopital_id`, `numero`, `type`, `specialite`, `statut`, `updated_at`) VALUES
(1, 1, 'CH-101', 'CHAMBRE', 'Cardiologie', 'DISPONIBLE', '2026-04-07 12:10:43'),
(2, 1, 'LIT-102', 'LIT', 'Urgence', 'OCCUPE', '2026-04-07 12:10:43'),
(3, 1, 'LIT-103', 'CHAMBRE', 'Cardiologie', 'DISPONIBLE', '2026-04-07 16:27:48'),
(4, 2, 'LIT-01', 'LIT', 'Urgence', 'DISPONIBLE', '2026-04-08 00:00:00'),
(5, 2, 'LIT-02', 'LIT', 'Urgence', 'DISPONIBLE', '2026-04-08 00:00:00'),
(6, 2, 'CH-01', 'CHAMBRE', 'Chirurgie', 'OCCUPE', '2026-04-08 00:00:00'),
(7, 2, 'BOX-01', 'BOX', 'Réanimation', 'DISPONIBLE', '2026-04-08 00:00:00');

COMMIT;
