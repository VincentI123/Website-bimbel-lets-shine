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
$banner_id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $banner_id !== null;
$banner = null;
$error_message = '';
$upload_dir = 'assets/img/banners/';
$upload_path = __DIR__ . '/../../' . $upload_dir; // Path absolut untuk PHP

// Ambil data jika edit
if ($is_edit) {
    $stmt = $db->prepare("SELECT * FROM homepage_banners WHERE id = ?");
    $stmt->execute([$banner_id]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitasi Input (Versi PHP 5.6 compatible)
    $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $alt_text = trim(isset($_POST['alt_text']) ? $_POST['alt_text'] : '');
    $display_order = trim(isset($_POST['display_order']) ? $_POST['display_order'] : '0');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $current_image_path = isset($_POST['current_image_path']) ? $_POST['current_image_path'] : '';
    $new_image_path = $current_image_path;

    // 2. Validasi Dasar
    if (empty($title)) {
        $error_message = __('banner_error_title');
    } elseif (empty($display_order) && $display_order !== '0') {
        $error_message = __('banner_error_order');
    } else {
        
        // 3. Validasi Upload Gambar
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['image_file']['tmp_name'];
            $file_name = basename($_FILES['image_file']['name']);
            
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp'); // PHP 5.6 array syntax

            if (in_array($file_ext, $allowed_ext)) {
                // Buat nama unik & aman
                $new_file_name = 'banner_' . time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $file_name);
                $target_file = $upload_path . $new_file_name;

                if (move_uploaded_file($file_tmp, $target_file)) {
                    $new_image_path = $upload_dir . $new_file_name;
                    
                    // Hapus gambar lama jika diganti
                    if ($is_edit && !empty($current_image_path) && $current_image_path != $new_image_path) {
                        $old_file_path = __DIR__ . '/../../' . $current_image_path;
                        if (file_exists($old_file_path)) { @unlink($old_file_path); }
                    }
                } else {
                    $error_message = __('banner_error_upload_failed');
                }
            } else {
                $error_message = __('banner_error_invalid_type');
            }
        } elseif (!$is_edit) {
            // Jika Tambah Baru, gambar wajib ada
            $error_message = __('banner_error_image_required');
        }

        // 4. Validasi Batas Banner Aktif (Maks 5)
        if (empty($error_message) && $is_active == 1) {
            try {
                $sql_count = "SELECT COUNT(*) FROM homepage_banners WHERE is_active = 1";
                $params_count = array(); // PHP 5.6 syntax

                if ($is_edit) {
                    $sql_count .= " AND id != ?";
                    $params_count[] = $banner_id;
                }

                $stmt_count = $db->prepare($sql_count);
                $stmt_count->execute($params_count);
                $active_banner_count = $stmt_count->fetchColumn();

                if ($active_banner_count >= 5) {
                    $error_message = __('banner_error_limit_reached');
                }
                
            } catch (PDOException $e) {
                $error_message = sprintf(__('banner_error_db'), $e->getMessage());
            }
        }

        // 5. Simpan Data jika tidak ada error
        // 5. Simpan Data jika tidak ada error
        if (empty($error_message)) {
            try {
                if ($is_edit) {
                    // Update Banner
                    $sql = "UPDATE homepage_banners SET title = ?, alt_text = ?, image_path = ?, display_order = ?, is_active = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array($title, $alt_text, $new_image_path, $display_order, $is_active, $banner_id));
                    
                    // [PENTING] Simpan pesan sukses UPDATE
                    $_SESSION['flash_message'] = __('banner_success_update');
                } else {
                    // Insert Banner Baru
                    $sql = "INSERT INTO homepage_banners (title, alt_text, image_path, display_order, is_active) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array($title, $alt_text, $new_image_path, $display_order, $is_active));
                    
                    // [PENTING] Simpan pesan sukses TAMBAH
                    $_SESSION['flash_message'] = __('banner_success_add');
                }
                
                // Bersihkan buffer dan Redirect
                ob_end_clean();
                header("Location: admin-dashboard.php?page=banners");
                exit;

            } catch (PDOException $e) {
                $error_message = sprintf(__('banner_error_db'), $e->getMessage());
            }
        }
    }
}

