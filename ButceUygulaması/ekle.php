<?php
// GÜVENLİK KONTROLÜ VE OTURUM BAŞLATMA (KODUNUZ KORUNDU)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: giris.php");
    exit;
}
$kullanici_id = $_SESSION['KullaniciID'];
$ad_soyad = $_SESSION['AdSoyad'] ?? 'Misafir';

// VERİTABANI BAĞLANTISI VE İŞLEMLER
$sunucu_adi = "localhost"; $kullanici_adi = "root"; $sifre_db = ""; $veritabani_adi = "butce_yonetim_db";
$hataMesaji = ''; $basariMesaji = ''; $categories = []; $incomeCategories = []; $expenseCategories = [];

try {
    $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sqlCategories = "SELECT KategoriID, KategoriAdi, Tip FROM Kategoriler WHERE KullaniciID = ? OR KullaniciID IS NULL ORDER BY Tip, KategoriAdi";
    $stmtCategories = $pdo->prepare($sqlCategories);
    $stmtCategories->execute([$kullanici_id]);
    $categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

    $incomeCategories = array_values(array_filter($categories, fn($cat) => $cat['Tip'] == 'Gelir'));
    $expenseCategories = array_values(array_filter($categories, fn($cat) => $cat['Tip'] == 'Gider'));

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $miktar = filter_input(INPUT_POST, 'miktar', FILTER_VALIDATE_FLOAT);
        $tip = filter_input(INPUT_POST, 'tip', FILTER_SANITIZE_STRING);
        $kategoriID = filter_input(INPUT_POST, 'kategori', FILTER_VALIDATE_INT);
        $aciklama = trim(filter_input(INPUT_POST, 'aciklama', FILTER_SANITIZE_STRING));
        $tarih = filter_input(INPUT_POST, 'tarih', FILTER_SANITIZE_STRING);

        if ($miktar && $tip && $kategoriID && $tarih) {
            $sqlInsert = "INSERT INTO Islemler (KullaniciID, KategoriID, Tip, Miktar, Tarih, Aciklama) VALUES (?, ?, ?, ?, ?, ?)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            if ($stmtInsert->execute([$kullanici_id, $kategoriID, $tip, $miktar, $tarih, $aciklama])) {
                $basariMesaji = "İşlem başarıyla kaydedildi.";
            } else {
                $hataMesaji = "Kayıt sırasında bir hata oluştu.";
            }
        } else {
             $hataMesaji = "Lütfen tüm zorunlu alanları doğru doldurunuz.";
        }
    }
} catch (PDOException $e) { $hataMesaji = "Veritabanı hatası: " . $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İşlem Ekle | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script> tailwind.config = { darkMode: 'class' }; </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        :root { font-family: 'Inter', sans-serif; }

        .blob { position: absolute; filter: blur(50px); z-index: -1; opacity: 0.4; animation: float 8s ease-in-out infinite; }
        @keyframes float { 0% { transform: translate(0,0) scale(1); } 33% { transform: translate(30px, -50px) scale(1.1); } 66% { transform: translate(-20px, 20px) scale(0.9); } 100% { transform: translate(0,0) scale(1); } }
        
        .glass-sidebar { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); }
        .dark .glass-sidebar { background: rgba(31, 41, 55, 0.9); }
        
        .fade-in { animation: fadeIn 0.6s ease-out forwards; opacity: 0; transform: translateY(10px); }
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
    <div class="blob bg-green-200 w-64 h-64 md:w-96 md:h-96 rounded-full bottom-0 right-0 translate-x-1/2 translate-y-1/2"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 glass-sidebar z-50 flex flex-col justify-between h-full shadow-lg border-r border-gray-200/50 dark:border-gray-700 md:relative md:translate-x-0 hidden-mobile">
        <div class="h-20 flex items-center justify-between px-6 border-b border-gray-200/50 dark:border-gray-700">
            <a href="dashboard.php" class="flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 font-extrabold text-xl">
                <i data-lucide="graduation-cap"></i><span>CEP BÜYÜSÜ</span>
            </a>
            <button class="md:hidden text-gray-500" onclick="toggleSidebar()"><i data-lucide="x"></i></button>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="layout-grid" class="w-5 h-5 mr-3"></i> Ana Panel</a>
            <a href="ekle.php" class="flex items-center px-4 py-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-xl font-bold shadow-sm transition-all"><i data-lucide="plus-circle" class="w-5 h-5 mr-3"></i> Hızlı Ekle</a>
            <a href="islemler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="list" class="w-5 h-5 mr-3"></i> İşlemler</a>
            <a href="raporlar.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="pie-chart" class="w-5 h-5 mr-3"></i> Raporlar</a>
            <a href="butceler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="shield-check" class="w-5 h-5 mr-3"></i> Bütçe Limitleri</a>
            <a href="hedefler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="target" class="w-5 h-5 mr-3"></i> Tasarruf Hedefleri</a>
            <a href="ai_asistan.php" class="flex items-center px-4 py-3 mt-4 text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg transform hover:scale-[1.02] transition-all"><i data-lucide="bot" class="w-5 h-5 mr-3"></i> AI Asistan</a>
            <a href="ayarlar.php" class="flex items-center px-4 py-3 mt-2 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="settings" class="w-5 h-5 mr-3"></i> Ayarlar</a>
            <div class="pt-4 mt-2 border-t border-gray-200/50 dark:border-gray-700">
                <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Fırsatlar</p>
                <a href="https://www.kariyer.net/is-ilanlari/part+time-2?tpst=4&cp=2" target="_blank" class="flex items-center px-4 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-xl transition-all text-sm font-semibold"><i data-lucide="briefcase" class="w-4 h-4 mr-3"></i> Part Time İş</a>
                <a href="https://www.linkedin.com/company/staj-ve-burs-ilanlari/posts/?feedView=all" target="_blank" class="flex items-center px-4 py-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition-all text-sm font-semibold"><i data-lucide="graduation-cap" class="w-4 h-4 mr-3"></i> Staj & Burs</a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200/50 dark:border-gray-700">
            <a href="cikis.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors font-bold text-xs"><i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Çıkış Yap</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="h-20 bg-white/60 dark:bg-gray-800/60 backdrop-blur-md border-b border-gray-200/50 dark:border-gray-700 flex justify-between items-center px-4 md:px-8 z-10">
            <div class="flex items-center">
                <button class="md:hidden p-2 mr-3 bg-white dark:bg-gray-700 rounded-lg shadow-sm" onclick="toggleSidebar()"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <h2 class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white truncate max-w-[150px] sm:max-w-none">Yeni İşlem</h2>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300"><i id="theme-icon" data-lucide="moon" class="w-5 h-5"></i></button>
                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm flex-shrink-0"><?php echo strtoupper(substr($ad_soyad, 0, 1)); ?></div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 relative">
            <div class="max-w-2xl mx-auto fade-in">
                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm p-6 md:p-8 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 transition-all">
                    <?php if (!empty($basariMesaji)): ?>
                        <div class="flex items-center p-4 mb-6 text-sm text-green-800 bg-green-50 rounded-xl border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800">
                            <i data-lucide="check-circle" class="w-5 h-5 mr-3"></i><span><?php echo htmlspecialchars($basariMesaji); ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="ekle.php" method="POST" id="transactionForm" class="space-y-6">
                        <div class="flex p-1 bg-gray-100 dark:bg-gray-700 rounded-xl">
                            <button type="button" id="gider-tab-button" class="flex-1 py-2 px-4 rounded-lg text-sm font-bold transition-all duration-200 flex justify-center items-center">
                                <i data-lucide="trending-down" class="w-4 h-4 mr-2"></i> Gider
                            </button>
                            <button type="button" id="gelir-tab-button" class="flex-1 py-2 px-4 rounded-lg text-sm font-bold transition-all duration-200 flex justify-center items-center">
                                <i data-lucide="trending-up" class="w-4 h-4 mr-2"></i> Gelir
                            </button>
                            <input type="hidden" name="tip" id="transactionType" value="Gider">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Miktar</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                                    <span class="text-lg font-bold">₺</span>
                                </div>
                                <input type="number" id="miktar" name="miktar" step="0.01" class="block w-full pl-10 pr-4 py-3 text-xl font-bold text-gray-900 dark:text-white bg-gray-50/50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Kategori</label>
                                <select id="kategori" name="kategori" required class="block w-full py-3 px-4 bg-gray-50/50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white focus:ring-4 focus:ring-indigo-500/10 outline-none appearance-none"></select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Tarih</label>
                                <input type="date" id="tarih" name="tarih" value="<?php echo date('Y-m-d'); ?>" required class="block w-full py-3 px-4 bg-gray-50/50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white focus:ring-4 focus:ring-indigo-500/10 outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Açıklama</label>
                            <textarea id="aciklama" name="aciklama" rows="3" class="block w-full py-3 px-4 bg-gray-50/50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none resize-none" placeholder="Market, kira, burs..."></textarea>
                        </div>

                        <button type="submit" class="w-full flex justify-center items-center py-4 px-6 rounded-xl shadow-lg shadow-indigo-100 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] transition-all">
                            <i data-lucide="save" class="w-5 h-5 mr-2"></i>İşlemi Kaydet
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden-mobile');
            sidebar.classList.toggle('show-mobile');
        }

        const incomeCategories = <?php echo json_encode($incomeCategories); ?>;
        const expenseCategories = <?php echo json_encode($expenseCategories); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            const transactionTypeInput = document.getElementById('transactionType');
            const kategoriSelect = document.getElementById('kategori');
            const giderButton = document.getElementById('gider-tab-button');
            const gelirButton = document.getElementById('gelir-tab-button');

            // ARKA PLAN VE TEMA KONTROLÜ
            function applySavedSettings() {
                const theme = localStorage.getItem('color-theme');
                if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }

                const currentBg = localStorage.getItem('app-background');
                const classesToRemove = ['bg-gradient-ocean', 'bg-gradient-sunset', 'bg-gradient-forest', 'bg-gradient-lavender'];
                document.body.classList.remove(...classesToRemove);
                if(currentBg && currentBg !== 'default') {
                    document.body.classList.add(currentBg);
                }
            }
            applySavedSettings();

            function selectType(type) {
                transactionTypeInput.value = type;
                const baseClass = "flex-1 py-2 px-4 rounded-lg text-sm font-bold transition-all duration-200 flex justify-center items-center";
                giderButton.className = baseClass;
                gelirButton.className = baseClass;

                if (type === 'Gider') {
                    giderButton.classList.add('bg-red-500', 'text-white', 'shadow-sm');
                    gelirButton.classList.add('text-gray-500', 'dark:text-gray-300', 'hover:text-gray-700');
                    updateCategoryOptions(expenseCategories);
                } else {
                    gelirButton.classList.add('bg-green-500', 'text-white', 'shadow-sm');
                    giderButton.classList.add('text-gray-500', 'dark:text-gray-300', 'hover:text-gray-700');
                    updateCategoryOptions(incomeCategories);
                }
            }

            function updateCategoryOptions(cats) {
                kategoriSelect.innerHTML = '<option value="" disabled selected>Seçiniz...</option>';
                cats.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.KategoriID;
                    o.textContent = c.KategoriAdi;
                    kategoriSelect.appendChild(o);
                });
            }

            giderButton.addEventListener('click', () => selectType('Gider'));
            gelirButton.addEventListener('click', () => selectType('Gelir'));
            selectType('Gider'); 

            const themeBtn = document.getElementById('theme-toggle');
            themeBtn.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('color-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                const isDark = document.documentElement.classList.contains('dark');
                document.getElementById('theme-icon').setAttribute('data-lucide', isDark ? 'sun' : 'moon');
                lucide.createIcons();
            });
        });
    </script>
</body>
</html>