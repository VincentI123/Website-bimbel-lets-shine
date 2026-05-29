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

// 3. Cek jika pengguna MENGKLIK tombol ganti bahasa (UPDATE: Tambah 'cn')
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {

    $_SESSION['lang'] = $_GET['lang'];

    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];

    $query_params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }

    unset($query_params['lang']);

    // Bangun ulang URL (agar parameter seperti ?view=week tidak hilang)
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

// 5. Mengatur "Locale" PHP (UPDATE: Tambah Mandarin)
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


// --- KODE ASLI HALAMAN ANDA DIMULAI DI SINI ---
$db = Database::getInstance()->getConnection();
$current_user_id = $user['id']; // ID guru yang sedang login

// --- [LOGIKA BARU DARI ADMIN-SCHEDULE] ---

// --- [PERBAIKAN #1: UBAH KEUERI DAN ARRAY] ---
$upcoming_exams_by_student = []; // Ganti nama variabel
$db_error = null;
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
    $db_error = __('approval_db_error') . " " . $e->getMessage();
}
// --- [AKHIR PERBAIKAN #1] ---

// --- [PERUBAHAN LOGIKA VIEW] ---
$current_day_en = date('l');
// Jika hari ini Minggu, otomatis tampilkan Senin
if ($current_day_en === 'Sunday') {
    $current_day_en = 'Monday';
}
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

// --- [PERBAIKAN #3: AMBIL HARI LES SEMUA SISWA (YANG HILANG)] ---
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

// [PERUBAHAN] Ambil slot waktu dari database (DENGAN FILTER HARI)
try {
    // Ambil time_label DAN specific_days
    $stmt_slots = $db->query("SELECT time_label, specific_days FROM time_slots ORDER BY display_order ASC");
    $all_slots_data = $stmt_slots->fetchAll(PDO::FETCH_ASSOC);
    
    $time_slots = []; // Array ini yang akan dipakai untuk merender baris tabel

    foreach ($all_slots_data as $slot_data) {
        $specific_days = $slot_data['specific_days'];
        
        // Logika Filter:
        // Masukkan slot ke daftar HANYA JIKA:
        // 1. specific_days KOSONG (berlaku semua hari), ATAU
        // 2. specific_days MENGANDUNG hari yang sedang dibuka ($day_to_show)
        if (empty($specific_days) || strpos($specific_days, $day_to_show) !== false) {
            $time_slots[] = $slot_data['time_label']; 
        }
    }
} catch (PDOException $e) {
    if ($db_error === null) {
        $db_error = $e->getMessage();
    }
    $time_slots = []; // Set default jika error
}
// [AKHIR PERUBAHAN] ---

