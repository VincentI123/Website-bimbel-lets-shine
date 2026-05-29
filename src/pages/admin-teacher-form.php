<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Mulai menangkap output

// --- "OTAK" BAHASA (VERSI UNIVERSAL REQUEST_URI + MANUAL QUERY + OB CLEAN) ---

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
    unset($query_params['error']); // Hapus error saat ganti bahasa

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
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI HALAMAN ANDA DIMULAI DI SINI ---
$pdo = Database::getInstance()->getConnection();

// Ambil semua mata pelajaran untuk checkbox
$subjects_stmt = $pdo->query("SELECT name FROM subjects ORDER BY name");
$all_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

// Ambil semua slot waktu
$slots_stmt = $pdo->query("SELECT time_label FROM time_slots ORDER BY display_order ASC");
$all_slots = $slots_stmt->fetchAll(PDO::FETCH_COLUMN);

// Daftar hari
$days_translation = [
    'Monday' => __('Monday'), 
    'Tuesday' => __('Tuesday'), 
    'Wednesday' => __('Wednesday'),
    'Thursday' => __('Thursday'), 
    'Friday' => __('Friday'), 
    'Saturday' => __('Saturday')
];
$all_days = array_keys($days_translation);

$teacherId = isset($_GET['edit']) ? $_GET['edit'] : null;
$is_edit = $teacherId !== null;
$teacher = null;

$pageTitleKey = $is_edit ? 'teacher_form_edit_title' : 'teacher_form_add_title';

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan semua data post ke session untuk diisi ulang jika gagal
    $_SESSION['form_data'] = $_POST;

    // Ambil semua field dari POST
    $name = isset($_POST['name']) ? ucwords(strtolower($_POST['name'])) : '';
    $address = isset($_POST['address']) ? $_POST['address'] : '';
    $phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
    $birth_place = isset($_POST['birth_place']) ? ucwords(strtolower($_POST['birth_place'])) : '';
    $birth_date = isset($_POST['birth_date']) ? $_POST['birth_date'] : '';
    $subjects = isset($_POST['subjects']) ? implode(', ', $_POST['subjects']) : '';
    
    // --- Logika Status, Hari, dan Waktu ---
    $employment_status = isset($_POST['employment_status']) ? $_POST['employment_status'] : 'part-time';
    $available_days = ''; 
    $available_times = ''; 
    
    if ($employment_status === 'part-time') {
        $available_days = isset($_POST['available_days']) ? implode(', ', $_POST['available_days']) : '';
        $available_times = isset($_POST['available_times']) ? implode(', ', $_POST['available_times']) : ''; 
    }

    // --- Ambil Username & Password (bisa kosong jika mode edit) ---
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    try {
        if ($is_edit) {
            // --- KONDISI 1: EDIT GURU ---
            $sql = "UPDATE users SET 
                        name = :name, address = :address, phone_number = :phone_number,
                        birth_place = :birth_place, birth_date = :birth_date, subjects = :subjects,
                        employment_status = :employment_status, available_days = :available_days,
                        available_times = :available_times"; 
            
            // Definisikan params awal
            $params = [
                ':name' => $name, 
                ':address' => $address, 
                ':phone_number' => $phone_number, 
                ':birth_place' => $birth_place, 
                ':birth_date' => $birth_date, 
                ':subjects' => $subjects,
                ':employment_status' => $employment_status, 
                ':available_days' => $available_days, 
                ':available_times' => $available_times, 
                ':id' => $teacherId
            ];

            // Jika password diisi, tambahkan ke query dan params
            if (!empty($password)) {
                $sql .= ", password = :password";
                $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = :id AND role = 'teacher'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params); // Eksekusi dengan variabel $params

            $_SESSION['flash_message'] = __('teacher_success_update'); 

        } else {
            // --- KONDISI 2: TAMBAH GURU BARU ---
            
            // Password default jika kosong
            if (empty($password)) {
                $password = '123456'; 
            }
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (name, username, password, role, address, phone_number, birth_place, birth_date, subjects, employment_status, available_days, available_times, status) 
                    VALUES (:name, :username, :password, 'teacher', :address, :phone_number, :birth_place, :birth_date, :subjects, :employment_status, :available_days, :available_times, 'approved')";
            
            // Definisikan params secara eksplisit
            $params = [
                ':name' => $name, 
                ':username' => $username, 
                ':password' => $hashedPassword,
                ':address' => $address, 
                ':phone_number' => $phone_number, 
                ':birth_place' => $birth_place, 
                ':birth_date' => $birth_date, 
                ':subjects' => $subjects,
                ':employment_status' => $employment_status, 
                ':available_days' => $available_days,
                ':available_times' => $available_times
            ];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params); // Eksekusi dengan variabel $params yang sudah didefinisikan

            $_SESSION['flash_message'] = __('teacher_success_add'); 
        }
        
        unset($_SESSION['form_data']); 
        ob_end_clean();
        header("Location: admin-dashboard.php?page=teachers");
        exit;

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { 
            $error_param = 'username_exists';
            $url_parts = parse_url($_SERVER['REQUEST_URI']);
            $path = $url_parts['path'];
            $query_params = array();
            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query_params);
            }
            $query_params['error'] = $error_param;
            $redirectUrl = $path . '?' . http_build_query($query_params);
            ob_end_clean();
            header("Location: " . $redirectUrl);
            exit;
        } else {
            throw $e;
        }
    }
}

