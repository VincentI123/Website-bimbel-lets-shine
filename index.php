<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); 
// --- AKHIR BAGIAN BARU ---


// --- "OTAK" BAHASA (VERSI UNIVERSAL FINAL) ---

// 1. Selalu mulai session di paling atas
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Tentukan bahasa default
$defaultLang = 'id';

// 3. Cek jika pengguna MENGKLIK tombol ganti bahasa (VERSI FINAL)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {

    $_SESSION['lang'] = $_GET['lang'];

    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];

    $query_params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }

    unset($query_params['lang']);

    // --- Perbaikan Manual Query String ---
    $queryStringParts = array();
    foreach ($query_params as $key => $value) {
        $queryStringParts[] = urlencode($key) . '=' . urlencode($value);
    }
    $queryString = implode('&', $queryStringParts);
    // --- Akhir Perbaikan ---

    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');

    // --- DIUBAH: Hentikan buffer dan lakukan redirect ---
    ob_end_clean(); 
    header("Location: " . $redirectUrl);
    die('Redirecting...'); 
    // --- AKHIR PERUBAHAN ---
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
$langFile = __DIR__ . '/src/pages/lang/' . $currentLang . '.php'; 
if (file_exists($langFile) && is_readable($langFile)) {
    $lang = include $langFile;
} else {
    $defaultFile = __DIR__ . '/src/pages/lang/' . $defaultLang . '.php';
    $lang = (file_exists($defaultFile) && is_readable($defaultFile)) ? include $defaultFile : array();
}