$schedules = [];
try {
    // --- [PERUBAHAN: Optimalkan kueri untuk mengambil hanya hari yang dipilih] ---
    // --- [PERUBAHAN PENTING: Tambahkan u.employment_status, u.available_days] ---
    $stmt = $db->prepare("
        SELECT s.id, sub.id as subject_id, sub.name as subject_name, u.id as teacher_id, u.name as teacher_name, 
               s.day_of_week, s.start_time, s.end_time, s.room,
               u.employment_status, u.available_days
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN users u ON s.teacher_id = u.id
        WHERE u.role = 'teacher' AND s.day_of_week = :day_to_show
        ORDER BY s.start_time
    ");
    $stmt->execute(array(':day_to_show' => $day_to_show));
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // --- [AKHIR PERUBAHAN] ---
} catch (PDOException $e) {
    if ($db_error === null) {
        $db_error = $e->getMessage();
    }
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
    
    // --- PERBAIKAN BUG BAHASA ---
    // Data di DB selalu "Ruangan X" (Indonesia).
    // Jika kita di mode Inggris, header tabel adalah "Room X". Kita harus samakan.
    
    $db_room_value = $schedule['room']; // Contoh: "Ruangan 1"
    
    // 1. Ambil angkanya saja (Contoh: "1")
    $room_number = preg_replace('/[^0-9]/', '', $db_room_value);
    
    // 2. Gabungkan dengan kata dasar bahasa aktif (Contoh: "Room" + " " + "1")
    // $room_base sudah didefinisikan di atas sebagai __('schedule_form_room_base')
    $room_key = $room_base . " " . $room_number; 
    
    // Update data schedule agar saat dirender di kartu tampilannya juga sesuai bahasa
    $schedule['room'] = $room_key; 
    // --- AKHIR PERBAIKAN ---

    // Pastikan time_key dan room_key ada di grid
    if (in_array($time_key, $time_slots) && in_array($room_key, $rooms)) {
        $students = getStudentsForSchedule($schedule['id'], $db);
        $schedule_with_students = $schedule;
        $schedule_with_students['students'] = $students;
        
        // Data guru yang baru ditambahkan
        $schedule_with_students['employment_status'] = $schedule['employment_status'];
        $schedule_with_students['available_days'] = $schedule['available_days'];

        $grid[$time_key][$room_key][] = $schedule_with_students;
    }
}
// --- [AKHIR PERUBAHAN] ---
?>

<style>
    /* Sembunyikan header cetak di layar biasa */
    .print-header { display: none; }

    /* --- TAMPILAN DESKTOP (TABEL) --- */
    #schedule-table {
        table-layout: fixed;
        width: 100%;
    }
    #schedule-table th:first-child, #schedule-table td:first-child { width: 100px; } /* Kolom Waktu */
    #schedule-table th:not(:first-child), #schedule-table td:not(:first-child) { width: 15%; } /* Kolom Ruangan */

    /* --- PENGATURAN PRINT (CETAK) --- */
    @media print {
        body * { visibility: hidden; } /* Sembunyikan semua body */
        .no-print, .no-print * { display: none !important; } /* Sembunyikan tombol/navigasi */
        
        /* Tampilkan Container Utama Jadwal */
        #printable-schedule, #printable-schedule * { visibility: visible; }

        /* Posisikan Jadwal di pojok kiri atas kertas */
        #printable-schedule {
            position: absolute;
            left: 0; top: 0; width: 100%; margin: 0; padding: 0; background-color: white;
        }

        /* --- [KUNCI] PAKSA TAMPILKAN TABEL DESKTOP, SEMBUNYIKAN KARTU MOBILE --- */
        #desktop-schedule-container {
            display: block !important; /* Paksa Tabel Muncul */
            overflow: visible !important;
        }
        #mobile-schedule-container {
            display: none !important; /* Paksa Kartu Mobile Hilang */
        }
        /* ----------------------------------------------------------------------- */

        /* Header Cetak Khusus */
        .custom-print-header {
            display: block !important; text-align: center; color: #000; margin-bottom: 20px;
        }

        /* Gaya Tabel Hitam Putih untuk Cetak */
        #schedule-table {
            width: 100% !important; border-collapse: collapse; font-size: 9pt; border: 1px solid #000;
        }
        #schedule-table th, #schedule-table td {
            border: 1px solid #000 !important; padding: 5px; text-align: left; vertical-align: top; color: #000 !important; background-color: #fff !important;
        }
        #schedule-table th { background-color: #eee !important; font-weight: bold; text-align: center; -webkit-print-color-adjust: exact; print-color-adjust:exact }

        /* Mencegah Potongan Halaman */
        tr { page-break-inside: avoid; break-inside: avoid; }
        thead { display: table-header-group; }

        /* Item Jadwal di dalam Tabel */
        .search-item {
            border: none !important; border-bottom: 1px dashed #ccc !important; background: none !important; padding: 2px 0 !important; margin: 0 !important; box-shadow: none !important;
        }
        .search-item p, .search-item span, .search-item div { color: #000 !important; }
        .search-item:last-child { border-bottom: none !important; }

        @page { size: A4 landscape; margin: 10mm; }
    }
</style>

<?php
// --- Logika URL Tombol Bahasa ---
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}
// Hapus 'lang' agar tidak duplikat saat diklik
unset($query_params['lang']);

// Link ID
$queryParams_id = $query_params;
$queryParams_id['lang'] = 'id';
$url_id = $path . '?' . http_build_query($queryParams_id);

// Link EN
$queryParams_en = $query_params;
$queryParams_en['lang'] = 'en';
$url_en = $path . '?' . http_build_query($queryParams_en);

// Link CN (BARU)
$queryParams_cn = $query_params;
$queryParams_cn['lang'] = 'cn';
$url_cn = $path . '?' . http_build_query($queryParams_cn);
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
    <a href="<?php echo $url_cn; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'cn' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="中文">
        <img src="https://flagcdn.com/w40/cn.png" srcset="https://flagcdn.com/w80/cn.png 2x" width="20" height="15" alt="China" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">CN</span>
    </a>
