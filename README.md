# 🏫 MCC Gestion

![Version](https://img.shields.io/badge/version-2.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white)
![CSS](https://img.shields.io/badge/CSS-Custom%20Grid%2FFlexbox-7952B3)
![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?logo=javascript&logoColor=black)
![FPDF](https://img.shields.io/badge/PDF-FPDF-orange)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/status-production%20ready-brightgreen)

> Système de Gestion complet pour Centres de Formation Professionnelle

MCC Gestion est une application web complète, robuste et intuitive conçue pour gérer l'ensemble des opérations d'un centre de formation professionnelle : apprenants, formations, inscriptions, formateurs, sessions, paiements et certificats. Développée en PHP vanilla avec une architecture modulaire, elle offre une interface moderne et responsive sans dépendances externes.

---

## 📸 Captures d'écran

<div align="center">

### 🔐 Page de Connexion
![Connexion](screenshots/login.png)

### 📊 Tableau de Bord
![Dashboard](screenshots/dashboard.png)

| Modules | Modules |
|:-------:|:-------:|
| ![Apprenants](screenshots/apprenants.png) | ![Formations](screenshots/formations.png) |
| **📚 Apprenants** | **🎓 Formations** |
| ![Inscriptions](screenshots/inscriptions.png) | ![Formateurs](screenshots/formateurs.png) |
| **📝 Inscriptions** | **👨‍🏫 Formateurs** |
| ![Sessions](screenshots/sessions.png) | ![Paiements](screenshots/paiements.png) |
| **📅 Sessions** | **💵 Paiements** |
| ![Certificats](screenshots/certificats.png) | ![Certificat PDF](screenshots/certificat-pdf.png) |
| **📜 Certificats** | **🖨️ Certificat PDF** |
| ![Utilisateurs](screenshots/utilisateurs.png) | |
| **👥 Utilisateurs** | |

</div>

---

## 🚀 Fonctionnalités Principales

### 🔐 Authentification Sécurisée
- ✅ Protection CSRF (Cross-Site Request Forgery)
- ✅ Limitation des tentatives de connexion (5 essais, blocage 5 minutes)
- ✅ Hachage des mots de passe avec bcrypt (`password_hash`)
- ✅ Système "Se souvenir de moi" avec token en base de données
- ✅ Journalisation des connexions/déconnexions dans `logs/login.log`
- ✅ Protection XSS (`htmlspecialchars`) et injections SQL (requêtes préparées PDO)
- ✅ Régénération de session après authentification
- ✅ Redirection automatique si déjà connecté
- ✅ Page de connexion épurée avec modal "Mot de passe oublié"

### 📊 Tableau de Bord Ultra Pro
- ✅ 6 cartes statistiques cliquables (Apprenants, Formations, Inscriptions, Formateurs, Sessions, Revenus)
- ✅ Graphique d'évolution des inscriptions sur 6 mois (barres animées)
- ✅ 4 indicateurs de performance (Actives, Terminées, En cours, Taux de réussite)
- ✅ Revenus du mois en cours avec barre de progression
- ✅ Top 4 des formations les plus populaires
- ✅ Prochaines sessions planifiées avec dates
- ✅ Dernières inscriptions avec statuts colorés
- ✅ Layout responsive Grid + Flexbox 70/30
- ✅ Animations CSS au chargement

### 📚 Gestion des Apprenants (CRUD)
- ✅ Fiche complète : Nom, Postnom, Prénom, Sexe, Date naissance, Téléphone, Email, Adresse
- ✅ Validation des champs obligatoires et format email
- ✅ Recherche en temps réel avec debounce (500ms)
- ✅ Filtrage par sexe (Hommes/Femmes/Tous)
- ✅ Avatars avec initiales colorées (bleu pour hommes, rose pour femmes)
- ✅ Compteur d'inscriptions avec noms des formations suivies
- ✅ Protection contre la suppression si inscriptions liées
- ✅ Statistiques rapides (Total, Hommes, Femmes, Inscriptions)

### 🎓 Gestion des Formations (CRUD)
- ✅ Nom, Durée, Coût (max 200$), Niveau (Débutant/Intermédiaire/Avancé), Description
- ✅ Recherche en temps réel avec debounce
- ✅ Filtrage par niveau
- ✅ Nombre d'inscriptions et de sessions liées
- ✅ Badges colorés par niveau
- ✅ Protection contre la suppression si dépendances
- ✅ Formulaire sticky (reste visible pendant le défilement)

### 📝 Gestion des Inscriptions (CRUD)
- ✅ Lien Apprenant ↔ Formation avec listes déroulantes
- ✅ Affichage du coût en temps réel lors de la sélection
- ✅ Calcul automatique du solde restant (Coût total - Total payé)
- ✅ Statuts : En cours, Actif, En attente, Terminé, Abandonné
- ✅ Lien direct vers la gestion des paiements
- ✅ Badges de statut colorés
- ✅ Vérification anti-doublon (un apprenant ne peut pas s'inscrire 2 fois à la même formation)
- ✅ Barre de statistiques (Total, Actives, Terminées, Revenus)

### 👨‍🏫 Gestion des Formateurs (CRUD)
- ✅ Profil complet : Nom, Spécialité, Téléphone, Email
- ✅ Autocomplétion des spécialités (datalist)
- ✅ Validation email unique
- ✅ Vue détaillée en modal des sessions du formateur
- ✅ Nombre de sessions assignées
- ✅ Avatars avec initiales

### 📅 Gestion des Sessions (CRUD)
- ✅ Double vue : Tableau + Calendrier mensuel interactif
- ✅ Planification : Dates début/fin, Horaire, Salle
- ✅ Détection automatique du statut (En cours, À venir, Terminée)
- ✅ Durée calculée automatiquement en jours
- ✅ Vérification des conflits de salle sur la période
- ✅ Filtrage par statut et par formation
- ✅ Calendrier avec pastilles de couleur pour les jours occupés

### 💵 Gestion des Paiements (CRUD)
- ✅ Suivi financier complet par inscription
- ✅ Calcul automatique du solde restant
- ✅ Barres de progression du paiement (pourcentage)
- ✅ Blocage si le montant saisi dépasse le reste à payer
- ✅ Modes de paiement : Espèces, Virement bancaire, Mobile Money, Chèque, Carte bancaire
- ✅ Filtrage par mode de paiement et par période (date début/fin)
- ✅ Répartition graphique par mode de paiement
- ✅ Présélection depuis le module Inscriptions
- ✅ Statistiques : Total perçu, Ce mois, Transactions, Soldes impayés

### 📜 Gestion des Certificats (CRUD) 🆕
- ✅ Mentions : Excellence, Distinction, Satisfaction, Passable
- ✅ Numérotation automatique (CERT-AAAA-NNNN)
- ✅ Génération de **PDF professionnel** via FPDF
- ✅ Design du certificat : double cadre bleu marine et or, fond ivoire
- ✅ QR Code intégré pour vérification d'authenticité
- ✅ Date de délivrance, durée, niveau, description de la formation
- ✅ Zone de signatures (Directeur, Formateur, Apprenant)
- ✅ Format Lettre paysage, une seule page
- ✅ Nettoyage automatique des accents pour compatibilité PDF
- ✅ Téléchargement direct du PDF dans le navigateur
- ✅ Protection : un seul certificat par inscription
- ✅ Filtrage par mention et par période
- ✅ Répartition statistique par mention

### 👥 Gestion des Utilisateurs (CRUD)
- ✅ Réservé aux administrateurs (vérification de rôle)
- ✅ 4 rôles : Admin, Formateur, Secrétaire, Gestionnaire
- ✅ Activation/Désactivation en un clic (toggle)
- ✅ Indicateur de force du mot de passe (barre colorée)
- ✅ Confirmation du mot de passe
- ✅ Protection contre l'auto-suppression et l'auto-désactivation
- ✅ Protection du dernier administrateur
- ✅ Avatars colorés par rôle avec initiales
- ✅ Filtrage par rôle et par statut (actif/inactif)
- ✅ Description des permissions par rôle

---

## 🛠️ Technologies

| Catégorie | Technologie | Détails |
|-----------|-------------|---------|
| **Backend** | PHP 8.0+ | PDO, requêtes préparées, architecture modulaire |
| **Base de données** | MySQL 5.7+ / MariaDB 10.4+ | Moteur InnoDB, contraintes FK, index |
| **Frontend** | HTML5, CSS3, JavaScript | CSS Grid, Flexbox, Vanilla JS, animations CSS3 |
| **Sécurité** | CSRF, bcrypt, Sessions | Protection complète OWASP Top 10 |
| **PDF** | FPDF | Génération de certificats sans dépendance externe |
| **QR Code** | API qrserver.com | Code QR intégré aux certificats |
| **Design** | CSS personnalisé | 0 dépendance externe, responsive design |
| **Icônes** | Emoji natifs | Aucune librairie d'icônes requise |

---

---

## 📦 Installation Rapide (5 minutes)

### Prérequis

| Composant | Version Minimum | Recommandé |
|-----------|-----------------|------------|
| PHP | 7.4 | 8.2+ |
| MySQL | 5.7 | 8.0+ / MariaDB 10.4+ |
| Apache | 2.4 | 2.4+ |
| Navigateur | Chrome 80+, Firefox 80+ | Dernière version |

### Étapes

1. **Cloner le dépôt**
   bash
   git clone https://github.com/cosmas-webdev/gestion_mcc.git
   cd gestion_mcc
