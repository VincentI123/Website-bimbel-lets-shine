<?php
// src/pages/admin-landing-settings.php

// --- 1. SETUP BAHASA ---
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$defaultLang = 'id';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];
    $query_params = [];
    if (isset($url_parts['query'])) { parse_str($url_parts['query'], $query_params); }
    unset($query_params['lang']);
    $queryString = http_build_query($query_params);
    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');
    ob_end_clean(); header("Location: " . $redirectUrl); exit;
}
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;
$langFile = __DIR__ . '/lang/' . $currentLang . '.php';
if (file_exists($langFile)) { $lang = include $langFile; } else { $defaultFile = __DIR__ . '/lang/' . $defaultLang . '.php'; $lang = (file_exists($defaultFile)) ? include $defaultFile : array(); }
if (!function_exists('__')) { function __($key) { global $lang; return isset($lang[$key]) ? $lang[$key] : $key; } }
// --- AKHIR SETUP BAHASA ---

$db = Database::getInstance()->getConnection();

// --- HANDLE POST REQUESTS (SAVE CONTACT ONLY) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_contact') {
    $phone = $_POST['contact_phone'];
    $email = $_POST['contact_email'];
    
    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('contact_phone', ?), ('contact_email', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$phone, $email]); 
    
    $_SESSION['flash_message'] = __('landing_settings_flash_contact');
    header("Location: admin-dashboard.php?page=landing-settings");
    exit;
}

// --- AMBIL DATA SETTINGS ---
$settings = [];
$stmt = $db->query("SELECT * FROM site_settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// --- [PENTING] AMBIL DATA ITEM UNTUK PREVIEW ---
$items = [];
$stmt = $db->query("SELECT * FROM landing_subject_items ORDER BY level_category, display_order ASC, id ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $items[$row['level_category']][] = $row;
}

$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']);
?>

<?php
// Link Bahasa
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = [];
if (isset($url_parts['query'])) { parse_str($url_parts['query'], $query_params); }
unset($query_params['lang']);

$queryParams_id = array_merge($query_params, ['lang' => 'id']);
$url_id = $path . '?' . http_build_query($queryParams_id);

$queryParams_en = array_merge($query_params, ['lang' => 'en']);
$url_en = $path . '?' . http_build_query($queryParams_en);

$queryParams_cn = array_merge($query_params, ['lang' => 'cn']);
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
    <a href="<?php echo $url_cn; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'cn' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="Chinese">
        <img src="https://flagcdn.com/w40/cn.png" srcset="https://flagcdn.com/w80/cn.png 2x" width="20" height="15" alt="China" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">CN</span>
    </a>
</div>

<h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo __('landing_settings_title'); ?></h1>
<?php if ($flash_message): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow flex items-center gap-2">
    <i data-lucide="check-circle" class="w-5 h-5"></i>
    <span><?php echo $flash_message; ?></span>
</div>
<?php endif; ?>

<div class="space-y-8">

    <div class="bg-white p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <h2 class="text-xl font-bold text-gray-800 mb-6 border-b border-gray-100 pb-4 flex items-center gap-2">
            <i data-lucide="contact" class="w-5 h-5 text-orange-500"></i>
            <?php echo __('landing_settings_contact_title'); ?>
        </h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <input type="hidden" name="action" value="save_contact">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('landing_settings_phone'); ?></label>
                <input type="text" name="contact_phone" value="<?php echo htmlspecialchars(isset($settings['contact_phone']) ? $settings['contact_phone'] : ''); ?>" class="w-full p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-shadow">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('landing_settings_email'); ?></label>
                <input type="email" name="contact_email" value="<?php echo htmlspecialchars(isset($settings['contact_email']) ? $settings['contact_email'] : ''); ?>" class="w-full p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-shadow">
            </div>
            <div class="md:col-span-2 text-right">
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-semibold shadow-md shadow-orange-500/20 transition-all flex items-center gap-2 ml-auto">
                    <i data-lucide="save" class="w-4 h-4"></i> <?php echo __('landing_settings_save_contact'); ?>
                </button>
            </div>
        </form>
    </div>

    <div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i data-lucide="layers" class="w-6 h-6 text-orange-500"></i> <?php echo __('landing_settings_manage_subjects'); ?>
        </h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php 
            $levels = [
                'prenursery' => ['label' => __('landing_subject_level_prenursery'), 'icon' => 'baby', 'color' => 'bg-green-100 text-green-600'],
                'sd' => ['label' => __('landing_subject_level_sd'), 'icon' => 'book', 'color' => 'bg-blue-100 text-blue-600'],
                'smp' => ['label' => __('landing_subject_level_smp'), 'icon' => 'calculator', 'color' => 'bg-yellow-100 text-yellow-600'],
                'sma' => ['label' => __('landing_subject_level_sma'), 'icon' => 'atom', 'color' => 'bg-red-100 text-red-600'],
            ];
            
            foreach ($levels as $key => $data): 
                $levelItems = isset($items[$key]) ? $items[$key] : [];
            ?>
            <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden flex flex-col h-full">
                <div class="p-5 flex items-center justify-between border-b border-gray-100 bg-gray-50/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 <?php echo $data['color']; ?> rounded-lg flex items-center justify-center">
                            <i data-lucide="<?php echo $data['icon']; ?>" class="w-5 h-5"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-800"><?php echo $data['label']; ?></h3>
                    </div>
                    <a href="admin-dashboard.php?page=landing-level&level=<?php echo $key; ?>" 
                    class="flex items-center gap-2 bg-white border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm font-semibold hover:bg-orange-50 hover:text-orange-600 hover:border-orange-200 transition-all shadow-sm">
                        <i data-lucide="edit-3" class="w-4 h-4"></i> <?php echo __('landing_settings_btn_edit'); ?>
                    </a>
                </div>

                <div class="p-5 flex-1 bg-white">
                    <?php if (empty($levelItems)): ?>
                        <div class="flex flex-col items-center justify-center h-full py-6 text-gray-400">
                            <i data-lucide="list-x" class="w-8 h-8 mb-2 opacity-50"></i>
                            <span class="text-sm italic"><?php echo __('landing_settings_empty'); ?></span>
                        </div>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php 
                            // Tampilkan maksimal 5 item agar tidak kepanjangan
                            $count = 0;
                            foreach ($levelItems as $item): 
                                if ($count >= 5) break; 
                            ?>
                            <li class="flex items-start gap-2 text-gray-600 text-sm">
                                <i data-lucide="check-circle" class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0"></i>
                                <span>
                                    <?php echo htmlspecialchars($item['content_id']); ?>
                                    <span class="text-gray-400 text-xs ml-1">(<?php echo htmlspecialchars($item['content_en']); ?>)</span>
                                </span>
                            </li>
                            <?php $count++; endforeach; ?>
                            
                            <?php if (count($levelItems) > 5): ?>
                                <li class="text-xs text-center text-gray-400 pt-2 border-t border-gray-50 mt-2">
                                    + <?php echo count($levelItems) - 5; ?> item lainnya...
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>