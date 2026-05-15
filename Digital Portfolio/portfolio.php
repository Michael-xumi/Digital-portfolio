<?php
// portfolio.php - Dynamic Portfolio Page
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Function to get categories with hierarchy
function getCategories($pdo, $parent_id = null) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . " ORDER BY id");
    $stmt->execute($parent_id === null ? [] : [$parent_id]);
    return $stmt->fetchAll();
}

function getFiles($pdo, $category_id, $user_role, $user_id) {
    if ($user_role === 'Administrator') {
        $stmt = $pdo->prepare("
            SELECT f.*, fv.file_path 
            FROM files f 
            LEFT JOIN file_versions fv ON f.current_version_id = fv.id 
            WHERE f.category_id = ? AND f.is_active = 1
        ");
        $stmt->execute([$category_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT f.*, fv.file_path 
            FROM files f
            INNER JOIN permissions p ON f.id = p.file_id
            LEFT JOIN file_versions fv ON f.current_version_id = fv.id
            WHERE f.category_id = ? AND f.is_active = 1 AND p.user_id = ?
        ");
        $stmt->execute([$category_id, $user_id]);
    }
    return $stmt->fetchAll();
}

// Helper function to render a file list for a given category name
function renderFileList($pdo, $categoryName, $user_role, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$categoryName]);
    $cat = $stmt->fetch();
    $cat_id = $cat ? $cat['id'] : null;
    $files = $cat_id ? getFiles($pdo, $cat_id, $user_role, $user_id) : [];

    if (empty($files)) {
        echo "<li class='p-2 text-text-muted italic'>No files uploaded yet.</li>";
    } else {
        foreach ($files as $file) {
            echo "<li class='p-2 border-b border-border-color flex justify-between items-center hover:bg-secondary rounded-sm transition-colors duration-300'>";
            echo "<span class='font-medium'>" . htmlspecialchars($file['title']) . "</span>";
            echo "<span class='text-sm text-text-muted hidden md:block'>" . htmlspecialchars($file['description']) . "</span>";
            echo "<a href='" . htmlspecialchars($file['file_path']) . "' target='_blank' class='text-accent hover:text-accent-dark text-sm font-medium' data-filetype='" . pathinfo($file['file_path'], PATHINFO_EXTENSION) . "'>(View Artifact)</a>";
            echo "</li>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Student Portfolio | Academic & Career</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'primary': 'var(--color-primary)',
                        'secondary': 'var(--color-secondary)',
                        'accent': 'var(--color-accent)',
                        'accent-dark': 'var(--color-accent-dark)',
                        'text-default': 'var(--color-text-default)',
                        'text-muted': 'var(--color-text-muted)',
                        'border-color': 'var(--color-border)',
                        'success-bg': '#15803d',
                        'success-text': '#d1fae5',
                        'error-bg': '#b91c1c',
                        'error-text': '#fee2e2',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="antialiased">

    <!-- Header -->
    <header class="sticky top-0 z-50 bg-primary/95 backdrop-blur-sm shadow-2xl border-b border-accent/20 transition-colors duration-300">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <a href="#home" class="text-2xl font-bold tracking-tight text-text-default">
                MICHAEL OBENG BOATENG <span class="text-accent">IT.</span>
            </a>
            <nav class="hidden md:flex space-x-8 items-center">
                <a href="#home" class="text-text-muted hover:text-accent transition duration-300 font-medium">Home</a>
                <a href="#academic" class="text-text-muted hover:text-accent transition duration-300 font-medium">Academic Work</a>
                <a href="#about" class="text-text-muted hover:text-accent transition duration-300 font-medium">About Me</a>
                <a href="#contact" class="text-text-muted hover:text-accent transition duration-300 font-medium">Contact</a>
                <button id="theme-toggle-desktop" aria-label="Toggle theme" class="p-2 rounded-full text-accent hover:bg-accent/10 transition duration-300 focus:outline-none focus:ring-2 focus:ring-accent">
                    <svg id="theme-icon-desktop" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"></path><path d="M12 3a9 9 0 109 9c0-.46-.04-.92-.12-1.36a6.997 6.997 0 01-5.88 5.88A9 9 0 0012 3z"></path></svg>
                </button>
                <a href="logout.php" class="text-text-muted hover:text-accent transition duration-300 font-medium">Logout</a>
            </nav>
            <div class="flex items-center space-x-4 md:hidden">
                <button id="theme-toggle-mobile" aria-label="Toggle theme" class="p-2 rounded-full text-accent hover:bg-accent/10 transition duration-300 focus:outline-none focus:ring-2 focus:ring-accent">
                    <svg id="theme-icon-mobile" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"></path><path d="M12 3a9 9 0 109 9c0-.46-.04-.92-.12-1.36a6.997 6.997 0 01-5.88 5.88A9 9 0 0012 3z"></path></svg>
                </button>
                <button id="mobile-menu-button" class="p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-accent">
                    <svg class="w-6 h-6 text-text-default" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                </button>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden absolute w-full bg-secondary shadow-xl transition-colors duration-300">
            <nav class="flex flex-col space-y-2 p-4 border-t border-border-color">
                <a href="#home" class="block py-2 px-3 text-text-default hover:bg-accent/10 rounded-lg transition duration-200">Home</a>
                <a href="#academic" class="block py-2 px-3 text-text-default hover:bg-accent/10 rounded-lg transition duration-200">Academic Work</a>
                <a href="#about" class="block py-2 px-3 text-text-default hover:bg-accent/10 rounded-lg transition duration-200">About Me</a>
                <a href="#contact" class="block py-2 px-3 text-text-default hover:bg-accent/10 rounded-lg transition duration-200">Contact</a>
                <a href="logout.php" class="block py-2 px-3 text-text-default hover:bg-accent/10 rounded-lg transition duration-200">Logout</a>
            </nav>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section id="home" class="relative py-24 sm:py-32 flex items-center justify-center text-center p-4 bg-secondary border-b border-accent/20 transition-colors duration-300">
            <div class="z-10 max-w-5xl">
                <h1 class="text-4xl sm:text-6xl font-extrabold mb-4 leading-tight text-text-default">
                    Structured Development & <span class="text-accent">Digital Innovation.</span>
                </h1>
                <p class="text-lg sm:text-xl text-text-muted mb-8 max-w-3xl mx-auto">
                    A comprehensive, project-based portfolio from NHL Stenden's Information Technology program.
                </p>
                <a href="#academic" class="inline-block px-8 py-3 bg-accent text-primary font-semibold rounded-lg shadow-accent hover:bg-accent-dark transition duration-300 transform hover:scale-[1.02]">
                    Explore My IT Journey
                </a>
            </div>
        </section>

        <!-- About Section -->
        <section id="about" class="py-20 px-4 sm:px-6 lg:px-8 bg-primary transition-colors duration-300">
            <div class="container mx-auto max-w-5xl flex flex-col lg:flex-row items-center gap-12">
                <div class="flex-shrink-0 w-full lg:w-1/3">
                    <img src="img/img digital port.jpeg" alt="Student Profile Picture"
                         class="w-full h-auto max-w-xs mx-auto rounded-full shadow-2xl object-cover border-4 border-accent p-1">
                </div>
                <div class="lg:w-2/3 text-text-default">
                    <h2 class="text-3xl sm:text-4xl font-bold mb-4 text-accent">About Michael Obeng Boateng</h2>
                    <p class="text-lg text-text-muted mb-3">
                        I am a dedicated student in Information Technology at NHL Stenden in Emmen, Netherlands, starting my studies in the 2025-2026 academic year.
                    </p>
                    <p class="text-lg text-text-muted mb-6">
                        I focus on building practical, scalable solutions using modern development tools and methodologies, including version control (GitHub) and containerization (Docker). This portfolio serves as my digital record of academic achievement and hands-on project experience.
                    </p>
                    <div class="flex space-x-4">
                        <a href="https://github.com/Michael-xumi" target="_blank" class="text-accent hover:text-accent-dark font-semibold flex items-center transition duration-300">
                            GitHub Profile (Michael-xumi)
                        </a>
                        <a href="https://www.linkedin.com/in/michaeg-boateng-55b28826a/" class="text-accent hover:text-accent-dark font-semibold flex items-center transition duration-300">
                            LinkedIn Profile Link
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Academic Portfolio Section -->
        <section id="academic" class="py-20 px-4 sm:px-6 lg:px-8 bg-secondary border-t border-accent/30 transition-colors duration-300">
            <div class="container mx-auto max-w-5xl">
                <h2 class="text-3xl sm:text-4xl font-bold text-center mb-12 text-accent">Academic Work Timeline</h2>

                <div id="portfolio-container" class="space-y-6">

                    <!-- YEAR 1 -->
                    <div class="bg-primary rounded-xl shadow-accent border border-accent/30 transition-colors duration-300">
                        <button class="year-toggle flex justify-between items-center w-full p-5 text-left font-extrabold text-xl sm:text-2xl text-accent hover:text-accent-dark transition duration-300" data-target="year-1">
                            <span>Year 1: IT Fundamentals - NHL Stenden (2025-2026)</span>
                            <svg class="w-6 h-6 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="year-1" class="year-content hidden p-5 pt-0">
                            <div class="space-y-6 border-l-4 border-accent pl-6">

                                <!-- Semester 1 -->
                                <div class="bg-secondary/50 rounded-lg shadow-md border border-accent/50">
                                    <button class="semester-toggle flex justify-between items-center w-full p-4 text-left font-extrabold text-lg sm:text-xl text-text-default hover:text-accent transition duration-300" data-target="semester-1">
                                        <span>Semester 1: Foundation Modules (Periods 1 & 2)</span>
                                        <svg class="w-6 h-6 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div id="semester-1" class="semester-content hidden p-4 pt-0 space-y-4">

                                        <!-- Period 1: Web Development -->
                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="period-1">
                                                Period 1: Web Development Basics (HTML, CSS, PHP, Figma)
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="period-1" class="period-content hidden mt-3 space-y-4">
                                                <p class="text-sm text-text-muted mb-4 border-b border-border-color pb-2">
                                                    Project Focus: Full design and initial development of the Sunny Socks e-commerce website.
                                                </p>
                                                <ul class="list-none space-y-2 text-text-default">
                                                    <?php renderFileList($pdo, 'Period 1: Web Development Basics (HTML, CSS, PHP, Figma)', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Period 1: Professional Skills -->
                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-accent/70 transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-accent hover:text-accent-dark transition" data-target="period-1-prof-skills">
                                                Period 1 Professional Skills & Documentation
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="period-1-prof-skills" class="period-content hidden mt-3 space-y-2">
                                                <ul class="list-none space-y-2 text-text-default pt-2">
                                                    <?php renderFileList($pdo, 'Period 1 Professional Skills & Documentation', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Period 2: Database Management -->
                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="period-2">
                                                Period 2: Database Management (MySQL, SQL, Proxmox)
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="period-2" class="period-content hidden mt-3 space-y-4">
                                                <p class="text-sm text-text-muted mb-4 border-b border-border-color pb-2">
                                                    Project Focus: Mastering Relational Database Management using MySQL, phpMyAdmin, and Proxmox Virtual Machines.
                                                </p>
                                                <ul class="list-none space-y-2 text-text-default">
                                                    <?php renderFileList($pdo, 'Period 2: Database Management (MySQL, SQL, Proxmox)', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Period 2: Professional Skills -->
                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-accent/70 transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-accent hover:text-accent-dark transition" data-target="period-2-prof-skills">
                                                Period 2 Professional Skills & Documentation
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="period-2-prof-skills" class="period-content hidden mt-3 space-y-2">
                                                <ul class="list-none space-y-2 text-text-default pt-2">
                                                    <?php renderFileList($pdo, 'Period 2 Professional Skills & Documentation', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <!-- Semester 1 End -->

                                <!-- Semester 2 -->
                                <div class="bg-secondary/50 rounded-lg shadow-md border border-accent/50">
                                    <button class="semester-toggle flex justify-between items-center w-full p-4 text-left font-extrabold text-lg sm:text-xl text-text-default hover:text-accent transition duration-300" data-target="semester-2">
                                        <span>Semester 2: Programming & Innovation (Periods 3 & 4)</span>
                                        <svg class="w-6 h-6 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div id="semester-2" class="semester-content hidden p-4 pt-0 space-y-4">

                                        <!-- Period 3: OOP -->
                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="year1-period-3">
                                                Period 3: Object-oriented Programming (Java, BattleBot)
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="year1-period-3" class="period-content hidden mt-3 space-y-4">
                                                <p class="text-sm text-text-muted mb-4 border-b border-border-color pb-2">
                                                    Project Focus: Take the first step towards programming a software application by working with Java. Work with hardware in the BattleBot project.
                                                </p>
                                                <ul class="list-none space-y-2 text-text-default">
                                                    <?php renderFileList($pdo, 'Period 3: Object-oriented Programming (Java, BattleBot)', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Period 3: Professional Skills -->
                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-accent/70 transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-accent hover:text-accent-dark transition" data-target="period-3-prof-skills">
                                                Period 3 Professional Skills & Documentation
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="period-3-prof-skills" class="period-content hidden mt-3 space-y-2">
                                                <ul class="list-none space-y-2 text-text-default pt-2">
                                                    <?php renderFileList($pdo, 'Period 3 Professional Skills & Documentation', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Period 4: Project Innovate -->
                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="year1-period-4">
                                                Period 4: Project Innovate (Computational Thinking, Project Management)
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="year1-period-4" class="period-content hidden mt-3 space-y-4">
                                                <p class="text-sm text-text-muted mb-4 border-b border-border-color pb-2">
                                                    Project Focus: Start your own project and, paired with a module in computational thinking, learn what decisions are needed to realise success.
                                                </p>
                                                <ul class="list-none space-y-2 text-text-default">
                                                    <?php renderFileList($pdo, 'Period 4: Project Innovate (Computational Thinking, Project Management)', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Period 4: Professional Skills -->
                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-accent/70 transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-accent hover:text-accent-dark transition" data-target="period-4-prof-skills">
                                                Period 4 Professional Skills & Documentation
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="period-4-prof-skills" class="period-content hidden mt-3 space-y-2">
                                                <ul class="list-none space-y-2 text-text-default pt-2">
                                                    <?php renderFileList($pdo, 'Period 4 Professional Skills & Documentation', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <!-- Semester 2 End -->

                                <!-- Tools & Version Control -->
                                <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                    <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="period-codes">
                                        Tools & Version Control (GitHub, Docker, VS Code)
                                        <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div id="period-codes" class="period-content hidden mt-3 space-y-3">
                                        <p class="text-text-muted font-medium">This section documents proficiency in critical development tools used across all periods.</p>
                                        <ul class="list-disc list-inside space-y-2 text-text-muted ml-4">
                                            <li class="text-text-default"><span class="font-bold text-accent">GitHub (Individual):</span> <a href="https://github.com/Michael-xumi" target="_blank" class="text-accent hover:underline ml-2 text-sm font-medium" data-filetype="link">(Go to Individual Assignments Repo)</a></li>
                                            <li class="text-text-default"><span class="font-bold text-accent">GitHub (Group):</span> <a href="https://github.com/JustinasLaunikonis/Sunny-Socks" target="_blank" class="text-accent hover:underline ml-2 text-sm font-medium" data-filetype="link">(Go to Sunny Socks Team Repo)</a></li>
                                            <li class="text-text-default"><span class="font-bold text-accent">Docker:</span> <a href="https://github.com/Michael-xumi/YOUR-NEW-REPOSITORY-NAME/Dockerfile" class="text-accent hover:underline ml-2 text-sm font-medium" data-filetype="yml">(View Dockerfile)</a></li>
                                        </ul>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <!-- Year 1 End -->

                    <!-- YEAR 2 -->
                    <div class="bg-primary rounded-xl shadow-accent border border-accent/30 transition-colors duration-300">
                        <button class="year-toggle flex justify-between items-center w-full p-5 text-left font-extrabold text-xl sm:text-2xl text-accent hover:text-accent-dark transition duration-300" data-target="year-2">
                            <span>Year 2: Advanced IT Studies - NHL Stenden (2026-2027)</span>
                            <svg class="w-6 h-6 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="year-2" class="year-content hidden p-5 pt-0">
                            <div class="space-y-6 border-l-4 border-accent pl-6">

                                <!-- Year 2 Semester 1 -->
                                <div class="bg-secondary/50 rounded-lg shadow-md border border-accent/50">
                                    <button class="semester-toggle flex justify-between items-center w-full p-4 text-left font-extrabold text-lg sm:text-xl text-text-default hover:text-accent transition duration-300" data-target="year2-semester-1">
                                        <span>Semester 1: Advanced Programming & Data (Periods 1 & 2)</span>
                                        <svg class="w-6 h-6 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div id="year2-semester-1" class="semester-content hidden p-4 pt-0 space-y-4">

                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="year2-period-1">
                                                Period 1: Object Oriented Programming 2 (Java, OOP, Design Patterns)
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="year2-period-1" class="period-content hidden mt-3 space-y-4">
                                                <p class="text-sm text-text-muted mb-4 border-b border-border-color pb-2">
                                                    Project Focus: Work on a project with external clients, applying Java and object-oriented programming skills.
                                                </p>
                                                <ul class="list-none space-y-2 text-text-default">
                                                    <?php renderFileList($pdo, 'Period 1: Object Oriented Programming 2 (Java, OOP, Design Patterns)', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="year2-period-2">
                                                Period 2: Data Processing (Data Analysis, Visualization, Python)
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="year2-period-2" class="period-content hidden mt-3 space-y-4">
                                                <p class="text-sm text-text-muted mb-4 border-b border-border-color pb-2">
                                                    Project Focus: Learn to interpret complex datasets through data manipulation, analysis, and visualization.
                                                </p>
                                                <ul class="list-none space-y-2 text-text-default">
                                                    <?php renderFileList($pdo, 'Period 2: Data Processing (Data Analysis, Visualization, Python)', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <!-- Year 2 Semester 2 -->
                                <div class="bg-secondary/50 rounded-lg shadow-md border border-accent/50">
                                    <button class="semester-toggle flex justify-between items-center w-full p-4 text-left font-extrabold text-lg sm:text-xl text-text-default hover:text-accent transition duration-300" data-target="year2-semester-2">
                                        <span>Semester 2: Quality & Development (Periods 3 & 4)</span>
                                        <svg class="w-6 h-6 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div id="year2-semester-2" class="semester-content hidden p-4 pt-0 space-y-4">

                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="year2-period-3">
                                                Period 3: Software Quality (Testing, QA, Debugging)
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="year2-period-3" class="period-content hidden mt-3 space-y-4">
                                                <p class="text-sm text-text-muted mb-4 border-b border-border-color pb-2">
                                                    Project Focus: Create great user experiences by learning about testing methodologies and quality assurance techniques.
                                                </p>
                                                <ul class="list-none space-y-2 text-text-default">
                                                    <?php renderFileList($pdo, 'Period 3: Software Quality (Testing, QA, Debugging)', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                        <div class="bg-primary p-4 rounded-lg shadow-inner border border-border-color transition-colors duration-300">
                                            <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-text-default hover:text-accent transition" data-target="year2-period-4">
                                                Period 4: App Development (Mobile Apps, UI/UX, Cross-Platform)
                                                <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div id="year2-period-4" class="period-content hidden mt-3 space-y-4">
                                                <p class="text-sm text-text-muted mb-4 border-b border-border-color pb-2">
                                                    Project Focus: Merge innovation and functionality to create intuitive and dynamic mobile applications.
                                                </p>
                                                <ul class="list-none space-y-2 text-text-default">
                                                    <?php renderFileList($pdo, 'Period 4: App Development (Mobile Apps, UI/UX, Cross-Platform)', $user_role, $user_id); ?>
                                                </ul>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <!-- Further Learning -->
                                <div class="bg-primary p-4 rounded-lg shadow-inner border border-accent/70 transition-colors duration-300">
                                    <button class="period-toggle flex justify-between items-center w-full text-left font-bold text-lg sm:text-xl text-accent hover:text-accent-dark transition" data-target="year2-further-learning">
                                        Further Learning & Key Competencies
                                        <svg class="w-5 h-5 accordion-icon text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div id="year2-further-learning" class="period-content hidden mt-3 space-y-3">
                                        <p class="text-text-muted font-medium">Follow-up your internship with modules such as Design Patterns, IT architecture, and IT change management.</p>
                                        <div class="flex flex-wrap gap-2 mt-4">
                                            <span class="skill-badge">Java</span>
                                            <span class="skill-badge">Software Quality</span>
                                            <span class="skill-badge">IT Service Management</span>
                                            <span class="skill-badge">Databases</span>
                                            <span class="skill-badge">Operating Systems</span>
                                            <span class="skill-badge">Data Exchange & Storage</span>
                                            <span class="skill-badge">Algorithms</span>
                                            <span class="skill-badge">Data Structures</span>
                                            <span class="skill-badge">Testing</span>
                                            <span class="skill-badge">JavaScript</span>
                                            <span class="skill-badge">Research</span>
                                            <span class="skill-badge">Dutch</span>
                                            <span class="skill-badge">Professional Skills</span>
                                            <span class="skill-badge">Organizational Processes</span>
                                            <span class="skill-badge">App Development</span>
                                            <span class="skill-badge">Agile Development</span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <!-- Year 2 End -->

                    <!-- YEAR 3 (Placeholder) -->
                    <div class="bg-primary rounded-xl shadow-accent border border-accent/30 transition-colors duration-300">
                        <button class="year-toggle flex justify-between items-center w-full p-5 text-left font-extrabold text-xl sm:text-2xl text-accent hover:text-accent-dark transition duration-300" data-target="year-3">
                            <span>Year 3: Advanced Studies (Placeholder)</span>
                            <svg class="w-6 h-6 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="year-3" class="year-content hidden p-5 pt-0">
                            <p class="text-text-muted italic">Content for Year 3 will appear here.</p>
                        </div>
                    </div>

                    <!-- YEAR 4 (Placeholder) -->
                    <div class="bg-primary rounded-xl shadow-accent border border-accent/30 transition-colors duration-300">
                        <button class="year-toggle flex justify-between items-center w-full p-5 text-left font-extrabold text-xl sm:text-2xl text-accent hover:text-accent-dark transition duration-300" data-target="year-4">
                            <span>Year 4: Final Year (Placeholder)</span>
                            <svg class="w-6 h-6 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="year-4" class="year-content hidden p-5 pt-0">
                            <p class="text-text-muted italic">Content for Year 4 will appear here.</p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section id="contact" class="py-20 px-4 sm:px-6 lg:px-8 bg-primary border-t border-accent/20 transition-colors duration-300">
            <div class="container mx-auto max-w-3xl">
                <h2 class="text-3xl sm:text-4xl font-bold text-center mb-4 text-accent">Get In Touch</h2>
                <p class="text-lg text-center text-text-muted mb-12">
                    I am open to discussions about internships, projects, and academic feedback.
                </p>
                <form action="#" method="POST" class="space-y-6 bg-secondary p-8 rounded-xl shadow-2xl border border-border-color transition-colors duration-300">
                    <div>
                        <label for="name" class="block text-sm font-medium text-text-default mb-1">Name</label>
                        <input type="text" id="name" name="name" required
                               class="w-full px-4 py-3 bg-primary border border-border-color rounded-lg focus:ring-accent focus:border-accent outline-none transition duration-200 text-text-default">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-text-default mb-1">Email</label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-4 py-3 bg-primary border border-border-color rounded-lg focus:ring-accent focus:border-accent outline-none transition duration-200 text-text-default">
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-text-default mb-1">Message</label>
                        <textarea id="message" name="message" rows="5" required
                                  class="w-full px-4 py-3 bg-primary border border-border-color rounded-lg focus:ring-accent focus:border-accent outline-none transition duration-200 text-text-default"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="w-full px-6 py-3 bg-accent text-primary font-semibold rounded-lg shadow-md hover:bg-accent-dark transition duration-300 transform hover:scale-[1.01]">
                            Send Message
                        </button>
                    </div>
                    <div id="status-message" class="hidden p-3 rounded-lg text-center text-sm font-medium"></div>
                </form>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-secondary border-t border-border-color mt-8 py-8 px-4 transition-colors duration-300">
        <div class="container mx-auto text-center text-text-muted text-sm">
            <p>&copy; 2025 MICHAEL OBENG BOATENG Academic Portfolio. Developed with dedication at NHL Stenden.</p>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>