<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function render_head(string $title): void
{
    $app = app_config();
    $fullTitle = htmlspecialchars($title . ' | ' . $app['app_name']);

    echo '<!doctype html>';
    echo '<html lang="nl">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $fullTitle . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="/assets/style.css">';
    echo '</head><body>';
}

function render_shell_start(string $title, string $subtitle = ''): void
{
    render_head($title);
    
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $isAdmin = (bool) ($_SESSION['is_admin'] ?? false);
    
    if ($isAdmin) {
        echo '<nav class="admin-nav">';
        echo '<a href="/admin.php" class="admin-nav-link">Admin Beheerpagina</a>';
        echo '</nav>';
    }
    
    echo '<main class="page">';
    echo '<div class="bg-shape bg-shape-a"></div><div class="bg-shape bg-shape-b"></div>';
    echo '<section class="card">';
    echo '<header class="hero">';
    echo '<p class="eyebrow">Tijdelijke digitale deurbel</p>';
    echo '<h1>' . htmlspecialchars($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="subtitle">' . htmlspecialchars($subtitle) . '</p>';
    }
    echo '</header>';
}

function render_shell_end(): void
{
    echo '</section>';
    echo '</main>';
    echo '<script src="/assets/app.js"></script>';
    echo '</body></html>';
}

function flash_html(?string $message, string $type = 'info'): string
{
    if ($message === null || trim($message) === '') {
        return '';
    }

    $safe = htmlspecialchars($message);
    return '<div class="flash flash-' . htmlspecialchars($type) . '">' . $safe . '</div>';
}
