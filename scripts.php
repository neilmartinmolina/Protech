<?php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$basePath = ($basePath === '' || $basePath === '/' || $basePath === '\\') ? '' : $basePath;
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
if (!empty($pageScripts) && is_array($pageScripts)) {
    foreach ($pageScripts as $src) {
        $href = $basePath . '/' . ltrim($src, '/');
        echo '<script src="' . htmlspecialchars($href) . '"></script>' . "\n";
    }
}
?>
