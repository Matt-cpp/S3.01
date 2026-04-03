<?php
/**
 * Model file to format resource labels into a more human-readable format.
 */
if (!function_exists('formatResourceLabel')) {
    /**
     * Format an input resource label into a more human-readable format.
     *
     * Input exemple : "INFFIS2-DEVELOPPEMENT ORIENTE OBJETS (T3BUTINFFI-R2.01)"
     * Output exemple: "R2.01 - DEVELOPPEMENT ORIENTE OBJETS"
     */
    function formatResourceLabel($fullLabel)
    {
        if (empty($fullLabel) || $fullLabel === 'N/A') {
            return $fullLabel ?? 'N/A';
        }

        // Extract the content inside the first pair of parentheses
        // e.g. "T3BUTINFFI-R2.01" from "(T3BUTINFFI-R2.01)"
        if (preg_match('/\(([^)]+)\)/', $fullLabel, $matches)) {
            $fullCode = $matches[1];

            // Short code = part after the last hyphen inside the parentheses
            $codeParts = explode('-', $fullCode);
            $code = end($codeParts);

            // Human-readable name = text between the first hyphen and the opening parenthesis
            if (preg_match('/^[^-]+-(.+?)\s*\(/', $fullLabel, $labelMatches)) {
                $label = trim($labelMatches[1]);
                return $code . ' - ' . $label;
            }
        }

        // Fallback: return the original label unchanged
        return $fullLabel;
    }
}
