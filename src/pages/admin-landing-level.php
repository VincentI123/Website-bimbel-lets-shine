<?php
// src/pages/admin-landing-level.php

// --- SETUP BAHASA ---
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$defaultLang = 'id';
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;
$langFile = __DIR__ . '/lang/' . $currentLang . '.php';
if (file_exists($langFile)) { $lang = include $langFile; } else { $defaultFile = __DIR__ . '/lang/' . $defaultLang . '.php'; $lang = (file_exists($defaultFile)) ? include $defaultFile : array(); }
if (!function_exists('__')) { function __($key) { global $lang; return isset($lang[$key]) ? $lang[$key] : $key; } }

$db = Database::getInstance()->getConnection();

// 1. Validasi Parameter Level
$level = isset($_GET['level']) ? $_GET['level'] : '';
$validLevels = ['prenursery', 'sd', 'smp', 'sma'];
if (!in_array($level, $validLevels)) {
    header("Location: admin-dashboard.php?page=landing-settings");
    exit;
}

// Translate Judul Level
$levelLabels = [
    'prenursery' => __('landing_subject_level_prenursery'),
    'sd' => __('landing_subject_level_sd'),
    'smp' => __('landing_subject_level_smp'),
    'sma' => __('landing_subject_level_sma')
];
$currentLabel = $levelLabels[$level];

// --- HANDLE POST REQUESTS ---

// A. TAMBAH ITEM BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $id = $_POST['new_id'];
    $en = $_POST['new_en'];
    $cn = $_POST['new_cn'];
    
    // Cari urutan terakhir
    $stmt = $db->prepare("SELECT MAX(display_order) as max_order FROM landing_subject_items WHERE level_category = ?");
    $stmt->execute([$level]);
    $row = $stmt->fetch();
    
    // [PERBAIKAN ERROR DI SINI] Mengganti ?? dengan isset
    $currentMax = isset($row['max_order']) ? $row['max_order'] : 0;
    $nextOrder = $currentMax + 1;

    $stmt = $db->prepare("INSERT INTO landing_subject_items (level_category, content_id, content_en, content_cn, display_order) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$level, $id, $en, $cn, $nextOrder]);
    
    $_SESSION['flash_message'] = __('landing_settings_flash_add');
    header("Location: admin-dashboard.php?page=landing-level&level=" . $level);
    exit;
}

// B. SIMPAN SEMUA PERUBAHAN (BULK UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        $sql = "UPDATE landing_subject_items SET content_id = ?, content_en = ?, content_cn = ?, display_order = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        
        foreach ($_POST['items'] as $id => $data) {
            $stmt->execute([
                $data['id'],
                $data['en'],
                $data['cn'],
                $data['order'],
                $id
            ]);
        }
    }
    $_SESSION['flash_message'] = "Semua perubahan berhasil disimpan!";
    header("Location: admin-dashboard.php?page=landing-level&level=" . $level);
    exit;
}

// C. HAPUS ITEM
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $db->prepare("DELETE FROM landing_subject_items WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['flash_message'] = __('landing_settings_flash_delete');
    header("Location: admin-dashboard.php?page=landing-level&level=" . $level);
    exit;
}

