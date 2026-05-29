<?php
// --- AKTIFKAN ERROR REPORTING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// --- "OTAK" BAHASA (VERSI UNIVERSAL 3 BAHASA) ---

// 1. Selalu mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Tentukan bahasa default
$defaultLang = 'id';

// 3. Cek jika pengguna MENGKLIK tombol ganti bahasa
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {
    
    $_SESSION['lang'] = $_GET['lang'];
    
    // Bersihkan URL dari parameter ?lang=...
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
    
    // Redirect bersih
    ob_end_clean();
    header("Location: " . $redirectUrl);
    exit;
}

// 4. Tentukan bahasa yang akan digunakan
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;

// 5. Mengatur "Locale" PHP
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

// 8. Fungsi Helper Format Tanggal Manual
if (!function_exists('date_format_intl')) {
    function date_format_intl($date_string, $lang) {
        if (empty($date_string)) return '-';
        $timestamp = strtotime($date_string);
        if ($lang == 'cn') {
            return date('Y', $timestamp) . '年' . date('m', $timestamp) . '月' . date('d', $timestamp) . '日';
        } elseif ($lang == 'id') {
            $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
        } else {
            return date('d F Y', $timestamp);
        }
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- LOGIKA PEMROSESAN FORMULIR ---
require_once 'src/config/database.php'; 
$db = Database::getInstance()->getConnection();
$student_id = $user['id'];
$success_message = '';
$error_message = '';

if (isset($_SESSION['flash_success'])) {
    $success_message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1. Handle Update Profil ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        try {
            // ... (Kode sanitasi input $nameInput, $addressInput, dll biarkan saja) ...
            $nameInput = isset($_POST['name']) ? trim($_POST['name']) : '';
            $name = !empty($nameInput) ? ucwords(strtolower($nameInput)) : $user['name'];
            
            // ... (lanjutkan variabel sanitasi lainnya seperti di kode asli Anda) ...
            $addressInput = isset($_POST['address']) ? trim($_POST['address']) : '';
            $address = !empty($addressInput) ? $addressInput : $user['address'];
            
            $phoneInput = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
            $phone_number = !empty($phoneInput) ? $phoneInput : $user['phone_number'];

            $birthPlaceInput = isset($_POST['birth_place']) ? trim($_POST['birth_place']) : '';
            $birth_place = !empty($birthPlaceInput) ? ucwords(strtolower($birthPlaceInput)) : $user['birth_place'];

            $birthDateInput = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : '';
            $birth_date = !empty($birthDateInput) ? $birthDateInput : $user['birth_date'];

            $schoolInput = isset($_POST['school']) ? trim($_POST['school']) : '';
            $school = !empty($schoolInput) ? ucwords(strtolower($schoolInput)) : $user['school'];

            $parentNameInput = isset($_POST['parent_name']) ? trim($_POST['parent_name']) : '';
            $parent_name = !empty($parentNameInput) ? ucwords(strtolower($parentNameInput)) : $user['parent_name'];

            $parentPhoneInput = isset($_POST['parent_phone']) ? trim($_POST['parent_phone']) : '';
            $parent_phone = !empty($parentPhoneInput) ? $parentPhoneInput : $user['parent_phone'];

            // Query SQL
            $sql = "UPDATE users SET 
                        name = :name, address = :address, phone_number = :phone_number,
                        birth_place = :birth_place, birth_date = :birth_date, school = :school,
                        parent_name = :parent_name, parent_phone = :parent_phone
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':address' => $address,
                ':phone_number' => $phone_number,
                ':birth_place' => $birth_place,
                ':birth_date' => $birth_date,
                ':school' => $school,
                ':parent_name' => $parent_name,
                ':parent_phone' => $parent_phone,
                ':id' => $student_id
            ]);
            
            $user = getCurrentUser(); // Refresh data user
            
            // --- [MODIFIKASI DISINI] ---
            // 1. Simpan pesan sukses ke Session
            $_SESSION['flash_success'] = __('profile_success_profile_updated');

            // 2. Redirect ke halaman 'Lihat Data Diri' (view_data)
            ob_end_clean();
            header("Location: student-dashboard.php?page=profile&action=view_data");
            exit();
            // --------------------------

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error_message = __('profile_error_generic');
        }
    }

   // --- 2. Handle Ganti Kata Sandi ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        
        $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6 && strlen($new_password) <= 50) {
                    try {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        // Prepared Statement AMAN
                        $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
                        $stmt->execute([':password' => $hashed_password, ':id' => $student_id]);
                        
                        // --- [MODIFIKASI DISINI] ---
                        // 1. Simpan pesan sukses ke Session
                        $_SESSION['flash_success'] = __('profile_success_password_updated');

                        // 2. Redirect ke halaman 'Lihat Data Diri' (view_data)
                        ob_end_clean();
                        header("Location: student-dashboard.php?page=profile&action=view_data");
                        exit();
                        // --------------------------

                    } catch (PDOException $e) {
                        error_log($e->getMessage());
                        $error_message = __('profile_error_generic');
                    }
                } else {
                    $error_message = __('profile_error_password_length');
                }
            } else {
                $error_message = __('profile_error_password_mismatch');
            }
        } else {
            $error_message = __('profile_error_current_password_wrong');
        }
    }
}

