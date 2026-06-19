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
    render_head('Systeem buiten gebruik');

    echo '<main class="page">';
    echo '<div class="bg-shape bg-shape-a"></div><div class="bg-shape bg-shape-b"></div>';
    echo '<section class="card" style="text-align:center; max-width:600px; margin:auto;">';
    echo '<header class="hero">';
    echo '<p class="eyebrow" style="color:#c0392b; font-weight:800; font-size:1rem; letter-spacing:.08em;">⚠️ SYSTEEM BUITEN GEBRUIK</p>';
    echo '<h1 style="font-size:1.6rem; color:#c0392b;">Systeem buiten gebruik</h1>';
    echo '<p class="subtitle" style="font-size:1.1rem; line-height:1.6; margin-top:1rem;">';
    echo 'Vanaf heden is dit systeem buiten gebruik.<br>';
    echo 'Voor meer informatie zie de berichtgeving van <strong>VVE165</strong>.';
    echo '</p>';
    echo '</header>';
    echo '</section></main>';
    echo '<script src="/assets/app.js"></script>';
    echo '</body></html>';
    exit;
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
