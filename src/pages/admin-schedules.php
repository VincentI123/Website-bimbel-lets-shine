<?php
// --- AKTIFKAN ERROR REPORTING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];
    
    $query_params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }
    unset($query_params['lang']);
    
    // Bangun ulang URL (agar parameter seperti ?page=schedules&day=Monday tidak hilang)
    $queryStringParts = array();
    foreach ($query_params as $key => $value) {
        $queryStringParts[] = urlencode($key) . '=' . urlencode($value);
    }
    $queryString = implode('&', $queryStringParts);
    
    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');
    
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
// Panggil database
// $db sudah ada dari admin-dashboard.php


// --- [PERBAIKAN #1: UBAH KEUERI DAN ARRAY] ---
$upcoming_exams_by_student = []; // Ganti nama variabel agar lebih jelas
try {
    // Ambil INDEKS HARI (Senin=0, Selasa=1, ...) dari tanggal ujian
    $stmt_exams = $db->prepare("
        SELECT 
            u.name as student_name, 
            s.name as subject_name, 
            WEEKDAY(e.exam_date) as exam_weekday_index 
        FROM exams e
        JOIN users u ON e.student_id = u.id
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.exam_date > CURDATE() AND e.exam_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ");
    
    $stmt_exams->execute();
    $exams_data = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);

    // Buat array: ['NamaSiswa']['NamaPelajaran'] = 'IndeksHariUjian' (cth: 1 untuk Selasa)
    foreach ($exams_data as $exam) {
        $upcoming_exams_by_student[$exam['student_name']][$exam['subject_name']] = $exam['exam_weekday_index'];
    }

} catch (PDOException $e) {
    // Ignored
}
// --- [AKHIR PERBAIKAN #1] ---

// --- [PERUBAHAN LOGIKA VIEW] ---
// Hapus $view, ganti dengan $day_to_show
$current_day_en = date('l');
// Jika hari ini Minggu, otomatis tampilkan Senin
if ($current_day_en === 'Sunday') {
    $current_day_en = 'Monday';
}
// INI ADALAH LOGIKA KUNCI YANG MEMBACA PARAMETER DARI JS
$day_to_show = isset($_GET['day']) ? $_GET['day'] : $current_day_en;
// --- [AKHIR PERUBAHAN LOGIKA VIEW] ---

// --- PERBAIKAN: Terjemahan Hari Penuh ---
// --- UBAH: Dihapus hari Minggu (Sunday) ---
$days_translation = [
    'Monday' => __('Monday'), 
    'Tuesday' => __('Tuesday'), 
    'Wednesday' => __('Wednesday'),
    'Thursday' => __('Thursday'), 
    'Friday' => __('Friday'), 
    'Saturday' => __('Saturday')
];

// --- [PERBAIKAN #2: Buat Peta Indeks Hari] ---
$day_index_map = [
    'Monday' => 0, 
    'Tuesday' => 1, 
    'Wednesday' => 2,
    'Thursday' => 3, 
    'Friday' => 4, 
    'Saturday' => 5
];

// --- [PERBAIKAN #3: AMBIL HARI LES SEMUA SISWA] ---
$student_lesson_days = [];
try {
    // 1. Ambil data hari les (string) dari DB
    $stmt_student_days = $db->query("SELECT name, available_days FROM users WHERE role = 'student' AND status = 'approved'");
    $students_data = $stmt_student_days->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Konversi string "Monday, Wednesday" menjadi array indeks [0, 2]
    foreach ($students_data as $student) {
        $indices = [];
        if (!empty($student['available_days'])) {
            $days_array = array_map('trim', explode(',', $student['available_days']));
            foreach ($days_array as $day_name) {
                if (isset($day_index_map[$day_name])) { // $day_index_map sudah ada
                    $indices[] = $day_index_map[$day_name];
                }
            }
        }
        $student_lesson_days[$student['name']] = $indices; // cth: ['Alex Smith'] = [2, 3, 4]
    }
} catch (PDOException $e) {
    // Ignored
}
// --- [AKHIR PERBAIKAN #3] ---


