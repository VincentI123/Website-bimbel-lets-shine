<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
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
    
    // Ambil URL saat ini dan bersihkan parameter lang
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
if (!function_exists('__')) { // <--- TAMBAHKAN BARIS INI
    function __($key) {
        global $lang;
        if (is_array($lang) && isset($lang[$key])) {
            return $lang[$key];
        } else {
            return $key;
        }
    }
} // <--- TAMBAHKAN KURUNG TUTUP INI
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI DASBOR GURU DIMULAI DI SINI ---
require_once 'src/config/database.php';

$db = Database::getInstance()->getConnection();
$teacher_id = $user['id'];
$today_day_name = date('l');

// Hitung kelas hari ini (Logika ini tetap)
$stmt_today_classes_count = $db->prepare("SELECT COUNT(*) FROM schedules WHERE teacher_id = :teacher_id AND day_of_week = :today");
$stmt_today_classes_count->execute([':teacher_id' => $teacher_id, ':today' => $today_day_name]);
$todayClassesCount = $stmt_today_classes_count->fetchColumn();

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
    AND e.teacher_id = :teacher_id -- Filter hanya untuk guru ini
    AND u_student.id IS NOT NULL
    AND u_student.available_days IS NOT NULL AND u_student.available_days != ''
    ORDER BY e.exam_date ASC, s.name ASC
");
$stmt_exams->execute([
    ':today' => $today_date,
    ':seven_days_later' => $seven_days_later,
    ':teacher_id' => $teacher_id // Tambahkan parameter ID guru
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


// Ambil jadwal hari ini (Logika ini tetap)
$stmt_today_schedule = $db->prepare("
    SELECT s.start_time, sub.name as subject_name, s.room
    FROM schedules s
    JOIN subjects sub ON s.subject_id = sub.id
    WHERE s.teacher_id = :teacher_id AND s.day_of_week = :today
    ORDER BY s.start_time
");
$stmt_today_schedule->execute([':teacher_id' => $teacher_id, ':today' => $today_day_name]);
$todaySchedule = $stmt_today_schedule->fetchAll(PDO::FETCH_ASSOC);

?>

<?php
// --- Logika Link Bahasa (Mempertahankan Parameter URL) ---
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}
unset($query_params['lang']); // Bersihkan parameter lang lama

// Link ID
$query_params_id = $query_params;
$query_params_id['lang'] = 'id';
$url_id = $path . '?' . http_build_query($query_params_id);

// Link EN
$query_params_en = $query_params;
$query_params_en['lang'] = 'en';
$url_en = $path . '?' . http_build_query($query_params_en);

// Link CN
$query_params_cn = $query_params;
$query_params_cn['lang'] = 'cn';
$url_cn = $path . '?' . http_build_query($query_params_cn);
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

<?php
// --- FUNGSI FORMAT TANGGAL MANUAL (Agar Mandarin tidak Error di Windows/Linux) ---
function date_format_intl($date_string, $lang) {
    $timestamp = strtotime($date_string);
    if ($lang == 'cn') {
        // Format Mandarin: YYYY年MM月DD日 星期X
        $days = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
        return date('Y', $timestamp) . '年' . date('m', $timestamp) . '月' . date('d', $timestamp) . '日 ' . $days[date('w', $timestamp)];
    } elseif ($lang == 'id') {
        // Format Indo manual agar aman
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $days[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
    } else {
        // Format Inggris standar
        return date('l, d F Y', $timestamp);
    }
}
$display_date = date_format_intl(date('Y-m-d'), $currentLang);
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?php echo sprintf(__('dashboard_welcome'), htmlspecialchars($user['name'])); ?></h1>
            <p class="text-gray-500"><?php echo __('teacher_dashboard_subtitle'); ?></p>
        </div>
    </div>
    <div class="grid md:grid-cols-2 gap-6">
        <div class="stat-card">
            <h3 class="font-semibold text-orange-700"><?php echo __('teacher_stat_today_classes'); ?></h3>
            <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo $todayClassesCount; ?></p>
            </div>
        <div class="stat-card">
            <h3 class="font-semibold text-yellow-700"><?php echo __('teacher_exams_title'); ?></h3>
            <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo count($reminders_for_today_flat); ?></p>
            </div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
    <h2 class="text-xl font-bold text-gray-800 mb-1"><?php echo __('student_schedule_title'); ?></h2>
    
    <p class="text-gray-500 mb-6"><?php echo sprintf(__('student_schedule_subtitle'), $display_date); ?></p>
    
    <div class="space-y-4">
        <?php if (empty($todaySchedule)) : ?>
            <p class="text-center text-gray-500 py-8"><?php echo __('student_schedule_none'); ?></p>
        <?php else: ?>
            <?php foreach ($todaySchedule as $class): 
                // --- [LOGIKA TRANSLATE RUANGAN BARU] ---
                $raw_room = $class['room']; // Dari DB: "Ruangan 1"
                $room_number = preg_replace('/[^0-9]/', '', $raw_room); // Ambil angka: "1"
                $room_base = __('schedule_form_room_base'); // Translate dasar: "Room" / "教室" / "Ruangan"
                $translated_room = $room_base . ' ' . $room_number; // Gabung: "教室 1"
                // --- [AKHIR LOGIKA] ---
            ?>
            <div class="flex items-center justify-between p-4 bg-orange-50/50 rounded-xl">
                <div class="flex items-center gap-4">
                    <div class="bg-orange-500 text-white font-bold text-sm px-4 py-3 rounded-lg"><?php echo date('H:i', strtotime($class['start_time'])); ?></div>
                    <div>
                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($class['subject_name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($translated_room); ?></p>
                    </div>
                </div>
                <span class="bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full"><?php echo __('student_schedule_status_active'); ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo __('teacher_exams_title'); ?></h2>
        <div class="space-y-4">
            <?php if (empty($reminders_for_today_flat)): ?>
                <p class="text-center text-gray-500 py-8"><?php echo __('teacher_exam_no_reminder_today'); ?></p>
            <?php else: ?>
                <?php foreach ($reminders_for_today_flat as $reminder): 
                    // Gunakan fungsi tanggal manual kita
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
                            <?php echo __('teacher_exam_label_exam'); ?> <span class="font-medium text-gray-700"><?php echo htmlspecialchars($reminder['subject_name']); ?></span>
                        </p>
                        <p class="text-sm text-gray-500 mb-2">
                            <?php echo __('teacher_exam_label_date'); ?> <?php echo $exam_date_display; ?>
                        </p>
                        
                        <?php if (!empty($reminder['student_times'])): ?>
                        <div class="text-sm text-gray-600 mt-2">
                            <p class="font-medium mb-1"><?php echo __('teacher_exam_label_lesson_time'); ?></p>
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
                        if ($days_until_exam == 1) {
                            echo sprintf(__('student_exam_day_left'), $days_until_exam);
                        } else {
                            echo sprintf(__('student_exam_days_left'), $days_until_exam);
                        }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>