// --- AMBIL DATA ---
$items = [];
$stmt = $db->prepare("SELECT * FROM landing_subject_items WHERE level_category = ? ORDER BY display_order ASC, id ASC");
$stmt->execute([$level]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']);

// --- [BARU] LOGIKA LINK BAHASA (Agar tidak hilang saat ganti bahasa) ---
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
// --- [AKHIR LOGIKA] ---
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

<div class="flex justify-between items-center mb-6">
    <div class="flex items-center gap-4">
        <a href="admin-dashboard.php?page=landing-settings" class="bg-white border border-gray-300 text-gray-600 p-2.5 rounded-xl hover:bg-gray-100 transition-colors shadow-sm">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <?php echo __('landing_level_edit_prefix'); ?> <span class="text-orange-500 bg-orange-50 px-2 py-0.5 rounded-lg border border-orange-100"><?php echo $currentLabel; ?></span>
            </h1>
            <p class="text-sm text-gray-500 mt-1"><?php echo __('landing_level_subtitle'); ?></p>
        </div>
    </div>
</div>

<?php if ($flash_message): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded flex items-center gap-2 shadow-sm">
    <i data-lucide="check-circle" class="w-5 h-5"></i><span><?php echo $flash_message; ?></span>
</div>
<?php endif; ?>

<div class="bg-white p-6 rounded-2xl shadow-md shadow-orange-900/5 mb-8 border border-orange-100">
    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2 border-b border-gray-100 pb-3">
        <div class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center"><i data-lucide="plus" class="w-5 h-5"></i></div>
        <?php echo __('landing_settings_add_item'); ?>
    </h3>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="action" value="add_item">
        <div>
            <label class="text-xs font-bold text-gray-500 uppercase mb-1 block"><?php echo __('landing_settings_th_indonesia'); ?></label>
            <input type="text" name="new_id" required class="w-full p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-shadow" placeholder="<?php echo __('placeholder_text_id'); ?>">
        </div>
        <div>
            <label class="text-xs font-bold text-gray-500 uppercase mb-1 block"><?php echo __('landing_settings_th_english'); ?></label>
            <input type="text" name="new_en" required class="w-full p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-shadow" placeholder="<?php echo __('placeholder_text_en'); ?>">
        </div>
        <div>
            <label class="text-xs font-bold text-gray-500 uppercase mb-1 block"><?php echo __('landing_settings_th_mandarin'); ?></label>
            <input type="text" name="new_cn" required class="w-full p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-shadow" placeholder="<?php echo __('placeholder_text_cn'); ?>">
        </div>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-4 rounded-lg flex items-center justify-center gap-2 shadow-md shadow-green-500/20 transition-all">
            <i data-lucide="plus-circle" class="w-5 h-5"></i> <?php echo __('landing_settings_btn_add'); ?>
        </button>
    </form>
</div>

<form method="POST" class="space-y-6 pb-24"> <input type="hidden" name="action" value="bulk_update">
    
    <div class="bg-white p-4 md:p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <h3 class="font-bold text-gray-800 flex items-center gap-2 text-lg">
                <i data-lucide="list" class="w-5 h-5 text-gray-400"></i> 
                <?php echo __('landing_settings_list_title'); ?> <?php echo $currentLabel; ?>
            </h3>
            
            <div class="text-xs text-orange-700 bg-orange-50 px-3 py-2 rounded-lg border border-orange-100 flex items-start gap-2 w-full md:w-auto">
                <i data-lucide="info" class="w-4 h-4 flex-shrink-0 mt-0.5"></i> 
                <span><?php echo __('landing_level_help_msg'); ?></span>
            </div>
        </div>

        <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-sm uppercase">
                        <th class="p-4 w-24 text-center font-bold border-r border-gray-200"><?php echo __('landing_settings_th_order'); ?></th>
                        <th class="p-4 w-1/4 font-bold"><?php echo __('landing_settings_th_indonesia'); ?></th>
                        <th class="p-4 w-1/4 font-bold"><?php echo __('landing_settings_th_english'); ?></th>
                        <th class="p-4 w-1/4 font-bold"><?php echo __('landing_settings_th_mandarin'); ?></th>
                        <th class="p-4 w-20 text-center font-bold border-l border-gray-200"><?php echo __('landing_settings_th_action'); ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(empty($items)): ?>
                        <tr><td colspan="5" class="p-12 text-center text-gray-400 italic bg-gray-50"><?php echo __('landing_settings_empty'); ?></td></tr>
                    <?php else: foreach ($items as $item): ?>
                    <tr class="hover:bg-orange-50/30 transition-colors group">
                        <td class="p-3 text-center border-r border-gray-100 bg-gray-50/50">
                            <input type="number" name="items[<?php echo $item['id']; ?>][order]" value="<?php echo $item['display_order']; ?>" 
                                   class="w-16 text-center p-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-500 font-bold text-gray-700">
                        </td>
                        <td class="p-3">
                            <input type="text" name="items[<?php echo $item['id']; ?>][id]" value="<?php echo htmlspecialchars($item['content_id']); ?>" 
                                   class="w-full p-2 border border-transparent hover:border-gray-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500 rounded-lg bg-transparent hover:bg-white transition-all">
                        </td>
                        <td class="p-3">
                            <input type="text" name="items[<?php echo $item['id']; ?>][en]" value="<?php echo htmlspecialchars($item['content_en']); ?>" 
                                   class="w-full p-2 border border-transparent hover:border-gray-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500 rounded-lg bg-transparent hover:bg-white transition-all">
                        </td>
                        <td class="p-3">
                            <input type="text" name="items[<?php echo $item['id']; ?>][cn]" value="<?php echo htmlspecialchars($item['content_cn']); ?>" 
                                   class="w-full p-2 border border-transparent hover:border-gray-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500 rounded-lg bg-transparent hover:bg-white transition-all">
                        </td>
                        <td class="p-3 text-center border-l border-gray-100">
                            <a href="admin-dashboard.php?page=landing-level&level=<?php echo $level; ?>&action=delete&id=<?php echo $item['id']; ?>" 
                               onclick="return confirm('Hapus?')" 
                               class="inline-flex items-center justify-center p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-4">
            <?php if(empty($items)): ?>
                <div class="text-center text-gray-400 p-8 border border-dashed rounded-xl bg-gray-50"><?php echo __('landing_settings_empty'); ?></div>
            <?php else: foreach ($items as $item): ?>
            
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm relative transition-shadow focus-within:ring-2 focus-within:ring-orange-200">
                
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">#<?php echo __('landing_settings_th_order'); ?></span>
                        <input type="number" name="items[<?php echo $item['id']; ?>][order]" value="<?php echo $item['display_order']; ?>" 
                               class="w-16 text-center border border-gray-300 rounded-md py-1 px-2 font-bold text-gray-700 bg-gray-50 focus:bg-white focus:ring-1 focus:ring-orange-500">
                    </div>
                    
                    <a href="admin-dashboard.php?page=landing-level&level=<?php echo $level; ?>&action=delete&id=<?php echo $item['id']; ?>" 
                        onclick="return confirm('Hapus?')"
                        class="text-red-500 bg-red-50 p-2 rounded-lg hover:bg-red-100 active:scale-95 transition-transform">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </a>
                </div>
                
                <div class="space-y-3">
                    <div>
                        <label class="flex items-center gap-2 text-xs font-bold text-gray-500 mb-1">
                            <img src="https://flagcdn.com/w20/id.png" width="16" class="rounded-sm shadow-sm"> INDONESIA
                        </label>
                        <input type="text" name="items[<?php echo $item['id']; ?>][id]" value="<?php echo htmlspecialchars($item['content_id']); ?>" 
                               class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-shadow">
                    </div>
                    
                    <div>
                        <label class="flex items-center gap-2 text-xs font-bold text-gray-500 mb-1">
                            <img src="https://flagcdn.com/w20/gb.png" width="16" class="rounded-sm shadow-sm"> ENGLISH
                        </label>
                        <input type="text" name="items[<?php echo $item['id']; ?>][en]" value="<?php echo htmlspecialchars($item['content_en']); ?>" 
                               class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-shadow">
                    </div>
                    
                    <div>
                        <label class="flex items-center gap-2 text-xs font-bold text-gray-500 mb-1">
                            <img src="https://flagcdn.com/w20/cn.png" width="16" class="rounded-sm shadow-sm"> MANDARIN
                        </label>
                        <input type="text" name="items[<?php echo $item['id']; ?>][cn]" value="<?php echo htmlspecialchars($item['content_cn']); ?>" 
                               class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-shadow">
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="fixed bottom-0 left-0 right-0 p-4 bg-white/90 backdrop-blur-md border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] z-40 flex items-center justify-between gap-3 md:static md:bg-transparent md:border-0 md:shadow-none md:p-0 md:justify-end">
        <a href="admin-dashboard.php?page=landing-settings" class="flex-1 md:flex-none py-3 px-6 rounded-lg border border-gray-300 text-gray-600 font-semibold hover:bg-gray-50 text-center bg-white transition-colors">
            <?php echo __('btn_cancel'); ?>
        </a>
        <button type="submit" class="flex-1 md:flex-none bg-orange-500 hover:bg-orange-600 text-white px-8 py-3 rounded-lg font-bold shadow-lg shadow-orange-500/30 flex items-center justify-center gap-2 transition-all transform active:scale-95">
            <i data-lucide="save" class="w-5 h-5"></i> 
            <?php echo __('landing_settings_btn_save'); ?>
        </button>
    </div>
</form>

<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>