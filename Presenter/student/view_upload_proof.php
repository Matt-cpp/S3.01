<?php

declare(strict_types=1);
/**
 * File: view_upload_proof.php
 *
 * Proof file viewer – Allows secure display of uploaded files.
 * Main features:
 * - Read files from proof_files (JSONB) or file_path (legacy system)
 * - Support for multiple files per proof (multi-file)
 * - Automatic MIME type detection for correct display
 * - Debug mode for troubleshooting
 * - Raw mode for direct file display
 * - Robust uploads folder location
 * - Proper HTTP headers for download/display
 * Used to view proofs submitted by students.
 */

session_start();
require_once __DIR__ . '/../../Model/ProofModel.php';

// Utility function to return an HTTP error
function http_err(int $code, string $msg): void
{
    http_response_code($code);
    echo $msg;
    exit;
}
// Request parameters (debug, raw mode, proof_id, file_index)
$debug = isset($_GET['debug']) ? (int) $_GET['debug'] : 0;
$rawMode = isset($_GET['raw']) ? (int) $_GET['raw'] : 0;
$proofId = isset($_GET['proof_id']) ? (int) $_GET['proof_id'] : 0;
$fileIndex = isset($_GET['file_index']) ? (int) $_GET['file_index'] : 0;

// Robust uploads folder location (at the same level as Presenter)
$projectRoot = dirname(dirname(__DIR__));
$candidates = [
    $projectRoot . DIRECTORY_SEPARATOR . 'uploads',
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads',
];
$baseDir = null;
foreach ($candidates as $c) {
    $rp = realpath($c);
    if ($rp && is_dir($rp)) {
        $baseDir = $rp;
        break;
    }
}
if (!$baseDir) {
    http_err(404, "'uploads' folder not found.");
}

$filePath = null;
$clientName = null;

if ($proofId > 0) {
    // Read by ID mode
    $row = (new ProofModel())->getProofFilePaths($proofId);
    if (!$row) {
        http_err(404, 'Proof not found.');
    }

    // Extract files from proof_files (JSONB) or file_path
    $allFiles = [];

    // 1. Check proof_files (JSONB)
    if (!empty($row['proof_files'])) {
        $jsonFiles = $row['proof_files'];

        // Decode if JSON string
        if (is_string($jsonFiles)) {
            $decoded = json_decode($jsonFiles, true);
            if (is_array($decoded)) {
                $jsonFiles = $decoded;
            }
        }

        // Extract paths
        if (is_array($jsonFiles)) {
            foreach ($jsonFiles as $file) {
                if (is_string($file)) {
                    $allFiles[] = $file;
                } elseif (is_array($file) && isset($file['path'])) {
                    $allFiles[] = $file['path'];
                } elseif (is_array($file) && isset($file['file_path'])) {
                    $allFiles[] = $file['file_path'];
                }
            }
        }
    }

    // 2. Fallback to file_path if proof_files is empty
    if (empty($allFiles) && !empty($row['file_path'])) {
        $allFiles[] = $row['file_path'];
    }

    // Check at least one file exists
    if (empty($allFiles)) {
        http_err(404, 'No file associated with this proof.');
    }

    // Select file by index
    if ($fileIndex >= count($allFiles)) {
        http_err(404, 'Invalid file index.');
    }

    $storedPath = $allFiles[$fileIndex]; // ex: "uploads/68e4....png"
    $p = str_replace('\\', '/', trim($storedPath));
    $p = ltrim($p, '/');
    if (stripos($p, 'uploads/') === 0) {
        $p = substr($p, strlen('uploads/'));
    }

    $cleanSegs = [];
    foreach (explode('/', $p) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..')
            continue;
        $cleanSegs[] = $seg;
    }
    if (empty($cleanSegs)) {
        http_err(404, 'Invalid file path.');
    }

    $candidate = $baseDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $cleanSegs);
    $filePath = realpath($candidate) ?: $candidate;
    if (!isset($clientName)) {
        $clientName = basename($filePath);
    }
} else {
    // Fallback session (preview after submission)
    $saved = $_SESSION['reason_data']['saved_file_name'] ?? ($_SESSION['reason_data']['proof_file'] ?? '');
    $clientName = $_SESSION['reason_data']['proof_file'] ?? basename((string) $saved);
    if ($saved === '')
        http_err(404, 'File not found.');
    $filePath = realpath($baseDir . DIRECTORY_SEPARATOR . basename((string) $saved)) ?: null;
}

