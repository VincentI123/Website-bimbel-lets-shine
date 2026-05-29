<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); 
// --- AKHIR BAGIAN BARU ---


// --- "OTAK" BAHASA (VERSI UNIVERSAL REQUEST_URI + MANUAL QUERY + OB CLEAN) ---

// 1. Selalu mulai session di paling atas
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Tentukan bahasa default
$defaultLang = 'id';

// 3. Cek jika pengguna MENGKLIK tombol ganti bahasa (VERSI FINAL)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {

    $_SESSION['lang'] = $_GET['lang'];

    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];

    $query_params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }

    unset($query_params['lang']);

    // --- Perbaikan Manual Query String ---
    $queryStringParts = array();
    foreach ($query_params as $key => $value) {
        $queryStringParts[] = urlencode($key) . '=' . urlencode($value);
    }
    $queryString = implode('&', $queryStringParts);
    // --- Akhir Perbaikan ---

    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');

    // --- DIUBAH: Hentikan buffer dan lakukan redirect ---
    ob_end_clean(); // Hapus output yang sudah ditangkap (jika ada)
    header("Location: " . $redirectUrl);
    die('Redirecting...'); // Hentikan script setelah header
    // --- AKHIR PERUBAHAN ---
}

// 4. Tentukan bahasa yang akan digunakan
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;


// 5. Mengatur "Locale" PHP untuk Tanggal
if ($currentLang == 'id') {
    setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian_Indonesia.1252', 'Indonesian');
} elseif ($currentLang == 'cn') {
    setlocale(LC_TIME, 'zh_CN.UTF-8', 'zh_CN', 'Chinese_China.936', 'Chinese');
} else {
    setlocale(LC_TIME, 'en_US.UTF-8', 'en_US', 'English_United States.1252', 'English');
}

// 6. Muat file "kamus"
$langFile = __DIR__ . '/lang/' . $currentLang . '.php'; 
if (file_exists($langFile) && is_readable($langFile)) {
    $lang = include $langFile;
} else {
    $defaultFile = __DIR__ . '/lang/' . $defaultLang . '.php';
    $lang = (file_exists($defaultFile) && is_readable($defaultFile)) ? include $defaultFile : array();
}

// 7. Fungsi helper untuk terjemahan
if (!function_exists('__')) { // <--- TAMBAHKAN BARIS INI
    function __($key) {
        global $lang;
        if (is_array($lang) && isset($lang[$key])) {
            return $lang[$key];
        } else {
            return $key;
        }
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI HALAMAN ANDA DIMULAI DI SINI ---
// user data is available from admin-dashboard.php
// global $user;

// DIUBAH: Menggunakan kunci terjemahan untuk label
$allNavItems = [
    'dashboard'   => ['icon' => 'layout-dashboard', 'label' => __('menu_nav_dashboard')],
    'users'       => ['icon' => 'users',            'label' => __('quick_manage_users')],
    'teachers'    => ['icon' => 'award',            'label' => __('quick_manage_teachers')],
    'subjects'    => ['icon' => 'book',             'label' => __('quick_manage_subjects')],
    'schedules'   => ['icon' => 'calendar-plus',    'label' => __('quick_manage_schedules')],
    'enrollments' => ['icon' => 'user-plus',        'label' => __('quick_enroll_students')],
    'approvals'   => ['icon' => 'user-check',       'label' => __('quick_approvals')],
    'exams'       => ['icon' => 'file-text',        'label' => __('quick_manage_exams')],
    'banners'     => ['icon' => 'image',            'label' => __('quick_manage_banners')],
];

?>

<?php
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}

$queryParams_id = $query_params;
$queryParams_id['lang'] = 'id';
$url_id = $path . '?' . http_build_query($queryParams_id);

$queryParams_en = $query_params;
$queryParams_en['lang'] = 'en';
$url_en = $path . '?' . http_build_query($queryParams_en);

$queryParams_cn = $query_params;
$queryParams_cn['lang'] = 'cn';
$url_cn = $path . '?' . http_build_query($queryParams_cn);
?>

<div class="flex space-x-4 mb-4 justify-end">
    <a href="<?php echo $url_id; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'id' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="Bahasa Indonesia">
        <img src="https://flagcdn.com/w40/id.png" srcset="https://flagcdn.com/w80/id.png 2x" width="20" height="15" alt="Indonesia" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">ID</span>
    </a>
    <a href="<?php echo $url_en; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'en' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="English">
        <img src="https://flagcdn.com/w40/gb.png" srcset="https://flagcdn.com/w80/gb.png 2x" width="20" height="15" alt="United Kingdom" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">EN</span>
    </a>
    <a href="<?php echo $url_cn; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'cn' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="中文">
        <img src="https://flagcdn.com/w40/cn.png" srcset="https://flagcdn.com/w80/cn.png 2x" width="20" height="15" alt="China" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">CN</span>
    </a>
</div>
<h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo __('menu_page_title'); ?></h1>

<div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">

    <div class="flex items-center gap-4 mb-8 pb-4 border-b border-gray-100">
        <div class="w-16 h-16 rounded-full bg-orange-100 flex items-center justify-center">
            <i data-lucide="user-cog" class="w-8 h-8 text-orange-500"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></h2>
            <p class="text-gray-500 font-medium"><?php echo __('menu_role_admin'); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($allNavItems as $pageId => $item): ?>
            <a href="admin-dashboard.php?page=<?php echo $pageId; ?>" class="flex items-center justify-between w-full p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                <div class="flex items-center gap-4">
                    <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5 text-gray-500"></i>
                    <span class="font-semibold text-gray-700"><?php echo $item['label']; ?></span>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400"></i>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="mt-8 pt-6 border-t border-gray-100">
        <a href="logout.php" class="flex items-center justify-center w-full p-4 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
            <div class="flex items-center gap-3">
                <i data-lucide="log-out" class="w-5 h-5 text-red-500"></i>
                <span class="font-semibold text-red-600"><?php echo __('profile_action_sign_out'); ?></span>
            </div>
        </a>
    </div>
</div>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>