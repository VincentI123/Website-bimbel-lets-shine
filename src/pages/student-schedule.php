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


// --- KODE LOGIKA HALAMAN ---
$db = Database::getInstance()->getConnection();
$student_id = $user['id'];
$db_error = null;

$view = isset($_GET['view']) ? $_GET['view'] : 'week';
$current_day_en = date('l');
$day_to_show = isset($_GET['day']) ? $_GET['day'] : $current_day_en;

// Daftar Hari
$days_translation = [
    'Monday' => __('Monday'), 
    'Tuesday' => __('Tuesday'), 
    'Wednesday' => __('Wednesday'),
    'Thursday' => __('Thursday'), 
    'Friday' => __('Friday'), 
    'Saturday' => __('Saturday')
];

$schedules = [];
try {
    // Query tetap mengambil teacher_name untuk keperluan debug/backend, tapi tidak akan ditampilkan
    $stmt = $db->prepare("
        SELECT s.id, sub.id as subject_id, sub.name as subject_name, u.name as teacher_name, 
               s.day_of_week, s.start_time, s.end_time, s.room
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN users u ON s.teacher_id = u.id
        JOIN student_enrollments se ON s.id = se.schedule_id
        WHERE se.student_id = :student_id
        ORDER BY s.start_time
    ");
    $stmt->execute([':student_id' => $student_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = __('approval_db_error') . " " . $e->getMessage();
}

// --- SIAPKAN GRID JADWAL ---
$time_slots = [];
$grid = [];

if ($view === 'week') {
    try {
        $stmt_slots = $db->query("SELECT time_label FROM time_slots ORDER BY display_order ASC");
        $time_slots_raw = $stmt_slots->fetchAll(PDO::FETCH_COLUMN); 
        
        if (empty($time_slots_raw)) {
            $time_slots = ['08:00 - 09:30', '10:00 - 11:30', '13:00 - 14:30']; 
        } else {
            $time_slots = $time_slots_raw;
        }
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
        $time_slots = [];
    }

    foreach ($time_slots as $slot) {
        foreach (array_keys($days_translation) as $day) {
            $grid[$slot][$day] = [];
        }
    }

    foreach ($schedules as $schedule) {
        $start_formatted = date('H:i', strtotime($schedule['start_time']));
        
        $found_slot = null;
        foreach ($time_slots as $ts) {
            if (strpos($ts, $start_formatted) !== false) {
                $found_slot = $ts;
                break;
            }
        }

        if ($found_slot && isset($grid[$found_slot][$schedule['day_of_week']])) {
            $grid[$found_slot][$schedule['day_of_week']][] = $schedule;
        }
    }
}

$day_schedules = [];
if ($view === 'day') {
    foreach ($schedules as $schedule) {
        if ($schedule['day_of_week'] === $day_to_show) {
            $day_schedules[] = $schedule;
        }
    }
}
?>

<style>
    /* --- CSS UTAMA --- */
    #schedule-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed; 
    }

    #schedule-table th.time-col, 
    #schedule-table td.time-col {
        width: 12%; 
        text-align: center;
        vertical-align: middle;
        background-color: #f9fafb;
        font-weight: 600;
        color: #4b5563;
    }

    #schedule-table th.day-col,
    #schedule-table td.day-col {
        width: 14.6%; 
        vertical-align: top;
    }

    #schedule-table th {
        padding: 12px;
        font-size: 0.875rem;
        border: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }

    #schedule-table td {
        padding: 8px;
        border: 1px solid #e5e7eb;
        height: 100px; 
    }

    /* --- CSS PRINT --- */
    @media print {
        body * {
            visibility: hidden;
        }
        .no-print, .no-print * {
            display: none !important;
        }
        #printable-schedule, #printable-schedule * {
            visibility: visible;
        }
        #printable-schedule {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            margin: 0;
            background-color: white;
        }
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        #schedule-table {
            width: 100%;
            font-size: 9pt;
            border: 1px solid #000;
        }
        #schedule-table th, #schedule-table td {
            border: 1px solid #000;
            padding: 5px;
        }
        #schedule-table th {
            background-color: #eee !important;
            color: #000 !important;
        }
        #printable-schedule::before {
            content: '<?php echo __('student_schedule_print'); ?>'; 
            display: block;
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 15px;
            visibility: visible;
            text-transform: uppercase;
        }
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
    }
</style>

