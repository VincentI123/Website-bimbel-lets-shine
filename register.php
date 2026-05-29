<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); 

// --- "OTAK" BAHASA (VERSI UNIVERSAL 3 BAHASA) ---

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
$langFile = __DIR__ . '/src/pages/lang/' . $currentLang . '.php'; 
if (file_exists($langFile) && is_readable($langFile)) {
    $lang = include $langFile;
} else {
    $defaultFile = __DIR__ . '/src/pages/lang/' . $defaultLang . '.php';
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


// --- LOGIKA TOMBOL KEMBALI (BACK BUTTON LOGIC) ---
$from = isset($_GET['from']) ? $_GET['from'] : 'login'; 
$backUrl = 'login.php';

$backToLoginDict = [
    'id' => 'Kembali ke Login',
    'en' => 'Back to Login',
    'cn' => '返回登录'
];
$backLabel = isset($backToLoginDict[$currentLang]) ? $backToLoginDict[$currentLang] : 'Back to Login';

if ($from === 'home') {
    $backUrl = 'index.php';
    $backLabel = __('login_back_home'); 
}
// -------------------------------------------------


// --- KODE LOGIKA REGISTRASI ---
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/includes/functions.php';
require_once __DIR__ . '/src/includes/auth.php';

initAuth();

if (isLoggedIn()) {
    redirect('index.php');
}

$pdo = Database::getInstance()->getConnection();
$subjects_stmt = $pdo->query("SELECT name FROM subjects ORDER BY name");
$all_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

$slots_stmt = $pdo->query("SELECT time_label FROM time_slots ORDER BY display_order ASC");
$all_slots = $slots_stmt->fetchAll(PDO::FETCH_COLUMN);

$days_translation = [
    'Monday' => __('Monday'), 
    'Tuesday' => __('Tuesday'), 
    'Wednesday' => __('Wednesday'),
    'Thursday' => __('Thursday'), 
    'Friday' => __('Friday'), 
    'Saturday' => __('Saturday')
];
$all_days = array_keys($days_translation);

$errors = [];
$old_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_data = $_POST;

    // --- SANITASI DATA (BACKEND PROTECTION) ---
    $name = isset($_POST['name']) ? ucwords(strtolower(trim($_POST['name']))) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $birth_place = isset($_POST['birth_place']) ? ucwords(strtolower(trim($_POST['birth_place']))) : '';
    $birth_date = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : '';
    $grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : '';
    $school = isset($_POST['school']) ? ucwords(strtolower(trim($_POST['school']))) : '';
    $parent_name = isset($_POST['parent_name']) ? ucwords(strtolower(trim($_POST['parent_name']))) : '';
    $parent_phone = isset($_POST['parent_phone']) ? trim($_POST['parent_phone']) : '';
    
    // Username: Hapus karakter berbahaya di backend juga
    $raw_username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $username = preg_replace('/[^a-zA-Z0-9_.]/', '', strtolower($raw_username));
    
    $password = isset($_POST['password']) ? $_POST['password'] : ''; 
    
    $subjects_array = isset($_POST['subjects']) && is_array($_POST['subjects']) ? $_POST['subjects'] : [];
    $subjects = implode(', ', $subjects_array);

    $days_array = isset($_POST['available_days']) && is_array($_POST['available_days']) ? $_POST['available_days'] : [];
    $available_days = implode(', ', $days_array);
    
    $times_array = isset($_POST['available_times']) && is_array($_POST['available_times']) ? $_POST['available_times'] : []; 
    $available_times = implode(', ', $times_array);

    // Validation
    if (empty($name)) $errors[] = __('register_error_name');
    if (empty($address)) $errors[] = __('register_error_address');
    if (empty($phone_number)) $errors[] = __('register_error_phone');
    if (empty($birth_place)) $errors[] = __('register_error_birthplace');
    if (empty($birth_date)) $errors[] = __('register_error_birthdate');
    if (empty($grade_level)) $errors[] = __('register_error_grade');
    if (empty($school)) $errors[] = __('register_error_school');
    if (empty($parent_name)) $errors[] = __('register_error_parent_name');
    if (empty($parent_phone)) $errors[] = __('register_error_parent_phone');
    if (empty($username)) $errors[] = __('register_error_username');
    if (empty($password)) $errors[] = __('register_error_password');
    if (strlen($password) < 6 || strlen($password) > 50) {
        // Kita gunakan satu pesan error umum untuk panjang karakter
        $errors[] = __('register_error_password_length'); 
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $role = 'student';
        $status = 'pending';

        try {
            // PREPARED STATEMENT (SQL INJECTION PROTECTION)
            $stmt = $pdo->prepare("INSERT INTO users 
                (name, address, phone_number, birth_place, birth_date, grade_level, school, parent_name, parent_phone, subjects, available_days, available_times, username, password, role, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $name, $address, $phone_number, $birth_place, $birth_date, $grade_level, 
                $school, $parent_name, $parent_phone, $subjects, $available_days, $available_times, 
                $username, $hashed_password, $role, $status
            ]);

            ob_end_clean(); 
            redirect('login.php?status=pending_approval&lang=' . $currentLang); 

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = __('register_error_username_taken');
            } else {
                error_log("Registration error: " . $e->getMessage());
                $errors[] = __('register_error_failed');
            }
        }
    }
}

