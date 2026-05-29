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

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];
    try {
        // 1. Get image path BEFORE deleting from DB
        $stmt_select = $db->prepare("SELECT image_path FROM homepage_banners WHERE id = ?");
        $stmt_select->execute([$id_to_delete]);
        $banner = $stmt_select->fetch();

        if ($banner) {
            // 2. Delete from DB
            $stmt_delete = $db->prepare("DELETE FROM homepage_banners WHERE id = ?");
            $stmt_delete->execute([$id_to_delete]);

            // 3. Delete physical file
            $file_path = __DIR__ . '/../../' . $banner['image_path'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            // DIUBAH: Gunakan terjemahan untuk pesan sukses
            $_SESSION['flash_message'] = __('banners_flash_deleted');
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    }
    
    // Redirect bersih
    ob_end_clean();
    header("Location: admin-dashboard.php?page=banners");
    exit;
}

// Fetch all banners
$stmt = $db->query("SELECT * FROM homepage_banners ORDER BY display_order ASC, created_at DESC");
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash messages
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
$flash_error = null;
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
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

        <?php if ($flash_error): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg shadow border-l-4 border-red-500 flex items-center gap-2" role="alert">
            <i data-lucide="alert-circle" class="w-5 h-5"></i>
            <p><?php echo $flash_error; ?></p>
        </div>
        <?php endif; ?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('banners_page_title'); ?></h1>
        <a href="admin-dashboard.php?page=banner-form" class="w-full md:w-auto text-center bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center gap-2 transition-colors">
            <i data-lucide="plus-circle" class="w-5 h-5"></i>
            <span><?php echo __('banners_add_new'); ?></span>
        </a>
    </div>

    <div class="bg-white p-4 md:p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left table-auto">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs"><?php echo __('banners_table_image'); ?></th>
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs"><?php echo __('banners_table_title'); ?></th>
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs"><?php echo __('banners_table_alt'); ?></th>
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs"><?php echo __('banners_table_order'); ?></th>
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs"><?php echo __('banners_table_status'); ?></th>
                        <th class="p-4 font-semibold text-gray-600 uppercase text-xs text-right"><?php echo __('banners_table_actions'); ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($banners)): ?>
                        <tr>
                            <td colspan="6" class="p-4 text-center text-gray-500"><?php echo __('banners_empty'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-4">
                                    <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" alt="<?php echo htmlspecialchars($banner['alt_text']); ?>" class="w-32 h-16 object-cover rounded-md border">
                                </td>
                                <td class="p-4 font-medium text-gray-800"><?php echo htmlspecialchars($banner['title']); ?></td>
                                <td class="p-4 text-gray-600 truncate max-w-xs"><?php echo htmlspecialchars($banner['alt_text']); ?></td>
                                <td class="p-4 text-gray-600"><?php echo htmlspecialchars($banner['display_order']); ?></td>
                                <td class="p-4">
                                    <?php if ($banner['is_active']): ?>
                                        <span class="px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700"><?php echo __('banners_status_active'); ?></span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600"><?php echo __('banners_status_inactive'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2 justify-end">
                                        <a href="admin-dashboard.php?page=banner-form&id=<?php echo $banner['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('banners_action_edit'); ?>">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </a>
                                        <a href="admin-dashboard.php?page=banners&action=delete&id=<?php echo $banner['id']; ?>" onclick="return confirm('<?php echo __('banners_delete_confirm'); ?>');" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('banners_action_delete'); ?>">
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

        <div class="md:hidden flex flex-col gap-6">
            <?php if (empty($banners)): ?>
                <div class="text-center text-gray-500 py-8 bg-gray-50 rounded-lg">
                    <p><?php echo __('banners_empty'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($banners as $banner): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                        <div class="relative h-40 w-full bg-gray-100">
                            <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" alt="<?php echo htmlspecialchars($banner['alt_text']); ?>" class="w-full h-full object-cover">
                            
                            <div class="absolute top-2 right-2">
                                <?php if ($banner['is_active']): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-green-500 text-white shadow-sm"><?php echo __('banners_status_active'); ?></span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-gray-500 text-white shadow-sm"><?php echo __('banners_status_inactive'); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="absolute top-2 left-2">
                                <span class="px-2 py-1 text-xs font-bold rounded bg-white/90 text-gray-700 shadow-sm border border-gray-200">
                                    <?php echo __('banners_table_order'); ?>: #<?php echo htmlspecialchars($banner['display_order']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800 mb-1 truncate"><?php echo htmlspecialchars($banner['title']); ?></h3>
                            <div class="text-sm text-gray-500 mb-4">
                                <span class="font-medium text-gray-600"><?php echo __('banners_table_alt'); ?>:</span> <?php echo htmlspecialchars($banner['alt_text'] ? $banner['alt_text'] : '-'); ?>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-100">
                                <a href="admin-dashboard.php?page=banner-form&id=<?php echo $banner['id']; ?>" class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-blue-50 text-blue-700 font-medium hover:bg-blue-100 transition-colors">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                    <span><?php echo __('banners_action_edit'); ?></span>
                                </a>
                                <a href="admin-dashboard.php?page=banners&action=delete&id=<?php echo $banner['id']; ?>" onclick="return confirm('<?php echo __('banners_delete_confirm'); ?>');" class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-red-50 text-red-700 font-medium hover:bg-red-100 transition-colors">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    <span><?php echo __('banners_action_delete'); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>