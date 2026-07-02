<?php
// GÜVENLİK VE OTURUM (KODUNUZ KORUNDU)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: giris.php");
    exit;
}
$kullanici_id = $_SESSION['KullaniciID'];
$ad_soyad = htmlspecialchars($_SESSION['AdSoyad'] ?? 'Misafir');

// VERİTABANI BAĞLANTISI
$sunucu_adi = "localhost"; $kullanici_adi = "root"; $sifre_db = ""; $veritabani_adi = "butce_yonetim_db";
$mesaj = ""; $mesaj_turu = "";

try {
    $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // TABLO YOKSA OLUŞTUR 
    $pdo->exec("CREATE TABLE IF NOT EXISTS Hedefler (
        HedefID INT AUTO_INCREMENT PRIMARY KEY,
        KullaniciID INT NOT NULL,
        HedefAdi VARCHAR(100) NOT NULL,
        HedeflenenMiktar DECIMAL(10, 2) NOT NULL,
        BirikenMiktar DECIMAL(10, 2) DEFAULT 0.00,
        HedefTarih DATE DEFAULT CURRENT_DATE,
        FOREIGN KEY (KullaniciID) REFERENCES Kullanicilar(KullaniciID)
    )");

    // YENİ HEDEF EKLEME
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hedef_ekle'])) {
        $baslik = $_POST['baslik'];
        $tutar = (float)$_POST['tutar'];
        $hedef_tarih = !empty($_POST['hedef_tarih']) ? $_POST['hedef_tarih'] : NULL;
        $sql = "INSERT INTO Hedefler (KullaniciID, HedefAdi, HedeflenenMiktar, HedefTarih) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$kullanici_id, $baslik, $tutar, $hedef_tarih]);
        $mesaj = "Yeni hedef oluşturuldu! 🎯";
        $mesaj_turu = "basari";
    }

    // PARA EKLEME / ÇIKARMA 
    if (isset($_POST['hedef_islem_ekle']) || isset($_POST['hedef_islem_cikar'])) {
        $hedef_id = (int)$_POST['modal_hedef_id'];
        $miktar = (float)$_POST['modal_miktar'];
        if ($miktar > 0) {
            if (isset($_POST['hedef_islem_cikar'])) {
                $sql = "UPDATE Hedefler SET BirikenMiktar = BirikenMiktar - ? WHERE HedefID = ? AND KullaniciID = ?";
                $pdo->prepare($sql)->execute([$miktar, $hedef_id, $kullanici_id]);
                $mesaj = "Para başarıyla çekildi.";
            } else {
                $sql = "UPDATE Hedefler SET BirikenMiktar = BirikenMiktar + ? WHERE HedefID = ? AND KullaniciID = ?";
                $pdo->prepare($sql)->execute([$miktar, $hedef_id, $kullanici_id]);
                $mesaj = "Harika! Birikim eklendi. 💸";
            }
            $mesaj_turu = "basari";
        }
    }

    // SİLME
    if (isset($_GET['sil'])) {
        $sil_id = (int)$_GET['sil'];
        $pdo->prepare("DELETE FROM Hedefler WHERE HedefID = ? AND KullaniciID = ?")->execute([$sil_id, $kullanici_id]);
        header("Location: hedefler.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM Hedefler WHERE KullaniciID = ? ORDER BY HedefID DESC");
    $stmt->execute([$kullanici_id]);
    $hedefler = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $mesaj = "Hata: " . $e->getMessage(); $mesaj_turu = "hata"; }
?>