$selected_subjects_old = isset($old_data['subjects']) ? $old_data['subjects'] : [];
$selected_days_old = isset($old_data['available_days']) ? $old_data['available_days'] : [];
$selected_times_old = isset($old_data['available_times']) ? $old_data['available_times'] : []; 

?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('register_page_title'); ?> - Let's Shine Tutoring</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>
<body class="bg-gradient-to-br from-orange-50 to-yellow-50 font-['Poppins'] flex items-center justify-center min-h-screen p-6">
    <div class="w-full max-w-lg">
        
        <?php
        $url_parts = parse_url($_SERVER['REQUEST_URI']);
        $path = $url_parts['path'];
        $query_params = array();
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query_params);
        }
        
        function buildLangUrl($params, $lang, $path) {
            $params['lang'] = $lang;
            return $path . '?' . http_build_query($params);
        }
        
        $url_id = buildLangUrl($query_params, 'id', $path);
        $url_en = buildLangUrl($query_params, 'en', $path);
        $url_cn = buildLangUrl($query_params, 'cn', $path);
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
        
        <a href="<?php echo $backUrl; ?>" class="flex items-center gap-2 text-orange-600 hover:text-orange-700 mb-6">
            <i data-lucide="arrow-left" class="w-5 h-5"></i> 
            <?php echo $backLabel; ?>
        </a>

        <div class="text-center mb-8">
            <img src="assets/img/logo.png" alt="Logo" class="h-24 w-24 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-800">Bimbel Let's Shine</h1>
            <p class="text-gray-500"><?php echo __('login_system_title'); ?></p>
            <p class="text-orange-500 font-semibold mt-1">⭐ Shine Like a Star ⭐</p>
        </div>

        <div class="bg-white/80 backdrop-blur-sm p-8 rounded-2xl shadow-lg">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800"><?php echo __('register_page_title'); ?></h2>
                <p class="text-gray-500"><?php echo __('register_page_subtitle'); ?></p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
                    <?php foreach ($errors as $error): ?><p><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php 
                $form_action = 'register.php';
                $form_params = [];
                if (isset($_GET['lang'])) $form_params['lang'] = $_GET['lang'];
                if (isset($_GET['from'])) $form_params['from'] = $_GET['from'];
                if (!empty($form_params)) $form_action .= '?' . http_build_query($form_params);
            ?>
            <form method="POST" action="<?php echo $form_action; ?>" id="registerForm" class="space-y-4">
                
                <h3 class="text-lg font-semibold text-gray-800 pt-2 -mb-2"><?php echo __('register_section_details'); ?></h3>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_name'); ?></label>
                    <input type="text" id="name" name="name" required class="contact-input" 
                        placeholder="<?php echo __('student_form_placeholder_name'); ?>" 
                        value="<?php echo htmlspecialchars(isset($old_data['name']) ? $old_data['name'] : ''); ?>"
                        oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_name')); ?>')"
                        oninput="this.setCustomValidity('')">
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_address'); ?></label>
                    <input type="text" id="address" name="address" required class="contact-input" 
                        placeholder="<?php echo __('student_form_placeholder_address'); ?>" 
                        value="<?php echo htmlspecialchars(isset($old_data['address']) ? $old_data['address'] : ''); ?>"
                        oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_address')); ?>')"
                        oninput="this.setCustomValidity('')">
                </div>

                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_phone'); ?></label>
                    <input type="tel" id="phone_number" name="phone_number" required class="contact-input" 
                        placeholder="<?php echo __('student_form_placeholder_phone'); ?>" 
                        value="<?php echo htmlspecialchars(isset($old_data['phone_number']) ? $old_data['phone_number'] : ''); ?>"
                        oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_phone')); ?>')"
                        oninput="this.setCustomValidity('')">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="birth_place" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_birth_place'); ?></label>
                        <input type="text" id="birth_place" name="birth_place" required class="contact-input" 
                            placeholder="<?php echo __('student_form_placeholder_birth_place'); ?>" 
                            value="<?php echo htmlspecialchars(isset($old_data['birth_place']) ? $old_data['birth_place'] : ''); ?>"
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_birthplace')); ?>')"
                            oninput="this.setCustomValidity('')">
                    </div>

                    <div>
                        <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_birth_date'); ?></label>
                        <input type="date" id="birth_date" name="birth_date" required class="contact-input" 
                            value="<?php echo htmlspecialchars(isset($old_data['birth_date']) ? $old_data['birth_date'] : ''); ?>"
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_birthdate')); ?>')"
                            oninput="this.setCustomValidity('')">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('student_form_label_grade'); ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2">
                        <?php
                        $grades = [
                            'Pre-Nursery' => 'student_form_grade_prenursery', 
                            'Kindergarten' => 'student_form_grade_kindergarten', 
                            'Elementary' => 'student_form_grade_elementary', 
                            'Junior High' => 'student_form_grade_juniorhigh', 
                            'Senior High' => 'student_form_grade_seniorhigh'
                        ];
                        foreach ($grades as $grade_value => $grade_key) {
                            $old_grade = isset($old_data['grade_level']) ? $old_data['grade_level'] : '';
                            $checked = ($old_grade === $grade_value) ? 'checked' : '';
                            $input_id = strtolower(str_replace(' ', '-', $grade_value));
                            echo '<label class="flex items-center space-x-2">';
                            
                            // UBAH DI SINI: Tambahkan required, oninvalid, dan oninput
                            echo '<input type="radio" name="grade_level" id="'.$input_id.'" value="'.$grade_value.'" 
                                        class="h-5 w-5 text-orange-600 border-gray-300 focus:ring-orange-500 flex-shrink-0" 
                                        required 
                                        oninvalid="this.setCustomValidity(\''.addslashes(__('register_error_grade')).'\')" 
                                        oninput="this.setCustomValidity(\'\')" 
                                        '.$checked.'>'; 
                            
                            echo '<span for="'.$input_id.'">'.__( $grade_key ).'</span>';
                            echo '</label>';
                        }
                        ?>
                    </div>
                </div>

                <div>
                <label for="school" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_school'); ?></label>
                <input type="text" id="school" name="school" required class="contact-input" 
                       placeholder="<?php echo __('student_form_placeholder_school'); ?>" 
                       value="<?php echo htmlspecialchars(isset($old_data['school']) ? $old_data['school'] : ''); ?>"
                       oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_school')); ?>')"
                       oninput="this.setCustomValidity('')">
            </div>
                
                <h3 class="text-lg font-semibold text-gray-800 -mb-2 pt-2"><?php echo __('register_section_subjects'); ?></h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('student_form_label_subjects'); ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2">
                        <?php
                        foreach ($all_subjects as $subject_name) {
                            $checked = in_array($subject_name, $selected_subjects_old) ? 'checked' : '';
                            echo '<label class="flex items-center space-x-2">';
                            // PERBAIKAN: Menambahkan h-5 w-5 dan border-gray-300 agar seragam
                            echo '<input type="checkbox" name="subjects[]" value="' . htmlspecialchars($subject_name) . '" class="h-5 w-5 rounded text-orange-600 border-gray-300 focus:ring-orange-500 flex-shrink-0" '.$checked.'>';
                            echo '<span>' . htmlspecialchars($subject_name) . '</span>';
                            echo '</label>';
                        }
                        ?>
                    </div>
                </div>

                <h3 class="text-lg font-semibold text-gray-800 -mb-2 pt-2"><?php echo __('student_form_label_days'); ?></h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('register_instruction_days'); ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2">
                        <?php
                        foreach ($all_days as $day_key) { 
                            $checked = in_array($day_key, $selected_days_old) ? 'checked' : '';
                            $day_id = 'day-' . strtolower($day_key);
                            echo '<label class="flex items-center space-x-2">';
                            // PERBAIKAN: Mengubah h-4 w-4 menjadi h-5 w-5 dan tambah flex-shrink-0
                            echo "<input type='checkbox' name='available_days[]' id='{$day_id}' value='{$day_key}' class='h-5 w-5 rounded text-orange-600 border-gray-300 focus:ring-orange-500 flex-shrink-0' {$checked}> ";
                            echo "<span for='{$day_id}'>" . $days_translation[$day_key] . "</span>"; 
                            echo '</label>';
                        }
                        ?>
                    </div>
                </div>
                
                <h3 class="text-lg font-semibold text-gray-800 -mb-2 pt-2"><?php echo __('student_form_label_times'); ?></h3>
                <div>
                     <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('register_instruction_times'); ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2">
                        <?php
                        foreach ($all_slots as $slot_label) {
                            $checked = in_array($slot_label, $selected_times_old) ? 'checked' : '';
                            $slot_id = 'slot-register-' . md5(htmlspecialchars($slot_label));
                            echo '<label class="flex items-center space-x-2">';
                            // PERBAIKAN: Mengubah h-4 w-4 menjadi h-5 w-5 dan tambah flex-shrink-0
                            // flex-shrink-0 mencegah checkbox menjadi gepeng saat teks label panjang
                            echo "<input type='checkbox' name='available_times[]' id='{$slot_id}' value='" . htmlspecialchars($slot_label) . "' class='h-5 w-5 rounded text-orange-600 border-gray-300 focus:ring-orange-500 flex-shrink-0' {$checked}> ";
                            echo "<span for='{$slot_id}'>" . htmlspecialchars($slot_label) . "</span>";
                            echo '</label>';
                        }
                        ?>
                    </div>
                </div>

                <hr class="my-4">

                <h3 class="text-lg font-semibold text-gray-800 -mb-2"><?php echo __('register_section_parent'); ?></h3>
                
                <div>
                    <label for="parent_name" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_parent_name'); ?></label>
                    <input type="text" id="parent_name" name="parent_name" required class="contact-input" 
                        placeholder="<?php echo __('student_form_placeholder_parent_name'); ?>" 
                        value="<?php echo htmlspecialchars(isset($old_data['parent_name']) ? $old_data['parent_name'] : ''); ?>"
                        oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_parent_name')); ?>')"
                        oninput="this.setCustomValidity('')">
                </div>

                <div>
                    <label for="parent_phone" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('student_form_label_parent_phone'); ?></label>
                    <input type="tel" id="parent_phone" name="parent_phone" required class="contact-input" 
                        placeholder="<?php echo __('student_form_placeholder_parent_phone'); ?>" 
                        value="<?php echo htmlspecialchars(isset($old_data['parent_phone']) ? $old_data['parent_phone'] : ''); ?>"
                        oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_parent_phone')); ?>')"
                        oninput="this.setCustomValidity('')">
                </div>

                <hr class="my-4">

                <h3 class="text-lg font-semibold text-gray-800 -mb-2"><?php echo __('register_section_account'); ?></h3>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('login_label_username'); ?></label>
                    <input type="text" id="username" name="username" required class="contact-input" 
                        placeholder="<?php echo __('register_placeholder_username'); ?>" 
                        value="<?php echo htmlspecialchars(isset($old_data['username']) ? $old_data['username'] : ''); ?>"
                        oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_username')); ?>')"
                        oninput="this.setCustomValidity('')">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('login_label_password'); ?></label>
                    <div class="relative">
                        <input type="password" 
                            id="password" 
                            name="password" 
                            required 
                            class="contact-input pr-10" 
                            placeholder="<?php echo __('register_placeholder_password'); ?>"
                            minlength="6"
                            maxlength="50"
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_password_length')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <button type="button" onclick="togglePasswordVisibility('password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i data-lucide="eye" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <p id="password-warning" class="text-xs text-red-600 mt-1.5 hidden"></p>
                </div>

                <button type="submit" id="registerButton" class="w-full btn-gradient text-white font-semibold py-3 rounded-lg !mt-8"><?php echo __('register_button_register'); ?></button>
                
                <div class="text-center mt-4">
                    <p class="text-sm text-gray-600">
                        <?php echo __('register_have_account'); ?> 
                        <a href="login.php" class="font-semibold text-orange-600 hover:text-orange-700">
                            <?php echo __('register_login_link'); ?>
                        </a>
                    </p>
                </div>
                
            </form>
        </div>
    </div>
    <script>