</div>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 no-print">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('teacher_all_schedule_title'); ?></h1>
        <div class="flex items-center gap-4">
            
            <div class="bg-gray-200 p-1 rounded-lg flex items-center text-sm overflow-x-auto">
                <?php
                // Ambil parameter saat ini (hanya 'page')
                $base_params = ['page' => 'schedule'];
                
                foreach ($days_translation as $day_en => $day_id) {
                    // Tentukan class
                    $isActive = ($day_to_show === $day_en);
                    $class = $isActive 
                        ? 'bg-white shadow font-semibold text-orange-600' 
                        : 'text-gray-600 hover:bg-gray-100';
                    
                    // Buat URL
                    $link_params = $base_params;
                    $link_params['day'] = $day_en;
                    // --- [PERBAIKAN] Gunakan teacher-dashboard.php sebagai basis ---
                    $url = 'teacher-dashboard.php?' . http_build_query($link_params);
                    
                    // Cetak tombol
                    echo "<a href='{$url}' class='px-3 py-1 rounded-md transition-all whitespace-nowrap {$class}'>{$day_id}</a>";
                }
                ?>
            </div>

            <button onclick="window.print()" class="btn-white text-gray-800 font-semibold px-4 py-2 rounded-lg flex items-center gap-2 text-sm">
                <i data-lucide="printer" class="w-5 h-5"></i> <?php echo __('teacher_schedule_print_button'); ?>
            </button>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <div class="mb-4 no-print">
            <input type="text" id="searchInput" onkeyup="searchSchedule()" placeholder="<?php echo __('teacher_all_schedule_search'); ?>" class="w-full p-3 border border-gray-300 rounded-lg">
        </div>

        <?php if (isset($db_error)): ?>
             <div class="text-center text-red-500 py-8"><?php echo __('schedule_db_error'); ?> <?php echo $db_error; ?></div>
        <?php elseif (empty($schedules) && empty($time_slots)): ?>
             <div class="text-center text-gray-500 py-8"><?php echo __('schedule_no_classes_day'); ?></div>
        <?php else: ?>
            <div id="printable-schedule">
                <div class="custom-print-header" style="display:none;">
                    <h1 style="margin:0; font-size:18pt; font-weight:bold; text-transform:uppercase;">
                        <?php echo __('teacher_all_schedule_title'); ?>
                    </h1>
                    <h2 style="margin:5px 0 20px 0; font-size:14pt; font-weight:bold;">
                        <?php echo $days_translation[$day_to_show]; ?>
                    </h2>
                </div>

                <?php 
                // Siapkan variabel status guru untuk filter
                $is_part_time_teacher = ($user['employment_status'] === 'part-time');
                $current_teacher_id = $user['id'];
                ?>

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
                            <?php foreach ($time_slots as $slot): 
                                // LOGIKA PART-TIME (Desktop): Sembunyikan baris kosong jika part-time
                                if ($is_part_time_teacher) {
                                    $has_class = false;
                                    foreach ($rooms as $r) {
                                        if (!empty($grid[$slot][$r])) {
                                            foreach ($grid[$slot][$r] as $sch) {
                                                if ($sch['teacher_id'] == $current_teacher_id) { $has_class = true; break 2; }
                                            }
                                        }
                                    }
                                    if (!$has_class) continue;
                                }
                            ?>
                                <tr>
                                    <td class="p-2 border border-gray-200 font-medium text-gray-500 text-center align-top h-36"><?php echo $slot; ?></td>
                                    
                                    <?php foreach ($rooms as $room): ?>
                                        <td class="p-1 border border-gray-200 align-top">
                                            <?php if (!empty($grid[$slot][$room])): ?>
                                                <?php foreach ($grid[$slot][$room] as $schedule): 
                                                    $is_own_schedule = ($schedule['teacher_id'] == $current_teacher_id);
                                                    $card_class = $is_own_schedule ? 'bg-orange-50 border-l-4 border-orange-500' : 'bg-gray-50 border-l-4 border-gray-300';
                                                ?>
                                                    <div class="<?php echo $card_class; ?> p-3 rounded-md mb-2 search-item" 
                                                         data-students="<?php echo htmlspecialchars(implode(', ', $schedule['students'])); ?>" 
                                                         data-teacher="<?php echo htmlspecialchars($schedule['teacher_name']); ?>">
                                                        
                                                        <p class="font-bold text-gray-800 text-sm <?php echo $is_own_schedule ? 'text-orange-700' : ''; ?>">
                                                            <?php echo htmlspecialchars($schedule['teacher_name']); ?>
                                                            <?php echo $is_own_schedule ? __('teacher_schedule_you') : ''; ?>
                                                        </p>
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
                                                                    // --- END LOGIKA ---
                                                                    echo '<div>- ' . htmlspecialchars($student) . $exam_indicator . '</div>';
                                                                }
                                                                echo '</div>';
                                                            else:
                                                                echo '<p class="text-gray-400">' . __('schedule_no_students') . '</p>';
                                                            endif; ?>
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

                <div id="mobile-schedule-container" class="md:hidden space-y-6">
                    <?php 
                    $hasAnySchedule = false;
                    foreach ($time_slots as $slot): 
                        // Kumpulkan jadwal
                        $schedulesInThisSlot = [];
                        foreach ($rooms as $room) {
                            if (!empty($grid[$slot][$room])) {
                                foreach ($grid[$slot][$room] as $s) {
                                    $s['room_name'] = $room;
                                    $schedulesInThisSlot[] = $s;
                                }
                            }
                        }

                        // LOGIKA PART-TIME (Mobile)
                        if ($is_part_time_teacher) {
                            $teacherHasClass = false;
                            foreach ($schedulesInThisSlot as $s) {
                                if ($s['teacher_id'] == $current_teacher_id) {
                                    $teacherHasClass = true; break;
                                }
                            }
                            if (!$teacherHasClass) continue; // Skip jika guru part-time tidak punya kelas
                        }

                        if (!empty($schedulesInThisSlot)): 
                            $hasAnySchedule = true;
                    ?>
                        <div class="time-block">
                            <h3 class="text-lg font-bold text-gray-700 mb-3 flex items-center gap-2 sticky top-0 bg-white py-2 z-10 border-b">
                                <i data-lucide="clock" class="w-5 h-5 text-orange-500"></i> 
                                <?php echo $slot; ?>
                            </h3>
                            
                            <div class="space-y-3">
                                <?php foreach ($schedulesInThisSlot as $schedule): 
                                    $is_own_schedule = ($schedule['teacher_id'] == $current_teacher_id);
                                    // Warna kartu mobile juga dibedakan
                                    $card_class = $is_own_schedule ? 'bg-white border-orange-300 ring-1 ring-orange-200' : 'bg-gray-50 border-gray-200';
                                ?>
                                    <div class="<?php echo $card_class; ?> border rounded-xl p-4 shadow-sm search-item" 
                                         data-students="<?php echo htmlspecialchars(implode(', ', $schedule['students'])); ?>"
                                         data-teacher="<?php echo htmlspecialchars($schedule['teacher_name']); ?>">
                                        
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded mb-2 inline-block">
                                                    <?php echo htmlspecialchars($schedule['room_name']); ?>
                                                </span>
                                                <h4 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($schedule['subject_name']); ?></h4>
                                                <p class="text-gray-600 text-sm flex items-center gap-1 mt-1">
                                                    <i data-lucide="user" class="w-3 h-3"></i> 
                                                    <?php echo htmlspecialchars($schedule['teacher_name']); ?>
                                                    <?php echo $is_own_schedule ? '<span class="text-orange-600 font-semibold text-xs ml-1">(' . __('teacher_schedule_you') . ')</span>' : ''; ?>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-3 pt-3 border-t border-gray-100">
                                            <p class="text-xs font-semibold text-gray-500 mb-1"><?php echo __('schedule_table_students'); ?>:</p>
                                            <div class="text-sm text-gray-700">
                                                <?php if (!empty($schedule['students'])): ?>
                                                    <?php foreach ($schedule['students'] as $student): 
                                                        // --- LOGIKA H-1 (MOBILE COPY) ---
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
                                                                $exam_indicator = ' <span class="text-yellow-600 font-bold text-xs">(' . $subject_list . ')</span>';
                                                            }
                                                        }
                                                        // --- END LOGIKA ---
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
            const studentData = item.getAttribute('data-students') ? item.getAttribute('data-students').toUpperCase() : '';
            const teacherData = item.getAttribute('data-teacher') ? item.getAttribute('data-teacher').toUpperCase() : '';
            const textContent = item.textContent.toUpperCase();

            if (textContent.indexOf(filter) > -1 || studentData.indexOf(filter) > -1 || teacherData.indexOf(filter) > -1) {
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
            const studentData = item.getAttribute('data-students') ? item.getAttribute('data-students').toUpperCase() : '';
            const teacherData = item.getAttribute('data-teacher') ? item.getAttribute('data-teacher').toUpperCase() : '';
            const textContent = item.textContent.toUpperCase();
            
            if (textContent.indexOf(filter) > -1 || studentData.indexOf(filter) > -1 || teacherData.indexOf(filter) > -1) {
                item.style.display = ''; 
            } else {
                item.style.display = 'none';
            }
        }
        
        // Sembunyikan Header Jam jika tidak ada kartu yang cocok
        const timeBlocks = mobileContainer.querySelectorAll('.time-block');
        timeBlocks.forEach(block => {
            const cards = block.querySelectorAll('.search-item');
            let hasVisibleCard = false;
            cards.forEach(card => {
                if (card.style.display !== 'none') {
                    hasVisibleCard = true;
                }
            });
            block.style.display = hasVisibleCard ? '' : 'none';
        });
    }
}
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>