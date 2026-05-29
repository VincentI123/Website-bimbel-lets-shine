<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Mulai menangkap output
// --- AKHIR BAGIAN BARU ---


// --- "OTAK" BAHASA (VERSI UNIVERSAL REQUEST_URI + MANUAL QUERY + OB CLEAN) ---

// 1. Selalu mulai session di paling atas
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Tentukan bahasa default
$defaultLang = 'id';

// 3. Cek jika pengguna MENGKLIK tombol ganti bahasa
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {

    $_SESSION['lang'] = $_GET['lang'];

    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];

    $query_params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }

    unset($query_params['lang']);

    // Bangun ulang URL
    $queryStringParts = array();
    foreach ($query_params as $key => $value) {
        $queryStringParts[] = urlencode($key) . '=' . urlencode($value);
    }
    $queryString = implode('&', $queryStringParts);

    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');

    // Hentikan buffer dan lakukan redirect
    ob_end_clean(); 
    header("Location: " . $redirectUrl);
    die('Redirecting...');
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
$db = Database::getInstance()->getConnection();

// Fetch all subjects
$stmt = $db->query("SELECT * FROM subjects ORDER BY name");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// [TAMBAHKAN INI] Ambil & Hapus Pesan dari Session
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Hapus agar tidak muncul di halaman lain
}
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

<?php if ($flash_message): ?>
<div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg shadow border-l-4 border-green-500 flex items-center gap-2" role="alert">
    <i data-lucide="check-circle" class="w-5 h-5"></i>
    <p><?php echo $flash_message; ?></p>
</div>
<?php endif; ?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('subjects_page_title'); ?></h1>
        <a href="admin-dashboard.php?page=subject-form" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-2 rounded-lg flex items-center gap-2 transition-colors">
            <i data-lucide="plus" class="w-5 h-5"></i>
            <?php echo __('subjects_add_new'); ?>
        </a>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5 overflow-x-auto max-w-3xl mx-auto">
    <div class="hidden md:block">
         <table class="w-full text-left table-auto">
            <thead>
                <tr class="border-b-2 border-gray-100">
                    <th class="p-4 font-semibold text-gray-600 w-full"><?php echo __('subjects_table_name'); ?></th>
                    <th class="p-4 font-semibold text-gray-600 text-right w-auto whitespace-nowrap"><?php echo __('subjects_table_actions'); ?></th>
                </tr>
            </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="3" class="p-4 text-center text-gray-500"><?php echo __('subjects_table_empty'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $subject): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="p-4 font-medium text-gray-800"><?php echo htmlspecialchars($subject['name']); ?></td>
                                <td class="p-4">
                                    <div class="flex gap-2 justify-end">
                                        <a href="admin-dashboard.php?page=subject-form&id=<?php echo $subject['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('subjects_action_edit'); ?>">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </a>
                                        <a href="admin-dashboard.php?page=subjects&action=delete&id=<?php echo $subject['id']; ?>" onclick="return confirm('<?php echo __('subjects_delete_confirm'); ?>');" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('subjects_action_delete'); ?>">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 gap-4 md:hidden">
            <?php if (empty($subjects)): ?>
                <div class="text-center text-gray-500 py-4">
                    <?php echo __('subjects_table_empty'); ?>
                </div>
            <?php else: ?>
                <?php foreach ($subjects as $subject): ?>
                <div class="bg-white p-4 rounded-lg shadow ring-1 ring-gray-200/50">
                    <div class="flex justify-between items-start">
                        <div class="flex-grow">
                        <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($subject['name']); ?></p>
                        </div>
                    </div>
                    <div class="border-t my-4"></div>
                    <div class="flex justify-end gap-2">
                        <a href="admin-dashboard.php?page=subject-form&id=<?php echo $subject['id']; ?>" class="flex-1 sm:flex-none justify-center p-2 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg inline-flex items-center gap-2 text-sm" title="<?php echo __('subjects_action_edit'); ?>">
                            <i data-lucide="edit" class="w-4 h-4"></i> <span><?php echo __('subjects_action_edit'); ?></span>
                        </a>
                        <a href="admin-dashboard.php?page=subjects&action=delete&id=<?php echo $subject['id']; ?>" onclick="return confirm('<?php echo __('subjects_delete_confirm'); ?>');" class="flex-1 sm:flex-none justify-center p-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg inline-flex items-center gap-2 text-sm" title="<?php echo __('subjects_action_delete'); ?>">
                            <i data-lucide="trash-2" class="w-4 h-4"></i> <span><?php echo __('subjects_action_delete'); ?></span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>