const gradeRadios = document.querySelectorAll('input[name="grade_level"]');
        gradeRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                // Reset validasi untuk SEMUA radio button 'grade_level'
                gradeRadios.forEach(r => r.setCustomValidity(''));
            });
        });

function setupCheckboxGroupValidation(groupName, errorMsg) {
            const checkboxes = document.querySelectorAll(`input[name="${groupName}[]"]`);
            const firstCheckbox = checkboxes[0];

            if (!firstCheckbox) return;

            function checkValidity() {
                const isChecked = document.querySelectorAll(`input[name="${groupName}[]"]:checked`).length > 0;
                
                if (isChecked) {
                    firstCheckbox.setCustomValidity('');
                    firstCheckbox.removeAttribute('required');
                } else {
                    firstCheckbox.setCustomValidity(errorMsg);
                    firstCheckbox.setAttribute('required', 'required');
                }
            }

            // Pasang event listener ke semua checkbox dalam grup
            checkboxes.forEach(cb => {
                cb.addEventListener('change', checkValidity);
            });

            // Cek saat tombol submit ditekan
            const form = document.getElementById('registerForm'); // Pastikan ID form Anda benar
            if(form) {
                form.addEventListener('submit', checkValidity);
            }
            
            // Set kondisi awal
            checkValidity();
        }

        // Inisialisasi Validasi untuk 3 Grup Checkbox
        // Kita ambil pesan error dari PHP agar sesuai bahasa yang dipilih
        setupCheckboxGroupValidation('subjects', '<?php echo addslashes(__('register_error_subjects')); ?>');
        setupCheckboxGroupValidation('available_days', '<?php echo addslashes(__('register_error_days')); ?>');
        setupCheckboxGroupValidation('available_times', '<?php echo addslashes(__('register_error_times')); ?>');

        lucide.createIcons();

        const registerForm = document.getElementById('registerForm');
        const registerButton = document.getElementById('registerButton');
        const MSG_PROCESSING = '<?php echo addslashes(__('register_processing')); ?>';
        
        // Pesan Error dari PHP Language File
        const MSG_DANGEROUS = '<?php echo addslashes(__('login_error_invalid_input')); ?>'; 

        // --- 1. ANIMASI TOMBOL SUBMIT ---
        registerForm.addEventListener('submit', () => {
            registerButton.disabled = true;
            registerButton.innerHTML = `
                <span class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    ${MSG_PROCESSING}
                </span>
            `;
        });

        // --- 2. FITUR TOGGLE PASSWORD (MATA) ---
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

        // --- 3. VALIDASI KARAKTER BERBAHAYA (ANTI-SQL INJECTION FRONTEND) ---
        const dangerousChars = /['"`;=\\]|--/;
        const passwordInput = document.getElementById('password');
        const passwordWarning = document.getElementById('password-warning');

        function validateInput(input, warningElement) {
            const value = input.value;
            if (dangerousChars.test(value)) {
                warningElement.textContent = MSG_DANGEROUS;
                warningElement.classList.remove('hidden'); // Tampilkan pesan
                input.classList.add('border-red-500', 'focus:ring-red-500');
                registerButton.disabled = true;
                registerButton.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                warningElement.classList.add('hidden'); // Sembunyikan pesan
                input.classList.remove('border-red-500', 'focus:ring-red-500');
                registerButton.disabled = false;
                registerButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        // Terapkan validasi ke password
        if(passwordInput) {
            passwordInput.addEventListener('input', () => validateInput(passwordInput, passwordWarning));
        }

        // --- 4. FORMATTER LAINNYA (YANG LAMA TETAP ADA) ---
        
        function setupUsernameInput(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.addEventListener('input', (e) => {
                // Username sangat ketat: hanya huruf, angka, titik, underscore
                let value = e.target.value.replace(/[^a-zA-Z0-9_.]/g, '');
                e.target.value = value.toLowerCase();
            });
        }

        function setupPhoneFormatter(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.addEventListener('input', (e) => {
                // 1. Hapus semua karakter selain angka
                let value = e.target.value.replace(/\D/g, '');
                
                // 2. Batasi panjang maksimal (misal 13 digit standar Indo)
                value = value.substring(0, 13);
                
                let formattedValue = '';

                if (value.length <= 4) {
                    // 0-4 digit: 0812
                    formattedValue = value;
                } else if (value.length <= 7) {
                    // 5-7 digit: 0812-123
                    formattedValue = value.substring(0, 4) + '-' + value.substring(4);
                } else if (value.length <= 10) {
                    // [LOGIKA BARU] 8-10 digit: 0812-123-123 
                    // Ini menangani permintaan Anda untuk format grup 3 digit di tengah
                    formattedValue = value.substring(0, 4) + '-' + value.substring(4, 7) + '-' + value.substring(7);
                } else {
                    // 11+ digit (Standar): 0812-1234-5678
                    formattedValue = value.substring(0, 4) + '-' + value.substring(4, 8) + '-' + value.substring(8);
                }
                
                e.target.value = formattedValue;
            });
        }

        function setupTextOnlyInput(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^a-zA-Z\s]/g, '');
                e.target.value = value;
            });
        }
        
        function setupSchoolInput(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^a-zA-Z0-9\s]/g, '');
                e.target.value = value;
            });
        }

        // Inisialisasi Formatter
        setupUsernameInput('username');
        setupPhoneFormatter('phone_number');
        setupPhoneFormatter('parent_phone');
        setupTextOnlyInput('name');
        setupTextOnlyInput('birth_place');
        setupTextOnlyInput('parent_name');
        setupSchoolInput('school');
    </script>
</body>
</html>
<?php
ob_end_flush(); 
?>