// --- DATA PREPARATION FOR VIEW ---
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];

// Ambil data dari form_data (jika error), database (jika edit), atau kosong
$name_val = isset($form_data['name']) ? $form_data['name'] : (isset($teacher['name']) ? ucwords(strtolower($teacher['name'])) : '');
$address_val = isset($form_data['address']) ? $form_data['address'] : (isset($teacher['address']) ? $teacher['address'] : '');
$phone_number_val = isset($form_data['phone_number']) ? $form_data['phone_number'] : (isset($teacher['phone_number']) ? $teacher['phone_number'] : '');
$birth_place_val = isset($form_data['birth_place']) ? $form_data['birth_place'] : (isset($teacher['birth_place']) ? ucwords(strtolower($teacher['birth_place'])) : '');
$birth_date_val = isset($form_data['birth_date']) ? $form_data['birth_date'] : (isset($teacher['birth_date']) ? $teacher['birth_date'] : '');
$username_val = isset($form_data['username']) ? $form_data['username'] : (isset($teacher['username']) ? $teacher['username'] : '');

// Pecah string array
$subjects_str = isset($form_data['subjects']) ? $form_data['subjects'] : (isset($teacher['subjects']) ? $teacher['subjects'] : '');
if(is_array($subjects_str)) { $selected_subjects = $subjects_str; } else { $selected_subjects = array_map('trim', explode(',', $subjects_str)); }

$days_str = isset($form_data['available_days']) ? $form_data['available_days'] : (isset($teacher['available_days']) ? $teacher['available_days'] : '');
if(is_array($days_str)) { $selected_days = $days_str; } else { $selected_days = array_map('trim', explode(',', $days_str)); }

$times_str = isset($form_data['available_times']) ? $form_data['available_times'] : (isset($teacher['available_times']) ? $teacher['available_times'] : '');
if(is_array($times_str)) { $selected_times = $times_str; } else { $selected_times = array_map('trim', explode(',', $times_str)); }

$employment_status_val = isset($form_data['employment_status']) ? $form_data['employment_status'] : (isset($teacher['employment_status']) ? $teacher['employment_status'] : 'part-time');

// Hapus session form data agar tidak muncul lagi saat refresh sukses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['form_data']);
}
?>

<?php
// --- Logika Link Bahasa ---
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}
unset($query_params['error']);

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

