<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="icon" type="image/x-icon" href="media/logo/ProtechIcon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://kit.fontawesome.com/e65444583f.js" crossorigin="anonymous"></script>
<?php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$basePath = ($basePath === '' || $basePath === '/' || $basePath === '\\') ? '' : $basePath;
$basePath = $basePath ?: '';
$cssDir = 'css';
$baseCss = $basePath ? $basePath . '/' . $cssDir : $cssDir;
?>
<link rel="stylesheet" href="<?= htmlspecialchars($baseCss); ?>/style.css">
<?php
if (!empty($pageCss) && is_array($pageCss)) {
    foreach ($pageCss as $href) {
        $href = strpos($href, 'css/') === 0 ? $href : $cssDir . '/' . ltrim($href, '/');
        $url = $basePath ? $basePath . '/' . $href : $href;
        echo '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">' . "\n";
    }
}
if (!empty($pageCssExt)) {
    foreach ($pageCssExt as $url) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">' . "\n";
    }
}
?>
<title><?= htmlspecialchars($pageTitle ?? 'ProTech'); ?></title>
