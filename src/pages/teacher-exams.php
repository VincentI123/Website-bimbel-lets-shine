<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Mulai menangkap output

// --- "OTAK" BAHASA (VERSI 3 BAHASA: ID, EN, CN) ---

// 1. Selalu mulai session
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

// 5. Mengatur "Locale" PHP (ID, EN, CN)
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
$teacher_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();


// --- [LOGIKA BARU: PENGINGAT H-1 CERDAS UNTUK GURU] ---
$today_date = date('Y-m-d');
$today_weekday_index = (int)date('w'); // 0=Minggu, 1=Senin, ..., 6=Sabtu
$seven_days_later = date('Y-m-d', strtotime('+7 days'));

$day_index_map = [
    'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
    'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6
];

// 1. Ambil semua ujian GURU INI yang akan datang
$stmt_exams = $db->prepare("
    SELECT 
        e.exam_date, 
        s.name as subject_name, 
        u_student.id as student_id,
        u_student.name as student_name, 
        u_student.available_days,
        u_student.available_times
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    LEFT JOIN users u_student ON e.student_id = u_student.id
    WHERE e.exam_date > :today
    AND e.exam_date <= :seven_days_later
    AND e.teacher_id = :teacher_id 
    AND u_student.id IS NOT NULL
    AND u_student.available_days IS NOT NULL AND u_student.available_days != ''
    ORDER BY e.exam_date ASC, s.name ASC
");
$stmt_exams->execute([
    ':today' => $today_date,
    ':seven_days_later' => $seven_days_later,
    ':teacher_id' => $teacher_id 
]);
$all_upcoming_exams = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);

$reminders_for_today_flat = [];

// 2. Loop setiap entri ujian untuk dicek
foreach ($all_upcoming_exams as $exam) {
    
    $exam_date_obj = new DateTime($exam['exam_date']);
    $exam_day_index = (int)$exam_date_obj->format('w'); 
    
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
            'student_name' => $exam['student_name'],
            'student_times'=> $exam['available_times']
        ];
    }
}
// --- [AKHIR LOGIKA BARU] ---

?>

<?php
// --- Logika Link Bahasa ---
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}
unset($query_params['lang']); 

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

<div class="space-y-6">
    <div class="flex flex-col md:flex-row items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0"><?php echo __('teacher_exams_title'); ?></h1>
    </div>

<?php
if (!function_exists('date_format_intl')) {
    function date_format_intl($date_string, $lang) {
        $timestamp = strtotime($date_string);
        if ($lang == 'cn') {
            $days = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
            return date('Y', $timestamp) . '年' . date('m', $timestamp) . '月' . date('d', $timestamp) . '日 ' . $days[date('w', $timestamp)];
        } elseif ($lang == 'id') {
            $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return $days[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
        } else {
            return date('l, d F Y', $timestamp);
        }
    }
}
?>

<div class="bg-white p-4 md:p-8 rounded-2xl shadow-lg shadow-orange-900/5">
    <div class="space-y-4">
        <?php if (empty($reminders_for_today_flat)): ?>
            <div class="text-center py-12 text-gray-500 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                <i data-lucide="clipboard-check" class="w-12 h-12 mx-auto text-gray-300 mb-3"></i>
                <p><?php echo __('teacher_exam_no_reminder_today'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($reminders_for_today_flat as $reminder): 
                $exam_date_display = date_format_intl($reminder['exam_date'], $currentLang);
                
                $exam_date_obj_check = new DateTime($reminder['exam_date']);
                $now_check = new DateTime('today');
                $interval_check = $now_check->diff($exam_date_obj_check);
                $days_until_exam = $interval_check->days;

                // --- LOGIKA WARNA UI ---
                if ($days_until_exam <= 1) {
                    $borderClass = "border-red-500";
                    $textClass = "text-red-600";
                    $badgeClass = "bg-red-100 text-red-700";
                    $iconClass = "text-red-500";
                } elseif ($days_until_exam <= 3) {
                    $borderClass = "border-yellow-500";
                    $textClass = "text-yellow-600";
                    $badgeClass = "bg-yellow-100 text-yellow-700";
                    $iconClass = "text-yellow-500";
                } else {
                    $borderClass = "border-green-500";
                    $textClass = "text-green-600";
                    $badgeClass = "bg-green-100 text-green-700";
                    $iconClass = "text-green-500";
                }
            ?>
            
            <div class="relative bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="absolute left-0 top-0 bottom-0 w-1.5 <?php echo str_replace('border-', 'bg-', $borderClass); ?>"></div>

                <div class="p-4 pl-5">
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="font-bold text-lg text-gray-800 leading-tight">
                            <?php echo htmlspecialchars($reminder['student_name']); ?>
                        </h3>
                        <span class="flex-shrink-0 text-xs font-bold px-2.5 py-1 rounded-full <?php echo $badgeClass; ?> flex items-center gap-1">
                            <i data-lucide="clock" class="w-3 h-3"></i>
                            <?php 
                            if ($days_until_exam <= 1) {
                                echo sprintf(__('student_exam_day_left'), $days_until_exam);
                            } else {
                                echo sprintf(__('student_exam_days_left'), $days_until_exam);
                            }
                            ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                        <div class="flex items-start gap-2">
                            <div class="bg-orange-50 p-1.5 rounded text-orange-600 mt-0.5">
                                <i data-lucide="book-open" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-0.5"><?php echo __('teacher_exam_label_exam'); ?></p>
                                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($reminder['subject_name']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <div class="bg-blue-50 p-1.5 rounded text-blue-600 mt-0.5">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-0.5"><?php echo __('teacher_exam_label_date'); ?></p>
                                <p class="text-sm font-semibold text-gray-800"><?php echo $exam_date_display; ?></p>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($reminder['student_times'])): ?>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1 flex items-center gap-1">
                            <i data-lucide="bell-ring" class="w-3 h-3"></i>
                            <?php echo __('teacher_exam_label_lesson_time'); ?>
                        </p>
                        <p class="text-sm font-medium text-gray-800">
                            <?php echo htmlspecialchars($reminder['student_times']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
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