// 7. Fungsi helper untuk terjemahan
function __($key) {
    global $lang;
    if (is_array($lang) && isset($lang[$key])) {
        return $lang[$key];
    } else {
        return $key;
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- LOGIKA OTENTIKASI (DIBIARKAN SAMA) ---
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/includes/functions.php';
require_once __DIR__ . '/src/includes/auth.php';

initAuth();

if (isLoggedIn()) {
    $user = getCurrentUser();
    // --- PERUBAHAN: Admin juga harus ke admin-dashboard ---
    if ($user['role'] === 'admin') {
        redirect('admin-dashboard.php');
    } elseif ($user['role'] === 'teacher') {
        redirect('teacher-dashboard.php');
    } else {
        redirect('student-dashboard.php');
    }
}

// --- [BARU] AMBIL DATA BANNER ---
// --- [NEW] FETCH LANDING PAGE DATA ---
try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Ambil Settings (Kontak)
    $stmt_settings = $db->query("SELECT * FROM site_settings");
    $site_settings = [];
    while($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
        $site_settings[$row['setting_key']] = $row['setting_value'];
    }
    // Fallback jika database kosong
    $phone_display = isset($site_settings['contact_phone']) ? $site_settings['contact_phone'] : '0877 2039 0206';
    $email_display = isset($site_settings['contact_email']) ? $site_settings['contact_email'] : 'bimbel.lets.shine@gmail.com';

    // 2. Ambil Subject Items
    $stmt_items = $db->query("SELECT * FROM landing_subject_items ORDER BY display_order ASC, id ASC");
    $landing_items = [];
    while($row = $stmt_items->fetch(PDO::FETCH_ASSOC)) {
        $landing_items[$row['level_category']][] = $row;
    }

    // --- [TAMBAHAN BARU] 3. Ambil Banner Aktif ---
    // Kita hanya mengambil banner yang is_active = 1
    $stmt_banners = $db->query("SELECT * FROM homepage_banners WHERE is_active = 1 ORDER BY display_order ASC, created_at DESC");
    $banners = $stmt_banners->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Fallback error handling
    $phone_display = '0877 2039 0206';
    $email_display = 'bimbel.lets.shine@gmail.com';
    $landing_items = [];
    $banners = []; // Set array kosong jika error agar tidak undefined
}

// Fungsi helper untuk mengambil teks item berdasarkan bahasa aktif
function get_item_text($item, $lang) {
    if ($lang == 'en') return $item['content_en'];
    if ($lang == 'cn') return $item['content_cn'];
    return $item['content_id'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bimbel Let's Shine - <?php echo __('landing_title_suffix'); ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <style>
        body { font-family: 'Poppins', sans-serif; }
        .gradient-text { background-image: linear-gradient(to right, #f97316, #facc15); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .btn-gradient { background-image: linear-gradient(to right, #f97316, #ea580c); transition: all 0.3s ease-in-out; }
        .btn-gradient:hover { box-shadow: 0 10px 20px -5px rgba(249, 115, 22, 0.4); transform: translateY(-2px); }
        .nav-link.active { color: #f97316; font-weight: 600; }
        /* ANIMASI */
        .fade-in { opacity: 0; transform: translateY(20px); transition: opacity 0.6s ease-out, transform 0.6s ease-out; }
        .fade-in.visible { opacity: 1; transform: translateY(0); }
        
        /* Style untuk Swiper */
        .swiper-button-next, .swiper-button-prev {
            color: #ffffff;
            background-color: rgba(0, 0, 0, 0.3);
            width: 44px;
            height: 44px;
            border-radius: 50%;
        }
        .swiper-button-next:after, .swiper-button-prev:after {
            font-size: 1.25rem;
            font-weight: 800;
        }
        .swiper-pagination-bullet-active {
            background-color: #f97316;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f97316', secondary: '#facc15', neutral: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a', }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white text-neutral-700 antialiased">

    <header id="header" class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-sm transition-all duration-300">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <a href="#home" class="flex items-center gap-2">
                    <img src="assets/img/logo.png" alt="Let's Shine Logo" class="w-10 h-10">
                    <span class="text-lg font-bold text-neutral-800">Let's Shine</span>
                </a>

                <nav class="hidden lg:flex items-center space-x-2">
                    <a href="#home" class="nav-link px-4 py-2 rounded-md hover:text-primary transition-colors duration-300"><?php echo __('landing_nav_home'); ?></a>
                    <a href="#matapelajaran" class="nav-link px-4 py-2 rounded-md hover:text-primary transition-colors duration-300"><?php echo __('landing_nav_subjects'); ?></a>
                    <a href="#keunggulan" class="nav-link px-4 py-2 rounded-md hover:text-primary transition-colors duration-300"><?php echo __('landing_nav_advantages'); ?></a>
                    <a href="#contact" class="nav-link px-4 py-2 rounded-md hover:text-primary transition-colors duration-300"><?php echo __('landing_nav_contact'); ?></a>
                </nav>

                <div class="flex items-center gap-4">
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
                    
                    <div class="hidden md:flex items-center gap-3">
                        <a href="<?php echo $url_id; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'id' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="Bahasa Indonesia">
                            <img src="https://flagcdn.com/w40/id.png" srcset="https://flagcdn.com/w80/id.png 2x" width="20" height="15" alt="Indonesia" class="rounded-sm shadow-sm">
                            <span class="font-semibold text-sm">ID</span>
                        </a>
                        
                        <div class="h-4 w-px bg-gray-300"></div>

                        <a href="<?php echo $url_en; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'en' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="English">
                            <img src="https://flagcdn.com/w40/gb.png" srcset="https://flagcdn.com/w80/gb.png 2x" width="20" height="15" alt="United Kingdom" class="rounded-sm shadow-sm">
                            <span class="font-semibold text-sm">EN</span>
                        </a>

                        <div class="h-4 w-px bg-gray-300"></div>

                        <a href="<?php echo $url_cn; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'cn' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="中文">
                            <img src="https://flagcdn.com/w40/cn.png" srcset="https://flagcdn.com/w80/cn.png 2x" width="20" height="15" alt="China" class="rounded-sm shadow-sm">
                            <span class="font-semibold text-sm">CN</span>
                        </a>
                    </div>

                    <div class="flex md:hidden items-center gap-2 mr-1">
                        <a href="<?php echo $url_id; ?>" class="<?php echo ($currentLang == 'id' ? 'opacity-100 scale-110' : 'opacity-50 hover:opacity-100'); ?> transition-all" title="ID">
                            <img src="https://flagcdn.com/w40/id.png" width="24" alt="ID" class="rounded-sm shadow-sm">
                        </a>
                        <a href="<?php echo $url_en; ?>" class="<?php echo ($currentLang == 'en' ? 'opacity-100 scale-110' : 'opacity-50 hover:opacity-100'); ?> transition-all" title="EN">
                            <img src="https://flagcdn.com/w40/gb.png" width="24" alt="EN" class="rounded-sm shadow-sm">
                        </a>
                        <a href="<?php echo $url_cn; ?>" class="<?php echo ($currentLang == 'cn' ? 'opacity-100 scale-110' : 'opacity-50 hover:opacity-100'); ?> transition-all" title="CN">
                            <img src="https://flagcdn.com/w40/cn.png" width="24" alt="CN" class="rounded-sm shadow-sm">
                        </a>
                    </div>
                    <a href="login.php" class="hidden lg:block btn-gradient text-white font-semibold px-6 py-2 rounded-full"><?php echo __('landing_nav_login'); ?></a>
                    <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-md hover:bg-neutral-100">
                        <i data-lucide="menu" class="h-6 w-6"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-neutral-200 shadow-lg">
            <nav class="flex flex-col items-center space-y-2 p-4">
                <a href="#home" class="nav-link w-full text-center py-2 rounded-md hover:bg-neutral-100"><?php echo __('landing_nav_home'); ?></a>
                <a href="#matapelajaran" class="nav-link w-full text-center py-2 rounded-md hover:bg-neutral-100"><?php echo __('landing_nav_subjects'); ?></a>
                <a href="#keunggulan" class="nav-link w-full text-center py-2 rounded-md hover:bg-neutral-100"><?php echo __('landing_nav_advantages'); ?></a>
                <a href="#contact" class="nav-link w-full text-center py-2 rounded-md hover:bg-neutral-100"><?php echo __('landing_nav_contact'); ?></a>
                
                <a href="login.php" class="w-full mt-2 btn-gradient text-white font-semibold py-2 rounded-full text-center"><?php echo __('landing_nav_login'); ?></a>
            </nav>
        </div>
    </header>

    <main>
        <section id="home" class="pt-28 pb-12 lg:pt-32 lg:pb-16 bg-neutral-50">
            <div class="container mx-auto px-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div class="text-center lg:text-left fade-in">
                        
                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-neutral-800 md:leading-tight lg:leading-tight">
                            <?php echo __('landing_hero_title_prefix'); ?><br> <span class="gradient-text">Let's Shine</span>
                        </h1>
                        
                        <p class="mt-6 text-lg text-neutral-600 max-w-xl mx-auto lg:mx-0">
                            <?php echo __('landing_hero_description'); ?>
                        </p>
                        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                             <a href="register.php?from=home" class="btn-gradient text-white font-semibold px-8 py-3 rounded-full text-lg">
                                <?php echo __('landing_hero_register'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="fade-in" style="transition-delay: 200ms;">
                        <div class="bg-white p-4 rounded-2xl shadow-2xl shadow-primary/10">
                            <div class="swiper main-banner w-full aspect-[16/9] rounded-xl overflow-hidden">
                                <div class="swiper-wrapper">
                                    <?php if (empty($banners)): ?>
                                        <div class="swiper-slide bg-neutral-100 flex items-center justify-center">
                                            <p class="text-neutral-500"><?php echo __('landing_banner_coming_soon'); ?></p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($banners as $banner): ?>
                                        <div class="swiper-slide w-full h-full">
                                            <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" alt="<?php echo htmlspecialchars($banner['alt_text']); ?>" class="w-full h-full object-cover">
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="swiper-pagination"></div>
                                <div class="swiper-button-prev"></div>
                                <div class="swiper-button-next"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="py-20 bg-white">
            <div class="container mx-auto px-6">
                <div class="text-center mb-12 fade-in">
                    <h2 class="text-3xl md:text-4xl font-bold text-neutral-800"><?php echo __('landing_nav_about'); ?> <span class="text-primary">Let's Shine</span></h2>
                    <p class="mt-4 text-lg text-neutral-600 max-w-3xl mx-auto"><?php echo __('landing_about_description'); ?></p>
                </div>
            </div>
        </section>

        <section id="matapelajaran" class="py-20 bg-neutral-50">
            <div class="container mx-auto px-6">
                <div class="text-center mb-12 fade-in">
                    <h2 class="text-3xl md:text-4xl font-bold text-neutral-800"><?php echo __('landing_subjects_title'); ?></h2>
                    <p class="mt-4 text-lg text-neutral-600 max-w-3xl mx-auto"><?php echo __('landing_subjects_desc'); ?></p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div class="group bg-white p-8 rounded-2xl shadow-lg shadow-primary/5 hover:shadow-xl hover:shadow-primary/10 transition-all duration-300 hover:-translate-y-2 fade-in">
                        <div class="w-16 h-16 bg-green-100 flex items-center justify-center rounded-xl mb-6">
                            <i data-lucide="baby" class="w-8 h-8 text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-4 text-neutral-800"><?php echo __('landing_subject_level_prenursery'); ?></h3>
                        <ul class="list-disc list-inside text-neutral-600 mb-4 space-y-1">
                            <?php if (isset($landing_items['prenursery'])): ?>
                                <?php foreach ($landing_items['prenursery'] as $item): ?>
                                    <li><?php echo htmlspecialchars(get_item_text($item, $currentLang)); ?></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="italic text-gray-400"><?php echo __('landing_no_data'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="group bg-white p-8 rounded-2xl shadow-lg shadow-primary/5 hover:shadow-xl hover:shadow-primary/10 transition-all duration-300 hover:-translate-y-2 fade-in" style="transition-delay: 200ms;">
                        <div class="w-16 h-16 bg-primary/10 flex items-center justify-center rounded-xl mb-6">
                            <i data-lucide="book" class="w-8 h-8 text-primary"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-4 text-neutral-800"><?php echo __('landing_subject_level_sd'); ?></h3>
                        <ul class="list-disc list-inside text-neutral-600 mb-4 space-y-1">
                            <?php if (isset($landing_items['sd'])): ?>
                                <?php foreach ($landing_items['sd'] as $item): ?>
                                    <li><?php echo htmlspecialchars(get_item_text($item, $currentLang)); ?></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="italic text-gray-400"><?php echo __('landing_no_data'); ?></li>
                            <?php endif; ?>
                        </ul>
                                            </div>

                    <div class="group bg-white p-8 rounded-2xl shadow-lg shadow-primary/5 hover:shadow-xl hover:shadow-primary/10 transition-all duration-300 hover:-translate-y-2 fade-in" style="transition-delay: 400ms;">
                        <div class="w-16 h-16 bg-secondary/10 flex items-center justify-center rounded-xl mb-6">
                            <i data-lucide="calculator" class="w-8 h-8 text-secondary"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-4 text-neutral-800"><?php echo __('landing_subject_level_smp'); ?></h3>
                         <ul class="list-disc list-inside text-neutral-600 mb-4 space-y-1">
                            <?php if (isset($landing_items['smp'])): ?>
                                <?php foreach ($landing_items['smp'] as $item): ?>
                                    <li><?php echo htmlspecialchars(get_item_text($item, $currentLang)); ?></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="italic text-gray-400"><?php echo __('landing_no_data'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="group bg-white p-8 rounded-2xl shadow-lg shadow-primary/5 hover:shadow-xl hover:shadow-primary/10 transition-all duration-300 hover:-translate-y-2 fade-in" style="transition-delay: 600ms;">
                        <div class="w-16 h-16 bg-blue-100 flex items-center justify-center rounded-xl mb-6">
                            <i data-lucide="atom" class="w-8 h-8 text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-4 text-neutral-800"><?php echo __('landing_subject_level_sma'); ?></h3>
                        <ul class="list-disc list-inside text-neutral-600 mb-4 space-y-1">
                            <?php if (isset($landing_items['sma'])): ?>
                                <?php foreach ($landing_items['sma'] as $item): ?>
                                    <li><?php echo htmlspecialchars(get_item_text($item, $currentLang)); ?></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="italic text-gray-400"><?php echo __('landing_no_data'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section id="keunggulan" class="py-20 bg-white">
            <div class="container mx-auto px-6">
                <div class="text-center mb-12 fade-in">
                    <h2 class="text-3xl md:text-4xl font-bold text-neutral-800"><?php echo __('landing_advantages_title'); ?></h2>
                    <p class="mt-4 text-lg text-neutral-600 max-w-3xl mx-auto"><?php echo __('landing_advantages_desc'); ?></p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="group bg-neutral-50 p-6 rounded-xl hover:bg-primary/5 transition-all duration-300 hover:-translate-y-2 hover:shadow-lg fade-in">
                        <div class="flex items-center gap-4">
                            <i data-lucide="air-vent" class="w-6 h-6 text-primary"></i>
                            <div>
                                <h3 class="font-bold text-neutral-800"><?php echo __('landing_adv_ac'); ?></h3>
                                <p class="text-sm"><?php echo __('landing_adv_ac_desc'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="group bg-neutral-50 p-6 rounded-xl hover:bg-primary/5 transition-all duration-300 hover:-translate-y-2 hover:shadow-lg fade-in" style="transition-delay: 100ms;">
                        <div class="flex items-center gap-4">
                            <i data-lucide="users" class="w-6 h-6 text-primary"></i>
                            <div>
                                <h3 class="font-bold text-neutral-800"><?php echo __('landing_adv_small'); ?></h3>
                                <p class="text-sm"><?php echo __('landing_adv_small_desc'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="group bg-neutral-50 p-6 rounded-xl hover:bg-primary/5 transition-all duration-300 hover:-translate-y-2 hover:shadow-lg fade-in" style="transition-delay: 200ms;">
                        <div class="flex items-center gap-4">
                            <i data-lucide="target" class="w-6 h-6 text-primary"></i>
                            <div>
                                <h3 class="font-bold text-neutral-800"><?php echo __('landing_adv_focus'); ?></h3>
                                <p class="text-sm"><?php echo __('landing_adv_focus_desc'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="group bg-neutral-50 p-6 rounded-xl hover:bg-primary/5 transition-all duration-300 hover:-translate-y-2 hover:shadow-lg fade-in" style="transition-delay: 300ms;">
                        <div class="flex items-center gap-4">
                            <i data-lucide="brain" class="w-6 h-6 text-primary"></i>
                            <div>
                                <h3 class="font-bold text-neutral-800"><?php echo __('landing_adv_psych'); ?></h3>
                                <p class="text-sm"><?php echo __('landing_adv_psych_desc'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <section id="contact" class="py-20 bg-neutral-50">
            <div class="container mx-auto px-6">
                <div class="text-center mb-12 fade-in">
                    <h2 class="text-3xl md:text-4xl font-bold text-neutral-800"><?php echo __('landing_contact_title'); ?></h2>
                    <p class="mt-4 text-lg text-neutral-600 max-w-3xl mx-auto"><?php echo __('landing_contact_subtitle'); ?></p>
                </div>
                
                <div class="bg-white p-8 rounded-2xl shadow-2xl shadow-primary/10">
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 lg:gap-12 items-start lg:items-center">
                        
                        <div class="lg:col-span-2 space-y-6 fade-in"> <div class="flex items-start gap-3"> <div class="p-2 bg-primary/10 rounded-lg flex-shrink-0">
                            <i data-lucide="map-pin" class="w-5 h-5 text-primary"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-base text-neutral-800 mb-1"><?php echo __('landing_contact_label_address'); ?></h3>
                            <p class="text-sm text-neutral-600 leading-relaxed">Ruko Pasar Mitra Raya Blok G No. 12, Batam Centre</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg flex-shrink-0">
                            <i data-lucide="phone" class="w-5 h-5 text-primary"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-base text-neutral-800 mb-1"><?php echo __('landing_contact_label_phone'); ?></h3>
                            <p class="text-sm text-neutral-600"><?php echo htmlspecialchars($phone_display); ?></p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg flex-shrink-0">
                            <i data-lucide="mail" class="w-5 h-5 text-primary"></i>
                        </div>
                        <div class="min-w-0 flex-1"> <h3 class="font-bold text-base text-neutral-800 mb-1"><?php echo __('landing_contact_label_email'); ?></h3>
                            <p class="text-sm text-neutral-600 break-all"><?php echo htmlspecialchars($email_display); ?></p>
                        </div>
                    </div>

                </div>
                        
                        <div class="lg:col-span-3 fade-in" style="transition-delay: 200ms;">
                            <div class="w-full h-64 md:h-80 lg:h-[350px] rounded-xl overflow-hidden shadow-lg">
                                <iframe
                                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.109405231267!2d104.0436094759608!3d1.120574600000003!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d9898754a5c61f%3A0x3ca6dcfa9ca52578!2sBimbel%20Let's%20Shine!5e0!3m2!1sid!2sid!4v1695632975932!5m2!1sid!2sid"
                                    width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade">
                                </iframe>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </section>
    </main>

    <footer class="bg-neutral-800 text-neutral-400">
        <div class="container mx-auto px-6 py-12">
            <div class="grid md:grid-cols-4 gap-8">
                <div class="md:col-span-2">
                    <a href="#home" class="flex items-center gap-2 mb-4">
                        <img src="assets/img/logo.png" alt="Let's Shine Logo" class="w-10 h-10">
                        <span class="text-lg font-bold text-white">Let's Shine</span>
                    </a>
                    <p class="max-w-md mb-6"><?php echo __('landing_footer_mission'); ?></p>
                </div>
                <div>
                    <h4 class="font-semibold text-white mb-4"><?php echo __('landing_footer_links_title'); ?></h4>
                    <ul class="space-y-2">
                        <li><a href="#home" class="hover:text-primary transition"><?php echo __('landing_nav_home'); ?></a></li>
                        <li><a href="#matapelajaran" class="hover:text-primary transition"><?php echo __('landing_nav_subjects'); ?></a></li>
                        <li><a href="#keunggulan" class="hover:text-primary transition"><?php echo __('landing_nav_advantages'); ?></a></li>
                        <li><a href="#contact" class="hover:text-primary transition"><?php echo __('landing_nav_contact'); ?></a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-white mb-4"><?php echo __('landing_footer_follow_title'); ?></h4>
                    <div class="flex space-x-4">
                        <a href="https://www.instagram.com/bimbel.lets.shine/" target="_blank" rel="noopener noreferrer" class="p-2 bg-neutral-700 rounded-full hover:bg-primary transition"><i data-lucide="instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="mt-12 border-t border-neutral-700 pt-6 text-center text-sm">
                <p>© <span id="current-year"></span> Bimbel Let's Shine. <?php echo __('landing_footer_copyright'); ?></p>
            </div>
        </div>
    </footer>

    <button id="scroll-to-top" class="fixed bottom-6 right-6 z-50 p-3 rounded-full bg-primary text-white shadow-lg opacity-0 translate-y-4 transition-all duration-300">
    <i data-lucide="arrow-up"></i>
    </button>
    
    <div id="notification" class="fixed bottom-5 right-5 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg opacity-0 transition-opacity duration-500">
        <?php echo __('landing_notification_success'); ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        document.addEventListener('DOMContentLoaded', () => {

            // ===== [BARU] Inisialisasi Swiper =====
            const swiper = new Swiper('.main-banner', {
                loop: true,
                autoplay: {
                    delay: 4000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
            });

            // ===== HEADER SHADOW ON SCROLL & MOBILE MENU TOGGLE =====
            const header = document.getElementById('header');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    header.classList.add('shadow-md');
                } else {
                    header.classList.remove('shadow-md');
                }
            });

            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
            document.querySelectorAll('#mobile-menu a').forEach(link => {
                link.addEventListener('click', () => mobileMenu.classList.add('hidden'));
            });

            // ===== NAV LINK ACTIVE STATE ON SCROLL =====
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');
            const observerOptions = { root: null, rootMargin: '0px', threshold: 0.4 };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        navLinks.forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href') === `#${id}`) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }, observerOptions);
            sections.forEach(section => observer.observe(section));

            // ===== FADE-IN ANIMATION ON SCROLL =====
            const fadeElements = document.querySelectorAll('.fade-in');
            const fadeObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        fadeObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            fadeElements.forEach(el => fadeObserver.observe(el));
            
            // ===== SCROLL-TO-TOP BUTTON & FOOTER YEAR =====
            const scrollToTopBtn = document.getElementById('scroll-to-top');
            window.addEventListener('scroll', () => {
                if(window.scrollY > 300) {
                    scrollToTopBtn.classList.remove('opacity-0', 'translate-y-4');
                } else {
                    scrollToTopBtn.classList.add('opacity-0', 'translate-y-4');
                }
            });
            scrollToTopBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            document.getElementById('current-year').textContent = new Date().getFullYear();
        });
    </script>

</body>
</html>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>