// --- [PERUBAHAN: Tentukan Ruangan & Slot Waktu] ---
$room_base = __('schedule_form_room_base'); // "Ruangan" atau "Room"
$rooms = [];
for ($i = 1; $i <= 6; $i++) {
    $rooms[] = $room_base . " " . $i;
}

// [PERUBAHAN] Ambil slot waktu dari database
// [BARU] Ambil slot waktu DAN specific_days
try {
    $stmt_slots = $db->query("SELECT time_label, specific_days FROM time_slots ORDER BY display_order ASC");
    $all_slots_data = $stmt_slots->fetchAll(PDO::FETCH_ASSOC);

    $time_slots = []; // Array baru untuk menampung jam yang LOLOS filter

    foreach ($all_slots_data as $slot) {
        // Logika: Masukkan ke list jika (specific_days kosong) ATAU (mengandung hari yang dipilih)
        // $day_to_show adalah variabel hari yang sedang dilihat (misal: "Monday")
        if (empty($slot['specific_days']) || strpos($slot['specific_days'], $day_to_show) !== false) {
            $time_slots[] = $slot['time_label'];
        }
    }

} catch (PDOException $e) {
    if ($db_error === null) {
        $db_error = $e->getMessage();
    }
    $time_slots = []; 
}
// [AKHIR PERUBAHAN] ---

$schedules = [];
$db_error = null;
try {
    // --- [PERUBAHAN: Optimalkan kueri untuk mengambil hanya hari yang dipilih] ---
    $stmt = $db->prepare("
        SELECT s.id, sub.id as subject_id, sub.name as subject_name, u.name as teacher_name, 
               s.day_of_week, s.start_time, s.end_time, s.room
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN users u ON s.teacher_id = u.id
        WHERE u.role = 'teacher' AND s.day_of_week = :day_to_show
        ORDER BY s.start_time
    ");
    $stmt->execute(array(':day_to_show' => $day_to_show));
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); 
}

// [TAMBAHKAN INI: AMBIL PESAN ERROR]
$flash_error = null;
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']); 
}
    // --- [AKHIR PERUBAHAN] ---
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

