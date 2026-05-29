<?php
ob_start(); 

// --- 1. SESSION & SETUP BAHASA ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Reset bahasa jika tombol diklik
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $query_params = [];
    if (isset($url_parts['query'])) parse_str($url_parts['query'], $query_params);
    unset($query_params['lang']);
    $queryString = http_build_query($query_params);
    $redirectUrl = $url_parts['path'] . ($queryString ? '?' . $queryString : '');
    ob_end_clean();
    header("Location: " . $redirectUrl);
    exit;
}

$defaultLang = 'id';
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;

// Atur Locale Tanggal
if ($currentLang == 'id') { setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian'); } 
elseif ($currentLang == 'cn') { setlocale(LC_TIME, 'zh_CN.UTF-8', 'Chinese'); } 
else { setlocale(LC_TIME, 'en_US.UTF-8', 'English'); }

// --- 2. MUAT FILE BAHASA (VERSI LEBIH KUAT) ---
// Kita definisikan path secara manual agar lebih akurat
$baseDir = __DIR__; // Folder root project
$langFolder = $baseDir . '/src/pages/lang/';
$langFile = $langFolder . $currentLang . '.php';

// Cek apakah file ada
if (file_exists($langFile)) {
    $lang = include $langFile;
} else {
    // JIKA GAGAL: Coba cari file default (id.php)
    $defaultFile = $langFolder . 'id.php';
    if (file_exists($defaultFile)) {
        $lang = include $defaultFile;
    } else {
        // JIKA GAGAL TOTAL: Buat array kosong & Tampilkan Error Kecil
        $lang = [];
        echo "<div style='background:red;color:white;padding:5px;'>Error: Kamus bahasa tidak ditemukan di: $langFolder</div>";
    }
}

// --- 3. FUNGSI PENERJEMAH (WAJIB ADA) ---
if (!function_exists('__')) {
    function __($key) {
        global $lang;
        // Cek apakah kuncinya ada di kamus
        if (is_array($lang) && isset($lang[$key])) {
            return $lang[$key];
        }
        // Jika tidak ada, kembalikan kuncinya agar ketahuan (misal: sidebar_menu_dashboard)
        return $key;
    }
}
// --- AKHIR LOGIKA BAHASA ---


// --- 4. LOGIKA AUTH & ADMIN ---
require_once __DIR__ . '/src/includes/auth.php';
require_once __DIR__ . '/src/includes/functions.php';

// Hanya admin yang dapat mengakses halaman ini
requireRole('admin');

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// --- [BARU] Hitung Pendaftaran Pending untuk Notifikasi ---
$pending_count = 0;
try {
    $stmt_pending = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $pending_count = $stmt_pending->fetchColumn();
} catch (PDOException $e) {
    // Abaikan jika error
}
// --- [AKHIR BARU] ---

// Automatically delete past exams...
// Automatically delete past exams when the exam page is loaded
if (isset($_GET['page']) && $_GET['page'] === 'exams') {
    try {
        $currentDate = date('Y-m-d');
        $stmt = $db->prepare("DELETE FROM exams WHERE exam_date <= ?");
        $stmt->execute([$currentDate]);
    } catch (PDOException $e) {
        // Silently ignore the error
    }
}

// Handle Approve/Deny Actions (Approvals Page)
if (isset($_GET['page']) && $_GET['page'] === 'approvals' && isset($_GET['action']) && isset($_GET['id'])) {
    $userId = $_GET['id'];
    if ($_GET['action'] === 'approve') {
        $stmt = $db->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $stmt->execute([$userId]);
        header("Location: admin-dashboard.php?page=approvals&status=approved");
        exit;
    } elseif ($_GET['action'] === 'deny') {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        header("Location: admin-dashboard.php?page=approvals&status=denied");
        exit;
    }
}

// Handle Delete Exam Action
if (isset($_GET['page']) && $_GET['page'] === 'exams' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $examIdToDelete = $_GET['id'];
    $stmt = $db->prepare("DELETE FROM exams WHERE id = ?");
    $stmt->execute([$examIdToDelete]);
    header("Location: admin-dashboard.php?page=exams");
    exit;
}

// Handle Delete User Action
if (isset($_GET['page']) && $_GET['page'] === 'users' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $userIdToDelete = $_GET['id'];
    if ($userIdToDelete !== $user['id']) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $userIdToDelete]);
        $_SESSION['flash_message'] = __('users_flash_deleted');
    }
    header("Location: admin-dashboard.php?page=users");
    exit;
}

// Handle Delete Subject Action
if (isset($_GET['page']) && $_GET['page'] === 'subjects' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $subjectIdToDelete = $_GET['id'];
    
    $stmt = $db->prepare("DELETE FROM subjects WHERE id = :id");
    $stmt->execute([':id' => $subjectIdToDelete]);
    
    // [BARU] Simpan pesan sukses ke session
    $_SESSION['flash_message'] = __('subjects_status_deleted');
    
    header("Location: admin-dashboard.php?page=subjects");
    exit;
}

