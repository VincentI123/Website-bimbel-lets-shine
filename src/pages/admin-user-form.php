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
$userId = isset($_GET['id']) ? $_GET['id'] : null;
$isEditing = $userId !== null;
$user = null;

// Ambil data form dari session jika ada error sebelumnya
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
} else {
    $form_data = [];
}

$pageTitleKey = $isEditing ? 'user_form_edit_title' : 'user_form_add_title';

if ($isEditing) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitasi Input (PHP 5.6 Safe)
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    // Hapus spasi dan karakter aneh di username
    $username = preg_replace('/[^a-zA-Z0-9_.]/', '', strtolower($username));
    
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Simpan data form ke session (biar gak ilang kalau error)
    $_SESSION['form_data'] = $_POST;

    // 2. Validasi Keras
    if (empty($name)) {
        // Panggil pesan error dari lang file (id/en/cn)
        $error_message = __('user_error_name'); 
    } elseif (empty($username)) {
        $error_message = __('user_error_username');
    } elseif (empty($role)) {
        $error_message = __('user_error_role');
    } // Validasi Password: Wajib diisi jika baru, Min 6 karakter, GAK BOLEH ADA SPASI
    elseif (!empty($password) && strpos($password, ' ') !== false) {
        $error_message = __('user_error_password_space');
    } 
    elseif (!$isEditing && (empty($password) || strlen($password) < 6)) {
        $error_message = __('register_error_password_length');
    } else {
        
        try {
            // 3. Cek Duplikasi Username
            $checkSql = "SELECT COUNT(*) FROM users WHERE username = :username";
            $checkParams = array(':username' => $username);

            if ($isEditing) {
                $checkSql .= " AND id != :id";
                $checkParams[':id'] = $userId;
            }

            $stmtCheck = $db->prepare($checkSql);
            $stmtCheck->execute($checkParams);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $error_message = sprintf(__('user_error_duplicate'), $username);
            } else {
                
                // 4. Simpan Data
                if ($isEditing) {
                    // Update existing user
                    $sql = "UPDATE users SET name = :name, username = :username, role = :role";
                    $params = array(':name' => $name, ':username' => $username, ':role' => $role, ':id' => $userId);
                    
                    if (!empty($password)) {
                        $sql .= ", password = :password";
                        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id = :id";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $_SESSION['flash_message'] = __('user_success_update');

                } else {
                    // Create new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (name, username, password, role, status) VALUES (:name, :username, :password, :role, 'approved')";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array(
                        ':name' => $name, 
                        ':username' => $username, 
                        ':password' => $hashedPassword, 
                        ':role' => $role
                    ));
                    $_SESSION['flash_message'] = __('user_success_add');
                }

                // Hapus data form dari session jika sukses
                unset($_SESSION['form_data']);
                
                ob_end_clean();
                header("Location: admin-dashboard.php?page=users");
                exit;
            }

        } catch (PDOException $e) {
            $error_message = sprintf(__('user_error_db'), $e->getMessage());
        }
    }
    
    // Jika ada error, tampilkan alert di bawah header (Tambahkan kode HTML ini di bagian view jika belum ada)
}

// Siapkan nilai untuk ditampilkan di form (Prioritas: Input User > Database > Kosong)
$name_val = isset($form_data['name']) ? $form_data['name'] : (isset($user['name']) ? $user['name'] : '');
$username_val = isset($form_data['username']) ? $form_data['username'] : (isset($user['username']) ? $user['username'] : '');
$role_val = isset($form_data['role']) ? $form_data['role'] : (isset($user['role']) ? $user['role'] : '');
?>

<?php
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}
unset($query_params['error']); // Bersihkan error di link bahasa

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
        <a href="admin-dashboard.php?page=users" class="text-orange-600 hover:text-orange-700 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-5 h-5"></i> 
            <?php echo __('user_form_back_link'); ?>
        </a>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'username_exists'): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo __('register_error_username_taken'); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-8 rounded-2xl shadow-lg shadow-orange-900/5">
        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm relative mb-6" role="alert">
            <strong class="font-bold">
                <?php echo ($currentLang == 'en') ? 'Error!' : (($currentLang == 'cn') ? '错误！' : 'Gagal!'); ?>
            </strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" class="space-y-8" id="userForm">
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-700"><?php echo __('user_form_section_info'); ?></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="relative">
                        <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($name_val); ?>" 
                               required 
                               class="pl-10 contact-input" 
                               placeholder="<?php echo __('user_form_placeholder_name'); ?>"
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('user_error_name')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                    
                    <div class="relative">
                        <i data-lucide="user-circle" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username_val); ?>" 
                               required 
                               class="pl-10 contact-input" 
                               placeholder="<?php echo __('user_form_placeholder_username'); ?>"
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('user_error_username')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-700"><?php echo __('user_form_section_security'); ?></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                    
                    <div>
                        <div class="relative">
                            <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                            <input type="password" id="password" name="password" 
                                   class="pl-10 pr-10 contact-input" 
                                   <?php echo !$isEditing ? 'required' : ''; ?> 
                                   placeholder="<?php echo __('user_form_placeholder_password'); ?>"
                                   minlength="6"
                                   maxlength="50"
                                   oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_password_length')); ?>')"
                                    oninput="this.setCustomValidity(''); this.value = this.value.replace(/\s/g, '');">                            
                            <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i data-lucide="eye" class="w-5 h-5"></i>
                            </button>
                        </div>
                        <?php if ($isEditing): ?>
                            <p class="text-xs text-gray-500 mt-1"><?php echo __('user_form_password_note'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="relative">
                        <i data-lucide="shield" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <select id="role" name="role" class="pl-10 contact-input" required
                                oninvalid="this.setCustomValidity('<?php echo addslashes(__('user_error_role')); ?>')"
                                oninput="this.setCustomValidity('')">
                            
                            <option value="" disabled <?php echo ($role_val === '') ? 'selected' : ''; ?> class="text-gray-400">
                                <?php echo __('user_form_select_role'); ?>
                            </option>
                            
                            <option value="student" <?php echo ($role_val === 'student') ? 'selected' : ''; ?>><?php echo __('users_role_student'); ?></option>
                            <option value="teacher" <?php echo ($role_val === 'teacher') ? 'selected' : ''; ?>><?php echo __('users_role_teacher'); ?></option>
                            <option value="admin" <?php echo ($role_val === 'admin') ? 'selected' : ''; ?>><?php echo __('users_role_admin'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-4 pt-4">
                 <a href="admin-dashboard.php?page=users" class="px-6 py-2 rounded-lg text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"><?php echo __('user_form_button_cancel'); ?></a>
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-2 rounded-lg transition-colors">
                    <?php echo $isEditing ? __('user_form_button_update') : __('user_form_button_create'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Toggle Password Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'password') {
                togglePassword.innerHTML = '<i data-lucide="eye" class="w-5 h-5"></i>';
            } else {
                togglePassword.innerHTML = '<i data-lucide="eye-off" class="w-5 h-5"></i>';
            }
            
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    }

    // 2. Formatter Username (Lowercase, No Spaces)
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^a-zA-Z0-9_.]/g, '');
            e.target.value = value.toLowerCase();
        });
    }

    // 3. Formatter Name (Text Only)
    const nameInput = document.getElementById('name');
    if (nameInput) {
        nameInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '');
        });
    }
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>