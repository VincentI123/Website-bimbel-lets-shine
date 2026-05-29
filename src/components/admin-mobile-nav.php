<?php
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// --- PERBAIKAN: Gunakan fungsi __() agar bisa ganti bahasa ---
// Kita gunakan kunci bahasa yang sama persis dengan Sidebar
$navItems = [
    // Grup 1: Halaman Utama
    'dashboard' => ['icon' => 'layout-dashboard', 'label' => __('sidebar_menu_dashboard')],
    
    // Grup 2: Manajemen Pengguna
    'approvals' => ['icon' => 'user-check', 'label' => __('sidebar_menu_approvals')],
    'enrollments' => ['icon' => 'user-plus', 'label' => __('sidebar_menu_enrollments')],
    'teachers' => ['icon' => 'award', 'label' => __('sidebar_menu_teachers')],
    'users' => ['icon' => 'users', 'label' => __('sidebar_menu_users')],
    
    // Grup 4: Manajemen Kelas
    'schedules' => ['icon' => 'calendar-plus', 'label' => __('sidebar_menu_schedules')],
    'exams' => ['icon' => 'file-text', 'label' => __('sidebar_menu_exams')],
    
    // Grup 3: Data Master
    'subjects' => ['icon' => 'book-text', 'label' => __('sidebar_menu_subjects')],
    'times' => ['icon' => 'clock', 'label' => __('sidebar_menu_times')],
    
    // Grup 5: Pengaturan Situs
    'banners' => ['icon' => 'image', 'label' => __('sidebar_menu_banners')],
    'landing-settings' => ['icon' => 'layout-template', 'label' => __('sidebar_menu_landing')]
];
// --- AKHIR PERBAIKAN ---
?>

<div class="lg:hidden flex justify-between items-center p-4 bg-white shadow-md sticky top-0 z-30">
    <a href="admin-dashboard.php" class="flex items-center gap-3">
        <img src="assets/img/logo.png" alt="Logo" class="h-8 w-8">
        <div>
            <h1 class="text-sm font-bold text-gray-800">Bimbel Let's Shine</h1>
        </div>
    </a>
    <button id="open-menu" class="text-gray-600 hover:text-orange-500 transition-colors">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>
</div>

<div id="mobile-menu" class="fixed inset-y-0 right-0 z-50 w-72 bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out lg:hidden flex flex-col border-l border-gray-200">
    
    <div class="flex items-center justify-between p-4 border-b border-gray-100 bg-orange-50">
        <h2 class="text-lg font-bold text-orange-600"><?php echo __('menu_page_title'); ?></h2>
        <button id="close-menu" class="text-gray-500 hover:text-red-500 transition-colors">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
    </div>

    <nav class="flex-1 flex flex-col p-4 space-y-2 overflow-y-auto">
        <?php foreach ($navItems as $pageId => $item): ?>
            <a href="admin-dashboard.php?page=<?php echo $pageId; ?>"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-300 font-medium
                      <?php
                        if ($currentPage === $pageId) {
                            echo 'bg-orange-500 text-white shadow-md hover:bg-orange-600';
                        } else {
                            echo 'text-gray-600 hover:bg-orange-50 hover:text-orange-600';
                        }
                      ?>">
                <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i>
                <span><?php echo $item['label']; ?></span>

                <?php if ($pageId === 'approvals' && isset($pending_count) && $pending_count > 0): ?>
                    <span class="ml-auto flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-sm">
                        <?php echo $pending_count; ?>
                    </span>
                <?php endif; ?>
                </a>
        <?php endforeach; ?>
        
        <hr class="border-gray-100 my-2 mt-auto">

        <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-red-50 hover:text-red-600 transition-all duration-300 font-medium">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span><?php echo __('sidebar_menu_logout'); ?></span>
        </a>
    </nav>
</div>

<div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden backdrop-blur-sm"></div>

<script>
    if (typeof mobileMenuHandler === 'undefined') {
        const mobileMenuHandler = true; 
        
        const mobileMenu = document.getElementById('mobile-menu');
        const openMenuButton = document.getElementById('open-menu');
        const closeMenuButton = document.getElementById('close-menu');
        const overlay = document.getElementById('mobile-menu-overlay');

        // Function to open the menu (UBAH LOGIKA JS JUGA)
        const openMenu = () => {
            if (mobileMenu) mobileMenu.classList.remove('translate-x-full'); // Hapus translate positif agar muncul
            if (overlay) overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; 
        };

        // Function to close the menu (UBAH LOGIKA JS JUGA)
        const closeMenu = () => {
            if (mobileMenu) mobileMenu.classList.add('translate-x-full'); // Tambah translate positif agar geser ke kanan
            if (overlay) overlay.classList.add('hidden');
            document.body.style.overflow = ''; 
        };

        // Event Listeners
        if (openMenuButton) openMenuButton.addEventListener('click', openMenu);
        if (closeMenuButton) closeMenuButton.addEventListener('click', closeMenu);
        if (overlay) overlay.addEventListener('click', closeMenu);
    }
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>