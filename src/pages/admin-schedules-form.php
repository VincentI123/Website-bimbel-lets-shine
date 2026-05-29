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
    
    // Bersihkan URL dari parameter ?lang=...
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];
    
    $query_params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }
    unset($query_params['lang']);
    
    // Bangun ulang URL (pertahankan parameter id jika sedang edit)
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
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI HALAMAN ANDA DIMULAI DI SINI ---
$db = Database::getInstance()->getConnection();
$scheduleId = isset($_GET['id']) ? $_GET['id'] : null;
$isEditing = $scheduleId !== null;
$schedule = null;

$pageTitleKey = $isEditing ? 'schedule_form_edit_title' : 'schedule_form_add_title';

$subjects = [];
$teachers = [];
$students = [];
$enrolled_student_ids = [];
$time_slots_options = []; 
$formError = null; 

try {
    $stmt_subjects = $db->query("SELECT id, name FROM subjects ORDER BY name");
    $subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);

    // --- [PERUBAHAN 1] ---
    // Ambil SEMUA data ketersediaan guru
    $stmt_teachers = $db->query("SELECT id, name, available_days, employment_status, subjects, available_times FROM users WHERE role = 'teacher' ORDER BY name");
    $teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

   // [DIUBAH] Ambil 'subjects', 'available_days' DAN 'available_times' untuk SISWA