<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hedefler | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script> tailwind.config = { darkMode: 'class' } </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        .modal-hidden { display: none !important; }
        .progress-bar { transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
        .blob { position: absolute; filter: blur(50px); z-index: -1; opacity: 0.4; animation: float 8s ease-in-out infinite; }
        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, -20px) scale(1.1); } 100% { transform: translate(0, 0) scale(1); } }
        .glass-sidebar { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.5); }
        .dark .glass-sidebar { background: rgba(31, 41, 55, 0.9); }
        .fade-in { animation: fadeIn 0.6s ease-out forwards; opacity: 0; transform: translateY(10px); }
        @keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }

        /* --- ARKA PLAN RENK TANIMLARI (TEMA SORUNUNU ÇÖZEN KISIM) --- */
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

    <div class="blob bg-green-100 w-96 h-96 rounded-full top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
    <div class="blob bg-blue-100 w-96 h-96 rounded-full bottom-0 right-0 translate-x-1/2 translate-y-1/2"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 glass-sidebar z-50 flex flex-col justify-between h-full shadow-lg border-r border-gray-200/50 dark:border-gray-700 md:relative md:translate-x-0 hidden-mobile">
        <div class="h-20 flex items-center justify-between px-6 border-b border-gray-200/50 dark:border-gray-700">
            <a href="dashboard.php" class="flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 font-extrabold text-xl">
                <i data-lucide="graduation-cap"></i><span>CEP BÜYÜSÜ</span>
            </a>
            <button class="md:hidden text-gray-500" onclick="toggleSidebar()"><i data-lucide="x"></i></button>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="layout-grid" class="w-5 h-5 mr-3"></i> Ana Panel</a>
            <a href="ekle.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="plus-circle" class="w-5 h-5 mr-3"></i> Hızlı Ekle</a>
            <a href="islemler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="list" class="w-5 h-5 mr-3"></i> İşlemler</a>
            <a href="raporlar.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="pie-chart" class="w-5 h-5 mr-3"></i> Raporlar</a>
            <a href="butceler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="shield-check" class="w-5 h-5 mr-3"></i> Bütçe Limitleri</a>
            <a href="hedefler.php" class="flex items-center px-4 py-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-xl font-semibold shadow-sm transition-all"><i data-lucide="target" class="w-5 h-5 mr-3"></i> Tasarruf Hedefleri</a>
            <a href="ai_asistan.php" class="flex items-center px-4 py-3 mt-4 text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg transform hover:scale-[1.02] transition-all"><i data-lucide="bot" class="w-5 h-5 mr-3"></i> AI Asistan</a>
            <a href="ayarlar.php" class="flex items-center px-4 py-3 mt-2 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 rounded-xl transition-all"><i data-lucide="settings" class="w-5 h-5 mr-3"></i> Ayarlar</a>

            <div class="pt-4 mt-2 border-t border-gray-200/50 dark:border-gray-700">
                <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Fırsatlar</p>
                <a href="https://www.kariyer.net/is-ilanlari/part+time-2" target="_blank" class="flex items-center px-4 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-xl transition-all group">
                    <i data-lucide="briefcase" class="w-4 h-4 mr-3 text-purple-500 group-hover:scale-110 transition-transform"></i>
                    <span class="text-sm font-semibold">Part Time İş</span>
                </a>
                <a href="https://www.linkedin.com/company/staj-ve-burs-ilanlari" target="_blank" class="flex items-center px-4 py-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition-all group">
                    <i data-lucide="graduation-cap" class="w-4 h-4 mr-3 text-blue-500 group-hover:scale-110 transition-transform"></i>
                    <span class="text-sm font-semibold">Staj & Burs</span>
                </a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200/50 dark:border-gray-700">
            <a href="cikis.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors font-bold text-xs"><i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Çıkış Yap</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="h-20 bg-white/60 dark:bg-gray-800/60 backdrop-blur-md border-b border-gray-200/50 dark:border-gray-700 flex justify-between items-center px-4 md:px-8 z-10 transition-colors">
            <div class="flex items-center">
                <button class="md:hidden p-2 mr-3 bg-white dark:bg-gray-700 rounded-lg shadow-sm" onclick="toggleSidebar()"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <h2 class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white truncate max-w-[180px] sm:max-w-none">Hayallerin & Hedeflerin 🎯</h2>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300"><i id="theme-icon" data-lucide="moon" class="w-5 h-5"></i></button>
                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm flex-shrink-0"><?php echo strtoupper(substr($ad_soyad, 0, 1)); ?></div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 relative">
            <div class="max-w-6xl mx-auto fade-in pb-20">
                
                <?php if ($mesaj): ?>
                    <div class="p-4 mb-6 rounded-xl border flex items-center text-sm <?php echo $mesaj_turu == 'basari' ? 'bg-green-50 border-green-200 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-800 dark:text-red-300'; ?>">
                        <i data-lucide="<?php echo $mesaj_turu == 'basari' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 mr-3"></i>
                        <span><?php echo $mesaj; ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-gradient-to-br from-indigo-600 to-purple-600 p-6 rounded-3xl shadow-xl mb-10 text-white">
                    <h3 class="text-lg font-bold mb-4 flex items-center tracking-tight"><i data-lucide="sparkles" class="mr-2 w-5 h-5"></i> Yeni Bir Hayal Ekle</h3>
                    <form method="POST" action="hedefler.php" class="flex flex-col lg:flex-row gap-3">
                        <input type="text" name="baslik" placeholder="Hedefin ne? (Örn: Yeni Bilgisayar)" required class="flex-[2] p-3.5 rounded-2xl text-gray-900 outline-none focus:ring-4 focus:ring-white/20 shadow-inner">
                        <input type="number" step="0.01" name="tutar" placeholder="Tutar (₺)" required class="flex-1 p-3.5 rounded-2xl text-gray-900 outline-none focus:ring-4 focus:ring-white/20 shadow-inner">
                        <input type="date" name="hedef_tarih" class="flex-1 p-3.5 rounded-2xl text-gray-900 outline-none focus:ring-4 focus:ring-white/20 shadow-inner">
                        <button type="submit" name="hedef_ekle" class="px-8 py-3.5 bg-white text-indigo-600 font-black rounded-2xl hover:bg-gray-100 active:scale-95 transition-all shadow-lg">OLUŞTUR</button>
                    </form>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
                    <?php if (empty($hedefler)): ?>
                        <div class="col-span-full flex flex-col items-center justify-center py-20 bg-white/40 dark:bg-gray-800/40 rounded-3xl border-2 border-dashed border-gray-300 dark:border-gray-700">
                            <i data-lucide="target" class="w-16 h-16 text-gray-300 mb-4"></i>
                            <p class="text-gray-500 font-medium px-4 text-center italic">Henüz bir hedefin yok. Hemen bir tane ekle!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($hedefler as $h): 
                            $hedef = (float)$h['HedeflenenMiktar'];
                            $biriken = (float)$h['BirikenMiktar'];
                            $yuzde = ($hedef > 0) ? ($biriken / $hedef) * 100 : 0;
                            
                            $kalan_gun_str = "Süre Belirsiz";
                            $badgeClass = "bg-gray-100 text-gray-500"; $iconName = "calendar";

                            if (!empty($h['HedefTarih'])) {
                                $bugun = new DateTime(); $bitis = new DateTime($h['HedefTarih']);
                                if ($bugun < $bitis) {
                                    $gun = $bugun->diff($bitis)->days;
                                    $kalan_gun_str = $gun . " gün kaldı";
                                    if ($gun <= 7) { $badgeClass = "bg-red-100 text-red-600"; $iconName = "alert-circle"; }
                                    else { $badgeClass = "bg-blue-50 text-blue-600"; $iconName = "clock"; }
                                } else { $kalan_gun_str = "Süre Doldu"; $badgeClass = "bg-gray-200 text-gray-500"; }
                            }
                        ?>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-xl transition-all group relative">
                            <div class="absolute top-4 right-4 z-20">
                                <a href="?sil=<?php echo $h['HedefID']; ?>" onclick="return confirm('Hedefi siliyorsunuz, emin misiniz?')" class="text-gray-300 hover:text-red-500 transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></a>
                            </div>

                            <div class="mb-4">
                                <h4 class="text-lg font-black text-gray-900 dark:text-white truncate pr-6 uppercase tracking-tighter" title="<?php echo htmlspecialchars($h['HedefAdi']); ?>"><?php echo htmlspecialchars($h['HedefAdi']); ?></h4>
                                <span class="inline-flex items-center px-2 py-1 mt-2 rounded-lg text-[10px] font-bold uppercase tracking-widest <?php echo $badgeClass; ?>">
                                    <i data-lucide="<?php echo $iconName; ?>" class="w-3 h-3 mr-1"></i><?php echo $kalan_gun_str; ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-end mb-2">
                                <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400"><?php echo number_format($biriken, 0, ',', '.'); ?> ₺</span>
                                <span class="text-[10px] font-bold text-gray-400 mb-1 uppercase tracking-tighter">Hedef: <?php echo number_format($hedef, 0, ',', '.'); ?> ₺</span>
                            </div>

                            <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden mb-2">
                                <div class="progress-bar bg-gradient-to-r from-blue-500 to-indigo-600 h-full shadow-lg" style="width: <?php echo min($yuzde, 100); ?>%"></div>
                            </div>
                            <div class="flex justify-between text-[11px] font-bold uppercase tracking-widest">
                                <span class="text-gray-400">İlerleme</span>
                                <span class="text-indigo-600">%<?php echo number_format($yuzde, 1); ?></span>
                            </div>
                            
                            <div class="flex gap-2 mt-6 pt-4 border-t border-gray-50 dark:border-gray-700">
                                <button type="button" class="hedef-islem-btn flex-1 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 font-bold py-2.5 rounded-xl text-xs active:scale-95 transition-all"
                                        data-hedef-id="<?php echo $h['HedefID']; ?>" data-hedef-ad="<?php echo htmlspecialchars($h['HedefAdi']); ?>" data-islem-tipi="ekle">EKLE</button>
                                <button type="button" class="hedef-islem-btn flex-1 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold py-2.5 rounded-xl text-xs active:scale-95 transition-all"
                                        data-hedef-id="<?php echo $h['HedefID']; ?>" data-hedef-ad="<?php echo htmlspecialchars($h['HedefAdi']); ?>" data-islem-tipi="cikar">ÇIKAR</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
    
    <div id="hedef-modal" class="modal-hidden fixed inset-0 z-[60] flex justify-center items-center p-4">
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl p-6 w-full max-w-[350px] border border-white/20">
            <h3 id="modal-baslik" class="text-lg font-black text-gray-900 dark:text-white mb-6 uppercase tracking-tighter">İşlem</h3>
            <form action="hedefler.php" method="POST" class="space-y-6">
                <input type="hidden" name="modal_hedef_id" id="modal_hedef_id">
                <div class="space-y-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Miktar</label>
                    <div class="flex items-center bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl p-2 focus-within:ring-2 focus-within:ring-indigo-500">
                        <span class="px-3 font-bold text-gray-400 text-xl">₺</span>
                        <input type="number" name="modal_miktar" id="modal_miktar" step="0.01" min="0.01" required class="w-full bg-transparent border-none outline-none font-black text-2xl dark:text-white" placeholder="0.00">
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <button type="submit" name="hedef_islem_ekle" id="modal-submit-ekle" class="hidden w-full py-4 rounded-2xl font-black text-white bg-indigo-600 shadow-lg transition-all">BIRİKİME EKLE</button>
                    <button type="submit" name="hedef_islem_cikar" id="modal-submit-cikar" class="hidden w-full py-4 rounded-2xl font-black text-white bg-red-500 shadow-lg transition-all">HEDEFTEN ÇIKAR</button>
                    <button type="button" onclick="closeModal()" class="w-full py-3 text-xs font-bold text-gray-400 uppercase tracking-widest">Vazgeç</button>
                </div>
            </form>
        </div>
    </div>

    <a href="ai_asistan.php" class="fixed bottom-6 right-6 z-40 flex items-center justify-center">
        <span class="absolute h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
        <div class="relative h-12 w-12 flex items-center justify-center rounded-full bg-indigo-600 shadow-xl shadow-indigo-500/50"><i data-lucide="bot" class="h-6 w-6 text-white"></i></div>
    </a>

    <script>
        lucide.createIcons();
        function toggleSidebar() { const sb = document.getElementById('sidebar'); sb.classList.toggle('hidden-mobile'); sb.classList.toggle('show-mobile'); }

        const modal = document.getElementById('hedef-modal');
        const btnEkle = document.getElementById('modal-submit-ekle');
        const btnCikar = document.getElementById('modal-submit-cikar');

        document.querySelectorAll('.hedef-islem-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('modal_hedef_id').value = btn.dataset.hedefId;
                document.getElementById('modal-baslik').textContent = btn.dataset.hedefAd;
                btnEkle.classList.toggle('hidden', btn.dataset.islemTipi !== 'ekle');
                btnCikar.classList.toggle('hidden', btn.dataset.islemTipi !== 'cikar');
                modal.classList.remove('modal-hidden');
            });
        });

        function closeModal() { modal.classList.add('modal-hidden'); }

        const themeBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        function updateThemeIcon() {
            const isDark = document.documentElement.classList.contains('dark');
            themeIcon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
            lucide.createIcons();
        }
        updateThemeIcon();

        // TEMA VE ARKA PLAN KONTROLÜ
        function applySavedSettings() {
            const theme = localStorage.getItem('color-theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else { document.documentElement.classList.remove('dark'); }

            const currentBg = localStorage.getItem('app-background');
            const classesToRemove = ['bg-gradient-ocean', 'bg-gradient-sunset', 'bg-gradient-forest', 'bg-gradient-lavender'];
            document.body.classList.remove(...classesToRemove);
            if(currentBg && currentBg !== 'default') { document.body.classList.add(currentBg); }
        }
        applySavedSettings();

        themeBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('color-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            updateThemeIcon();
        });
    </script>
</body>
</html>