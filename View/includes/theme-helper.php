<?php

declare(strict_types=1);

/**
 * Theme Helper
 * Include this in the <head> of all template files to enable dark mode support
 */

function renderThemeSupport(): void
{
?>
    <!-- Dark Mode Support -->
    <link rel="stylesheet" href="/View/assets/css/shared/dark-mode.css">
    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const theme = document.cookie.split('; ').find(row => row.startsWith('theme='));
            if (theme && theme.split('=')[1] === 'dark') {
                document.documentElement.classList.add('dark-mode');
                // Also add to body if it exists
                if (document.body) {
                    document.body.classList.add('dark-mode');
                }
            }
        })();
    </script>
<?php
}

function renderThemeScript(): void
{
?>
    <!-- Theme Manager Script -->
    <script src="/View/assets/js/shared/theme.js"></script>
<?php
}

function getCurrentTheme(): string
{
    return $_COOKIE['theme'] ?? 'light';
}

function isDarkMode(): bool
{
    return getCurrentTheme() === 'dark';
}
?>