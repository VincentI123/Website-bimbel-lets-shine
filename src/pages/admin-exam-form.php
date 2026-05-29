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
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI HALAMAN ANDA DIMULAI DI SINI ---
$db = Database::getInstance()->getConnection();

// Determine if we are in edit mode based on the URL parameter.
$is_edit_mode = isset($_GET['ids']) && !empty($_GET['ids']);
$exam_ids_str = $is_edit_mode ? $_GET['ids'] : '';
$exam_ids = $is_edit_mode ? explode(',', $exam_ids_str) : [];
$first_exam_id = $is_edit_mode ? $exam_ids[0] : null;

$subject_id = '';
$teacher_id = '';
$exam_date = '';
$selected_students = [];
$error_message = '';
$success_message = '';

// If in edit mode, fetch the existing exam data to populate the form.
if ($is_edit_mode && $first_exam_id) {
    try {
        // Fetch details from the first exam ID (they should all be the same).
        $stmt = $db->prepare("SELECT subject_id, teacher_id, exam_date FROM exams WHERE id = ?");
        $stmt->execute([$first_exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exam) {
            $subject_id = $exam['subject_id'];
            $teacher_id = $exam['teacher_id'];
            $exam_date = $exam['exam_date'];
        }

        // Fetch all unique student IDs associated with this exam group.
        $placeholders = implode(',', array_fill(0, count($exam_ids), '?'));
        $stmt_students = $db->prepare("SELECT DISTINCT student_id FROM exams WHERE id IN ($placeholders) AND student_id IS NOT NULL");
        $stmt_students->execute($exam_ids);
        $selected_students = $stmt_students->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // DIUBAH: Menggunakan terjemahan
        $error_message = __('exam_form_error_fetch') . " " . $e->getMessage();
    }
}

// Handle form submission for both creating and updating exams.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    $subject_id_post = $_POST['subject_id'];
    $teacher_id_post = $_POST['teacher_id'];
    $exam_date_post = $_POST['exam_date'];
    $student_ids_post = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];

    // Check if this is an update based on the hidden input field.
    $is_update_action = isset($_POST['ids']) && !empty($_POST['ids']);
    $exam_ids_to_process = $is_update_action ? explode(',', $_POST['ids']) : [];

    try {
        $db->beginTransaction();

        // If this is an update, delete all previous entries for this exam group.
        if ($is_update_action && !empty($exam_ids_to_process)) {
            $placeholders = implode(',', array_fill(0, count($exam_ids_to_process), '?'));
            $stmt_delete = $db->prepare("DELETE FROM exams WHERE id IN ($placeholders)");
            $stmt_delete->execute($exam_ids_to_process);
        }

        // Insert the new set of records.
        if (!empty($student_ids_post)) {
            $stmt_insert = $db->prepare("INSERT INTO exams (subject_id, teacher_id, exam_date, student_id) VALUES (?, ?, ?, ?)");
            foreach ($student_ids_post as $student_id) {
                $stmt_insert->execute([$subject_id_post, $teacher_id_post, $exam_date_post, $student_id]);
            }
        } else {
            // If no students are selected, create one exam entry with no student.
            $stmt_insert = $db->prepare("INSERT INTO exams (subject_id, teacher_id, exam_date) VALUES (?, ?, ?)");
            $stmt_insert->execute([$subject_id_post, $teacher_id_post, $exam_date_post]);
        }

        $db->commit();

        // [PERBAIKAN] Simpan pesan ke Session agar muncul di halaman list
        if ($is_update_action) {
            $_SESSION['flash_message'] = __('exam_success_update');
        } else {
            $_SESSION['flash_message'] = __('exam_success_add');
        }

        ob_end_clean();
        // Gunakan header location PHP yang lebih stabil daripada JS window.location
        header("Location: admin-dashboard.php?page=exams");
        exit;

    } catch (PDOException $e) {
        $db->rollBack();
        // DIUBAH: Menggunakan terjemahan
        $error_message = __('exam_form_error_db') . " " . $e->getMessage();
    }
}