// Final checks + confinement to uploads folder
$realDir = $filePath ? (realpath(dirname($filePath) ?: '') ?: '') : '';
if ($debug) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "baseDir = {$baseDir}\n";
    echo "filePath = " . ($filePath ?? '(null)') . "\n";
    echo "realDir = {$realDir}\n";
    exit;
}
if (!$filePath || !is_file($filePath) || strncmp($realDir, $baseDir, strlen($baseDir)) !== 0) {
    error_log('view_upload_proof: not found or outside uploads. base=' . $baseDir . ' file=' . $filePath . ' realDir=' . $realDir);
    http_err(404, 'File not found.');
}

// MIME detection
$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
        $det = finfo_file($fi, $filePath);
        if (is_string($det) && $det !== '')
            $mime = $det;
        finfo_close($fi);
    }
}

// Fallback to mime_content_type if available
if ($mime === 'application/octet-stream' && function_exists('mime_content_type')) {
    $det = @mime_content_type($filePath);
    if (is_string($det) && $det !== '')
        $mime = $det;
}

// Last fallback: file extension mapping
if ($mime === 'application/octet-stream') {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $extMap = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain'
    ];
    if (isset($extMap[$ext])) {
        $mime = $extMap[$ext];
    }
}

if ($mime === 'application/octet-stream') {
    error_log('view_upload_proof: MIME not determined for file ' . $filePath . ', returning application/octet-stream');
}

// Raw mode: return file directly (for <img>/<iframe>)
if ($rawMode) {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . addslashes($clientName ?? basename($filePath)) . '"');
    header('Content-Length: ' . (string) filesize($filePath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=60');
    while (ob_get_level()) {
        ob_end_clean();
    }
    readfile($filePath);
    exit;
}

// Page mode: render an HTML page that embeds the image/PDF
$clientNameHtml = htmlspecialchars($clientName ?? basename($filePath), ENT_QUOTES, 'UTF-8');
$self = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '/Presenter/view_upload_proof.php';
$params = $_GET;
unset($params['debug']);
$params['raw'] = 1;
$rawUrl = htmlspecialchars($self . '?' . http_build_query($params), ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title><?= $clientNameHtml ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: #111;
            color: #ddd;
        }

        .wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        img,
        iframe,
        object {
            max-width: 100%;
            max-height: 100%;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            background: #222;
        }

        .bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 8px 12px;
            background: #1e1e1e;
            font: 14px/1.4 sans-serif;
        }

        .bar a {
            color: #7fb0ff;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="bar">
        <span><?= $clientNameHtml ?></span>
        &nbsp;|&nbsp;<a href="<?= $rawUrl ?>" target="_blank" rel="noopener">Ouvrir l’original</a>
        &nbsp;|&nbsp;<a
            href="<?= htmlspecialchars($self . '?' . http_build_query(array_diff_key($_GET, ['raw' => 1])), ENT_QUOTES, 'UTF-8') ?>">Rafraîchir</a>
    </div>
    <div class="wrap" style="padding-top:42px;">
        <?php if (strpos($mime, 'image/') === 0): ?>
            <img src="<?= $rawUrl ?>" alt="<?= $clientNameHtml ?>">
        <?php elseif ($mime === 'application/pdf'): ?>
            <iframe src="<?= $rawUrl ?>" title="<?= $clientNameHtml ?>" style="width:100%;height:100%;border:0;"></iframe>
        <?php else: ?>
            <div>
                <p>Type non prévisualisable : Veuillez télécharger le fichier et l'ouvrir avec l'application adéquate</p>
                <p><a href="<?= $rawUrl ?>">Télécharger le fichier</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>