// --- Judul Halaman Dinamis ---
$pageTitleKey = $is_edit ? 'banner_form_edit_title' : 'banner_form_add_title';
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
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __($pageTitleKey); ?></h1>
        <a href="admin-dashboard.php?page=banners" class="text-orange-600 hover:text-orange-700 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <?php echo __('banner_form_back_link'); ?>
        </a>
    </div>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-8 rounded-2xl shadow-lg shadow-orange-900/5">
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            
            <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars(isset($banner['image_path']) ? $banner['image_path'] : ''); ?>">

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('banner_form_label_title'); ?></label>
                <div class="relative">
                    <input type="text" id="title" name="title" 
                           value="<?php echo htmlspecialchars(isset($banner['title']) ? $banner['title'] : ''); ?>" 
                           required 
                           class="contact-input" 
                           placeholder="<?php echo __('banner_form_placeholder_title'); ?>"
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('banner_error_title')); ?>')"
                           oninput="this.setCustomValidity('')">
                </div>
            </div>

            <div>
                <label for="alt_text" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('banner_form_label_alt_text'); ?></label>
                <div class="relative">
                    <input type="text" id="alt_text" name="alt_text" 
                           value="<?php echo htmlspecialchars(isset($banner['alt_text']) ? $banner['alt_text'] : ''); ?>" 
                           class="contact-input" 
                           placeholder="<?php echo __('banner_form_placeholder_alt_text'); ?>">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('banner_form_label_image'); ?></label>
                
                <div class="flex items-center gap-3">
                    <input type="file" id="image_file" name="image_file" 
                           accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                           class="hidden"
                           onchange="document.getElementById('file-name-display').textContent = this.files[0] ? this.files[0].name : '<?php echo __('banner_form_no_file_selected'); ?>'"
                           <?php if (!$is_edit): ?>
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('banner_error_image')); ?>')"
                               oninput="this.setCustomValidity('')"
                           <?php endif; ?>>
                    
                    <label for="image_file" class="px-4 py-2 bg-orange-50 text-orange-600 rounded-lg cursor-pointer font-semibold hover:bg-orange-100 border border-orange-200 transition-colors text-sm">
                        <?php echo __('banner_form_choose_file'); ?>
                    </label>
                    
                    <span id="file-name-display" class="text-sm text-gray-500 italic truncate max-w-xs">
                        <?php echo __('banner_form_no_file_selected'); ?>
                    </span>
                </div>
                
                <?php if ($is_edit && !empty($banner['image_path'])): ?>
                    <div class="mt-3 p-2 border rounded-lg bg-gray-50 inline-block">
                        <p class="text-xs text-gray-500 mb-1"><?php echo __('banner_form_image_current'); ?></p>
                        <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" class="h-24 w-auto rounded border shadow-sm">
                    </div>
                <?php endif; ?>
                <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                    <i data-lucide="info" class="w-3 h-3"></i> <?php echo __('banner_form_image_note'); ?>
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="display_order" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('banner_form_label_order'); ?></label>
                    <div class="relative">
                        <input type="number" id="display_order" name="display_order" 
                               value="<?php echo htmlspecialchars(isset($banner['display_order']) ? $banner['display_order'] : '0'); ?>" 
                               required 
                               class="contact-input" 
                               min="0"
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('banner_error_order')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>

                <div class="flex items-center pt-6">
                    <div class="flex items-center h-5">
                        <input type="checkbox" id="is_active" name="is_active" class="w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500" <?php echo ($is_edit ? ($banner['is_active'] ? 'checked' : '') : 'checked'); ?>>
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="is_active" class="font-medium text-gray-700"><?php echo __('banner_form_label_active'); ?></label>
                        
                        <p class="text-gray-500 text-xs"><?php echo __('banner_form_help_active'); ?></p>
                    
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4 pt-4 border-t border-gray-100">
                 <a href="admin-dashboard.php?page=banners" class="px-6 py-2 rounded-lg text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"><?php echo __('banner_form_button_cancel'); ?></a>
                <button type="submit" class="btn-gradient text-white font-semibold px-6 py-2 rounded-lg flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <?php echo $is_edit ? __('banner_form_button_update') : __('banner_form_button_create'); ?>
                </button>
            </div>
        </form>
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