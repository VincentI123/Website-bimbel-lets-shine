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
$langFile = __DIR__ . '/src/pages/lang/' . $currentLang . '.php'; 
if (file_exists($langFile) && is_readable($langFile)) {
    $lang = include $langFile;
} else {
    $defaultFile = __DIR__ . '/src/pages/lang/' . $defaultLang . '.php';
    $lang = (file_exists($defaultFile) && is_readable($defaultFile)) ? include $defaultFile : array();
}

// 7. Fungsi helper untuk terjemahan
function __($key) {
    global $lang;
    if (is_array($lang) && isset($lang[$key])) {
        return $lang[$key];
    } else {
        return $key;
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE LOGIKA LOGIN ASLI DIMULAI DI SINI ---
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/includes/functions.php';
require_once __DIR__ . '/src/includes/auth.php';

initAuth();

function getDashboardByRole($role) {
    switch ($role) {
        case 'admin':
            return 'admin-dashboard.php';
        case 'teacher':
            return 'teacher-dashboard.php';
        case 'student':
            return 'student-dashboard.php';
        default:
            return 'index.php'; 
    }
}

if (isLoggedIn()) {
    $user = getCurrentUser();
    redirect(getDashboardByRole($user['role']));
}

$error = '';
$success_message = '';

if (isset($_GET['status']) && $_GET['status'] === 'pending_approval') {
    $success_message = __('login_status_awaiting_approval');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!empty($username) && !empty($password)) {
        $result = loginUser($username, $password);
        if ($result['success']) {
            redirect(getDashboardByRole($result['user']['role']));
        } else {
            $error = __($result['message']); 
        }
    } else {
        $error = __('login_error_required');
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bimbel Let's Shine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css"> 
    <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>
<body class="bg-gradient-to-br from-orange-50 to-yellow-50 font-['Poppins'] flex items-center justify-center min-h-screen p-6">
    <div class="w-full max-w-md">
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
        <a href="index.php" class="flex items-center gap-2 text-orange-600 hover:text-orange-700 mb-6">
            <i data-lucide="arrow-left" class="w-5 h-5"></i> 
            <?php echo __('login_back_home'); ?>
        </a>
        <div class="text-center mb-8">
            <img src="assets/img/logo.png" alt="Logo" class="h-24 w-24 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-800">Bimbel Let's Shine</h1>
            <p class="text-gray-500"><?php echo __('login_system_title'); ?></p>
            <p class="text-orange-500 font-semibold mt-1"><?php echo __('login_tagline_shine'); ?></p>
        </div>
        <div class="bg-white/80 backdrop-blur-sm p-8 rounded-2xl shadow-lg shadow-orange-900/10">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800"><?php echo __('login_welcome'); ?></h2>
                <p class="text-gray-500"><?php echo __('login_subtitle'); ?></p>
            </div>
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="login.php" id="loginForm">
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('login_label_username'); ?></label>
                    <div class="relative">
                        <input type="text" id="username" name="username" required class="contact-input" placeholder="<?php echo __('login_placeholder_username'); ?>">
                    </div>
                    <p id="username-warning" class="text-xs text-red-600 mt-1.5" style="display: none;"></p>
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('login_label_password'); ?></label>
                    <div class="relative">
                        <input type="password" 
                            id="password" 
                            name="password" 
                            required 
                            class="contact-input pr-10" 
                            placeholder="<?php echo __('login_placeholder_password'); ?>"
                            minlength="6"
                            maxlength="50"
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_password_length')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i data-lucide="eye" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <p id="password-warning" class="text-xs text-red-600 mt-1.5" style="display: none;"></p>
                </div>
                <button type="submit" id="loginButton" class="w-full btn-gradient text-white font-semibold py-3 rounded-lg"><?php echo __('login_button_sign_in'); ?></button>
                
                <div class="text-center mt-6">
                    <p class="text-sm text-gray-600">
                        <?php echo __('login_no_account'); ?>
                        <a href="register.php?from=login" class="font-semibold text-orange-600 hover:text-orange-700">
                            <?php echo __('login_register_link'); ?>
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
    <script>
        lucide.createIcons();

        // Ambil pesan terjemahan untuk JS
        const MSG_SIGNING_IN = '<?php echo addslashes(__('login_button_signing_in')); ?>';

        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const usernameWarning = document.getElementById('username-warning');

        // Animasi Loading saat Submit
        loginForm.addEventListener('submit', () => {
            loginButton.disabled = true;
            loginButton.innerHTML = `
                <span class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    ${MSG_SIGNING_IN}
                </span>
            `;
        });

        // --- 1. VALIDASI USERNAME (Sama persis seperti Register) ---
        // Aturan: Hanya Huruf, Angka, Underscore (_), dan Titik (.). Otomatis huruf kecil.
        usernameInput.addEventListener('input', function() {
            // Hapus karakter selain a-z, 0-9, _, dan .
            let sanitized = this.value.replace(/[^a-zA-Z0-9_.]/g, '');
            
            // Paksa huruf kecil
            sanitized = sanitized.toLowerCase();

            // Update nilai input jika ada perubahan
            if (this.value !== sanitized) {
                this.value = sanitized;
            }

            // Bersihkan tampilan error jika ada sisa
            if(usernameWarning) usernameWarning.style.display = 'none';
            this.classList.remove('border-red-500');
            
            // Aktifkan tombol login kembali jika sebelumnya disable
            loginButton.disabled = false;
        });

        // --- 2. VALIDASI PASSWORD (Auto Hapus Spasi) ---
        passwordInput.addEventListener('input', function() {
            if (this.value.includes(' ')) {
                this.value = this.value.replace(/\s/g, '');
            }
            this.classList.remove('border-red-500');
        });

        // --- 3. FITUR LIHAT PASSWORD ---
        const togglePassword = document.getElementById('togglePassword');
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
    </script>
</body>
</html>
<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>