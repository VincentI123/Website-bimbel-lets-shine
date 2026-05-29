<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); 
// --- AKHIR BAGIAN BARU ---


// --- "OTAK" BAHASA (VERSI UNIVERSAL FINAL) ---

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

// 8. Fungsi Helper Format Tanggal Manual (Agar Mandarin Aman)
if (!function_exists('date_format_intl')) {
    function date_format_intl($date_string, $lang) {
        $timestamp = strtotime($date_string);
        if ($lang == 'cn') {
            // Format Mandarin: YYYY年MM月DD日 星期X
            $days = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
            return date('Y', $timestamp) . '年' . date('m', $timestamp) . '月' . date('d', $timestamp) . '日 ' . $days[date('w', $timestamp)];
        } elseif ($lang == 'id') {
            // Format Indonesia
            $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return $days[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
        } else {
            // Format Inggris
            return date('l, d F Y', $timestamp);
        }
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI ANDA ---
$db = Database::getInstance()->getConnection();

// Total Students
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$totalStudents = $stmt->fetchColumn();

// Total Teachers
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'");
$totalTeachers = $stmt->fetchColumn();

// Total Subjects
$stmt = $db->query("SELECT COUNT(*) FROM subjects");
$totalSubjects = $stmt->fetchColumn();


// --- [LOGIKA PENGINGAT H-1 CERDAS] ---
$today_date = date('Y-m-d');
$today_weekday_index = (int)date('w'); // 0=Minggu, 1=Senin, ..., 6=Sabtu
$seven_days_later = date('Y-m-d', strtotime('+7 days'));

// Peta nama hari (Inggris) ke indeks (0=Minggu)
$day_index_map = [
    'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
    'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6
];

// 1. Ambil semua ujian yang akan datang dalam 7 hari ke depan
$stmt_exams = $db->prepare("
    SELECT 
        e.exam_date, 
        s.name as subject_name, 
        u_student.id as student_id,
        u_student.name as student_name, 
        u_teacher.name as teacher_name,
        u_student.available_days,
        u_student.available_times
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN users u_teacher ON e.teacher_id = u_teacher.id
    LEFT JOIN users u_student ON e.student_id = u_student.id
    WHERE e.exam_date > :today -- Hanya ujian di masa depan
    AND e.exam_date <= :seven_days_later
    AND u_student.id IS NOT NULL
    AND u_student.available_days IS NOT NULL AND u_student.available_days != ''
    ORDER BY e.exam_date ASC, s.name ASC
");
$stmt_exams->execute([
    ':today' => $today_date,
    ':seven_days_later' => $seven_days_later
]);
$all_upcoming_exams = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);

$reminders_for_today_flat = [];

// 2. Loop setiap entri ujian untuk dicek
foreach ($all_upcoming_exams as $exam) {
    
    $exam_date_obj = new DateTime($exam['exam_date']);
    
    $student_lesson_indices = [];
    $days_array = array_map('trim', explode(',', $exam['available_days']));
    foreach ($days_array as $day_name) {
        if (isset($day_index_map[$day_name])) {
            $student_lesson_indices[] = $day_index_map[$day_name];
        }
    }
    if (empty($student_lesson_indices)) {
        continue; 
    }
    
    $last_lesson_day_index = -1;
    
    // Cek 7 hari ke belakang dari H-1 ujian
    for ($i = 1; $i <= 7; $i++) {
        $check_date_obj = clone $exam_date_obj;
        $check_date_obj->modify("-$i day"); 

        $check_day_index = (int)$check_date_obj->format('w');

        if (in_array($check_day_index, $student_lesson_indices)) {
            $last_lesson_day_index = $check_day_index;
            break; 
        }
    }

    // 3. PERIKSA FINAL: Apakah hari les terakhir itu adalah HARI INI?
    if ($last_lesson_day_index === $today_weekday_index) {
        $reminders_for_today_flat[] = [
            'subject_name' => $exam['subject_name'],
            'exam_date'    => $exam['exam_date'],
            'teacher_name' => $exam['teacher_name'],
            'student_name' => $exam['student_name'],
            'student_times'=> $exam['available_times']
        ];
    }
}
// --- [AKHIR BLOK LOGIKA BARU] ---
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
<div class="space-y-8">
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                <?php echo sprintf(__('dashboard_welcome'), htmlspecialchars($user['name'])); ?>
            </h1>
            <p class="text-gray-500"><?php echo __('dashboard_subtitle'); ?></p>
        </div>
    </div>
    <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-500 text-sm font-medium"><?php echo __('stat_total_students'); ?></p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $totalStudents; ?></p>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <i data-lucide="users" class="text-orange-500"></i>
                </div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-500 text-sm font-medium"><?php echo __('stat_total_teachers'); ?></p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $totalTeachers; ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <i data-lucide="award" class="text-blue-500"></i>
                </div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-500 text-sm font-medium"><?php echo __('stat_total_subjects'); ?></p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $totalSubjects; ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i data-lucide="book-text" class="text-green-500"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo __('quick_access_title'); ?></h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="admin-dashboard.php?page=users" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="users" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_manage_users'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=teachers" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="award" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_manage_teachers'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=subjects" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="book" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_manage_subjects'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=schedules" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="calendar-plus" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_manage_schedules'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=exams" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="file-text" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_manage_exams'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=enrollments" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="user-plus" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_enroll_students'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=approvals" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="check-circle" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_approvals'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=banners" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="image" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_manage_banners'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=times" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="clock" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo __('quick_manage_times'); ?></span>
            </a>
            <a href="admin-dashboard.php?page=landing-settings" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center justify-center">
                <i data-lucide="layout-template" class="w-12 h-12 text-orange-500 mb-2"></i>
                <span class="text-sm font-medium text-center text-gray-700"><?php echo __('quick_landing_settings'); ?></span>
            </a>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo __('admin_reminder_title'); ?></h2>
        <div class="space-y-4">
            <?php if (empty($reminders_for_today_flat)): ?>
                <p class="text-center text-gray-500 py-8"><?php echo __('admin_reminder_empty'); ?></p>
            <?php else: ?>
                <?php foreach ($reminders_for_today_flat as $reminder): 
    // GUNAKAN FUNGSI BARU AGAR MANDARIN TIDAK ERROR
    $exam_date_display = date_format_intl($reminder['exam_date'], $currentLang);
    
    $exam_date_obj_check = new DateTime($reminder['exam_date']);
                    $now_check = new DateTime('today');
                    $interval_check = $now_check->diff($exam_date_obj_check);
                    $days_until_exam = $interval_check->days;
                ?>
                <div class="flex flex-col md:flex-row items-start justify-between p-4 rounded-xl bg-yellow-50 border-l-4 border-yellow-500">
                    <div class="flex-grow">
                        
                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($reminder['student_name']); ?></p>
                        
                        <p class="text-sm text-gray-500">
                            <?php echo __('admin_reminder_exam_label'); ?> <span class="font-medium text-gray-700"><?php echo htmlspecialchars($reminder['subject_name']); ?></span>
                        </p>
                        <p class="text-sm text-gray-500 mb-2">
                            <?php echo __('admin_reminder_date_label'); ?> <?php echo $exam_date_display; ?>
                        </p>
                        <p class="text-sm text-gray-500 mb-2">
                            <span class="font-medium"><?php echo __('admin_reminder_teacher_label'); ?></span> <?php echo htmlspecialchars($reminder['teacher_name']); ?>
                        </p>
                        
                        <?php if (!empty($reminder['student_times'])): ?>
                        <div class="text-sm text-gray-600 mt-2">
                            <p class="font-medium mb-1"><?php echo __('admin_reminder_time_label'); ?></p>
                            <div class="pl-0 text-xs text-gray-600 font-medium bg-yellow-100 inline-block px-2 py-1 rounded">
                                <i data-lucide="clock" class="w-3 h-3 inline-block mr-1 -mt-0.5"></i>
                                <?php echo htmlspecialchars($reminder['student_times']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-yellow-600 font-semibold text-left md:text-right flex-shrink-0 ml-0 md:ml-4 mt-3 md:mt-0">
                        <i data-lucide="alert-triangle" class="w-5 h-5 inline-block"></i>
                        <?php 
                        if ($days_until_exam <= 1) {
                            echo sprintf(__('myexams_day_left_singular'), $days_until_exam);
                        } else {
                            echo sprintf(__('myexams_day_left_plural'), $days_until_exam);
                        }
                        ?>
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