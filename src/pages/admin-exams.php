<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Mulai menangkap output
// --- AKHIR BAGIAN BARU ---


// --- "OTAK" BAHASA (VERSI UNIVERSAL REQUEST_URI + MANUAL QUERY + OB CLEAN) ---

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
            // Format Mandarin: YYYY年MM月DD日
            return date('Y', $timestamp) . '年' . date('m', $timestamp) . '月' . date('d', $timestamp) . '日';
        } elseif ($lang == 'id') {
            // Format Indonesia
            $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
        } else {
            // Format Inggris
            return date('d F Y', $timestamp);
        }
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI HALAMAN ANDA DIMULAI DI SINI ---

// Ambil data ujian dari database
$stmt = $db->prepare("
    SELECT
        e.id AS exam_id,
        s.name AS subject_name,
        e.exam_date,
        u_teacher.name AS teacher_name,
        u_student.name AS student_name,
        e.subject_id,
        e.teacher_id
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN users u_teacher ON e.teacher_id = u_teacher.id
    LEFT JOIN users u_student ON e.student_id = u_student.id
    ORDER BY e.exam_date DESC, s.name
");
$stmt->execute();
$exams_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan ujian
$grouped_exams = [];
foreach ($exams_raw as $exam) {
    $key = $exam['subject_id'] . '-' . $exam['exam_date'] . '-' . $exam['teacher_id'];
    if (!isset($grouped_exams[$key])) {
        $grouped_exams[$key] = [
            'subject_name' => $exam['subject_name'],
            'exam_date' => $exam['exam_date'],
            'teacher_name' => $exam['teacher_name'],
            'students' => [],
            'exam_ids' => []
        ];
    }
    if ($exam['student_name']) {
        $grouped_exams[$key]['students'][] = $exam['student_name'];
    }
    $grouped_exams[$key]['exam_ids'][] = $exam['exam_id'];
}

// Urutkan siswa di setiap grup ujian
foreach ($grouped_exams as &$exam_group) {
    if (!empty($exam_group['students'])) {
        sort($exam_group['students']);
    }
}
unset($exam_group);

// Logika untuk menghapus ujian (dipindahkan dari admin-dashboard.php atau dibiarkan di sini jika struktur Anda mengizinkan)
// PENTING: Jika logika hapus ada di admin-dashboard.php, kode ini mungkin redundan atau perlu disesuaikan.
// Namun, saya sertakan di sini dengan perbaikan bahasa untuk kelengkapan.
if (isset($_GET['action']) && $_GET['action'] == 'delete_exam' && isset($_GET['ids'])) {
    $exam_ids_to_delete = explode(',', $_GET['ids']);
    try {
        $db->beginTransaction();
        $stmt_delete_exam = $db->prepare("DELETE FROM exams WHERE id = :exam_id");
        foreach ($exam_ids_to_delete as $exam_id) {
            $stmt_delete_exam->execute([':exam_id' => $exam_id]);
        }
        $db->commit();
        
        $_SESSION['flash_message'] = __('exams_status_deleted'); // Terjemahan
        ob_end_clean();
        echo '<script>window.location.href="admin-dashboard.php?page=exams";</script>';
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['flash_error'] = __('exams_status_error') . " " . $e->getMessage(); // Terjemahan
        ob_end_clean();
        echo '<script>window.location.href="admin-dashboard.php?page=exams";</script>';
        exit;
    }
}

// Ambil pesan flash (jika belum diambil di admin-dashboard.php)
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
$flash_error = null;
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
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

    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <h1 class="text-3xl font-bold text-gray-800 self-start md:self-center"><?php echo __('exams_page_title'); ?></h1>
        <div class="flex items-center space-x-2 sm:space-x-4 w-full md:w-auto">
            <div class="relative flex-grow">
                <input type="text" id="examSearch" placeholder="<?php echo __('exams_search_placeholder'); ?>" class="contact-input pl-10 w-full">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
            </div>
            <a href="admin-dashboard.php?page=exam-form" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-4 sm:px-6 py-3 rounded-lg flex items-center gap-2 transition-colors whitespace-nowrap">
                <i data-lucide="plus" class="w-5 h-5"></i>
                <span class="hidden sm:inline"><?php echo __('exams_add_new'); ?></span>
            </a>
        </div>
    </div>

    <div class="bg-white p-4 sm:p-8 rounded-2xl shadow-lg shadow-orange-900/5">
        <?php if (empty($grouped_exams)): ?>
            <div class="text-center py-10 text-gray-500">
                 <i data-lucide="clipboard-x" class="mx-auto h-12 w-12 text-gray-400"></i>
                 <h3 class="mt-2 text-lg font-medium text-gray-900"><?php echo __('exams_table_empty'); ?></h3>
            </div>
        <?php else: ?>
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left" id="examsTable">
                    <thead>
                        <tr class="border-b-2 border-gray-200 bg-gray-50">
                            <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('exams_table_no'); ?></th>
                            <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('exams_table_subject'); ?></th>
                            <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('exams_table_students'); ?></th>
                            <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('exams_table_date'); ?></th>
                            <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider"><?php echo __('exams_table_teacher'); ?></th>
                            <th class="px-5 py-4 text-sm font-semibold text-gray-600 uppercase tracking-wider text-center"><?php echo __('exams_table_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 0; foreach ($grouped_exams as $exam): $i++; ?>
                        <tr class="exam-row border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-5 py-5 text-gray-800 align-top"><?php echo $i; ?></td>
                            <td class="px-5 py-5 text-gray-800 font-medium align-top"><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                            <td class="px-5 py-5 text-gray-800 align-top">
                                <?php 
                                if (!empty($exam['students'])) {
                                    echo '<div class="flex flex-col gap-1">';
                                    foreach($exam['students'] as $student) {
                                        echo '<div class="text-sm">- ' . htmlspecialchars($student) . '</div>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<span class="text-sm text-gray-500">'. __('exams_table_no_students') .'</span>';
                                }
                                ?>
                            </td>
                            <td class="px-5 py-5 text-gray-800 align-top">
                                <?php echo date_format_intl($exam['exam_date'], $currentLang); ?>
                            </td>
                            <td class="px-5 py-5 text-gray-800 align-top"><?php echo htmlspecialchars($exam['teacher_name']); ?></td>
                            <td class="px-5 py-5 text-gray-800 align-top">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="admin-dashboard.php?page=exam-form&ids=<?php echo implode(',', $exam['exam_ids']); ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('exams_action_edit'); ?>">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </a>
                                    <a href="admin-dashboard.php?page=exams&action=delete_exam&ids=<?php echo implode(',', $exam['exam_ids']); ?>" onclick="return confirm('<?php echo __('exams_delete_confirm'); ?>')" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('exams_action_delete'); ?>">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-4" id="examCardsContainer">
                <?php foreach ($grouped_exams as $exam): ?>
                <div class="exam-card bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                    <div class="flex flex-col gap-3">
                        <div class="flex justify-between items-start">
                            <div class="flex-grow">
                                <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($exam['subject_name']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($exam['teacher_name']); ?></p>
                                <p class="text-sm text-gray-500 mt-1"><?php echo date_format_intl($exam['exam_date'], $currentLang); ?></p>                            </div>
                            <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                                <a href="admin-dashboard.php?page=exam-form&ids=<?php echo implode(',', $exam['exam_ids']); ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('exams_action_edit'); ?>">
                                    <i data-lucide="edit" class="w-5 h-5"></i>
                                </a>
                                <a href="admin-dashboard.php?page=exams&action=delete_exam&ids=<?php echo implode(',', $exam['exam_ids']); ?>" onclick="return confirm('<?php echo __('exams_delete_confirm'); ?>')" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('exams_action_delete'); ?>">
                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-3">
                            <h4 class="font-semibold text-gray-700 mb-2"><?php echo __('exams_table_students'); ?></h4>
                             <?php 
                                if (!empty($exam['students'])) {
                                    echo '<div class="flex flex-col gap-1 text-sm text-gray-600">';
                                    foreach($exam['students'] as $student) {
                                        echo '<div class="pl-2">- ' . htmlspecialchars($student) . '</div>';
                                    }
                                    echo '</div>';
                                } else {
                                     echo '<span class="text-sm text-gray-500 pl-2">' . __('exams_table_no_students') . '</span>';
                                }
                                ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('examSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase().trim();

            // Filter table rows (desktop)
            const tableRows = document.querySelectorAll('#examsTable tbody .exam-row');
            tableRows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                if (rowText.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });

            // Filter cards (mobile)
            const cards = document.querySelectorAll('#examCardsContainer .exam-card');
            cards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                if (cardText.includes(filter)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        });
    }
});

if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>