function getStudentsForSchedule($scheduleId, $db) {
    try {
        $stmt = $db->prepare("SELECT u.name FROM users u JOIN student_enrollments se ON u.id = se.student_id WHERE se.schedule_id = :schedule_id ORDER BY u.name");
        $stmt->execute(array(':schedule_id' => $scheduleId));
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

// --- [PERUBAHAN: Bangun Grid berdasarkan Waktu x Ruangan] ---
$grid = [];
// Inisialisasi grid kosong
foreach ($time_slots as $slot) {
    foreach ($rooms as $room) {
        $grid[$slot][$room] = [];
    }
}

// Isi grid dengan data jadwal (yang sudah difilter per hari)
foreach ($schedules as $schedule) {
    $start_formatted = date('H:i', strtotime($schedule['start_time']));
    $end_formatted = date('H:i', strtotime($schedule['end_time']));
    $time_key = $start_formatted . ' - ' . $end_formatted;
    
    // --- PERBAIKAN MULAI ---
    // 1. Ambil hanya angkanya dari database (misal "Ruangan 1" diambil "1"-nya saja)
    $room_number = preg_replace('/[^0-9]/', '', $schedule['room']);
    
    // 2. Gabungkan angka tersebut dengan kata dasar bahasa yang aktif (Room/Ruangan/教室)
    // $room_base sudah kita definisikan di atas pakai __()
    $room_key = $room_base . " " . $room_number; 
    // --- PERBAIKAN SELESAI ---

    // Pastikan time_key dan room_key ada di grid
    // (Sekarang pengecekan "in_array" pakai $time_slots yang benar)
    if (in_array($time_key, $time_slots) && in_array($room_key, $rooms)) {
        $students = getStudentsForSchedule($schedule['id'], $db);
        $schedule_with_students = $schedule;
        $schedule_with_students['students'] = $students;
        $grid[$time_key][$room_key][] = $schedule_with_students;
    }
}
// --- [AKHIR PERUBAHAN] ---
?>

<style>
    /* Sembunyikan header cetak di layar biasa */
    .print-header {
        display: none;
    }

    /* --- TAMBAHAN KODE BARU (Agar Tabel Rapi & Pas Layar) --- */
    #schedule-table {
        table-layout: fixed; /* Memaksa lebar kolom dikunci */
        width: 100%;         /* Lebar mengikuti layar */
    }

    /* Mengatur Lebar Kolom Waktu (Kolom Pertama) */
    #schedule-table th:first-child,
    #schedule-table td:first-child {
        width: 100px; /* Lebar tetap untuk jam */
    }

    /* Mengatur Lebar Kolom Ruangan (Sisanya dibagi rata) */
    #schedule-table th:not(:first-child),
    #schedule-table td:not(:first-child) {
        width: 15%; /* Sisa lebar dibagi rata ke 6 ruangan */
    }
    /* --- AKHIR TAMBAHAN KODE --- */

    @media print {
        /* 1. Reset Tampilan: Sembunyikan semua elemen body */
        body * { visibility: hidden; }
        
        /* Sembunyikan elemen yang ditandai no-print */
        .no-print, .no-print * { display: none !important; }
        
        /* 2. TAMPILKAN HANYA Area Jadwal */
        #printable-schedule, #printable-schedule * { visibility: visible; }

        /* 3. Atur Posisi Utama */
        #printable-schedule {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            margin: 0;
            background-color: white;
        }

        /* --- [PERBAIKAN UTAMA: PAKSA TAMPILAN TABEL] --- */
        
        /* a. Paksa Container Tabel Desktop MUNCUL (Override class 'hidden') */
        #desktop-schedule-container {
            display: block !important;
            overflow: visible !important; /* Hilangkan scrollbar saat print */
        }

        /* b. Paksa Container Mobile HILANG */
        #mobile-schedule-container {
            display: none !important;
        }

        /* ---------------------------------------------- */

        /* 4. Header Cetak */
        .custom-print-header {
            display: block !important;
            text-align: center;
            color: #000;
            margin-bottom: 20px;
        }
        .print-header { display: none !important; }

        /* 5. GAYA TABEL (Agar Hitam Putih & Rapi) */
        #schedule-table {
            width: 100% !important;
            border-collapse: collapse;
            font-size: 9pt;
            border: 1px solid #000;
            table-layout: fixed;
        }

        #schedule-table th, 
        #schedule-table td {
            border: 1px solid #000 !important;
            padding: 8px;
            text-align: left;
            vertical-align: top;
            color: #000 !important;
            background-color: #fff !important;
        }

        #schedule-table th {
            background-color: #eee !important; 
            font-weight: bold;
            text-align: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* 6. Mencegah Potongan Halaman Jelek */
        tr {
            page-break-inside: avoid; 
            break-inside: avoid;
        }
        thead {
            display: table-header-group;
        }

        /* 7. Tampilan Item di dalam Sel */
        .search-item {
            border: none !important;
            border-bottom: 1px dashed #ccc !important;
            background: none !important;
            padding: 5px 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
        }
        .search-item:last-child { border-bottom: none !important; }
        
        .search-item p, .search-item div, .search-item span { 
            color: #000 !important; 
        }
        
        .search-item .flex.justify-end {
            display: none !important; /* Sembunyikan tombol edit/hapus */
        }

        @page {
            size: A4 landscape; /* Paksa Landscape agar tabel muat */
            margin: 10mm;
        }
    }
</style>
<?php
// --- Link Bahasa (Mempertahankan Parameter URL) ---
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}
unset($query_params['lang']); // Hapus param lang lama

