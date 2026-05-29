<?php
ob_start(); // Mulai output buffering

// --- 1. SESSION & BAHASA ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Logika Ganti Bahasa
$defaultLang = 'id';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {
    $_SESSION['lang'] = $_GET['lang'];
    
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];
    $query_params = array();
    if (isset($url_parts['query'])) { parse_str($url_parts['query'], $query_params); }
    unset($query_params['lang']);
    
    $queryStringParts = array();
    foreach ($query_params as $key => $value) { $queryStringParts[] = urlencode($key) . '=' . urlencode($value); }
    $queryString = implode('&', $queryStringParts);
    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');
    
    ob_end_clean(); 
    header("Location: " . $redirectUrl);
    die();
}

// Set Bahasa Aktif
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;
if ($currentLang == 'id') { setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian_Indonesia.1252', 'Indonesian'); } 
elseif ($currentLang == 'cn') { setlocale(LC_TIME, 'zh_CN.UTF-8', 'zh_CN', 'Chinese_China.936', 'Chinese'); } 
else { setlocale(LC_TIME, 'en_US.UTF-8', 'en_US', 'English_United States.1252', 'English'); }

// Muat Kamus Bahasa
$langFile = __DIR__ . '/src/pages/lang/' . $currentLang . '.php';
if (file_exists($langFile)) { $lang = include $langFile; } else { $lang = []; }
function __($key) { global $lang; return (isset($lang[$key]) ? $lang[$key] : $key); }


// --- 2. AUTHENTICATION (BAGIAN PENTING) ---
require_once 'src/includes/auth.php';
require_once 'src/includes/functions.php';

// Cek apakah user adalah STUDENT
requireRole('student'); 

$user = getCurrentUser();
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Mapping Halaman Konten
$page_paths = [
    'dashboard' => 'src/pages/student-dashboard-content.php',
    'schedule' => 'src/pages/student-schedule.php',
    'exams' => 'src/pages/student-exams.php',
    'profile' => 'src/pages/student-profile.php',
];
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Bimbel Let's Shine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>
<body class="bg-gray-50 font-['Poppins']">
    <div class="flex h-screen">
        <div class="hidden lg:block w-64 flex-shrink-0">
            <?php include 'src/components/student-sidebar.php'; ?>
        </div>
        
        <div class="flex-1 flex flex-col overflow-hidden">
            <main class="flex-1 overflow-x-hidden overflow-y-auto">
                <div class="container mx-auto px-6 py-8 pb-24 lg:pb-8">
                    <?php
                    // Load halaman sesuai parameter ?page=...
                    if (isset($page_paths[$page]) && file_exists($page_paths[$page])) {
                        include $page_paths[$page];
                    } else {
                        echo "<h1 class='text-2xl font-bold'>Halaman tidak ditemukan</h1>";
                        echo "<p>Konten untuk halaman '" . htmlspecialchars(ucfirst($page)) . "' tidak ditemukan.</p>";
                    }
                    ?>
                </div>
            </main>
        </div>
    </div>
    
    <div class="lg:hidden">
        <?php include 'src/components/student-mobile-nav.php'; ?>
    </div>
    
    <script>lucide.createIcons();</script>
</body>
</html>
<?php ob_end_flush(); ?>