$stmt_students = $db->query("SELECT id, name, available_days, available_times, subjects FROM users WHERE role = 'student' AND status = 'approved' ORDER BY name");
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
    // --- [AKHIR PERUBAHAN] ---

    $stmt_slots = $db->query("SELECT time_value, time_label, specific_days FROM time_slots ORDER BY display_order ASC");
    $time_slots_options = $stmt_slots->fetchAll(PDO::FETCH_ASSOC);

    if ($isEditing) {
        $stmt = $db->prepare("SELECT * FROM schedules WHERE id = :id");
        $stmt->execute(array(':id' => $scheduleId));
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($schedule) {
            // [DIUBAH] Simpan juga time_value (09:30,11:00) untuk mencocokkan dropdown
            $schedule['start_time_formatted'] = date('H:i', strtotime($schedule['start_time']));
            $schedule['end_time_formatted'] = date('H:i', strtotime($schedule['end_time']));
            $schedule['time_value'] = $schedule['start_time_formatted'] . ',' . $schedule['end_time_formatted'];
            
            $stmt_enrolled = $db->prepare("SELECT student_id FROM student_enrollments WHERE schedule_id = :schedule_id");
            $stmt_enrolled->execute(array(':schedule_id' => $scheduleId));
            $enrolled_student_ids = $stmt_enrolled->fetchAll(PDO::FETCH_COLUMN);
        }
    }
} catch (PDOException $e) {
    $db_error = __('approval_db_error') . " " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = isset($_POST['subject_id']) ? $_POST['subject_id'] : null;
    $teacher_id = isset($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
    $day_of_week = isset($_POST['day_of_week']) ? $_POST['day_of_week'] : null;
    
    $time_slot = isset($_POST['time_slot']) ? $_POST['time_slot'] : null;
    $start_time = null;
    $end_time = null;

    $time_slot_label = $time_slot;
    if ($time_slot && !empty($time_slots_options)) {
        foreach ($time_slots_options as $option) {
            if ($option['time_value'] === $time_slot) {
                $time_slot_label = $option['time_label'];
                break;
            }
        }
    }
    
    if ($time_slot) {
        $times = explode(',', $time_slot);
        if (count($times) == 2) {
            $start_time = $times[0];
            $end_time = $times[1];
        }
    }
    
    $room = isset($_POST['room']) ? $_POST['room'] : null;
    $student_ids = isset($_POST['students']) ? $_POST['students'] : [];

    // --- VALIDASI BENTROK (Tidak berubah) ---
    if (!$start_time || !$end_time) {
        $formError = __('schedule_form_error_invalid_time'); 
    } else {
        try {
            // 1. Cek Bentrok Guru
            $sql_teacher = "SELECT s.id, u.name as teacher_name, sub.name as subject_name 
                            FROM schedules s 
                            JOIN users u ON s.teacher_id = u.id 
                            JOIN subjects sub ON s.subject_id = sub.id 
                            WHERE s.teacher_id = :teacher_id 
                              AND s.day_of_week = :day_of_week 
                              AND s.start_time < :end_time 
                              AND s.end_time > :start_time";
            $params_teacher = [
                ':teacher_id' => $teacher_id, 
                ':day_of_week' => $day_of_week, 
                ':start_time' => $start_time, 
                ':end_time' => $end_time
            ];
            if ($isEditing) {
                $sql_teacher .= " AND s.id != :schedule_id";
                $params_teacher[':schedule_id'] = $scheduleId;
            }
            $stmt_teacher = $db->prepare($sql_teacher);
            $stmt_teacher->execute($params_teacher);
            $teacher_conflict = $stmt_teacher->fetch(PDO::FETCH_ASSOC);

            if ($teacher_conflict) {
                $formError = sprintf(
                    __('schedule_form_error_teacher_conflict'), 
                    htmlspecialchars($teacher_conflict['teacher_name']), 
                    htmlspecialchars($teacher_conflict['subject_name'])
                );
            } else {
                // 2. Cek Bentrok Ruangan
                $sql_room = "SELECT s.id, u.name as teacher_name, sub.name as subject_name 
                             FROM schedules s 
                             JOIN users u ON s.teacher_id = u.id 
                             JOIN subjects sub ON s.subject_id = sub.id 
                             WHERE s.room = :room 
                               AND s.day_of_week = :day_of_week 
                               AND s.start_time < :end_time 
                               AND s.end_time > :start_time";
                $params_room = [
                    ':room' => $room,
                    ':day_of_week' => $day_of_week,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time
                ];
                if ($isEditing) {
                    $sql_room .= " AND s.id != :schedule_id";
                    $params_room[':schedule_id'] = $scheduleId;
                }
                $stmt_room = $db->prepare($sql_room);
                $stmt_room->execute($params_room);
                $room_conflict = $stmt_room->fetch(PDO::FETCH_ASSOC);

                if ($room_conflict) {
                    $formError = sprintf(
                        __('schedule_form_error_room_conflict'), 
                        htmlspecialchars($room), 
                        htmlspecialchars($room_conflict['teacher_name']), 
                        htmlspecialchars($room_conflict['subject_name'])
                    );
                } else {
                    // 3. Cek Bentrok Siswa
                    foreach ($student_ids as $student_id) {
                        $sql_student = "SELECT s.id, u_student.name as student_name, u_teacher.name as teacher_name, sub.name as subject_name 
                                        FROM schedules s 
                                        JOIN student_enrollments se ON s.id = se.schedule_id 
                                        JOIN users u_student ON se.student_id = u_student.id 
                                        JOIN users u_teacher ON s.teacher_id = u_teacher.id 
                                        JOIN subjects sub ON s.subject_id = sub.id 
                                        WHERE se.student_id = :student_id 
                                          AND s.day_of_week = :day_of_week 
                                          AND s.start_time < :end_time 
                                          AND s.end_time > :start_time";
                        $params_student = [
                            ':student_id'   => $student_id,
                            ':day_of_week'  => $day_of_week,
                            ':start_time'   => $start_time,
                            ':end_time'     => $end_time
                        ];
                        if ($isEditing) {
                            $sql_student .= " AND s.id != :schedule_id";
                            $params_student[':schedule_id'] = $scheduleId;
                        }
                        $stmt_student = $db->prepare($sql_student);
                        $stmt_student->execute($params_student);
                        $student_conflict = $stmt_student->fetch(PDO::FETCH_ASSOC);
                        
                        if ($student_conflict) {
                            $formError = sprintf(
                                __('schedule_form_error_student_conflict'), 
                                htmlspecialchars($student_conflict['student_name']), 
                                htmlspecialchars($student_conflict['subject_name']),
                                htmlspecialchars($student_conflict['teacher_name']),
                                htmlspecialchars($time_slot_label)
                            );
                            break; 
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $formError = __('schedule_form_db_error') . " " . $e->getMessage();
        }
    }
    // --- AKHIR VALIDASI BENTROK ---


    // --- SIMPAN DATA (Tidak berubah) ---
    if (empty($formError)) {
        try {
            $db->beginTransaction();

            if ($isEditing) {
                $sql = "UPDATE schedules SET subject_id = :subject_id, teacher_id = :teacher_id, day_of_week = :day_of_week, start_time = :start_time, end_time = :end_time, room = :room WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute(array(
                    ':subject_id' => $subject_id,
                    ':teacher_id' => $teacher_id,
                    ':day_of_week' => $day_of_week,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time,
                    ':room' => $room,
                    ':id' => $scheduleId
                ));
                $current_schedule_id = $scheduleId;
            } else {
                $sql = "INSERT INTO schedules (subject_id, teacher_id, day_of_week, start_time, end_time, room) VALUES (:subject_id, :teacher_id, :day_of_week, :start_time, :end_time, :room)";
                $stmt = $db->prepare($sql);
                $stmt->execute(array(
                    ':subject_id' => $subject_id,
                    ':teacher_id' => $teacher_id,
                    ':day_of_week' => $day_of_week,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time,
                    ':room' => $room
                ));
                $current_schedule_id = $db->lastInsertId();
            }

            $sql_delete_enrollments = "DELETE FROM student_enrollments WHERE schedule_id = :schedule_id";
            $stmt_delete = $db->prepare($sql_delete_enrollments);
            $stmt_delete->execute(array(':schedule_id' => $current_schedule_id));

            if (!empty($student_ids)) {
                $sql_insert_enrollment = "INSERT INTO student_enrollments (student_id, schedule_id) VALUES (:student_id, :schedule_id)";
                $stmt_insert = $db->prepare($sql_insert_enrollment);
                foreach ($student_ids as $student_id) {
                    $stmt_insert->execute(array(':student_id' => $student_id, ':schedule_id' => $current_schedule_id));
                }
            }
            
            $db->commit();

            // [PERBAIKAN] Simpan pesan sukses ke Session
            if ($isEditing) {
                $_SESSION['flash_message'] = __('schedule_success_update');
            } else {
                $_SESSION['flash_message'] = __('schedule_success_add');
            }

            ob_end_clean();
            header("Location: admin-dashboard.php?page=schedules");
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $formError = __('schedule_form_db_error') . " " . $e->getMessage();
        }
    }
}

$days_translation = [
    'Monday' => __('Monday'), 
    'Tuesday' => __('Tuesday'), 
    'Wednesday' => __('Wednesday'),
    'Thursday' => __('Thursday'), 
    'Friday' => __('Friday'), 
    'Saturday' => __('Saturday')
];
$days = array_keys($days_translation); 
?>

<?php
// --- Link Bahasa (Mempertahankan Parameter URL seperti ?id=...) ---
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

<div class="flex space-x-4 mb-4 justify-end">
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

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __($pageTitleKey); ?></h1>
        <a href="admin-dashboard.php?page=schedules" class="text-orange-600 hover:text-orange-700 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-5 h-5"></i> 
            <?php echo __('schedule_form_back_link'); ?>
        </a>
    </div>

    <?php if (isset($formError)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
            <p><?php echo $formError; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($db_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
            <p><?php echo $db_error; ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-8" id="scheduleForm">
        <div class="bg-white p-8 rounded-2xl shadow-lg shadow-orange-900/5 space-y-6">
            <h3 class="text-xl font-bold text-gray-800"><?php echo __('schedule_form_section_details'); ?></h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="relative">
                    <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <select id="teacher_id" name="teacher_id" required class="pl-10 contact-input"
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('schedule_error_teacher')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <option value=""><?php echo __('schedule_form_select_teacher'); ?></option>
                        <?php foreach ($teachers as $teacher): 
                            $days_data = !empty($teacher['available_days']) ? htmlspecialchars($teacher['available_days']) : '';
                            $status_data = !empty($teacher['employment_status']) ? htmlspecialchars($teacher['employment_status']) : 'part-time';
                            $subjects_data = !empty($teacher['subjects']) ? htmlspecialchars($teacher['subjects']) : '';
                            $times_data = !empty($teacher['available_times']) ? htmlspecialchars($teacher['available_times']) : '';
                        ?>
                            <option value="<?php echo $teacher['id']; ?>" 
                                    data-days="<?php echo $days_data; ?>"
                                    data-status="<?php echo $status_data; ?>"
                                    data-subjects="<?php echo $subjects_data; ?>"
                                    data-times="<?php echo $times_data; ?>"
                                    <?php echo (isset($schedule['teacher_id']) && $schedule['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="relative">
                    <i data-lucide="book" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <select id="subject_id" name="subject_id" required class="pl-10 contact-input" disabled
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('schedule_error_subject')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <option value=""><?php echo __('schedule_form_select_subject'); ?></option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" 
                                    data-subject-name="<?php echo htmlspecialchars($subject['name']); ?>"
                                    <?php echo (isset($schedule['subject_id']) && $schedule['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="relative">
                    <i data-lucide="calendar" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <select id="day_of_week" name="day_of_week" required class="pl-10 contact-input" disabled
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('schedule_error_day')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <option value=""><?php echo __('schedule_form_label_day'); ?></option> 
                        <?php foreach ($days as $day_en): ?>
                            <option value="<?php echo $day_en; ?>" <?php echo (isset($schedule['day_of_week']) && $schedule['day_of_week'] == $day_en) ? 'selected' : ''; ?>><?php echo $days_translation[$day_en]; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="relative">
                    <i data-lucide="clock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors"></i>
                    <label for="time_slot" class="sr-only"><?php echo __('schedule_table_time'); ?></label>
                    <select id="time_slot" name="time_slot" required class="pl-10 contact-input" disabled
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('schedule_error_time')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <option value=""><?php echo __('schedule_form_select_none'); ?> <?php echo __('schedule_table_time'); ?></option>
                        <?php
                        foreach ($time_slots_options as $slot):
                            $value = $slot['time_value']; 
                            $label = $slot['time_label'];
                            $days_data = isset($slot['specific_days']) ? $slot['specific_days'] : ''; // [BARU]
                            $selected = (isset($schedule['time_value']) && $schedule['time_value'] === $value) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                    <?php echo $selected; ?> 
                                    data-time-label="<?php echo htmlspecialchars($label); ?>"
                                    data-days="<?php echo htmlspecialchars($days_data); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                 <div class="relative md:col-span-2">
                    <i data-lucide="map-pin" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <select id="room" name="room" required class="pl-10 contact-input" disabled
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('schedule_error_room')); ?>')"
                            oninput="this.setCustomValidity('')">
                        <option value=""><?php echo __('schedule_form_select_room'); ?></option>
                        <?php
                        $room_base = __('schedule_form_room_base'); 
                        $db_room_number = 0;
                        if (isset($schedule['room'])) {
                            $db_room_number = (int)preg_replace('/[^0-9]/', '', $schedule['room']);
                        }
                        for ($i = 1; $i <= 6; $i++) {
                            $room_name_display = $room_base . " " . $i;
                            $selected = ($db_room_number == $i) ? 'selected' : '';
                            echo "<option value='{$room_name_display}' {$selected}>{$room_name_display}</option>";
                        }
                        ?>
                    </select>
                </div>

            </div>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-lg shadow-orange-900/5 space-y-4">
            <h3 class="text-xl font-bold text-gray-800"><?php echo __('schedule_form_section_enrollment'); ?></h3>
            
            <div class="relative w-full md:w-1/2">
                <input type="text" id="studentSearchInput" placeholder="<?php echo __('exams_search_placeholder'); ?>" class="contact-input pl-10 w-full" disabled>
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
            </div>
            <div class="w-full p-4 border rounded-lg h-64 overflow-y-auto bg-gray-50 opacity-50 transition-opacity" id="studentListContainer">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    
                    <?php foreach ($students as $student): 
                        // [DIUBAH] Tambahkan data-times
                        $days_data = !empty($student['available_days']) ? htmlspecialchars($student['available_days']) : '';
                        $times_data = !empty($student['available_times']) ? htmlspecialchars($student['available_times']) : '';
                        $subjects_data = !empty($student['subjects']) ? htmlspecialchars($student['subjects']) : '';
                    ?>
                        <label class="student-label-item flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer"
                               data-days="<?php echo $days_data; ?>"
                               data-times="<?php echo $times_data; ?>"
                               data-subjects="<?php echo $subjects_data; ?>"
                               data-availability-visible="false"
                               style="display: none;"> <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>"
                                <?php echo in_array($student['id'], $enrolled_student_ids) ? 'checked' : ''; ?>
                                class="h-5 w-5 rounded border-gray-300 text-orange-600 shadow-sm focus:ring-orange-500"
                            >
                            <span class="text-gray-800 font-medium student-name"><?php echo htmlspecialchars($student['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                 <div id="studentListEmpty" class="text-center text-gray-500 pt-10">
                    <p>Silakan pilih Guru, Hari, dan Waktu terlebih dahulu.</p>
                </div>
            </div>
            <p class="text-sm text-gray-500"><?php echo __('schedule_form_enrollment_note'); ?></p>
        </div>

        <div class="flex justify-end gap-4 pt-4">
            <a href="admin-dashboard.php?page=schedules" class="px-6 py-2 rounded-lg text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"><?php echo __('schedule_form_button_cancel'); ?></a>
            <button type="submit" id="submitButton" data-saving-text="<?php echo __('form_button_saving'); ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-8 py-3 rounded-lg flex items-center gap-2 transition-colors">
                <i data-lucide="save" class="w-5 h-5"></i>
                <span><?php echo $isEditing ? __('schedule_form_button_update') : __('schedule_form_button_create'); ?></span>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. Ambil elemen DOM ---
    const teacherDropdown = document.getElementById('teacher_id');
    const subjectDropdown = document.getElementById('subject_id');
    const dayDropdown = document.getElementById('day_of_week');
    const timeDropdown = document.getElementById('time_slot');
    const roomDropdown = document.getElementById('room');
    
    const studentSearch = document.getElementById('studentSearchInput');
    const studentContainer = document.getElementById('studentListContainer');
    const studentListEmpty = document.getElementById('studentListEmpty');
    const studentLabels = studentContainer.querySelectorAll('.student-label-item');

    // Ambil opsi untuk filtering
    const subjectOptions = subjectDropdown.querySelectorAll('option');
    const dayOptions = dayDropdown.querySelectorAll('option');
    const timeOptions = timeDropdown.querySelectorAll('option');

    // --- 2. Fungsi Reset ---
    function resetAndDisableFields() {
        [subjectDropdown, dayDropdown, timeDropdown, roomDropdown].forEach(dropdown => {
            dropdown.disabled = true;
            dropdown.value = '';
            Array.from(dropdown.options).forEach(opt => opt.style.display = '');
        });
        resetStudentList();
    }

    // --- 3. LOGIKA FILTER GURU (Full-time vs Part-time) ---
    function onTeacherChange(isInitialLoad = false) {
        const selectedTeacherValue = teacherDropdown.value;

        if (!selectedTeacherValue) {
            resetAndDisableFields();
            return;
        }

        const selectedOption = teacherDropdown.querySelector(`option[value="${selectedTeacherValue}"]`);
        const status = selectedOption.getAttribute('data-status') || 'part-time';
        const teacherSubjects = selectedOption.getAttribute('data-subjects') ? selectedOption.getAttribute('data-subjects').split(',').map(s => s.trim()) : [];
        const teacherDays = selectedOption.getAttribute('data-days') ? selectedOption.getAttribute('data-days').split(',').map(d => d.trim()) : [];
        const teacherTimes = selectedOption.getAttribute('data-times') ? selectedOption.getAttribute('data-times').split(',').map(t => t.trim()) : [];

        // A. Filter Mapel
        subjectOptions.forEach(opt => {
            if (opt.value === "") return;
            const subjectName = opt.getAttribute('data-subject-name');
            if (teacherSubjects.includes(subjectName)) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
        subjectDropdown.disabled = false;
        if (!isInitialLoad) subjectDropdown.value = ''; 

        // B. Filter Hari
        dayOptions.forEach(opt => {
            if (opt.value === "") return;
            if (status === 'full-time') {
                opt.style.display = ''; 
            } else {
                if (teacherDays.includes(opt.value)) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            }
        });
        dayDropdown.disabled = false;
        if (!isInitialLoad) dayDropdown.value = '';

        // C. Filter Waktu
        timeOptions.forEach(opt => {
            if (opt.value === "") return;
            if (status === 'full-time') {
                opt.style.display = '';
            } else {
                const timeLabel = opt.getAttribute('data-time-label');
                if (teacherTimes.includes(timeLabel)) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            }
        });
        timeDropdown.disabled = false;
        if (!isInitialLoad) timeDropdown.value = '';

        roomDropdown.disabled = false;
        resetStudentList(!isInitialLoad);
    }

    // --- 4. LOGIKA FILTER SISWA (Subject + Day + Time) ---
    function onFilterChange() {
        // Ambil nilai yang dipilih
        const selectedSubjectId = subjectDropdown.value;
        const selectedDay = dayDropdown.value;
        const selectedTimeValue = timeDropdown.value;
        
        // Ambil Nama Mapel & Label Waktu untuk pencocokan
        let selectedSubjectName = null;
        if (selectedSubjectId) {
            const subjectOpt = subjectDropdown.querySelector(`option[value="${selectedSubjectId}"]`);
            if (subjectOpt) selectedSubjectName = subjectOpt.getAttribute('data-subject-name');
        }

        let selectedTimeLabel = null;
        if (selectedTimeValue) {
            const timeOpt = timeDropdown.querySelector(`option[value="${selectedTimeValue}"]`);
            if (timeOpt) selectedTimeLabel = timeOpt.getAttribute('data-time-label');
        }

        // Jika salah satu belum dipilih, sembunyikan list siswa
        if (!selectedSubjectId || !selectedDay || !selectedTimeValue) {
            resetStudentList();
            return;
        }
        
        // Aktifkan list siswa
        studentSearch.disabled = false;
        studentContainer.classList.remove('opacity-50', 'bg-gray-50');
        studentListEmpty.style.display = 'none';

        let visibleCount = 0;
        studentLabels.forEach(label => {
            // Ambil data siswa dari atribut
            const sDays = label.getAttribute('data-days') ? label.getAttribute('data-days').split(',').map(s => s.trim()) : [];
            const sTimes = label.getAttribute('data-times') ? label.getAttribute('data-times').split(',').map(s => s.trim()) : [];
            const sSubjects = label.getAttribute('data-subjects') ? label.getAttribute('data-subjects').split(',').map(s => s.trim()) : [];
            
            // 1. Cek Mapel (Wajib punya)
            const subjectMatch = sSubjects.includes(selectedSubjectName);

            // 2. Cek Hari (Jika kosong dianggap bisa semua, jika ada isi harus cocok)
            const dayMatch = (sDays.length === 0 || sDays[0] === "") || sDays.includes(selectedDay);

            // 3. Cek Waktu (Jika kosong dianggap bisa semua, jika ada isi harus cocok)
            const timeMatch = (sTimes.length === 0 || sTimes[0] === "") || sTimes.includes(selectedTimeLabel);
            
            // Gabungkan semua kondisi
            const isAvailable = subjectMatch && dayMatch && timeMatch;
            
            label.style.display = isAvailable ? 'flex' : 'none';
            label.setAttribute('data-availability-visible', isAvailable ? 'true' : 'false');

            if (isAvailable) {
                visibleCount++;
            } else {
                // Uncheck jika siswa disembunyikan karena tidak cocok
                const checkbox = label.querySelector('input[type="checkbox"]');
                if(checkbox && !checkbox.disabled) checkbox.checked = false;
            }
        });

        if (visibleCount === 0) {
            studentListEmpty.style.display = 'block';
            studentListEmpty.querySelector('p').textContent = 'Tidak ada siswa yang mengambil mapel ini dan tersedia di waktu ini.';
        }
        
        // Jalankan pencarian nama (jika ada input search)
        filterStudentsBySearch();
    }
    
    function resetStudentList(clearSelections = true) {
        studentSearch.disabled = true;
        studentSearch.value = '';
        studentContainer.classList.add('opacity-50', 'bg-gray-50');
        studentListEmpty.style.display = 'block';
        studentListEmpty.querySelector('p').textContent = 'Silakan pilih Guru, Mapel, Hari, dan Waktu.';
        
        studentLabels.forEach(label => {
            label.style.display = 'none';
            label.setAttribute('data-availability-visible', 'false');
            if (clearSelections) {
                const checkbox = label.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = false;
            }
        });
    }

    function filterStudentsBySearch() {
        const filter = studentSearch.value.toLowerCase().trim();
        let visibleCount = 0;
        
        studentLabels.forEach(label => {
            const studentName = label.querySelector('.student-name').textContent.toLowerCase().trim();
            const isAvailable = label.getAttribute('data-availability-visible') === 'true';
            const matchesSearch = studentName.includes(filter);

            if (isAvailable && matchesSearch) {
                label.style.display = 'flex';
                visibleCount++;
            } else {
                label.style.display = 'none';
            }
        });
        
        if (visibleCount === 0 && !studentSearch.disabled) {
             studentListEmpty.style.display = 'block';
             // Pesan jika sudah filter lengkap tapi search tidak ketemu
             if (subjectDropdown.value && dayDropdown.value && timeDropdown.value) {
                 studentListEmpty.querySelector('p').textContent = 'Tidak ditemukan siswa yang cocok.';
             }
        } else if (visibleCount > 0) {
             studentListEmpty.style.display = 'none';
        }
    }

    // --- 5. Event Listeners ---
    teacherDropdown.addEventListener('change', () => onTeacherChange(false));
    
    // Listener untuk Mapel, Hari, dan Waktu memicu filter siswa
    // [BARU] Fungsi Filter Jam berdasarkan Hari
function filterTimeSlots() {
    const selectedDay = dayDropdown.value;
    const timeOptions = timeDropdown.querySelectorAll('option');

    timeOptions.forEach(opt => {
        if (opt.value === "") return; 
        const allowedDays = opt.getAttribute('data-days');

        // Tampilkan jika (Days kosong) ATAU (Days mengandung hari yang dipilih)
        if (!allowedDays || allowedDays === "" || allowedDays.includes(selectedDay)) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
            if (timeDropdown.value === opt.value) timeDropdown.value = ""; // Reset jika tersembunyi
        }
    });
}

// Listener
subjectDropdown.addEventListener('change', onFilterChange);

// [UBAH] Saat hari ganti, filter jam dulu, baru filter siswa
dayDropdown.addEventListener('change', () => {
    filterTimeSlots();
    onFilterChange();
});

timeDropdown.addEventListener('change', onFilterChange);
    
    studentSearch.addEventListener('keyup', filterStudentsBySearch);
    
    // --- 6. Inisialisasi (Jika Edit Mode) ---
    if (teacherDropdown.value) {
        onTeacherChange(true); 
        if (subjectDropdown.value && dayDropdown.value && timeDropdown.value) {
            onFilterChange();
        }
    } else {
        resetAndDisableFields();
    }

    // --- 7. Form Submit Handler ---
    const form = document.getElementById('scheduleForm');
    const submitButton = document.getElementById('submitButton');
    
    if (form && submitButton) {
        const savingText = submitButton.getAttribute('data-saving-text') || 'Saving...';
        form.addEventListener('submit', function(e) {
            e.preventDefault(); 
            if (submitButton.disabled) return;

            const checkedStudents = document.querySelectorAll('#studentListContainer input[name="students[]"]:checked');
            const checkedCount = checkedStudents.length;
            const studentLimit = 5;
            let proceed = true;

            if (checkedCount > studentLimit) {
                const msg = `Peringatan: Anda mendaftarkan ${checkedCount} siswa. Batas yang disarankan adalah 5. Lanjutkan?`;
                proceed = confirm(msg);
            }

            if (proceed) {
                submitButton.disabled = true;
                submitButton.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i><span>${savingText}</span>`;
                if (typeof lucide !== 'undefined') lucide.createIcons();
                form.submit();
            }
        });
    }
});
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>