// Tentukan halaman/aksi
$action = isset($_GET['action']) ? $_GET['action'] : 'main'; // 'main', 'view_data', 'edit_profile', 'edit_password'

// Terjemahan Grade
$grade_translations = [
    'Pre-Nursery' => __('student_form_grade_prenursery'),
    'Kindergarten' => __('student_form_grade_kindergarten'),
    'Elementary' => __('student_form_grade_elementary'),
    'Junior High' => __('student_form_grade_juniorhigh'),
    'Senior High' => __('student_form_grade_seniorhigh')
];
$grade_key = isset($user["grade_level"]) ? $user["grade_level"] : null;
if (!empty($grade_key) && isset($grade_translations[$grade_key])) {
    $grade_display = $grade_translations[$grade_key];
} elseif (!empty($grade_key)) {
    $grade_display = htmlspecialchars($grade_key);
} else {
    $grade_display = '-';
}
?>

<?php
// --- Link Bahasa (Mempertahankan Parameter URL) ---
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) { parse_str($url_parts['query'], $query_params); }
unset($query_params['lang']); // Hapus lang dulu

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
    <a href="<?php echo $url_cn; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'cn' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="中文">
        <img src="https://flagcdn.com/w40/cn.png" srcset="https://flagcdn.com/w80/cn.png 2x" width="20" height="15" alt="China" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">CN</span>
    </a>
</div>

<?php if ($success_message): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert">
        <p><?php echo $success_message; ?></p>
    </div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
        <p><?php echo $error_message; ?></p>
    </div>
<?php endif; ?>


<?php if ($action == 'view_data'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('profile_view_data_title'); ?></h1>
        <a href="student-dashboard.php?page=profile" class="text-sm text-orange-600 hover:text-orange-700 flex items-center gap-1">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> <?php echo __('user_form_back_link'); ?>
        </a>
    </div>
    
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg shadow-yellow-900/5">
        <div class="flex justify-end mb-6">
            <a href="student-dashboard.php?page=profile&action=edit_profile" class="btn-gradient text-white font-semibold px-6 py-2 rounded-lg inline-flex items-center gap-2">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
                <?php echo __('profile_edit_title'); ?>
            </a>
        </div>
        
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_name'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['name']); ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('login_label_username'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['username']); ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_phone'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['phone_number'] ?: '-'); ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_birth_place'); ?> & <?php echo __('student_form_label_birth_date'); ?></dt>
                <dd class="text-gray-800 mt-1">
                    <?php echo htmlspecialchars($user['birth_place'] ?: '-'); ?>, 
                    <?php echo date_format_intl($user['birth_date'], $currentLang); ?>
                </dd>
            </div>
            <div class="border-b border-gray-100 py-3 md:col-span-2">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_address'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['address'] ?: '-'); ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_grade'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo $grade_display; ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_school'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['school'] ?: '-'); ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_parent_name'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['parent_name'] ?: '-'); ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_parent_phone'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['parent_phone'] ?: '-'); ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3 md:col-span-2">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_subjects'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['subjects'] ?: '-'); ?></dd>
            </div>
             <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_days'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['available_days'] ?: '-'); ?></dd>
            </div>
            <div class="border-b border-gray-100 py-3">
                <dt class="font-semibold text-gray-600 text-sm"><?php echo __('student_form_label_times'); ?></dt>
                <dd class="text-gray-800 mt-1"><?php echo htmlspecialchars($user['available_times'] ?: '-'); ?></dd>
            </div>
        </dl>
    </div>

