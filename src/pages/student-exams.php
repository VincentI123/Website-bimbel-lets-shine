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

// 8. Fungsi Helper Format Tanggal Manual
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
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE LOGIKA HALAMAN ---
$db = Database::getInstance()->getConnection();
$student_id = $user['id'];
$today = date('Y-m-d');

// Ambil SEMUA ujian yang akan datang
$stmt = $db->prepare("
    SELECT
        e.id AS exam_id,
        s.name AS subject_name,
        e.exam_date
    FROM
        exams e
    JOIN
        subjects s ON e.subject_id = s.id
    WHERE
        e.student_id = :student_id
        AND e.exam_date >= :today
    ORDER BY
        e.exam_date ASC, s.name ASC
");

$stmt->execute([
    ':student_id' => $student_id,
    ':today' => $today
]);
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

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
        <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0"><?php echo __('myexams_page_title'); ?></h1>
    </div>

    <div class="bg-white p-4 md:p-8 rounded-2xl shadow-lg shadow-orange-900/5">
        
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left" id="examsTable">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50">
                        <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('myexams_table_no'); ?></th>
                        <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('myexams_table_subject'); ?></th>
                        <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('myexams_table_date'); ?></th>
                        <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('myexams_table_days_left'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcoming_exams)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-10 text-gray-500">
                                <?php echo __('myexams_no_exams'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $i = 0; foreach ($upcoming_exams as $exam): $i++; ?>
                        <?php
                            $examDate = new DateTime($exam['exam_date']);
                            $todayDate = new DateTime('today'); 
                            $interval = $todayDate->diff($examDate);
                            $days_left_raw = (int)$interval->format('%r%a'); 
                            
                            $formatted_date = date_format_intl($exam['exam_date'], $currentLang);
                        ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-5 py-5 text-gray-800 align-top"><?php echo $i; ?></td>
                            <td class="px-5 py-5 text-gray-800 font-medium align-top"><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                            <td class="px-5 py-5 text-gray-800 align-top"><?php echo $formatted_date; ?></td>
                            <td class="px-5 py-5 text-gray-800 align-top">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    <?php if ($days_left_raw <= 1): ?>
                                        bg-red-100 text-red-800
                                    <?php elseif ($days_left_raw <= 3): ?>
                                        bg-yellow-100 text-yellow-800
                                    <?php else: ?>
                                        bg-green-100 text-green-800
                                    <?php endif; ?>
                                ">
                                    <?php 
                                        if ($days_left_raw == 0) {
                                            echo __('student_exam_today');
                                        } elseif ($days_left_raw == 1) {
                                            echo sprintf(__('myexams_day_left_singular'), $days_left_raw);
                                        } else {
                                            echo sprintf(__('myexams_day_left_plural'), $days_left_raw);
                                        }
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="md:hidden flex flex-col gap-4">
            <?php if (empty($upcoming_exams)): ?>
                <div class="text-center py-8 text-gray-500 bg-gray-50 rounded-xl border border-gray-100">
                    <i data-lucide="calendar-x" class="w-10 h-10 mx-auto text-gray-300 mb-2"></i>
                    <p><?php echo __('myexams_no_exams'); ?></p>
                </div>
            <?php else: ?>
                <?php 
                $no = 0; 
                foreach ($upcoming_exams as $exam): 
                    $no++; 
                    $examDate = new DateTime($exam['exam_date']);
                    $todayDate = new DateTime('today');
                    $interval = $todayDate->diff($examDate);
                    $days_left_raw = (int)$interval->format('%r%a');
                    $formatted_date = date_format_intl($exam['exam_date'], $currentLang);
                    
                    // Warna badge polos (tanpa animasi)
                    $badgeClass = "bg-green-100 text-green-700 border-green-200";
                    $stripColor = "bg-green-500";

                    if ($days_left_raw <= 1) {
                        $badgeClass = "bg-red-100 text-red-700 border-red-200";
                        $stripColor = "bg-red-500";
                    } elseif ($days_left_raw <= 3) {
                        $badgeClass = "bg-yellow-100 text-yellow-700 border-yellow-200";
                        $stripColor = "bg-yellow-500";
                    }
                ?>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex flex-col gap-3 relative overflow-hidden">
                    <div class="absolute left-0 top-0 bottom-0 w-1 <?php echo $stripColor; ?>"></div>
                    
                    <div class="flex justify-between items-start pl-2">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 mt-1">
                                <span class="text-gray-400 font-semibold text-sm">#<?php echo $no; ?></span>
                            </div>

                            <div>
                                <h3 class="font-bold text-lg text-gray-800 leading-tight"><?php echo htmlspecialchars($exam['subject_name']); ?></h3>
                                <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                    <i data-lucide="calendar" class="w-3 h-3"></i>
                                    <?php echo $formatted_date; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex-shrink-0 ml-2">
                            <span class="px-2 py-1 text-xs font-bold rounded-lg border <?php echo $badgeClass; ?>">
                                <?php 
                                    if ($days_left_raw == 0) {
                                        echo __('student_exam_today');
                                    } elseif ($days_left_raw == 1) {
                                        echo sprintf(__('myexams_day_left_singular'), $days_left_raw);
                                    } else {
                                        echo sprintf(__('myexams_day_left_plural'), $days_left_raw);
                                    }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>