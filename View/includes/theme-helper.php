<?php
/**
 * Theme Helper
 * Include this in the <head> of all template files to enable dark mode support
 */

function renderThemeSupport()
{
    ?>
    <!-- Dark Mode Support -->
    <link rel="stylesheet" href="/View/assets/css/dark-mode.css">
    <script>
        // Apply theme immediately to prevent flash
        (function () {
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

function renderThemeScript()
{
    ?>
    <!-- Theme Manager Script -->
    <script src="/View/assets/js/theme.js"></script>
    <?php
}

/**
 * Get current theme from cookie
 */
function getCurrentTheme()
{
    return $_COOKIE['theme'] ?? 'light';
}

/**
 * Check if dark mode is enabled
 */
function isDarkMode()
{
    return getCurrentTheme() === 'dark';
}
?>