<?php
// --- Link Bahasa ---
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
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('student_schedule_my_title'); ?></h1>
        <div class="flex items-center gap-4">
            <div class="bg-gray-200 p-1 rounded-lg hidden md:flex items-center text-sm">
                
                <a href="student-dashboard.php?page=schedule&view=week" 
                   class="hidden md:block px-3 py-1 rounded-md transition-all <?php echo ($view === 'week') ? 'bg-white shadow font-semibold text-orange-600' : 'text-gray-600 hover:bg-gray-100' ?>">
                   <?php echo __('schedule_view_week'); ?>
                </a>

                <a href="student-dashboard.php?page=schedule&view=day" 
                   class="w-full md:w-auto text-center px-3 py-1 rounded-md transition-all <?php echo ($view === 'day') ? 'bg-white shadow font-semibold text-orange-600' : 'text-gray-600 hover:bg-gray-100' ?>">
                   <?php echo __('schedule_view_day'); ?>
                </a>
                
            </div>
            <?php if ($view === 'week'): ?>
                 <button onclick="window.print()" class="btn-white text-gray-800 font-semibold px-4 py-2 rounded-lg flex items-center gap-2 text-sm bg-white border border-gray-200 hover:bg-gray-50 transition-colors">
                    <i data-lucide="printer" class="w-5 h-5"></i> <?php echo __('schedule_print_week'); ?>
                 </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="printable-schedule" class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <?php if (isset($db_error)): ?>
             <div class="text-center text-red-500 py-8"><?php echo __('approval_db_error'); ?> <?php echo $db_error; ?></div>
        <?php elseif (empty($schedules)): ?>
             <div class="text-center text-gray-500 py-8"><?php echo __('student_schedule_no_schedule'); ?></div>
        <?php else: ?>
            <?php if ($view === 'week'): ?>
                <div class="overflow-x-auto">
                    <table id="schedule-table">
                        <thead>
                            <tr>
                                 <th class="time-col"><?php echo __('schedule_table_time'); ?></th>
                                <?php foreach ($days_translation as $day_id): ?>
                                    <th class="day-col"><?php echo $day_id; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalClassesInView = 0; 
                            foreach ($time_slots as $slot): 
                                $hasClassesInThisSlot = false;
                                foreach (array_keys($days_translation) as $day_en) {
                                    if (!empty($grid[$slot][$day_en])) {
                                        $hasClassesInThisSlot = true;
                                        $totalClassesInView++;
                                        break;
                                    }
                                }
                                if (!$hasClassesInThisSlot) continue; 
                            ?>
                                <tr>
                                    <td class="time-col"><?php echo $slot; ?></td>
                                    <?php foreach (array_keys($days_translation) as $day_en): ?>
                                        <td class="day-col">
                                            <?php if (!empty($grid[$slot][$day_en])): ?>
                                                <?php foreach ($grid[$slot][$day_en] as $schedule): 
                                                    $raw_room = $schedule['room']; 
                                                    $room_number = preg_replace('/[^0-9]/', '', $raw_room); 
                                                    $room_base_word = __('schedule_form_room_base'); 
                                                    $translated_room = $room_base_word . ' ' . $room_number;
                                                ?>
                                                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-2 rounded mb-2 hover:shadow-md transition-shadow text-left">
                                                         <p class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($schedule['subject_name']); ?></p>
                                                        <p class="text-xs text-gray-600 mt-1">
                                                            <span class="font-medium"><?php echo __('student_schedule_room'); ?></span> <?php echo htmlspecialchars($translated_room); ?>
                                                        </p>
                                                        </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if ($totalClassesInView === 0): ?>
                                <tr>
                                    <td colspan="<?php echo count($days_translation) + 1; ?>" class="p-4 text-center text-gray-500">
                                        <?php echo __('student_schedule_no_schedule'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: // TAMPILAN HARIAN ?>
                <div class="no-print flex border-b mb-4 overflow-x-auto">
                    <?php foreach ($days_translation as $day_en => $day_id): ?>
                        <a href="student-dashboard.php?page=schedule&view=day&day=<?php echo $day_en; ?>" class="px-6 py-3 text-center text-sm font-medium whitespace-nowrap transition-colors <?php echo ($day_to_show === $day_en) ? 'border-b-2 border-orange-600 text-orange-600' : 'text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?>">
                            <?php echo $day_id; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div id="schedule-table">
                    <table class="w-full text-left table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="p-3 font-semibold text-gray-600 border"><?php echo __('schedule_table_time'); ?></th>
                                <th class="p-3 font-semibold text-gray-600 border"><?php echo __('schedule_table_subject'); ?></th>
                                <th class="p-3 font-semibold text-gray-600 border"><?php echo __('schedule_table_room'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($day_schedules)): ?>
                            <?php foreach ($day_schedules as $schedule): 
                                $raw_room = $schedule['room']; 
                                $room_number = preg_replace('/[^0-9]/', '', $raw_room); 
                                $room_base_word = __('schedule_form_room_base'); 
                                $translated_room = $room_base_word . ' ' . $room_number;
                            ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="p-3 align-top border"><?php echo date('H:i', strtotime($schedule['start_time'])) . ' - ' . date('H:i', strtotime($schedule['end_time'])); ?></td>
                                    <td class="p-3 align-top font-bold border"><?php echo htmlspecialchars($schedule['subject_name']); ?></td>
                                    <td class="p-3 align-top border"><?php echo htmlspecialchars($translated_room); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-gray-400 py-8 border"><?php echo __('schedule_no_classes_day'); ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    function checkAndEnforceMobileView() {
        // Cek lebar layar (kurang dari 768px dianggap Mobile)
        if (window.innerWidth < 768) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentView = urlParams.get('view');

            // Jika view masih 'week' (atau kosong), PAKSA pindah ke 'day'
            if (currentView !== 'day') {
                urlParams.set('view', 'day');
                
                // Jika parameter 'day' belum ada, set ke hari ini
                if (!urlParams.has('day')) {
                    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    const today = days[new Date().getDay()];
                    // Koreksi jika hari Minggu, ubah ke Senin (sesuai logika PHP Anda)
                    urlParams.set('day', today === 'Sunday' ? 'Monday' : today);
                }

                // Lakukan Redirect
                window.location.search = urlParams.toString();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Jalankan saat halaman pertama kali dimuat
        checkAndEnforceMobileView();

        // 2. Jalankan juga saat layar di-resize (PENTING UNTUK TES DI DESKTOP)
        window.addEventListener('resize', () => {
            checkAndEnforceMobileView();
        });
    });
</script>