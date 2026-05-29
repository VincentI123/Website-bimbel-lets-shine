<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Mulai menangkap output

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
if (!function_exists('__')) { 
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

// Ambil semua slot waktu, diurutkan berdasarkan display_order
$stmt = $db->query("SELECT * FROM time_slots ORDER BY display_order ASC");
$time_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('times_page_title'); ?></h1>
        <a href="admin-dashboard.php?page=time-form" class="w-full md:w-auto text-center bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center gap-2 transition-colors">
            <i data-lucide="plus-circle" class="w-5 h-5"></i>
            <span><?php echo __('times_add_new'); ?></span>
        </a>
    </div>

    <div class="bg-white p-4 md:p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left table-auto">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs tracking-wider"><?php echo __('times_table_order'); ?></th>
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs tracking-wider"><?php echo __('times_table_label'); ?></th>
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs tracking-wider"><?php echo __('times_table_value'); ?></th>
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs tracking-wider"><?php echo __('time_table_days'); ?></th> 
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs tracking-wider text-right"><?php echo __('times_table_actions'); ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($time_slots)): ?>
                        <tr>
                            <td colspan="4" class="p-4 text-center text-gray-500"><?php echo __('times_table_empty'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($time_slots as $slot): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-4 font-medium text-gray-800 w-24"><?php echo htmlspecialchars($slot['display_order']); ?></td>
                                <td class="p-4 font-medium text-gray-800"><?php echo htmlspecialchars($slot['time_label']); ?></td>
                                <td class="p-4 font-mono text-gray-600"><?php echo htmlspecialchars($slot['time_value']); ?></td>
                                <td class="p-4">
            <?php 
            if (empty($slot['specific_days'])) {
                echo '<span class="text-red-500 text-xs italic">' . __('time_warning_no_days') . '</span>';            } else {
                $days = explode(',', $slot['specific_days']);
                echo '<div class="flex flex-wrap gap-1">';
                foreach($days as $d) {
                    // Gunakan fungsi __() untuk menerjemahkan nama hari dari DB (Monday -> Senin/周一)
                    echo '<span class="bg-orange-50 text-orange-700 px-2 py-1 rounded text-xs font-medium border border-orange-100">'.__($d).'</span>';
                }
                echo '</div>';
            }
            ?>
        </td>
                                <td class="p-4">
                                    <div class="flex gap-2 justify-end">
                                        <a href="admin-dashboard.php?page=time-form&id=<?php echo $slot['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('subjects_action_edit'); ?>">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </a>
                                        <a href="admin-dashboard.php?page=times&action=delete&id=<?php echo $slot['id']; ?>" onclick="return confirm('<?php echo __('times_delete_confirm'); ?>');" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('subjects_action_delete'); ?>">
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

        <div class="md:hidden flex flex-col gap-4">
            <?php if (empty($time_slots)): ?>
                <div class="text-center text-gray-500 py-8 bg-gray-50 rounded-lg">
                    <?php echo __('times_table_empty'); ?>
                </div>
            <?php else: ?>
                <?php foreach ($time_slots as $slot): ?>
                    <div class="border rounded-xl p-4 bg-white shadow-sm hover:shadow-md transition-shadow ring-1 ring-gray-100">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                        #<?php echo htmlspecialchars($slot['display_order']); ?>
                                    </span>
                                    <h3 class="font-bold text-gray-800 text-lg">
                                        <?php echo htmlspecialchars($slot['time_label']); ?>
                                    </h3>
                                </div>
                                <div class="flex items-center gap-2 text-gray-500 text-sm mt-2">
                                    <i data-lucide="code" class="w-4 h-4"></i>
                                    <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-600">
                                        <?php echo htmlspecialchars($slot['time_value']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-t border-gray-100 flex justify-end gap-3">
                            <a href="admin-dashboard.php?page=time-form&id=<?php echo $slot['id']; ?>" class="flex-1 flex items-center justify-center gap-2 px-4 py-2 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg text-sm font-medium transition-colors">
                                <i data-lucide="edit" class="w-4 h-4"></i>
                                <?php echo __('subjects_action_edit'); ?>
                            </a>
                            <a href="admin-dashboard.php?page=times&action=delete&id=<?php echo $slot['id']; ?>" onclick="return confirm('<?php echo __('times_delete_confirm'); ?>');" class="flex-1 flex items-center justify-center gap-2 px-4 py-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg text-sm font-medium transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                <?php echo __('subjects_action_delete'); ?>
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