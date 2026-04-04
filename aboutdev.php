<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/includes/dashboard/view_data.php';

// Hard gate — before any output.
$user = app_require_login();
$role = $user['role'] ?? 'customer';

if ($role !== 'admin') {
    header('Location: index.php');
    exit;
}

$conn = app_db();
$view = dashboard_build_view_data($conn, $user, $role);
extract($view, EXTR_SKIP);

$tab = 'about_dev';
// Add this page to the same variable contract expected by sidebar/topbar.
$allowedTabs['about_dev'] = ['About Developer', 'fa-solid fa-circle-user'];

$pageTitle  = 'About Developer — ProTech';
$pageCss    = ['admin.css', 'dashboard.css', 'aboutdev.css'];
$pageCssExt = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/dashboard/partials/sidebar.php'; ?>

    <div class="admin-main">
        <?php include __DIR__ . '/includes/dashboard/partials/topbar.php'; ?>

        <div class="admin-content">
            <div class="about-dev-wrap">

                <!-- ── About Section ─────────────────────────────────────────
                     Structure mirrors portfolio .about-section exactly:
                     .section-header > .bg-text + h2
                     .row > col-lg-5 (image) + col-lg-7 (bio + .info-list)
                ──────────────────────────────────────────────────────────── -->
                <section class="dev-about-section">

                    <div class="dev-section-header">
                        <span class="dev-bg-text">About Me</span>
                        <h2 class="dev-section-title">About the Developer</h2>
                    </div>

                    <div class="panel-card">
                        <div class="row align-items-center g-4">

                            <div class="col-lg-5 mb-4 mb-lg-0">
                                <img
                                    src="media/DEV/me.png"
                                    alt="Neil Martin Molina"
                                    class="dev-portrait img-fluid rounded shadow-lg"
                                >
                            </div>

                            <div class="col-lg-7 ps-lg-5">
                                <h3 class="dev-about-title">Hi There! I'm Neil Martin Molina</h3>
                                <p class="dev-about-subtitle">Developer</p>

                                <p class="dev-lead mb-4">
                                    I am a developer focused on coding and an avid video game enthusiast.
                                </p>

                                <!-- Mirrors .info-list: strong key + span value -->
                                <ul class="dev-info-list">
                                    <li>
                                        <strong>Birthday</strong>
                                        <span>: July 02, 2004</span>
                                    </li>
                                    <li>
                                        <strong>Email</strong>
                                        <span>: <a href="mailto:neilmartinmolina@gmail.com" class="dev-email-link">neilmartinmolina@gmail.com</a></span>
                                    </li>
                                    <li>
                                        <strong>From</strong>
                                        <span>: Philippines</span>
                                    </li>
                                    <li>
                                        <strong>Language</strong>
                                        <span>: English</span>
                                    </li>
                                    <li>
                                        <strong>Freelance</strong>
                                        <span>: <span class="pill pending">Not Available</span></span>
                                    </li>
                                </ul>

                                <div class="dev-socials mt-3">
                                    <a href="https://www.tiktok.com/@nlmrtnmlna"
                                       class="dev-social-btn" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
                                        <i class="fa-brands fa-tiktok"></i>
                                    </a>
                                    <a href="https://www.instagram.com/nlmrtnmlina/"
                                       class="dev-social-btn" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                                        <i class="fa-brands fa-instagram"></i>
                                    </a>
                                    <a href="https://github.com/neilmartinmolina"
                                       class="dev-social-btn" target="_blank" rel="noopener noreferrer" aria-label="GitHub">
                                        <i class="fa-brands fa-github"></i>
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>

                <!-- ── Skills Section ─────────────────────────────────────────
                     Structure mirrors portfolio .skills-section exactly:
                     .section-header > .bg-text + h2
                     .skills-box > h3 + .skills-logo-grid          (core)
                     .skills-box > h3 + .auto-carousel-container   (familiar)
                ──────────────────────────────────────────────────────────── -->
                <section class="dev-skills-section mt-5">

                    <div class="dev-section-header">
                        <span class="dev-bg-text">Skills</span>
                        <h2 class="dev-section-title">Skills</h2>
                    </div>

                    <!-- Core — static logo grid -->
                    <div class="panel-card mb-4">
                        <div class="dev-skills-box text-center">
                            <h3 class="dev-skills-box-title">Core</h3>
                            <div class="dev-skills-logo-grid">
                                <div class="dev-skill-item" data-bs-toggle="tooltip" data-bs-placement="top" title="HTML5">
                                    <img src="media/languagelogo/HTML5.png" alt="HTML5" class="dev-skill-logo">
                                    <span>HTML5</span>
                                </div>
                                <div class="dev-skill-item" data-bs-toggle="tooltip" data-bs-placement="top" title="CSS3">
                                    <img src="media/languagelogo/CSS3.png" alt="CSS3" class="dev-skill-logo">
                                    <span>CSS3</span>
                                </div>
                                <div class="dev-skill-item" data-bs-toggle="tooltip" data-bs-placement="top" title="JavaScript">
                                    <img src="media/languagelogo/javascriptlogo.png" alt="JavaScript" class="dev-skill-logo">
                                    <span>JavaScript</span>
                                </div>
                                <div class="dev-skill-item" data-bs-toggle="tooltip" data-bs-placement="top" title="Bootstrap">
                                    <img src="media/languagelogo/Bootstrap.png" alt="Bootstrap" class="dev-skill-logo">
                                    <span>Bootstrap</span>
                                </div>
                                <div class="dev-skill-item" data-bs-toggle="tooltip" data-bs-placement="top" title="PHP">
                                    <img src="media/languagelogo/PHP.png" alt="PHP" class="dev-skill-logo">
                                    <span>PHP</span>
                                </div>
                                <div class="dev-skill-item" data-bs-toggle="tooltip" data-bs-placement="top" title="MySQL">
                                    <img src="media/languagelogo/MySQL.png" alt="MySQL" class="dev-skill-logo">
                                    <span>MySQL</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Familiar / Academic — hover-to-scroll
                         Single set of items. No clones, no CSS animation.
                         JS scrolls the container on mouseenter, stops on mouseleave.
                         To add skills: update $familiarSkills array only.
                    -->
                    <div class="panel-card">
                        <div class="dev-skills-box text-center">
                            <h3 class="dev-skills-box-title">Familiar / Academic</h3>
                            <div class="dev-scroll-carousel mt-4" id="devScrollCarousel">
                                <?php
                                $familiarSkills = [
                                    ['src' => 'media/languagelogo/GitHub.png',       'label' => 'GitHub'],
                                    ['src' => 'media/languagelogo/Git.png',          'label' => 'Git'],
                                    ['src' => 'media/languagelogo/Java.png',         'label' => 'Java'],
                                    ['src' => 'media/languagelogo/Python.png',       'label' => 'Python'],
                                    ['src' => 'media/languagelogo/C.png',            'label' => 'C'],
                                    ['src' => 'media/languagelogo/C++.png',          'label' => 'C++'],
                                    ['src' => 'media/languagelogo/Tailwind CSS.png', 'label' => 'Tailwind CSS'],
                                ];
                                foreach ($familiarSkills as $skill): ?>
                                    <div class="dev-carousel-item"
                                         data-bs-toggle="tooltip" data-bs-placement="top"
                                         title="<?= htmlspecialchars($skill['label']) ?>">
                                        <img src="<?= htmlspecialchars($skill['src']) ?>"
                                             alt="<?= htmlspecialchars($skill['label']) ?>"
                                             class="dev-skill-logo">
                                        <span><?= htmlspecialchars($skill['label']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </section>

            </div><!-- /about-dev-wrap -->
        </div><!-- /admin-content -->
    </div><!-- /admin-main -->
</div><!-- /admin-layout -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tooltips
    const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipEls].forEach(el => new bootstrap.Tooltip(el));

    // Hover-to-scroll carousel
    (function () {
        const track    = document.getElementById('devScrollCarousel');
        const speed    = 1.2; // px per frame — adjust to taste
        let   rafId    = null;
        let   direction = 1; // 1 = forward, -1 = backward

        function scroll() {
            track.scrollLeft += speed * direction;

            // Bounce at ends
            if (track.scrollLeft + track.clientWidth >= track.scrollWidth) {
                direction = -1;
            } else if (track.scrollLeft <= 0) {
                direction = 1;
            }

            rafId = requestAnimationFrame(scroll);
        }

        track.addEventListener('mouseenter', () => {
            if (!rafId) rafId = requestAnimationFrame(scroll);
        });

        track.addEventListener('mouseleave', () => {
            cancelAnimationFrame(rafId);
            rafId = null;
        });
    })();
</script>
</body>
</html>