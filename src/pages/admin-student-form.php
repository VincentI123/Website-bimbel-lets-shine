<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// --- "OTAK" BAHASA ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Reset session form_data
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
} else {
    $form_data = [];
}

$defaultLang = 'id';

// Cek Ganti Bahasa
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];
    $query_params = array();
    if (isset($url_parts['query'])) { parse_str($url_parts['query'], $query_params); }
    unset($query_params['lang']); 
    unset($query_params['error']);
    $queryStringParts = array();
    foreach ($query_params as $key => $value) { $queryStringParts[] = urlencode($key) . '=' . urlencode($value); }
    $queryString = implode('&', $queryStringParts);
    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');
    ob_end_clean(); 
    header("Location: " . $redirectUrl);
    exit;
}

$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;

// Locale & Bahasa
if ($currentLang == 'id') { setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian'); } 
elseif ($currentLang == 'cn') { setlocale(LC_TIME, 'zh_CN.UTF-8', 'Chinese'); } 
else { setlocale(LC_TIME, 'en_US.UTF-8', 'English'); }

$langFile = __DIR__ . '/lang/' . $currentLang . '.php'; 
if (file_exists($langFile)) { $lang = include $langFile; } 
else { $defaultFile = __DIR__ . '/lang/' . $defaultLang . '.php'; $lang = (file_exists($defaultFile)) ? include $defaultFile : array(); }

if (!function_exists('__')) { function __($key) { global $lang; return (isset($lang[$key]) ? $lang[$key] : $key); } }

// --- KODE LOGIKA UTAMA ---
$pdo = Database::getInstance()->getConnection();

// Data Master untuk Form
$subjects_stmt = $pdo->query("SELECT name FROM subjects ORDER BY name");
$all_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

$slots_stmt = $pdo->query("SELECT time_label FROM time_slots ORDER BY display_order ASC");
$all_slots = $slots_stmt->fetchAll(PDO::FETCH_COLUMN);

$days_translation = [
    'Monday' => __('Monday'), 'Tuesday' => __('Tuesday'), 'Wednesday' => __('Wednesday'),
    'Thursday' => __('Thursday'), 'Friday' => __('Friday'), 'Saturday' => __('Saturday')
];
$all_days = array_keys($days_translation);

$studentId = isset($_GET['edit']) ? $_GET['edit'] : null;
$is_edit = $studentId !== null;
$student = null;
$pageTitleKey = $is_edit ? 'student_form_edit_title' : 'student_form_add_title';

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) { header("Location: admin-dashboard.php?page=enrollments"); exit; }
}