// Handle Delete Time Slot Action
if (isset($_GET['page']) && $_GET['page'] === 'times' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $timeSlotIdToDelete = $_GET['id'];
    $stmt = $db->prepare("DELETE FROM time_slots WHERE id = :id");
    $stmt->execute([':id' => $timeSlotIdToDelete]);
    
    // [BARU] Simpan pesan sukses ke session
    $_SESSION['flash_message'] = __('times_flash_deleted');
    
    header("Location: admin-dashboard.php?page=times");
    exit;
}

// Handle Delete Schedule Action
if (isset($_GET['page']) && $_GET['page'] === 'schedules' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $scheduleIdToDelete = $_GET['id'];
        $stmt = $db->prepare("DELETE FROM schedules WHERE id = :id");
        $stmt->execute([':id' => $scheduleIdToDelete]);
        
        // [BARU] Simpan pesan sukses
        $_SESSION['flash_message'] = __('schedule_success_delete');
        
    } catch (PDOException $e) {
        // Silently fail
    }
    $dayParam = isset($_GET['day']) ? '&day=' . urlencode($_GET['day']) : '';
    header("Location: admin-dashboard.php?page=schedules" . $dayParam);
    exit;
}

// Handle Delete Student Action (Enrollments)
if (isset($_GET['page']) && $_GET['page'] === 'enrollments' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $studentIdToDelete = $_GET['id'];
    
    // Hapus data siswa
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'student'");
    $stmt->execute([':id' => $studentIdToDelete]);
    
    // [BARU] Simpan pesan sukses ke session (3 Bahasa)
    $_SESSION['flash_message'] = __('enrollments_status_deleted');
    
    // Redirect kembali ke halaman daftar siswa
    header("Location: admin-dashboard.php?page=enrollments");
    exit;
}

// Handle Delete Teacher Action
if (isset($_GET['page']) && $_GET['page'] === 'teachers' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $teacherIdToDelete = $_GET['id'];
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'teacher'");
    $stmt->execute([':id' => $teacherIdToDelete]);
    $_SESSION['flash_message'] = __('teachers_status_deleted');
    
    header("Location: admin-dashboard.php?page=teachers");
    exit;
}


// Mendapatkan halaman konten dinamis
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
// Whitelist of allowed pages
$allowedPages = [
    'dashboard',
    'users', 'user-form',
    'subjects', 'subject-form',
    'schedules', 'schedules-form',
    'enrollments', 'student-form',
    'teachers', 'teacher-form',
    'approvals',
    'exams', 'exam-form',
    'banners', 'banner-form',
    'times', 'time-form',
    'landing-settings',
    'landing-level'
];

if (in_array($page, $allowedPages)) {
    if ($page === 'dashboard') {
        $contentFile = __DIR__ . '/src/pages/admin-dashboard-content.php';
    } else {
        $contentFile = __DIR__ . '/src/pages/admin-' . $page . '.php';
    }
} else {
    $contentFile = __DIR__ . '/src/pages/admin-dashboard-content.php';
}

?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bimbel Let's Shine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>
<body class="font-['Poppins']">

    <div class="flex h-screen bg-gray-50">
        <?php require_once 'src/components/admin-sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <?php require_once 'src/components/admin-mobile-nav.php'; ?>

            <main class="flex-1 p-6 overflow-y-auto">
                <?php 
                // Memuat konten halaman dinamis
                if (file_exists($contentFile)) {
                    include $contentFile;
                } else {
                    echo "<p>Halaman tidak ditemukan.</p>";
                }
                ?>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
    
    <script>
        (function() {
            const navId = 'sidebar-nav';
            const storageKey = 'sidebarScrollTop';
            
            // 1. Saat halaman dimuat, pulihkan posisi scroll
            document.addEventListener('DOMContentLoaded', () => {
                const sidebarNav = document.getElementById(navId);
                if (sidebarNav) {
                    const savedScrollTop = localStorage.getItem(storageKey);
                    if (savedScrollTop) {
                        sidebarNav.scrollTop = parseInt(savedScrollTop, 10);
                    }
                }
            });

            // 2. Saat link di dalam sidebar diklik, simpan posisi scroll
            document.addEventListener('click', (e) => {
                if (e.target.closest('#' + navId)) {
                    const sidebarNav = document.getElementById(navId);
                    if (sidebarNav) {
                        localStorage.setItem(storageKey, sidebarNav.scrollTop);
                    }
                }
            });
        })();
    </script>
</body>
</html>
<?php
ob_end_flush(); // Kirim semua output buffer di akhir
?>