<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// --- "OTAK" BAHASA ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Reset Bahasa / Link Bahasa
$defaultLang = 'id';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];
    $query_params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }
    unset($query_params['lang']);
    $queryStringParts = array();
    foreach ($query_params as $key => $value) {
        $queryStringParts[] = urlencode($key) . '=' . urlencode($value);
    }
    $queryString = implode('&', $queryStringParts);
    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');
    ob_end_clean(); 
    header("Location: " . $redirectUrl);
    exit;
}

$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;

// 2. Locale PHP
if ($currentLang == 'id') {
    setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian_Indonesia.1252', 'Indonesian');
} elseif ($currentLang == 'cn') {
    setlocale(LC_TIME, 'zh_CN.UTF-8', 'zh_CN', 'Chinese_China.936', 'Chinese');
} else {
    setlocale(LC_TIME, 'en_US.UTF-8', 'en_US', 'English_United States.1252', 'English');
}

// 3. Muat Kamus Bahasa
$langFile = __DIR__ . '/lang/' . $currentLang . '.php'; 
if (file_exists($langFile) && is_readable($langFile)) {
    $lang = include $langFile;
} else {
    $defaultFile = __DIR__ . '/lang/' . $defaultLang . '.php';
    $lang = (file_exists($defaultFile) && is_readable($defaultFile)) ? include $defaultFile : array();
}

// 4. Fungsi Helper Translate
if (!function_exists('__')) {
    function __($key) {
        global $lang;
        return (is_array($lang) && isset($lang[$key])) ? $lang[$key] : $key;
    }
}
// --- AKHIR OTAK BAHASA ---


// --- LOGIKA UTAMA HALAMAN ---
$db = Database::getInstance()->getConnection();
$subjectId = isset($_GET['id']) ? $_GET['id'] : null;
$isEditing = $subjectId !== null;
$subject = null;
$error_message = ''; // Variabel penampung error

$pageTitleKey = $isEditing ? 'subject_form_edit_title' : 'subject_form_add_title';

// Jika Edit, Ambil Data Lama
if ($isEditing) {
    $stmt = $db->prepare("SELECT * FROM subjects WHERE id = :id");
    $stmt->execute([':id' => $subjectId]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subject) {
        ob_end_clean();
        header("Location: admin-dashboard.php?page=subjects");
        exit;
    }
}

// HANDLE FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Ambil Input Mentah & Trim Spasi
    $nameRaw = isset($_POST['name']) ? trim($_POST['name']) : '';

    // --- VALIDASI KERAS (DENGAN TRANSLATE) ---
    if (empty($nameRaw)) {
        $error_message = __('subject_error_name');
    } 
    // Cek apakah mengandung karakter SELAIN Huruf, Angka, dan Spasi
    elseif (!preg_match("/^[a-zA-Z0-9\s]+$/", $nameRaw)) {
        // FIX: Gunakan kunci terjemahan
        $error_message = __('subject_error_invalid_chars');
    } 
    else {
        // Jika lolos validasi karakter, baru format teksnya
        $name = ucwords(strtolower($nameRaw));

        try {
            // 2. CEK DUPLIKASI NAMA
            $checkSql = "SELECT COUNT(*) FROM subjects WHERE name = :name";
            $checkParams = [':name' => $name];

            if ($isEditing) {
                $checkSql .= " AND id != :id";
                $checkParams[':id'] = $subjectId;
            }

            $stmtCheck = $db->prepare($checkSql);
            $stmtCheck->execute($checkParams);
            $count = $stmtCheck->fetchColumn();

            if ($count > 0) {
                // FIX: Gunakan kunci terjemahan + sprintf untuk memasukkan nama mapel
                $error_message = sprintf(__('subject_error_duplicate'), $name);
            } else {
                // 3. Simpan ke Database
                if ($isEditing) {
                    $sql = "UPDATE subjects SET name = :name WHERE id = :id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([':name' => $name, ':id' => $subjectId]);
                    // FIX: Translate pesan sukses
                    $_SESSION['flash_message'] = __('subject_success_update');
                } else {
                    $sql = "INSERT INTO subjects (name) VALUES (:name)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([':name' => $name]);
                    // FIX: Translate pesan sukses
                    $_SESSION['flash_message'] = __('subject_success_add');
                }

                // Redirect Bersih
                ob_end_clean();
                header("Location: admin-dashboard.php?page=subjects");
                exit;
            }

        } catch (PDOException $e) {
            // FIX: Translate pesan error DB
            $error_message = sprintf(__('subject_db_error'), $e->getMessage());
        }
    }
}
?>

<?php
// --- Link Ganti Bahasa UI ---
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) { parse_str($url_parts['query'], $query_params); }

$queryParams_id = $query_params; $queryParams_id['lang'] = 'id'; $url_id = $path . '?' . http_build_query($queryParams_id);
$queryParams_en = $query_params; $queryParams_en['lang'] = 'en'; $url_en = $path . '?' . http_build_query($queryParams_en);
$queryParams_cn = $query_params; $queryParams_cn['lang'] = 'cn'; $url_cn = $path . '?' . http_build_query($queryParams_cn);
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
    <a href="<?php echo $url_cn; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'cn' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="Chinese">
        <img src="https://flagcdn.com/w40/cn.png" srcset="https://flagcdn.com/w80/cn.png 2x" width="20" height="15" alt="China" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">CN</span>
    </a>
</div>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __($pageTitleKey); ?></h1>
        <a href="admin-dashboard.php?page=subjects" class="text-orange-600 hover:text-orange-700 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
             <?php echo __('subject_form_back_link'); ?>
        </a>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm relative" role="alert">
            <strong class="font-bold">
                <?php 
                // Translate kata "Gagal!" atau "Perhatian!" secara manual atau biarkan ikon
                echo ($currentLang == 'en') ? 'Attention!' : (($currentLang == 'cn') ? '注意！' : 'Perhatian!'); 
                ?>
            </strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <form method="POST">
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('subject_form_label_name'); ?></label>
                    <div class="relative">
                        <i data-lucide="book" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars(isset($_POST['name']) ? $_POST['name'] : (isset($subject['name']) ? $subject['name'] : '')); ?>" 
                               required 
                               class="pl-10 contact-input <?php echo !empty($error_message) ? 'border-red-500 focus:ring-red-500' : ''; ?>" 
                               placeholder="<?php echo __('subject_form_placeholder_name'); ?>"
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('subject_error_name')); ?>')"
                               oninput="this.setCustomValidity(''); 
                                        /* Live Validation di Browser: Tolak simbol */
                                        this.value = this.value.replace(/[^a-zA-Z0-9\s]/g, '');">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php 
                        // Pesan bantuan kecil di bawah input (Translate manual disini atau tambah key baru)
                        if($currentLang == 'en') echo "Only letters, numbers, and spaces allowed.";
                        elseif($currentLang == 'cn') echo "仅允许字母、数字和空格。";
                        else echo "Hanya huruf, angka, dan spasi. Simbol dilarang.";
                        ?>
                    </p>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end gap-4">
                 <a href="admin-dashboard.php?page=subjects" class="px-6 py-2 rounded-lg text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"><?php echo __('subject_form_button_cancel'); ?></a>
                <button type="submit" class="btn-gradient text-white font-semibold px-6 py-2 rounded-lg">
                    <?php echo $isEditing ? __('subject_form_button_update') : __('subject_form_button_create'); ?>
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
// --- AKHIRI OUTPUT BUFFER ---
ob_end_flush(); 
?>