// Fetch master data for dropdowns.
try {
    $subjects = $db->query("SELECT id, name FROM subjects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $teachers = $db->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $students = $db->query("SELECT id, name, subjects, available_days, available_times FROM users WHERE role = 'student' AND status = 'approved' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // DIUBAH: Menggunakan terjemahan
    $error_message = __('exam_form_error_master') . " " . $e->getMessage();
}

// DIUBAH: Judul dinamis
$pageTitleKey = $is_edit_mode ? 'exam_form_edit_title' : 'exam_form_add_title';
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
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __($pageTitleKey); ?></h1>
        <a href="admin-dashboard.php?page=exams" class="text-orange-600 hover:text-orange-700 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <?php echo __('exam_form_back_link'); ?>
        </a>
    </div>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="space-y-8">
        <?php if ($is_edit_mode): ?>
            <input type="hidden" name="ids" value="<?php echo htmlspecialchars($exam_ids_str); ?>">
        <?php endif; ?>

        <div class="bg-white p-8 rounded-2xl shadow-lg shadow-orange-900/5 space-y-6">
            <h3 class="text-xl font-bold text-gray-800"><?php echo __('exam_form_section_details'); ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="relative">
                    <i data-lucide="book" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <select id="subject_id" name="subject_id" required class="pl-10 contact-input"
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('exam_error_subject')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <option value=""><?php echo __('exam_form_select_subject'); ?></option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo ($subject_id == $subject['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="relative">
                    <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <select id="teacher_id" name="teacher_id" required class="pl-10 contact-input"
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('exam_error_teacher')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <option value=""><?php echo __('exam_form_select_teacher'); ?></option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo ($teacher_id == $teacher['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($teacher['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="relative">
                    <i data-lucide="calendar" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="date" id="exam_date" name="exam_date" 
                           value="<?php echo htmlspecialchars($exam_date); ?>" 
                           min="<?php echo date('Y-m-d'); ?>" 
                           required class="pl-10 contact-input"
                           oninvalid="this.setCustomValidity('<?php echo addslashes(__('exam_error_date')); ?>')"
                           oninput="this.setCustomValidity('')">
                </div>
            </div>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-lg shadow-orange-900/5 space-y-4">
            <h3 class="text-xl font-bold text-gray-800"><?php echo __('exam_form_section_enrollment'); ?></h3>
            
            <div class="relative w-full md:w-1/2">
                <input type="text" id="studentSearchInput" placeholder="<?php echo __('exam_form_search_placeholder'); ?>" class="contact-input pl-10 w-full">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
            </div>

            <div id="studentListContainer" class="w-full p-4 border rounded-lg h-80 overflow-y-auto bg-gray-50/50">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($students as $student): ?>
                        <label class="student-label-item flex items-start space-x-3 p-3 rounded-xl bg-white border border-gray-100 hover:border-orange-300 hover:shadow-md transition-all cursor-pointer">
                            <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>"
                                <?php echo in_array($student['id'], $selected_students) ? 'checked' : ''; ?>
                                class="mt-1 h-5 w-5 rounded border-gray-300 text-orange-600 shadow-sm focus:ring-orange-500 focus:ring-offset-0 flex-shrink-0">
                            
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-gray-800 font-bold student-name"><?php echo htmlspecialchars($student['name']); ?></span>
                                
                                <div class="text-xs text-gray-500 mt-1.5 space-y-1">
                                    <?php if(!empty($student['subjects'])): ?>
                                        <div class="flex gap-1">
                                            <i data-lucide="book-open" class="w-3 h-3 text-orange-400 flex-shrink-0 mt-0.5"></i>
                                            <span class="break-words"><?php echo htmlspecialchars($student['subjects']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if(!empty($student['available_days'])): ?>
                                        <div class="flex gap-1">
                                            <i data-lucide="calendar" class="w-3 h-3 text-blue-400 flex-shrink-0 mt-0.5"></i>
                                            <span class="break-words"><?php echo htmlspecialchars($student['available_days']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if(!empty($student['available_times'])): ?>
                                         <div class="flex gap-1">
                                            <i data-lucide="clock" class="w-3 h-3 text-green-400 flex-shrink-0 mt-0.5"></i>
                                            <span class="break-words"><?php echo htmlspecialchars($student['available_times']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div id="studentListEmpty" class="text-center text-gray-500 pt-20 hidden">
                    <i data-lucide="search-x" class="w-12 h-12 mx-auto text-gray-300 mb-3"></i>
                    <p><?php echo __('exam_form_empty_search'); ?></p> 
                </div>
            </div>
            <p class="text-sm text-gray-500"><?php echo __('exam_form_enrollment_note'); ?></p>
        </div>

        <div class="flex justify-end gap-4 pt-4">
            <a href="admin-dashboard.php?page=exams" class="px-6 py-2 rounded-lg text-gray-800 bg-gray-300 hover:bg-gray-400 transition-colors font-bold"><?php echo __('exam_form_button_cancel'); ?></a>
            <button type="submit" name="submit_exam" class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-6 py-2 rounded-lg transition-colors flex items-center gap-2">
                <i data-lucide="check" class="w-5 h-5"></i>
                <span><?php echo $is_edit_mode ? __('exam_form_button_update') : __('exam_form_button_create'); ?></span>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- FUNGSI PENCARIAN SISWA (KODE LAMA) ---
    const searchInput = document.getElementById('studentSearchInput');
    const studentContainer = document.getElementById('studentListContainer');
    const studentLabels = studentContainer.querySelectorAll('.student-label-item');
    const studentListEmpty = document.getElementById('studentListEmpty');

    function filterStudentsBySearch() {
        const filter = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        studentLabels.forEach(label => {
            const studentNameSpan = label.querySelector('.student-name');
            if (studentNameSpan) {
                const cardText = label.textContent.toLowerCase().trim();
                if (cardText.includes(filter)) {
                    label.style.display = 'flex';
                    visibleCount++;
                } else {
                    label.style.display = 'none';
                }
            }
        });

        if (studentListEmpty) {
            studentListEmpty.classList.toggle('hidden', visibleCount > 0);
        }
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', filterStudentsBySearch);
    }
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // --- [BARU] VALIDASI CHECKBOX SISWA ---
    // Memastikan minimal 1 siswa dipilih sebelum submit
    const checkboxGroup = document.querySelectorAll('input[name="student_ids[]"]');
    const form = document.querySelector('form');
    const errorMsg = '<?php echo addslashes(__('exam_error_students')); ?>';

    function validateCheckboxes() {
        const firstCheckbox = checkboxGroup[0];
        if (!firstCheckbox) return;

        const isChecked = document.querySelectorAll('input[name="student_ids[]"]:checked').length > 0;
        
        if (isChecked) {
            firstCheckbox.setCustomValidity('');
            firstCheckbox.removeAttribute('required');
        } else {
            firstCheckbox.setCustomValidity(errorMsg);
            firstCheckbox.setAttribute('required', 'required');
        }
    }

    // Pasang listener
    checkboxGroup.forEach(cb => cb.addEventListener('change', validateCheckboxes));
    if (form) form.addEventListener('submit', validateCheckboxes);
    
    // Cek awal
    validateCheckboxes();
});
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>