// --- PROSES SIMPAN DATA (INI YANG PENTING!) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['form_data'] = $_POST;

    // --- FUNGSI PEMBERSIH INPUT (KOMPATIBEL PHP 5.6) ---
    // Fungsi ini menghapus karakter berbahaya seperti < > ? " ' agar tidak bisa di-hack
    if (!function_exists('clean_input_secure')) {
        function clean_input_secure($data) {
            // Cek apakah data ada, jika tidak kosongkan (pengganti ??)
            $str = isset($data) ? $data : ''; 
            $str = trim($str);
            // Hanya izinkan Huruf, Angka, Spasi, Titik, Koma, Strip, Garis Miring, Pagar
            // Karakter lain akan dihapus otomatis
            return preg_replace('/[^a-zA-Z0-9\s\.\,\-\'\/\#]/', '', $str);
        }
    }

    // 1. AMBIL & BERSIHKAN INPUT (VALIDASI KEAMANAN)
    
    // Nama: Bersihkan karakter aneh, lalu buat Huruf Kapital Awal
    $raw_name = isset($_POST['name']) ? $_POST['name'] : '';
    $name = ucwords(strtolower(clean_input_secure($raw_name)));

    // Alamat
    $raw_address = isset($_POST['address']) ? $_POST['address'] : '';
    $address = clean_input_secure($raw_address);

    // No HP: Hanya izinkan angka, +, -, dan spasi
    $raw_phone = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
    $phone_number = preg_replace('/[^0-9\+\-\s]/', '', trim($raw_phone));

    // Tempat Lahir
    $raw_birth_place = isset($_POST['birth_place']) ? $_POST['birth_place'] : '';
    $birth_place = ucwords(strtolower(clean_input_secure($raw_birth_place)));

    // Tanggal Lahir (Format date aman)
    $birth_date = isset($_POST['birth_date']) ? $_POST['birth_date'] : '';

    // Grade Level (Radio Button) - Validasi Pilihan
    $grade_input = isset($_POST['grade_level']) ? $_POST['grade_level'] : '';
    $allowed_grades = array('Pre-Nursery', 'Kindergarten', 'Elementary', 'Junior High', 'Senior High');
    $grade_level = in_array($grade_input, $allowed_grades) ? $grade_input : '';

    // Sekolah
    $raw_school = isset($_POST['school']) ? $_POST['school'] : '';
    $school = ucwords(strtolower(clean_input_secure($raw_school)));

    // Orang Tua
    $raw_parent = isset($_POST['parent_name']) ? $_POST['parent_name'] : '';
    $parent_name = ucwords(strtolower(clean_input_secure($raw_parent)));

    // No HP Orang Tua
    $raw_parent_phone = isset($_POST['parent_phone']) ? $_POST['parent_phone'] : '';
    $parent_phone = preg_replace('/[^0-9\+\-\s]/', '', trim($raw_parent_phone));
    
    // 2. Tangkap Array Checkbox & Ubah jadi String
    // Gunakan array() manual agar aman di PHP versi sangat lama
    $subjects_arr = isset($_POST['subjects']) ? $_POST['subjects'] : array();
    $subjects = implode(', ', $subjects_arr);

    $days_arr = isset($_POST['available_days']) ? $_POST['available_days'] : array();
    $available_days = implode(', ', $days_arr);
    
    $times_arr = isset($_POST['available_times']) ? $_POST['available_times'] : array();
    $available_times = implode(', ', $times_arr);
    try {
        if ($is_edit) {
            // --- LOGIKA UPDATE (EDIT SISWA) ---
            $sql = "UPDATE users SET 
                        name = :name, address = :address, phone_number = :phone_number,
                        birth_place = :birth_place, birth_date = :birth_date, 
                        grade_level = :grade_level, school = :school, 
                        subjects = :subjects, 
                        parent_name = :parent_name, parent_phone = :parent_phone,
                        available_days = :available_days, 
                        available_times = :available_times
                    WHERE id = :id AND role = 'student'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name, 
                ':address' => $address, 
                ':phone_number' => $phone_number, 
                ':birth_place' => $birth_place, 
                ':birth_date' => $birth_date, 
                ':grade_level' => $grade_level, 
                ':school' => $school, 
                ':subjects' => $subjects, 
                ':parent_name' => $parent_name, 
                ':parent_phone' => $parent_phone, 
                ':available_days' => $available_days,
                ':available_times' => $available_times,
                ':id' => $studentId
            ]);

            // [BARU] Simpan pesan sukses EDIT ke session
            $_SESSION['flash_message'] = __('student_success_update');

        } else {
            // --- LOGIKA INSERT (TAMBAH SISWA BARU) ---
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . rand(10,99); // Generate username unik
            $password = '123456';
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (
                        name, username, password, role, address, phone_number, 
                        birth_place, birth_date, grade_level, school, subjects, 
                        parent_name, parent_phone, available_days, available_times, status
                    ) VALUES (
                        :name, :username, :password, 'student', :address, :phone_number, 
                        :birth_place, :birth_date, :grade_level, :school, :subjects, 
                        :parent_name, :parent_phone, :available_days, :available_times, 'approved'
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name, 
                ':username' => $username, 
                ':password' => $hashedPassword,
                ':address' => $address, 
                ':phone_number' => $phone_number, 
                ':birth_place' => $birth_place, 
                ':birth_date' => $birth_date, 
                ':grade_level' => $grade_level, 
                ':school' => $school, 
                ':subjects' => $subjects, 
                ':parent_name' => $parent_name, 
                ':parent_phone' => $parent_phone,
                ':available_days' => $available_days,
                ':available_times' => $available_times
            ]);

            // [BARU] Simpan pesan sukses TAMBAH ke session
            $_SESSION['flash_message'] = __('student_success_add');
        }
        
        unset($_SESSION['form_data']); // Bersihkan data form
        ob_end_clean();
        header("Location: admin-dashboard.php?page=enrollments");
        exit;

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            // Error jika username duplikat (jarang terjadi karena ada random angka, tapi aman untuk ditangani)
            $error_param = 'username_exists';
            header("Location: " . $_SERVER['REQUEST_URI'] . "&error=username_exists");
            exit;
        } else {
            die("Database Error: " . $e->getMessage());
        }
    }
}

// --- PERSIAPAN DATA UNTUK FORM VIEW ---
$name_val = isset($form_data['name']) ? $form_data['name'] : (isset($student['name']) ? ucwords(strtolower($student['name'])) : '');
$address_val = isset($form_data['address']) ? $form_data['address'] : (isset($student['address']) ? $student['address'] : '');
$phone_number_val = isset($form_data['phone_number']) ? $form_data['phone_number'] : (isset($student['phone_number']) ? $student['phone_number'] : '');
$birth_place_val = isset($form_data['birth_place']) ? $form_data['birth_place'] : (isset($student['birth_place']) ? ucwords(strtolower($student['birth_place'])) : '');
$birth_date_val = isset($form_data['birth_date']) ? $form_data['birth_date'] : (isset($student['birth_date']) ? $student['birth_date'] : '');
$grade_level_val = isset($form_data['grade_level']) ? $form_data['grade_level'] : (isset($student['grade_level']) ? $student['grade_level'] : '');
$school_val = isset($form_data['school']) ? $form_data['school'] : (isset($student['school']) ? ucwords(strtolower($student['school'])) : '');
$parent_name_val = isset($form_data['parent_name']) ? $form_data['parent_name'] : (isset($student['parent_name']) ? ucwords(strtolower($student['parent_name'])) : '');
$parent_phone_val = isset($form_data['parent_phone']) ? $form_data['parent_phone'] : (isset($student['parent_phone']) ? $student['parent_phone'] : '');

