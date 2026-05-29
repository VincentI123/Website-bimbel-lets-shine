<?php
// --- AKTIFKAN ERROR REPORTING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- "OTAK" BAHASA (VERSI UNIVERSAL 3 BAHASA) ---

// 1. Selalu mulai session di paling atas
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

// 8. Fungsi Helper Format Tanggal Manual (Agar Mandarin konsisten)
if (!function_exists('date_format_intl')) {
    function date_format_intl($date_string, $lang) {
        $timestamp = strtotime($date_string);
        if ($lang == 'cn') {
            // Format Mandarin: YYYY年MM月DD日 星期X
            $days = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
            return date('Y', $timestamp) . '年' . date('m', $timestamp) . '月' . date('d', $timestamp) . '日 ' . $days[date('w', $timestamp)];
        } elseif ($lang == 'id') {
            // Format Indo manual
            $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return $days[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
        } else {
            // Format Inggris standar
            return date('l, d F Y', $timestamp);
        }
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE LOGIKA DATABASE ---
require_once 'src/config/database.php';

$db = Database::getInstance()->getConnection();
$student_id = $user['id'];
$today = date('l');
$current_date = date('Y-m-d');

// 1. Ambil Jadwal Hari Ini
$stmt = $db->prepare("
    SELECT s.start_time, sub.name as subject_name, u.name as teacher_name, s.room
    FROM schedules s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN users u ON s.teacher_id = u.id
    JOIN student_enrollments se ON s.id = se.schedule_id
    WHERE se.student_id = :student_id AND s.day_of_week = :today
    ORDER BY s.start_time
");
$stmt->execute([':student_id' => $student_id, ':today' => $today]);
$todaySchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
$todayClassesCount = count($todaySchedule);

// 2. Ambil SEMUA Ujian Mendatang (Tanpa batasan 4 hari)
$stmt_exams = $db->prepare("
    SELECT e.exam_date, s.name as subject_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    WHERE e.student_id = :student_id 
    AND e.exam_date >= :current_date
    ORDER BY e.exam_date ASC
");
$stmt_exams->execute([
    ':student_id' => $student_id, 
    ':current_date' => $current_date
]);

$upcomingExams = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);
$upcomingExamsCount = count($upcomingExams);

// Tanggal hari ini untuk subtitle
$display_date = date_format_intl(date('Y-m-d'), $currentLang);
?>

<?php
// --- Link Ganti Bahasa ---
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

<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800"><?php echo sprintf(__('dashboard_welcome'), htmlspecialchars($user['name'])); ?></h1>
        <p class="text-gray-500"><?php echo __('student_dashboard_subtitle'); ?></p>
    </div>
    
    <div class="grid grid-cols-2 gap-6">
        <div class="stat-card">
            <h3 class="font-semibold text-yellow-700"><?php echo __('student_stat_today_classes'); ?></h3>
            <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo $todayClassesCount; ?></p>
            <p class="text-sm text-gray-500"><?php echo __('student_stat_today_classes_sub'); ?></p>
        </div>
        <div class="stat-card">
            <h3 class="font-semibold text-orange-700"><?php echo __('student_stat_upcoming_exams'); ?></h3>
            <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo $upcomingExamsCount; ?></p>
            <p class="text-sm text-gray-500"><?php echo __('student_stat_upcoming_exams_sub'); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-yellow-900/5">
        <h2 class="text-xl font-bold text-gray-800 mb-1"><?php echo __('student_schedule_title'); ?></h2>
        
        <p class="text-gray-500 mb-6"><?php echo sprintf(__('student_schedule_subtitle'), $display_date); ?></p>
        
        <div class="space-y-4">
            <?php if (empty($todaySchedule)): ?>
                <p class="text-center text-gray-500 py-8"><?php echo __('student_schedule_none'); ?></p>
            <?php else: ?>
                <?php foreach ($todaySchedule as $class): ?>
                <div class="flex items-center justify-between p-4 bg-yellow-50/50 rounded-xl">
                    <div class="flex items-center gap-4">
                        <div class="bg-yellow-500 text-white font-bold text-sm px-4 py-3 rounded-lg"><?php echo date('H:i', strtotime($class['start_time'])); ?></div>
                        <div>
                            <p class="font-bold text-gray-800"><?php echo htmlspecialchars($class['subject_name']); ?></p>
                            <p class="text-sm text-gray-500">
                                <?php echo __('student_schedule_teacher'); ?> <?php echo htmlspecialchars($class['teacher_name']); ?> • 
                                <?php echo __('student_schedule_room'); ?> <?php echo htmlspecialchars($class['room']); ?>
                            </p>
                        </div>
                    </div>
                    <span class="bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full"><?php echo __('student_schedule_status_active'); ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo __('student_exam_title'); ?></h2>
        <div class="space-y-4">
            <?php if (empty($upcomingExams)): ?>
                <p class="text-center text-gray-500 py-8"><?php echo __('student_exam_none'); ?></p>
            <?php else: ?>
                <?php foreach ($upcomingExams as $exam):
                    // Hitung selisih hari
                    $exam_date_check = new DateTime($exam['exam_date']);
                    $now_check = new DateTime('today');
                    $interval = $now_check->diff($exam_date_check);
                    $days_until_exam = $interval->days;
                    
                    // Format tanggal sesuai bahasa
                    $formatted_exam_date = date_format_intl($exam['exam_date'], $currentLang);
                ?>
                <div class="flex items-start justify-between p-4 rounded-xl bg-yellow-50 border-l-4 border-yellow-500">
                    <div>
                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo $formatted_exam_date; ?></p>
                    </div>
                    <div class="text-yellow-600 font-semibold text-right flex-shrink-0">
                        <i data-lucide="alert-triangle" class="w-5 h-5 inline-block"></i>
                        <?php 
                        // Menggunakan kunci terjemahan yang benar
                        if ($days_until_exam == 0) {
                            echo " " . __('student_exam_today');
                        } elseif ($days_until_exam == 1) {
                            echo " " . sprintf(__('student_exam_day_left'), $days_until_exam);
                        } else {
                            echo " " . sprintf(__('student_exam_days_left'), $days_until_exam);
                        }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>