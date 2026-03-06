<?php
    declare(strict_types=1);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }

    $lang = $_SESSION['lang'] ?? 'fr';

    $translations = [
        'fr' => [
            'home' => 'Accueil',
            'dashboard' => 'Dashboard',
            'backoffice' => 'Backoffice',
            'logout' => 'Déconnexion',
            'login' => 'Connexion',
            'landscape' => 'Paysage (KLCD)',
            'dashboard_subtitle' => 'Version maquette : graphiques uniquement (pas d’affichage de données sensibles).',
            'mode' => 'Mode',
            'open' => 'Ouvrir',
        ],
        'en' => [
            'home' => 'Home',
            'dashboard' => 'Dashboard',
            'backoffice' => 'Backoffice',
            'logout' => 'Logout',
            'login' => 'Login',
            'landscape' => 'Landscape (KLCD)',
            'dashboard_subtitle' => 'Mockup version: charts only (no sensitive data displayed).',
            'mode' => 'Mode',
            'open' => 'Open',
        ],
    ];

    function t(string $key): string {
        global $translations, $lang;
        return $translations[$lang][$key] ?? $key;
    }
?>