<?php elseif ($action == 'edit_profile'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('profile_edit_title'); ?></h1>
        <a href="student-dashboard.php?page=profile&action=view_data" class="text-sm text-orange-600 hover:text-orange-700 flex items-center gap-1">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> <?php echo __('user_form_back_link'); ?>
        </a>
    </div>
    
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg shadow-yellow-900/5">
        <form action="student-dashboard.php?page=profile&action=edit_profile" method="POST" class="space-y-6">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_name'); ?></label>
                    <input type="text" name="name" id="name" class="contact-input" 
                           value="<?php echo htmlspecialchars($user['name']); ?>" 
                           required
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_name')); ?>')"
                           oninput="this.setCustomValidity('')">
                </div>
                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_phone'); ?></label>
                    <input type="tel" 
                           name="phone_number" 
                           id="phone_number" 
                           class="contact-input" 
                           value="<?php echo htmlspecialchars($user['phone_number']); ?>" 
                           maxlength="16"
                           autocomplete="off"
                           required
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_phone')); ?>')"
                           oninput="this.setCustomValidity('');
                               let v = this.value.replace(/\D/g, '').substring(0, 13);
                               let f = '';
                               if (v.length <= 4) { f = v; } 
                               else if (v.length <= 7) { f = v.slice(0, 4) + '-' + v.slice(4); } 
                               else if (v.length <= 10) { f = v.slice(0, 4) + '-' + v.slice(4, 7) + '-' + v.slice(7); } 
                               else { f = v.slice(0, 4) + '-' + v.slice(4, 8) + '-' + v.slice(8); }
                               this.value = f;
                           ">
                    <script>(function(){var el=document.getElementById('phone_number');if(el&&el.value){el.dispatchEvent(new Event('input'));}})();</script>
                </div>
                 <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_address'); ?></label>
                    <textarea name="address" id="address" rows="3" class="contact-input" 
                              required
                              oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_address')); ?>')"
                              oninput="this.setCustomValidity('')"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>
                 <div>
                    <label for="birth_place" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_birth_place'); ?></label>
                    <input type="text" name="birth_place" id="birth_place" class="contact-input" 
                           value="<?php echo htmlspecialchars($user['birth_place']); ?>"
                           required
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_birthplace')); ?>')"
                           oninput="this.setCustomValidity('')">
                </div>
                 <div>
                    <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_birth_date'); ?></label>
                    <input type="date" name="birth_date" id="birth_date" class="contact-input" 
                           value="<?php echo htmlspecialchars($user['birth_date']); ?>"
                           required
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_birthdate')); ?>')"
                           oninput="this.setCustomValidity('')">
                </div>
                <div class="md:col-span-2">
                    <label for="school" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_school'); ?></label>
                    <input type="text" name="school" id="school" class="contact-input" 
                           value="<?php echo htmlspecialchars($user['school']); ?>"
                           required
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_school')); ?>')"
                           oninput="this.setCustomValidity('')">
                </div>
                
                <hr class="md:col-span-2 my-2">
                
                <div>
                    <label for="parent_name" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_parent_name'); ?></label>
                    <input type="text" name="parent_name" id="parent_name" class="contact-input" 
                           value="<?php echo htmlspecialchars($user['parent_name']); ?>"
                           required
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_parent_name')); ?>')"
                           oninput="this.setCustomValidity('')">
                </div>
                <div>
                    <label for="parent_phone" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_parent_phone'); ?></label>
                    <input type="tel" 
                           name="parent_phone" 
                           id="parent_phone" 
                           class="contact-input" 
                           value="<?php echo htmlspecialchars($user['parent_phone']); ?>" 
                           placeholder="08xx-xxxx-xxxx"
                           maxlength="16"
                           autocomplete="off"
                           required
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_parent_phone')); ?>')"
                           oninput="this.setCustomValidity('');
                               let k = this.value.replace(/\D/g, '').substring(0, 13);
                               let l = '';
                               if (k.length <= 4) { l = k; } 
                               else if (k.length <= 7) { l = k.slice(0, 4) + '-' + k.slice(4); } 
                               else if (k.length <= 10) { l = k.slice(0, 4) + '-' + k.slice(4, 7) + '-' + k.slice(7); } 
                               else { l = k.slice(0, 4) + '-' + k.slice(4, 8) + '-' + k.slice(8); }
                               this.value = l;
                           ">
                    <script>(function(){var el=document.getElementById('parent_phone');if(el&&el.value){el.dispatchEvent(new Event('input'));}})();</script>
                </div>
                
            </div>
            <p class="text-sm text-gray-500 mt-2"><?php echo __('student_profile_note'); ?></p>
            
            <div class="mt-8 flex justify-end gap-4">
                <a href="student-dashboard.php?page=profile&action=view_data" class="px-6 py-2 rounded-lg text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"><?php echo __('student_form_button_cancel'); ?></a>
                <button type="submit" class="btn-gradient text-white font-semibold px-6 py-2 rounded-lg">
                    <?php echo __('profile_button_update_profile'); ?>
                </button>
            </div>
        </form>
    </div>