<div class="max-w-4xl mx-auto bg-gray-50 p-8 rounded-lg">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __($pageTitleKey); ?></h1>
        <a href="admin-dashboard.php?page=teachers" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center">
            <i data-lucide="arrow-left" class="w-5 h-5 mr-2"></i>
            <?php echo __('teacher_form_back_link'); ?>
        </a>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'username_exists'): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo __('register_error_username_taken'); ?></p>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST">

        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-md mb-6">
            <h2 class="text-xl font-semibold text-orange-600 mb-6 border-b-2 border-orange-200 pb-4"><?php echo __('teacher_form_section_details'); ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('teacher_form_label_name'); ?></label>
                    <div class="relative group">
                        <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="text" name="name" id="name" placeholder="<?php echo __('teacher_form_placeholder_name'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($name_val); ?>" 
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('teacher_error_name')); ?>')" 
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('teacher_form_label_address'); ?></label>
                     <div class="relative group">
                        <i data-lucide="map-pin" class="absolute left-3 top-3 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <textarea name="address" id="address" placeholder="<?php echo __('teacher_form_placeholder_address'); ?>" rows="3" 
                                  class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition"
                                  required
                                  oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_address')); ?>')"
                                  oninput="this.setCustomValidity('')"><?php echo htmlspecialchars($address_val); ?></textarea>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label for="phone_number" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('teacher_form_label_phone'); ?></label>
                     <div class="relative group">
                        <i data-lucide="phone" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="tel" name="phone_number" id="phone_number" placeholder="<?php echo __('teacher_form_placeholder_phone'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($phone_number_val); ?>" inputmode="numeric" autocomplete="tel-national"
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('teacher_error_phone')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>

                 <div>
                    <label for="birth_place" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('teacher_form_label_birth_place'); ?></label>
                     <div class="relative group">
                        <i data-lucide="home" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="text" name="birth_place" id="birth_place" placeholder="<?php echo __('teacher_form_placeholder_birth_place'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($birth_place_val); ?>"
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_birthplace')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
                <div>
                    <label for="birth_date" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('teacher_form_label_birth_date'); ?></label>
                     <div class="relative group">
                        <i data-lucide="calendar" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="date" name="birth_date" id="birth_date" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($birth_date_val); ?>" max="<?php echo date('Y-m-d'); ?>"
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_birthdate')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-600 mb-2"><?php echo __('teacher_form_label_subjects'); ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-2">
                        <?php
                        foreach ($all_subjects as $subject_name) {
                            $checked = in_array($subject_name, $selected_subjects) ? 'checked' : '';
                            $subject_id = 'subject-' . strtolower(str_replace(' ', '-', $subject_name));
                            echo "<div class='flex items-center'>";
                            echo "<input type='checkbox' name='subjects[]' id='{$subject_id}' value='" . htmlspecialchars($subject_name) . "' class='h-4 w-4 rounded text-orange-600 border-gray-300 focus:ring-orange-500' {$checked}> ";
                            echo "<label for='{$subject_id}' class='ml-2 block text-sm text-gray-900'>" . htmlspecialchars($subject_name) . "</label>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-600 mb-2"><?php echo __('teacher_form_label_employment_status'); ?></label>
                    <div class="flex items-center gap-x-6">
                        <div class="flex items-center">
                            <input type="radio" name="employment_status" id="status_full_time" value="full-time" class="h-4 w-4 text-orange-600 border-gray-300 focus:ring-orange-500" <?php echo ($employment_status_val === 'full-time') ? 'checked' : ''; ?>>
                            <label for="status_full_time" class="ml-2 block text-sm text-gray-900"><?php echo __('teacher_form_status_full_time'); ?></label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="employment_status" id="status_part_time" value="part-time" class="h-4 w-4 text-orange-600 border-gray-300 focus:ring-orange-500" <?php echo ($employment_status_val === 'part-time') ? 'checked' : ''; ?>>
                            <label for="status_part_time" class="ml-2 block text-sm text-gray-900"><?php echo __('teacher_form_status_part_time'); ?></label>
                        </div>
                    </div>
                </div>
                
                <div class="md:col-span-2" id="availableDaysContainer">
                    <label class="block text-sm font-medium text-gray-600 mb-2"><?php echo __('teacher_form_label_days'); ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-2">
                        <?php
                        foreach ($all_days as $day_key) { 
                            $checked = in_array($day_key, $selected_days) ? 'checked' : '';
                            $day_id = 'day-' . strtolower($day_key);
                            echo "<div class='flex items-center'>";
                            echo "<input type='checkbox' name='available_days[]' id='{$day_id}' value='{$day_key}' class='h-4 w-4 rounded text-orange-600 border-gray-300 focus:ring-orange-500' {$checked}> ";
                            echo "<label for='{$day_id}' class='ml-2 block text-sm text-gray-900'>" . $days_translation[$day_key] . "</label>"; 
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="md:col-span-2" id="availableTimesContainer">
                    <label class="block text-sm font-medium text-gray-600 mb-2"><?php echo __('teacher_form_label_times'); ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-2">
                        <?php
                        foreach ($all_slots as $slot_label) { 
                            $checked = in_array($slot_label, $selected_times) ? 'checked' : '';
                            $slot_id = 'slot-' . md5(htmlspecialchars($slot_label)); 
                            echo "<div class='flex items-center'>";
                            echo "<input type='checkbox' name='available_times[]' id='{$slot_id}' value='" . htmlspecialchars($slot_label) . "' class='h-4 w-4 rounded text-orange-600 border-gray-300 focus:ring-orange-500' {$checked}> ";
                            echo "<label for='{$slot_id}' class='ml-2 block text-sm text-gray-900'>" . htmlspecialchars($slot_label) . "</label>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$is_edit): ?>
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-md mb-6">
            <h2 class="text-xl font-semibold text-orange-600 mb-6 border-b-2 border-orange-200 pb-4"><?php echo __('teacher_form_section_account'); ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                 <div>
                    <label for="username" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('teacher_form_label_username'); ?></label>
                    <div class="relative group">
                        <i data-lucide="user-circle" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="text" name="username" id="username" placeholder="<?php echo __('teacher_form_placeholder_username'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($username_val); ?>" 
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_username')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('teacher_form_label_password'); ?></label>
                     <div class="relative group">
                        <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="password" name="password" id="password" 
                            class="pl-10 pr-10 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                            placeholder="<?php echo __('teacher_form_placeholder_password_add'); ?>" 
                            required
                            minlength="6" 
                            maxlength="50"
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_password_length')); ?>')"
                            oninput="this.setCustomValidity('')">
                        
                        <button type="button" onclick="togglePasswordVisibility('password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none" tabindex="-1">
                            <i data-lucide="eye" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-8 flex justify-end gap-4">
            <a href="admin-dashboard.php?page=teachers" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">
                <?php echo __('teacher_form_button_cancel'); ?>
            </a>
            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center gap-2">
                <i data-lucide="<?php echo $is_edit ? 'check-circle' : 'plus-circle'; ?>" class="w-5 h-5"></i>
                <span><?php echo $is_edit ? __('teacher_form_button_update') : __('teacher_form_button_add'); ?></span>
            </button>
        </div>
    </form>
</div>

<script>
    // --- KONFIGURASI PESAN ERROR DARI PHP ---
    const MSG_SUBJECTS = '<?php echo addslashes(__('teacher_error_subjects')); ?>';
    const MSG_DAYS = '<?php echo addslashes(__('teacher_error_days')); ?>';
    const MSG_TIMES = '<?php echo addslashes(__('teacher_error_times')); ?>';

    document.addEventListener('DOMContentLoaded', function() {
        // Elemen DOM
        const fullTimeRadio = document.getElementById('status_full_time');
        const partTimeRadio = document.getElementById('status_part_time');
        const daysContainer = document.getElementById('availableDaysContainer');
        const timesContainer = document.getElementById('availableTimesContainer');

        // 1. Fungsi Mengatur Tampilan & Validasi Berdasarkan Status
        function toggleEmploymentStatus() {
            if (fullTimeRadio && fullTimeRadio.checked) {
                if(daysContainer) daysContainer.style.display = 'none';
                if(timesContainer) timesContainer.style.display = 'none';
                
                setCheckboxRequired('available_days', false);
                setCheckboxRequired('available_times', false);
                
            } else if (partTimeRadio && partTimeRadio.checked) {
                if(daysContainer) daysContainer.style.display = 'block';
                if(timesContainer) timesContainer.style.display = 'block';
                
                setCheckboxRequired('available_days', true, MSG_DAYS);
                setCheckboxRequired('available_times', true, MSG_TIMES);
            }
        }

        if(fullTimeRadio && partTimeRadio) {
            fullTimeRadio.addEventListener('change', toggleEmploymentStatus);
            partTimeRadio.addEventListener('change', toggleEmploymentStatus);
            toggleEmploymentStatus(); // Jalankan saat load
        }
        
        // 2. Jalankan Validasi Mata Pelajaran
        setupCheckboxGroupValidation('subjects', MSG_SUBJECTS);
    });

    // --- FUNGSI BARU: TOGGLE PASSWORD ---
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

    // --- FUNGSI VALIDASI CHECKBOX ---
    function setCheckboxRequired(groupName, isRequired, errorMsg = '') {
        const checkboxes = document.querySelectorAll(`input[name="${groupName}[]"]`);
        const firstCheckbox = checkboxes[0];
        if (!firstCheckbox) return;

        if (isRequired) {
            setupCheckboxGroupValidation(groupName, errorMsg);
            const isChecked = document.querySelectorAll(`input[name="${groupName}[]"]:checked`).length > 0;
            if (!isChecked) {
                firstCheckbox.setCustomValidity(errorMsg);
                firstCheckbox.setAttribute('required', 'required');
            }
        } else {
            firstCheckbox.setCustomValidity('');
            firstCheckbox.removeAttribute('required');
        }
    }

    function setupCheckboxGroupValidation(groupName, errorMsg) {
        const checkboxes = document.querySelectorAll(`input[name="${groupName}[]"]`);
        const firstCheckbox = checkboxes[0];
        if (!firstCheckbox) return;

        function checkValidity() {
            const container = firstCheckbox.closest('.md\\:col-span-2');
            if (container && container.style.display === 'none') {
                firstCheckbox.setCustomValidity('');
                firstCheckbox.removeAttribute('required');
                return;
            }
            const isChecked = document.querySelectorAll(`input[name="${groupName}[]"]:checked`).length > 0;
            if (isChecked) {
                firstCheckbox.setCustomValidity('');
                firstCheckbox.removeAttribute('required');
            } else {
                firstCheckbox.setCustomValidity(errorMsg);
                firstCheckbox.setAttribute('required', 'required');
            }
        }

        checkboxes.forEach(cb => {
            cb.removeEventListener('change', checkValidity); 
            cb.addEventListener('change', checkValidity);
        });

        const form = document.querySelector('form');
        if (form) form.addEventListener('submit', checkValidity);
    }

    // --- FORMATTER INPUT ---
    function setupUsernameInput(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('input', function() {
            let sanitized = this.value.replace(/[^a-zA-Z0-9_.]/g, '').toLowerCase();
            if (this.value !== sanitized) {
                this.value = sanitized;
            }
            this.setCustomValidity('');
        });
    }

    function setupPasswordInput(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('input', function() {
            if (this.value.includes(' ')) {
                this.value = this.value.replace(/\s/g, '');
            }
            this.setCustomValidity('');
        });
    }

    function setupPhoneFormatter(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '').substring(0, 16);
            if (value.length > 4 && value.length <= 8) value = value.slice(0,4) + '-' + value.slice(4);
            else if (value.length > 8) value = value.slice(0,4) + '-' + value.slice(4,8) + '-' + value.slice(8);
            e.target.value = value;
        });
    }

    function setupTextOnlyInput(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '');
        });
    }

    // --- INISIALISASI ---
    setupUsernameInput('username');
    setupPasswordInput('password');
    setupPhoneFormatter('phone_number');
    setupTextOnlyInput('name');
    setupTextOnlyInput('birth_place');
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

<?php
ob_end_flush(); 
?>