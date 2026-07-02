<?php
// GÜVENLİK VE VERİTABANI BAĞLANTISI (KODUNUZ KORUNDU)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: giris.php");
    exit;
}
$kullanici_id = $_SESSION['KullaniciID'];
$ad_soyad = htmlspecialchars($_SESSION['AdSoyad'] ?? 'Misafir');

$sunucu_adi = "localhost"; $kullanici_adi = "root"; $sifre_db = ""; $veritabani_adi = "butce_yonetim_db";
$net_bakiye = 0.00;

try {
    $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt_gelir = $pdo->prepare("SELECT SUM(Miktar) FROM Islemler WHERE KullaniciID = :id AND Tip = 'Gelir'");
    $stmt_gelir->execute(['id' => $kullanici_id]);
    $gelir = $stmt_gelir->fetchColumn() ?? 0;

    $stmt_gider = $pdo->prepare("SELECT SUM(Miktar) FROM Islemler WHERE KullaniciID = :id AND Tip = 'Gider'");
    $stmt_gider->execute(['id' => $kullanici_id]);
    $gider = $stmt_gider->fetchColumn() ?? 0;

    $net_bakiye = $gelir - $gider;
} catch (PDOException $e) { }
?>

<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Sayfa | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script> tailwind.config = { darkMode: 'class' } </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        :root { font-family: 'Inter', sans-serif; }

        .blob { position: absolute; filter: blur(50px); z-index: -1; opacity: 0.4; animation: float 8s ease-in-out infinite; }
        @keyframes float {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        
        .glass-sidebar { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); }
        .dark .glass-sidebar { background: rgba(31, 41, 55, 0.9); }
        
        .fade-in { animation: fadeIn 0.8s ease-out forwards; opacity: 0; transform: translateY(10px); }
        @keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }

        /* --- ARKA PLAN RENK TANIMLARI (DİĞER SAYFALARDA DA OLMALI) --- */
        body.bg-gradient-ocean { background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%) !important; }
        body.bg-gradient-sunset { background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%) !important; }
        body.bg-gradient-forest { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important; }
        body.bg-gradient-lavender { background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%) !important; }

        html.dark body.bg-gradient-ocean { background: linear-gradient(135deg, #0c4a6e 0%, #075985 100%) !important; }
        html.dark body.bg-gradient-sunset { background: linear-gradient(135deg, #7c2d12 0%, #9a3412 100%) !important; }
        html.dark body.bg-gradient-forest { background: linear-gradient(135deg, #14532d 0%, #166534 100%) !important; }
        html.dark body.bg-gradient-lavender { background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%) !important; }

        #sidebar { transition: transform 0.3s ease-in-out; }
        @media (max-width: 768px) {
            #sidebar.hidden-mobile { transform: translateX(-100%); }
            #sidebar.show-mobile { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 flex h-screen overflow-hidden relative transition-colors duration-300">

    <div class="blob bg-indigo-200 w-64 h-64 md:w-96 md:h-96 rounded-full top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
    <div class="blob bg-purple-200 w-64 h-64 md:w-96 md:h-96 rounded-full bottom-0 right-0 translate-x-1/2 translate-y-1/2"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 glass-sidebar z-50 flex flex-col justify-between h-full shadow-lg border-r border-gray-200/50 dark:border-gray-700 md:relative md:translate-x-0 hidden-mobile">
        <div class="h-20 flex items-center justify-between px-6 border-b border-gray-200/50 dark:border-gray-700">
            <a href="dashboard.php" class="flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 font-extrabold text-xl">
                <i data-lucide="graduation-cap"></i>
                <span>CEP BÜYÜSÜ</span>
            </a>
            <button class="md:hidden text-gray-500" onclick="toggleSidebar()">
                <i data-lucide="x"></i>
            </button>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto font-medium">
            <a href="dashboard.php" class="flex items-center px-4 py-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-xl font-bold shadow-sm">
                <i data-lucide="layout-grid" class="w-5 h-5 mr-3"></i> Ana Panel
            </a>
            <a href="ekle.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all">
                <i data-lucide="plus-circle" class="w-5 h-5 mr-3"></i> Hızlı Ekle
            </a>
            <a href="islemler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all">
                <i data-lucide="list" class="w-5 h-5 mr-3"></i> İşlemler
            </a>
            <a href="raporlar.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all">
                <i data-lucide="pie-chart" class="w-5 h-5 mr-3"></i> Raporlar
            </a>
            <a href="butceler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all">
                <i data-lucide="shield-check" class="w-5 h-5 mr-3"></i> Bütçe Limitleri
            </a>
            <a href="hedefler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all">
                <i data-lucide="target" class="w-5 h-5 mr-3"></i> Tasarruf Hedefleri
            </a>
            <a href="ai_asistan.php" class="flex items-center px-4 py-3 mt-4 text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg hover:shadow-indigo-500/30 transform hover:scale-[1.02] transition-all">
                <i data-lucide="bot" class="w-5 h-5 mr-3"></i> AI Asistan
            </a>
            <a href="ayarlar.php" class="flex items-center px-4 py-3 mt-2 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all">
                <i data-lucide="settings" class="w-5 h-5 mr-3"></i> Ayarlar
            </a>

            <div class="pt-4 mt-2 border-t border-gray-200/50 dark:border-gray-700">
                <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Fırsatlar</p>
                <a href="https://www.linkedin.com/company/staj-ve-burs-ilanlari/posts/?feedView=all" target="_blank" class="flex items-center px-4 py-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition-all group">
                    <i data-lucide="graduation-cap" class="w-5 h-5 mr-3 text-blue-500"></i>
                    <span class="text-sm font-semibold">Staj & Burs</span>
                </a>
                <a href="https://www.kariyer.net/is-ilanlari/part+time" target="_blank" class="flex items-center px-4 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-xl transition-all group">
                    <i data-lucide="briefcase" class="w-5 h-5 mr-3 text-purple-500"></i>
                    <span class="text-sm font-semibold">Part Time İş</span>
                </a>
            </div>
        </nav>

        <div class="p-4 border-t border-gray-200/50 dark:border-gray-700">
            <a href="cikis.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors text-sm font-bold">
                <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Çıkış Yap
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="h-20 bg-white/60 dark:bg-gray-800/60 backdrop-blur-md border-b border-gray-200/50 dark:border-gray-700 flex justify-between items-center px-4 md:px-8 z-10 transition-colors duration-300">
            <div class="flex items-center">
                <button class="md:hidden p-2 mr-3 bg-white dark:bg-gray-700 rounded-lg shadow-sm" onclick="toggleSidebar()">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <h2 class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white truncate max-w-[150px] sm:max-w-none">Hoş Geldin, <?php echo $ad_soyad; ?>!</h2>
            </div>
            
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300">
                    <i id="theme-icon" data-lucide="moon" class="w-5 h-5"></i>
                </button>
                
                <div class="text-right hidden sm:block">
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-bold">Bakiye</p>
                    <p class="text-base md:text-xl font-extrabold <?php echo $net_bakiye >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-red-600 dark:text-red-400'; ?>">
                        <?php echo number_format($net_bakiye, 2, ',', '.'); ?> ₺
                    </p>
                </div>
                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm flex-shrink-0">
                    <?php echo strtoupper(substr($ad_soyad, 0, 1)); ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 relative">
            <div class="sm:hidden bg-white dark:bg-gray-800 p-4 rounded-2xl mb-6 shadow-sm border border-gray-100 dark:border-gray-700">
                 <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Mevcut Bakiye</p>
                 <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1"><?php echo number_format($net_bakiye, 2, ',', '.'); ?> ₺</p>
            </div>

            <div class="max-w-5xl mx-auto h-full flex flex-col justify-center items-center fade-in">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-full shadow-xl shadow-indigo-100 dark:shadow-none mb-6 animate-bounce transition-colors duration-300" style="animation-duration: 3s;">
                    <i data-lucide="sparkles" class="w-10 h-10 md:w-12 md:h-12 text-indigo-500"></i>
                </div>

                <h1 class="text-2xl md:text-5xl font-extrabold text-gray-900 dark:text-white text-center mb-4">
                    Bugün ne yapmak istersin?
                </h1>
                <p class="text-sm md:text-lg text-gray-500 dark:text-gray-400 text-center mb-8 md:mb-12 max-w-2xl">
                    Senin için her şeyi hazırladık. İster harcama ekle, ister analiz et.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 w-full">
                    <a href="ekle.php" class="group bg-white dark:bg-gray-800 p-6 md:p-8 rounded-2xl shadow-sm hover:shadow-2xl border border-gray-100 dark:border-gray-700 transition-all duration-300 transform hover:-translate-y-1 text-center">
                        <div class="w-12 h-12 md:w-16 md:h-16 bg-green-50 dark:bg-green-900/20 rounded-2xl flex items-center justify-center mx-auto mb-4 md:mb-6 group-hover:bg-green-500 transition-colors">
                            <i data-lucide="plus" class="w-6 h-6 md:w-8 md:h-8 text-green-600 dark:text-green-400 group-hover:text-white transition-colors"></i>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold dark:text-white mb-2">Gelir/Gider Ekle</h3>
                        <p class="text-gray-500 dark:text-gray-400 text-xs md:text-sm">Kahve mi aldın? Hemen kaydet.</p>
                    </a>

                    <a href="ai_asistan.php" class="group bg-gradient-to-br from-indigo-500 to-purple-600 p-6 md:p-8 rounded-2xl shadow-lg hover:shadow-indigo-500/40 transition-all duration-300 transform hover:-translate-y-1 text-center relative overflow-hidden">
                        <div class="w-12 h-12 md:w-16 md:h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mx-auto mb-4 md:mb-6">
                            <i data-lucide="bot" class="w-6 h-6 md:w-8 md:h-8 text-white"></i>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold text-white mb-2">AI Asistan</h3>
                        <p class="text-indigo-100 text-xs md:text-sm">Bütçeni analiz etsin, tavsiye versin.</p>
                    </a>

                    <a href="raporlar.php" class="group bg-white dark:bg-gray-800 p-6 md:p-8 rounded-2xl shadow-sm hover:shadow-2xl border border-gray-100 dark:border-gray-700 transition-all duration-300 transform hover:-translate-y-1 text-center">
                        <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-50 dark:bg-blue-900/20 rounded-2xl flex items-center justify-center mx-auto mb-4 md:mb-6 group-hover:bg-blue-500 transition-colors">
                            <i data-lucide="bar-chart-2" class="w-6 h-6 md:w-8 md:h-8 text-blue-600 dark:text-blue-400 group-hover:text-white transition-colors"></i>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold dark:text-white mb-2">Durumunu Gör</h3>
                        <p class="text-gray-500 dark:text-gray-400 text-xs md:text-sm">Grafikleri ve harcamaları incele.</p>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <a href="ai_asistan.php" class="fixed bottom-6 right-6 z-40 flex items-center justify-center">
        <span class="absolute h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
        <div class="relative h-12 w-12 md:h-14 md:w-14 flex items-center justify-center rounded-full bg-indigo-600 shadow-xl">
            <i data-lucide="bot" class="h-6 w-6 text-white"></i>
        </div>
    </a>

    <script>
        lucide.createIcons();

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden-mobile');
            sidebar.classList.toggle('show-mobile');
        }

        const themeBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        
        function updateThemeIcon() {
            const isDark = document.documentElement.classList.contains('dark');
            themeIcon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
            lucide.createIcons();
        }
        updateThemeIcon();

        themeBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('color-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            updateThemeIcon();
        });

        // ARKA PLAN YÜKLEYİCİ (Sorunu çözen kısım)
        const currentBg = localStorage.getItem('app-background');
        if(currentBg && currentBg !== 'default') {
            document.body.classList.remove('bg-gradient-ocean', 'bg-gradient-sunset', 'bg-gradient-forest', 'bg-gradient-lavender');
            document.body.classList.add(currentBg);
        }
    </script>
</body>
</html>