// Pecah string ke Array untuk Checkbox
$subjects_str = isset($form_data['subjects']) ? $form_data['subjects'] : (isset($student['subjects']) ? $student['subjects'] : '');
$selected_subjects = is_array($subjects_str) ? $subjects_str : array_map('trim', explode(',', $subjects_str));

$days_str = isset($form_data['available_days']) ? $form_data['available_days'] : (isset($student['available_days']) ? $student['available_days'] : '');
$selected_days = is_array($days_str) ? $days_str : array_map('trim', explode(',', $days_str));

$times_str = isset($form_data['available_times']) ? $form_data['available_times'] : (isset($student['available_times']) ? $student['available_times'] : '');
$selected_times = is_array($times_str) ? $times_str : array_map('trim', explode(',', $times_str));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { unset($_SESSION['form_data']); }
?>

<?php
// --- Logika link bahasa ---
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}
unset($query_params['error']); // Hapus parameter error dari link ganti bahasa

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
        <a href="admin-dashboard.php?page=enrollments" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center">
            <i data-lucide="arrow-left" class="w-5 h-5 mr-2"></i>
            <?php echo __('student_form_back_link'); ?>
        </a>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'username_exists'): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
            <p class="font-bold"><?php echo __('form_error_username_exists_title'); ?></p>
            <p><?php echo __('form_error_username_exists_message'); ?></p>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST">

        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-md mb-6">
            <h2 class="text-xl font-semibold text-orange-600 mb-6 border-b-2 border-orange-200 pb-4"><?php echo __('student_form_section_details'); ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('student_form_label_name'); ?></label>
                    <div class="relative group">
                        <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="text" name="name" id="name" placeholder="<?php echo __('student_form_placeholder_name'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($name_val); ?>" 
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_name')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
                
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('student_form_label_address'); ?></label>
                     <div class="relative group">
                        <i data-lucide="map-pin" class="absolute left-3 top-3 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <textarea name="address" id="address" placeholder="<?php echo __('student_form_placeholder_address'); ?>" rows="3" 
                                  class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition"
                                  required
                                  oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_address')); ?>')"
                                  oninput="this.setCustomValidity('')"><?php echo htmlspecialchars($address_val); ?></textarea>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label for="phone_number" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('student_form_label_phone'); ?></label>
                     <div class="relative group">
                        <i data-lucide="phone" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="tel" name="phone_number" id="phone_number" placeholder="<?php echo __('student_form_placeholder_phone'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($phone_number_val); ?>" inputmode="numeric" autocomplete="tel-national"
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_phone')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>

                 <div>
                    <label for="birth_place" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('student_form_label_birth_place'); ?></label>
                     <div class="relative group">
                        <i data-lucide="home" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="text" name="birth_place" id="birth_place" placeholder="<?php echo __('student_form_placeholder_birth_place'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($birth_place_val); ?>"
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_birthplace')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
                <div>
                    <label for="birth_date" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('student_form_label_birth_date'); ?></label>
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
                    <label class="block text-sm font-medium text-gray-600 mb-2"><?php echo __('student_form_label_grade'); ?></label>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <?php
                        $grades_with_keys = [
                            'Pre-Nursery' => 'student_form_grade_prenursery', 
                            'Kindergarten' => 'student_form_grade_kindergarten', 
                            'Elementary' => 'student_form_grade_elementary', 
                            'Junior High' => 'student_form_grade_juniorhigh', 
                            'Senior High' => 'student_form_grade_seniorhigh'
                        ];
                        foreach ($grades_with_keys as $grade_value => $grade_key) {
                            $checked = ($grade_level_val === $grade_value) ? 'checked' : '';
                            $grade_id = 'grade-' . strtolower(str_replace(' ', '-', $grade_value)); 
                            echo "<div class='flex items-center'>";
                            echo "<input type='radio' name='grade_level' id='{$grade_id}' value='{$grade_value}' 
                                         class='h-4 w-4 text-orange-600 border-gray-300 focus:ring-orange-500' 
                                         required
                                         oninvalid=\"this.setCustomValidity('" . addslashes(__('register_error_grade')) . "')\"
                                         oninput=\"this.setCustomValidity('')\"
                                         {$checked}> ";
                            echo "<label for='{$grade_id}' class='ml-2 block text-sm text-gray-900'>" . __($grade_key) . "</label>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>

                 <div class="md:col-span-2">
                    <label for="school" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('student_form_label_school'); ?></label>
                     <div class="relative group">
                        <i data-lucide="school" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="text" name="school" id="school" placeholder="<?php echo __('student_form_placeholder_school'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($school_val); ?>"
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_school')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
                
                 <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-600 mb-2"><?php echo __('student_form_label_subjects'); ?></label>
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
                    <label class="block text-sm font-medium text-gray-600 mb-2"><?php echo __('student_form_label_days'); ?></label>
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

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-600 mb-2"><?php echo __('student_form_label_times'); ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-2">
                        <?php
                        foreach ($all_slots as $slot_label) { 
                            $checked = in_array($slot_label, $selected_times) ? 'checked' : '';
                            $slot_id = 'slot-student-' . md5(htmlspecialchars($slot_label)); 
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

        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-md mb-6">
            <h2 class="text-xl font-semibold text-orange-600 mb-6 border-b-2 border-orange-200 pb-4"><?php echo __('student_form_section_parent'); ?></h2>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                <div class="md:col-span-2">
                    <label for="parent_name" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('student_form_label_parent_name'); ?></label>
                    <div class="relative group">
                        <i data-lucide="user-check" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="text" name="parent_name" id="parent_name" placeholder="<?php echo __('student_form_placeholder_parent_name'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($parent_name_val); ?>"
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_parent_name')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label for="parent_phone" class="block text-sm font-medium text-gray-600 mb-1"><?php echo __('student_form_label_parent_phone'); ?></label>
                    <div class="relative group">
                        <i data-lucide="smartphone" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                        <input type="tel" name="parent_phone" id="parent_phone" placeholder="<?php echo __('student_form_placeholder_parent_phone'); ?>" 
                               class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition" 
                               value="<?php echo htmlspecialchars($parent_phone_val); ?>" inputmode="numeric" autocomplete="tel-national"
                               required
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('register_error_parent_phone')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-4">
            <a href="admin-dashboard.php?page=enrollments" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">
                <?php echo __('student_form_button_cancel'); ?>
            </a>
            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-6 rounded-lg transition-colors flex items-center gap-2">
                <i data-lucide="<?php echo $is_edit ? 'check-circle' : 'plus-circle'; ?>" class="w-5 h-5"></i>
                <span><?php echo $is_edit ? __('student_form_button_update') : __('student_form_button_add'); ?></span>
            </button>
        </div>
    </form>
</div>

<script>
    // --- SCRIPT UNTUK FORMAT NOMOR HP (Update: Support 10 Digit) ---
    function setupPhoneFormatter(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, ''); 
            value = value.substring(0, 16); 
            
            let formattedValue = '';
            
            if (value.length <= 4) {
                formattedValue = value;
            } else if (value.length <= 7) {
                formattedValue = value.substring(0, 4) + '-' + value.substring(4);
            } else if (value.length <= 10) {
                // [LOGIKA BARU] 8-10 digit: 0812-123-123
                formattedValue = value.substring(0, 4) + '-' + value.substring(4, 7) + '-' + value.substring(7);
            } else {
                // 11+ digit: 0812-1234-5678
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

    // Terapkan Formatter
    setupPhoneFormatter('phone_number');
    setupPhoneFormatter('parent_phone');
    setupTextOnlyInput('name');
    setupTextOnlyInput('birth_place');
    setupTextOnlyInput('parent_name');
    setupSchoolInput('school');

    // --- VALIDASI CHECKBOX GROUP (Wajib Pilih Minimal Satu) ---
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

        // Pasang event listener
        checkboxes.forEach(cb => {
            cb.addEventListener('change', checkValidity);
        });

        // Cek saat submit form
        const form = document.querySelector('form'); 
        if(form) {
            form.addEventListener('submit', checkValidity);
        }
        
        // Cek kondisi awal
        checkValidity();
    }

    // Terapkan Validasi Checkbox
    setupCheckboxGroupValidation('subjects', '<?php echo addslashes(__('register_error_subjects')); ?>');
    setupCheckboxGroupValidation('available_days', '<?php echo addslashes(__('register_error_days')); ?>');
    setupCheckboxGroupValidation('available_times', '<?php echo addslashes(__('register_error_times')); ?>');

    // --- FIX VALIDASI RADIO BUTTON ---
    const gradeRadios = document.querySelectorAll('input[name="grade_level"]');
    gradeRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            gradeRadios.forEach(r => r.setCustomValidity(''));
        });
    });
</script>

<?php
ob_end_flush();
?>