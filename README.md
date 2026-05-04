# 🏫 MCC Gestion

![Version](https://img.shields.io/badge/version-1.2.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white)
![CSS](https://img.shields.io/badge/CSS-Custom%20Grid%2FFlexbox-7952B3)
![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/status-production%20ready-brightgreen)

> Système de Gestion complet pour Centres de Formation Professionnelle

MCC Gestion est une application web complète, robuste et intuitive conçue pour gérer l'ensemble des opérations d'un centre de formation professionnelle : apprenants, formations, inscriptions, formateurs, sessions, paiements et certificats. Développée en PHP vanilla avec une architecture modulaire, elle offre une interface moderne et responsive sans dépendances externes.

---

## 🚀 Fonctionnalités Principales

### 🔐 Authentification Sécurisée
- Protection CSRF (Cross-Site Request Forgery)
- Limitation des tentatives de connexion (5 essais, blocage 5 minutes)
- Hachage des mots de passe avec bcrypt
- Système "Se souvenir de moi" avec token
- Journalisation des connexions/déconnexions
- Protection XSS et injections SQL

### 📊 Tableau de Bord
- 6 cartes statistiques cliquables
- Graphique d'évolution des inscriptions (6 mois)
- 4 indicateurs de performance (Actives, Terminées, En cours, Réussite)
- Revenus du mois, prochaines sessions, top formations
- Layout responsive Grid + Flexbox

### 📚 Gestion des Apprenants (CRUD)
- Fiche complète avec validation
- Recherche en temps réel, filtrage par sexe
- Avatars avec initiales colorées
- Compteur d'inscriptions

### 🎓 Gestion des Formations (CRUD)
- Nom, durée, coût (max 200$), niveau, description
- Filtrage par niveau, recherche
- Nombre d'inscriptions et sessions liées

### 📝 Gestion des Inscriptions (CRUD)
- Lien Apprenant ↔ Formation
- Calcul automatique du solde restant
- Statuts : En cours, Actif, Terminé, Abandonné
- Lien direct vers les paiements

### 👨‍🏫 Gestion des Formateurs (CRUD)
- Profil complet avec spécialité
- Vue détaillée des sessions par formateur

### 📅 Gestion des Sessions (CRUD)
- Double vue : Tableau + Calendrier mensuel
- Détection automatique du statut
- Vérification des conflits de salle

### 💵 Gestion des Paiements (CRUD)
- Suivi financier complet
- Barres de progression, blocage dépassement
- Modes : Espèces, Virement, Mobile Money, Chèque, Carte

### 📜 Gestion des Certificats (CRUD)
- Mentions : Excellence, Distinction, Satisfaction, Passable
- Numérotation automatique (CERT-AAAA-NNNN)
- Aperçu en direct avant validation

### 👥 Gestion des Utilisateurs (CRUD)
- 4 rôles : Admin, Formateur, Secrétaire, Gestionnaire
- Activation/Désactivation en un clic
- Indicateur de force du mot de passe

---

## 🛠️ Technologies

| Catégorie | Technologie |
|-----------|-------------|
| **Backend** | PHP 8.0+ (PDO, requêtes préparées) |
| **Base de données** | MySQL 5.7+ / MariaDB 10.4+ |
| **Frontend** | HTML5, CSS3 Grid/Flexbox, JavaScript Vanilla |
| **Sécurité** | CSRF, bcrypt, Sessions, Validation |
| **Design** | CSS personnalisé, 0 dépendance, responsive |

---

## 📁 Structure du Projet
gestion_mcc/
├── config/
│ ├── database.php
│ └── database.example.php
├── css/
│ └── style.css
├── includes/
│ └── header.php
├── logs/
├── index.php # Connexion
├── dashboard.php # Tableau de bord
├── apprenants.php # CRUD Apprenants
├── formations.php # CRUD Formations
├── inscriptions.php # CRUD Inscriptions
├── formateurs.php # CRUD Formateurs
├── sessions.php # CRUD Sessions + Calendrier
├── paiements.php # CRUD Paiements
├── certificats.php # CRUD Certificats
├── utilisateurs.php # CRUD Utilisateurs (Admin)
├── logout.php # Déconnexion
├── setup.php # Installation automatique
├── .gitignore
├── LICENSE
└── README.md

---

## 📦 Installation Rapide (5 minutes)

### Prérequis
- XAMPP / WAMP / LAMP / MAMP
- PHP 8.0+ | MySQL 5.7+ | Apache

### Étapes

1. **Cloner le dépôt**
   ```bash
   git clone https://github.com/cosmas-webdev/gestion_mcc.git
   cd gestion_mcc

2. **Placer dans le dossier web**

XAMPP : C:\xampp\htdocs\gestion_mcc\

3. **Configurer la base de données**
   cp config/database.example.php config/database.php
4.  **Démarrer Apache et MySQL**
   Ouvrir : http://localhost/gestion_mcc/setup.php

**Ou Se connecter**

**URL : http://localhost/gestion_mcc/index.php**