<?php elseif ($action == 'edit_password'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('profile_password_title'); ?></h1>
        <a href="student-dashboard.php?page=profile" class="text-sm text-orange-600 hover:text-orange-700 flex items-center gap-1">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> <?php echo __('user_form_back_link'); ?>
        </a>
    </div>
    
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg shadow-yellow-900/5">
        <form action="student-dashboard.php?page=profile&action=edit_password" method="POST" id="passwordForm" class="space-y-6 max-w-lg mx-auto">
            <input type="hidden" name="action" value="update_password">
            
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('profile_label_current_password'); ?></label>
                <div class="relative">
                    <input type="password" name="current_password" id="current_password" class="contact-input pr-10" 
                           placeholder="<?php echo __('profile_placeholder_current_password'); ?>" 
                           required
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_password')); ?>')"
                           oninput="this.setCustomValidity('')">
                    <button type="button" onclick="togglePasswordVisibility('current_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i data-lucide="eye" class="w-5 h-5"></i>
                    </button>
                </div>
                <p id="warning-current" class="text-xs text-red-600 mt-1.5 hidden"></p>
            </div>
            
            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('profile_label_new_password'); ?></label>
                <div class="relative">
                    <input type="password" 
                        name="new_password" 
                        id="new_password" 
                        class="contact-input pr-10" 
                        placeholder="<?php echo __('profile_placeholder_new_password'); ?>" 
                        required
                        minlength="6" 
                        maxlength="50"
                        oninvalid="if (this.validity.valueMissing) { this.setCustomValidity('<?php echo addslashes(__('register_error_password')); ?>'); } else { this.setCustomValidity('<?php echo addslashes(__('register_error_password_length')); ?>'); }"
                        oninput="this.setCustomValidity('')">
                    <button type="button" onclick="togglePasswordVisibility('new_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i data-lucide="eye" class="w-5 h-5"></i>
                    </button>
                </div>
                <p id="warning-new" class="text-xs text-red-600 mt-1.5 hidden"></p>
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('profile_label_confirm_password'); ?></label>
                <div class="relative">
                    <input type="password" 
                        name="confirm_password" 
                        id="confirm_password" 
                        class="contact-input pr-10" 
                        placeholder="<?php echo __('profile_placeholder_confirm_password'); ?>" 
                        required
                        minlength="6" 
                        maxlength="50"
                        oninvalid="if (this.validity.valueMissing) { this.setCustomValidity('<?php echo addslashes(__('register_error_password')); ?>'); } else { this.setCustomValidity('<?php echo addslashes(__('register_error_password_length')); ?>'); }"
                        oninput="this.setCustomValidity('')">
                    <button type="button" onclick="togglePasswordVisibility('confirm_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i data-lucide="eye" class="w-5 h-5"></i>
                    </button>
                </div>
                <p id="warning-confirm" class="text-xs text-red-600 mt-1.5 hidden"></p>
            </div>
            
            <div class="mt-8 flex justify-end gap-4">
                <a href="student-dashboard.php?page=profile" class="px-6 py-2 rounded-lg text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"><?php echo __('student_form_button_cancel'); ?></a>
                <button type="submit" id="submitButton" class="btn-gradient text-white font-semibold px-6 py-2 rounded-lg">
                    <?php echo __('profile_button_update_password'); ?>
                </button>
            </div>
        </form>
    </div>

    <script>
    // 1. Fungsi Toggle Mata (Lihat Password)
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<i data-lucide="eye-off" class="w-5 h-5"></i>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<i data-lucide="eye" class="w-5 h-5"></i>';
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // 2. Logic Baru: Hapus Validasi Karakter Berbahaya, Terapkan Auto-Hapus Spasi
    const passwordMap = [
        { input: document.getElementById('current_password'), warning: document.getElementById('warning-current') },
        { input: document.getElementById('new_password'), warning: document.getElementById('warning-new') },
        { input: document.getElementById('confirm_password'), warning: document.getElementById('warning-confirm') }
    ];

    const submitBtn = document.getElementById('submitButton');

    passwordMap.forEach(item => {
        if (item.input) {
            item.input.addEventListener('input', function() {
                // A. Hapus spasi secara otomatis jika ada
                if (this.value.includes(' ')) {
                    this.value = this.value.replace(/\s/g, '');
                }
                
                // B. Bersihkan tampilan error (jika ada sisa dari validasi lama)
                this.classList.remove('border-red-500', 'focus:ring-red-500');
                if (item.warning) item.warning.classList.add('hidden');
                
                // C. Pastikan tombol submit aktif kembali
                if(submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
        }
    });
    </script>

<?php else: // HALAMAN UTAMA PROFIL ?>
    <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo __('profile_page_title'); ?></h1>
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg shadow-yellow-900/5">
        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-1 flex flex-col items-center text-center">
                <div class="w-24 h-24 rounded-full bg-yellow-100 flex items-center justify-center mb-4">
                    <i data-lucide="user" class="w-12 h-12 text-yellow-500"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></h2>
                <p class="text-gray-500"><?php echo htmlspecialchars($user['username']); ?></p>
                <span class="mt-2 inline-block bg-yellow-100 text-yellow-700 text-xs font-semibold px-3 py-1 rounded-full capitalize">
                    <?php echo __('profile_role_student'); ?>
                </span>
            </div>

            <div class="md:col-span-2 space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo __('profile_section_actions'); ?></h3>
                    <div class="space-y-4">
                        <a href="student-dashboard.php?page=profile&action=view_data" class="flex items-center justify-between w-full p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                            <div class="flex items-center gap-4">
                                <i data-lucide="eye" class="w-5 h-5 text-gray-500"></i>
                                <span class="font-semibold text-gray-700"><?php echo __('profile_view_data_title'); ?></span>
                            </div>
                            <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400"></i>
                        </a>
                        <a href="student-dashboard.php?page=profile&action=edit_password" class="flex items-center justify-between w-full p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                            <div class="flex items-center gap-4">
                                <i data-lucide="lock" class="w-5 h-5 text-gray-500"></i>
                                <span class="font-semibold text-gray-700"><?php echo __('profile_action_change_password'); ?></span>
                            </div>
                            <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400"></i>
                        </a>
                        <a href="logout.php" class="flex items-center justify-between w-full p-4 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                            <div class="flex items-center gap-4">
                                <i data-lucide="log-out" class="w-5 h-5 text-red-500"></i>
                                <span class="font-semibold text-red-600"><?php echo __('profile_action_sign_out'); ?></span>
                            </div>
                            <i data-lucide="chevron-right" class="w-5 h-5 text-red-400"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// --- AKHIR OUTPUT BUFFER ---
ob_end_flush(); 
?>