$queryParams_id = $query_params; $queryParams_id['lang'] = 'id'; $url_id = $path . '?' . http_build_query($queryParams_id);
$queryParams_en = $query_params; $queryParams_en['lang'] = 'en'; $url_en = $path . '?' . http_build_query($queryParams_en);
$queryParams_cn = $query_params; $queryParams_cn['lang'] = 'cn'; $url_cn = $path . '?' . http_build_query($queryParams_cn);
?>

<div class="flex space-x-4 mb-4 justify-end no-print">
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

<?php if ($flash_message): ?>
<div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg shadow border-l-4 border-green-500 flex items-center gap-2" role="alert">
    <i data-lucide="check-circle" class="w-5 h-5"></i>
    <p><?php echo $flash_message; ?></p>
</div>
<?php endif; ?>

<?php if ($flash_error): ?>
<div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg shadow border-l-4 border-red-500 flex items-center gap-2" role="alert">
    <i data-lucide="alert-circle" class="w-5 h-5"></i>
    <p><?php echo $flash_error; ?></p>
</div>
<?php endif; ?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 no-print">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('schedule_page_title'); ?></h1>
        <div class="flex flex-wrap items-center gap-4">
            
            <div class="bg-gray-200 p-1 rounded-lg flex items-center text-sm overflow-x-auto">
                <?php
                // Ambil parameter saat ini (hanya 'page')
                $base_params = ['page' => 'schedules'];
                
                foreach ($days_translation as $day_en => $day_id) {
                    // Tentukan class
                    $isActive = ($day_to_show === $day_en);
                    $class = $isActive 
                        ? 'bg-white shadow font-semibold text-orange-600' 
                        : 'text-gray-600 hover:bg-gray-100';
                    
                    // Buat URL
                    $link_params = $base_params;
                    $link_params['day'] = $day_en;
                    $url = 'admin-dashboard.php?' . http_build_query($link_params);
                    
                    // Cetak tombol
                    echo "<a href='{$url}' class='px-3 py-1 rounded-md transition-all whitespace-nowrap {$class}'>{$day_id}</a>";
                }
                ?>
            </div>

            <button onclick="window.print()" class="btn-white text-gray-800 font-semibold px-4 py-2 rounded-lg flex items-center gap-2 text-sm">
                <i data-lucide="printer" class="w-5 h-5"></i> <?php echo __('teacher_schedule_print_button'); ?>
            </button>
            <a href="admin-dashboard.php?page=schedules-form" class="btn-gradient text-white font-semibold px-4 py-2 rounded-lg flex items-center gap-2 text-sm"><i data-lucide="plus" class="w-5 h-5"></i> <?php echo __('schedule_new_button'); ?></a>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <div class="mb-4 no-print"> <input type="text" id="searchInput" onkeyup="searchSchedule()" placeholder="<?php echo __('schedule_search_placeholder'); ?>" class="w-full p-3 border border-gray-300 rounded-lg">
        </div>

        <?php if (isset($db_error)): ?>
             <div class="text-center text-red-500 py-8"><?php echo __('schedule_db_error'); ?> <?php echo $db_error; ?></div>
        <?php elseif (empty($schedules) && empty($time_slots)): ?>
             <div class="text-center text-gray-500 py-8"><?php echo __('schedule_no_classes_day'); ?> <a href="admin-dashboard.php?page=schedules-form" class="text-orange-600 font-semibold"><?php echo __('schedule_add_link'); ?></a>.</div>
        <?php else: ?>
            <div id="printable-schedule">
                <div class="custom-print-header" style="display:none;">
                    <h1 style="margin:0; font-size:18pt; font-weight:bold; text-transform:uppercase;">
                        <?php echo __('schedule_page_title'); ?>
                    </h1>
                    <h2 style="margin:5px 0 20px 0; font-size:14pt; font-weight:bold;">
                        <?php echo $days_translation[$day_to_show]; ?>
                    </h2>
                </div>
                
                <div class="print-header">
                    <img src="assets/img/logo.png" alt="Logo Bimbel Let's Shine">
                    <div>
                        <h1><?php echo __('schedule_page_title'); ?></h1>
                        <h2><?php echo $days_translation[$day_to_show]; ?></h2>
                    </div>
                </div>
                
                <div id="desktop-schedule-container" class="hidden md:block overflow-x-auto">
    <table id="schedule-table" class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="p-3 font-semibold text-gray-600 border border-gray-200 text-left w-28"><?php echo __('schedule_table_time'); ?></th>
                            <?php foreach ($rooms as $room): ?>
                                <th class="p-3 font-semibold text-gray-600 border border-gray-200 text-center"><?php echo htmlspecialchars($room); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($time_slots as $slot): ?>
                            <tr>
                                <td class="p-2 border border-gray-200 font-medium text-gray-500 text-center align-top h-36"><?php echo $slot; ?></td>
                                
                                <?php foreach ($rooms as $room): ?>
                                    <td class="p-1 border border-gray-200 align-top">
                                        <?php if (!empty($grid[$slot][$room])): ?>
                                            <?php foreach ($grid[$slot][$room] as $schedule): ?>
                                                
                                                <div class="bg-orange-50 border-l-4 border-orange-500 p-3 rounded-md mb-2 search-item" data-students="<?php echo htmlspecialchars(implode(', ', $schedule['students'])); ?>">
                                                    <p class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($schedule['teacher_name']); ?></p>
                                                    <p class="text-xs text-gray-600"><?php echo __('schedule_table_subject'); ?>: <?php echo htmlspecialchars($schedule['subject_name']); ?></p>
                                                    
                                                    <div class="text-xs mt-2">
                                                        <p class="font-medium text-gray-600"><?php echo __('schedule_table_students'); ?>:</p>
                                                        <?php if (!empty($schedule['students'])):
                                                            echo '<div class="text-gray-500">';
                                                            foreach ($schedule['students'] as $student) {
                                                                // --- LOGIKA H-1 (DESKTOP) ---
                                                                $exam_indicator = '';
                                                                if (isset($upcoming_exams_by_student[$student])) {
                                                                    $student_exams = $upcoming_exams_by_student[$student]; 
                                                                    $exams_to_show_today = [];
                                                                    $cell_day_index = isset($day_index_map[$day_to_show]) ? $day_index_map[$day_to_show] : -1;
                                                                    $student_days_indices = isset($student_lesson_days[$student]) ? $student_lesson_days[$student] : []; 
                                                                    
                                                                    if (empty($student_days_indices)) {
                                                                        foreach ($student_exams as $exam_subject => $exam_weekday_index) {
                                                                            $days_until = ($exam_weekday_index - $cell_day_index + 7) % 7;
                                                                            if ($days_until == 1) $exams_to_show_today[] = htmlspecialchars($exam_subject);
                                                                        }
                                                                    } else {
                                                                        if (in_array($cell_day_index, $student_days_indices)) {
                                                                            foreach ($student_exams as $exam_subject => $exam_weekday_index) { 
                                                                                $days_until_exam = ($exam_weekday_index - $cell_day_index + 7) % 7;
                                                                                if ($days_until_exam > 0) {
                                                                                    $is_last_lesson_day = true;
                                                                                    for ($i = 1; $i < $days_until_exam; $i++) {
                                                                                        $check_day = ($cell_day_index + $i) % 7;
                                                                                        if (in_array($check_day, $student_days_indices)) { $is_last_lesson_day = false; break; }
                                                                                    }
                                                                                    if ($is_last_lesson_day) $exams_to_show_today[] = htmlspecialchars($exam_subject);
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                    if (!empty($exams_to_show_today)) {
                                                                        $subject_list = implode(', ', $exams_to_show_today);
                                                                        $exam_indicator = ' <span class="text-yellow-600 font-bold">(' . $subject_list . ')</span>';
                                                                    }
                                                                }
                                                                // --- END LOGIKA H-1 ---
                                                                
                                                                echo '<div>- ' . htmlspecialchars($student) . $exam_indicator . '</div>';
                                                            }
                                                            echo '</div>';
                                                        else:
                                                            echo '<p class="text-gray-400">' . __('schedule_no_students') . '</p>';
                                                        endif; ?>
                                                    </div>
                                                    
                                                    <div class="flex justify-end gap-1 mt-2 no-print">
                                                        <a href="admin-dashboard.php?page=schedules-form&id=<?php echo $schedule['id']; ?>" class="p-1 text-blue-600 hover:bg-blue-100 rounded-full"><i data-lucide="edit" class="w-3 h-3"></i></a>
                                                        <a href="admin-dashboard.php?page=schedules&action=delete&id=<?php echo $schedule['id']; ?>&day=<?php echo $day_to_show; ?>" onclick="return confirm('<?php echo __('schedule_confirm_delete'); ?>');" class="p-1 text-red-600 hover:bg-red-100 rounded-full"><i data-lucide="trash-2" class="w-3 h-3"></i></a>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-6" id="mobile-schedule-container">
                <?php 
                $hasAnySchedule = false;
                foreach ($time_slots as $slot): 
                    // Kumpulkan semua jadwal di jam ini dari semua ruangan
                    $schedulesInThisSlot = [];
                    foreach ($rooms as $room) {
                        if (!empty($grid[$slot][$room])) {
                            foreach ($grid[$slot][$room] as $s) {
                                $s['room_name'] = $room; // Simpan nama ruangan agar bisa ditampilkan
                                $schedulesInThisSlot[] = $s;
                            }
                        }
                    }

                    if (!empty($schedulesInThisSlot)): 
                        $hasAnySchedule = true;
                ?>
                    <div class="time-block">
                        <h3 class="text-lg font-bold text-gray-700 mb-3 flex items-center gap-2 sticky top-0 bg-gray-50 py-2 z-10 border-b border-gray-200">
                            <i data-lucide="clock" class="w-5 h-5 text-orange-500"></i> 
                            <?php echo $slot; ?>
                        </h3>
                        
                        <div class="space-y-3">
                            <?php foreach ($schedulesInThisSlot as $schedule): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm search-item" data-students="<?php echo htmlspecialchars(implode(', ', $schedule['students'])); ?>">
                                    
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded mb-2 inline-block">
                                                <?php echo htmlspecialchars($schedule['room_name']); ?>
                                            </span>
                                            
                                            <h4 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($schedule['subject_name']); ?></h4>
                                            
                                            <p class="text-gray-600 text-sm flex items-center gap-1 mt-1">
                                                <i data-lucide="user" class="w-3 h-3"></i> 
                                                <?php echo htmlspecialchars($schedule['teacher_name']); ?>
                                            </p>
                                        </div>
                                        
                                        <div class="flex flex-col gap-2 no-print">
                                            <a href="admin-dashboard.php?page=schedules-form&id=<?php echo $schedule['id']; ?>" class="p-2 bg-blue-50 text-blue-600 rounded-lg active:scale-95 transition-transform">
                                                <i data-lucide="edit" class="w-5 h-5"></i>
                                            </a>
                                            <a href="admin-dashboard.php?page=schedules&action=delete&id=<?php echo $schedule['id']; ?>&day=<?php echo $day_to_show; ?>" onclick="return confirm('<?php echo __('schedule_confirm_delete'); ?>');" class="p-2 bg-red-50 text-red-600 rounded-lg active:scale-95 transition-transform">
                                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="mt-3 pt-3 border-t border-gray-100">
                                        <p class="text-xs font-semibold text-gray-500 mb-1"><?php echo __('schedule_table_students'); ?>:</p>
                                        <div class="text-sm text-gray-700 pl-1">
                                            <?php if (!empty($schedule['students'])): ?>
                                                <?php foreach ($schedule['students'] as $student): 
                                                    // --- LOGIKA H-1 (MOBILE - COPY PASTE) ---
                                                    $exam_indicator = '';
                                                    if (isset($upcoming_exams_by_student[$student])) {
                                                        $student_exams = $upcoming_exams_by_student[$student]; 
                                                        $exams_to_show_today = [];
                                                        $cell_day_index = isset($day_index_map[$day_to_show]) ? $day_index_map[$day_to_show] : -1;
                                                        $student_days_indices = isset($student_lesson_days[$student]) ? $student_lesson_days[$student] : []; 
                                                        
                                                        if (empty($student_days_indices)) {
                                                            foreach ($student_exams as $exam_subject => $exam_weekday_index) {
                                                                $days_until = ($exam_weekday_index - $cell_day_index + 7) % 7;
                                                                if ($days_until == 1) $exams_to_show_today[] = htmlspecialchars($exam_subject);
                                                            }
                                                        } else {
                                                            if (in_array($cell_day_index, $student_days_indices)) {
                                                                foreach ($student_exams as $exam_subject => $exam_weekday_index) { 
                                                                    $days_until_exam = ($exam_weekday_index - $cell_day_index + 7) % 7;
                                                                    if ($days_until_exam > 0) {
                                                                        $is_last_lesson_day = true;
                                                                        for ($i = 1; $i < $days_until_exam; $i++) {
                                                                            $check_day = ($cell_day_index + $i) % 7;
                                                                            if (in_array($check_day, $student_days_indices)) { $is_last_lesson_day = false; break; }
                                                                        }
                                                                        if ($is_last_lesson_day) $exams_to_show_today[] = htmlspecialchars($exam_subject);
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        if (!empty($exams_to_show_today)) {
                                                            $subject_list = implode(', ', $exams_to_show_today);
                                                            $exam_indicator = ' <span class="text-yellow-600 font-bold">(' . $subject_list . ')</span>';
                                                        }
                                                    }
                                                    // --- END LOGIKA H-1 MOBILE ---
                                                ?>
                                                    <div class="mb-1 flex items-start">
                                                        <span class="mr-2">•</span> 
                                                        <span><?php echo htmlspecialchars($student) . $exam_indicator; ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs italic"><?php echo __('schedule_no_students'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; endforeach; ?>

                <?php if (!$hasAnySchedule): ?>
                    <div class="text-center py-12 text-gray-500">
                        <i data-lucide="calendar-off" class="w-12 h-12 mx-auto text-gray-300 mb-2"></i>
                        <p><?php echo __('schedule_no_classes_day'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function searchSchedule() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    
    // 1. SEARCH DI DESKTOP (TABEL)
    const table = document.getElementById('schedule-table');
    if (table) {
        const itemsTable = table.getElementsByClassName('search-item');
        for (let item of itemsTable) {
            const studentData = item.getAttribute('data-students').toUpperCase();
            const textContent = item.textContent.toUpperCase();
            if (textContent.indexOf(filter) > -1 || studentData.indexOf(filter) > -1) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        }
    }

    // 2. SEARCH DI MOBILE (KARTU)
    const mobileContainer = document.getElementById('mobile-schedule-container');
    if (mobileContainer) {
        const itemsMobile = mobileContainer.getElementsByClassName('search-item');
        for (let item of itemsMobile) {
            const studentData = item.getAttribute('data-students').toUpperCase();
            const textContent = item.textContent.toUpperCase();
            
            // Tampilkan/Sembunyikan Kartu
            if (textContent.indexOf(filter) > -1 || studentData.indexOf(filter) > -1) {
                item.style.display = ''; 
            } else {
                item.style.display = 'none';
            }
        }
        
        // Sembunyikan Header Jam jika semua kartu di dalamnya tersembunyi
        const timeBlocks = mobileContainer.querySelectorAll('.time-block');
        timeBlocks.forEach(block => {
            const cards = block.querySelectorAll('.search-item');
            let hasVisibleCard = false;
            cards.forEach(card => {
                if (card.style.display !== 'none') {
                    hasVisibleCard = true;
                }
            });
            // Jika tidak ada kartu visible, sembunyikan satu blok jam ini
            block.style.display = hasVisibleCard ? '' : 'none';
        });
    }
}
</script>