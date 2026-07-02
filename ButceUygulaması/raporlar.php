<?php
// GÜVENLİK KONTROLÜ VE OTURUM BAŞLATMA (KODUNUZ KORUNDU)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: giris.php");
    exit;
}
$kullanici_id = (int)$_SESSION['KullaniciID'];
$ad_soyad = htmlspecialchars($_SESSION['AdSoyad'] ?? 'Misafir');

// VERİTABANI BAĞLANTISI VE VERİ ÇEKME İŞLEMLERİ (KODUNUZ KORUNDU)
$sunucu_adi = "localhost"; $kullanici_adi = "root"; $sifre_db = ""; $veritabani_adi = "butce_yonetim_db";
$hata_mesaji = ''; $butce_asimlari = []; $hedefler = [];
$trend_verisi = ['labels' => [], 'data' => []]; 
$kategori_dagilim_verisi = ['labels' => [], 'data' => [], 'kategoriler' => []];

$secilen_periyot = isset($_GET['period']) ? (int)$_GET['period'] : 1;
if (!in_array($secilen_periyot, [1, 3, 6, 9])) { $secilen_periyot = 1; }

try {
    $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $guncel_ay = date('m'); $guncel_yil = date('Y');

    if ($secilen_periyot == 1) {
        $stmt_trend = $pdo->prepare("SELECT DAY(Tarih) AS Zaman, SUM(Miktar) AS Toplam FROM Islemler WHERE KullaniciID = :k_id AND Tip = 'Gider' AND MONTH(Tarih) = :ay AND YEAR(Tarih) = :yil GROUP BY Zaman ORDER BY Zaman ASC");
        $stmt_trend->execute(['k_id' => $kullanici_id, 'ay' => $guncel_ay, 'yil' => $guncel_yil]);
        $ham_veri = $stmt_trend->fetchAll(PDO::FETCH_KEY_PAIR);
        $gun_sayisi = date('t');
        for ($i = 1; $i <= $gun_sayisi; $i++) {
            $trend_verisi['labels'][] = $i . ". Gün";
            $trend_verisi['data'][] = isset($ham_veri[$i]) ? $ham_veri[$i] : 0;
        }
    } else {
        $stmt_trend = $pdo->prepare("SELECT DATE_FORMAT(Tarih, '%Y-%m') AS Zaman, SUM(Miktar) AS Toplam FROM Islemler WHERE KullaniciID = :k_id AND Tip = 'Gider' AND Tarih >= DATE_SUB(CURDATE(), INTERVAL :p MONTH) GROUP BY Zaman ORDER BY Zaman ASC");
        $stmt_trend->execute(['k_id' => $kullanici_id, 'p' => $secilen_periyot]);
        $ham_veri = $stmt_trend->fetchAll(PDO::FETCH_ASSOC);
        $aylar = ['01'=>'Ocak', '02'=>'Şubat', '03'=>'Mart', '04'=>'Nisan', '05'=>'Mayıs', '06'=>'Haziran', '07'=>'Temmuz', '08'=>'Ağustos', '09'=>'Eylül', '10'=>'Ekim', '11'=>'Kasım', '12'=>'Aralık'];
        foreach ($ham_veri as $veri) {
            $yil_ay = explode('-', $veri['Zaman']);
            $trend_verisi['labels'][] = $aylar[$yil_ay[1]] . " " . $yil_ay[0];
            $trend_verisi['data'][] = $veri['Toplam'];
        }
    }

    $stmt_kategori = $pdo->prepare("SELECT k.KategoriID, k.KategoriAdi, SUM(i.Miktar) AS ToplamMiktar FROM Islemler i JOIN Kategoriler k ON i.KategoriID = k.KategoriID WHERE i.KullaniciID = :k_id AND i.Tip = 'Gider' AND MONTH(i.Tarih) = :ay AND YEAR(i.Tarih) = :yil GROUP BY k.KategoriID, k.KategoriAdi HAVING ToplamMiktar > 0 ORDER BY ToplamMiktar DESC");
    $stmt_kategori->execute(['k_id' => $kullanici_id, 'ay' => $guncel_ay, 'yil' => $guncel_yil]);
    $kategori_verileri = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);
    foreach ($kategori_verileri as $veri) {
        $kategori_dagilim_verisi['labels'][] = $veri['KategoriAdi'];
        $kategori_dagilim_verisi['data'][] = $veri['ToplamMiktar'];
        $kategori_dagilim_verisi['kategoriler'][] = $veri;
    }

    $sql_butce = "SELECT b.AylikLimit, k.KategoriAdi, (SELECT COALESCE(SUM(i.Miktar), 0) FROM Islemler i WHERE i.KullaniciID = b.KullaniciID AND i.KategoriID = b.KategoriID AND i.Tip = 'Gider' AND MONTH(i.Tarih) = ? AND YEAR(i.Tarih) = ?) AS BuAykiHarcama FROM Butceler b JOIN Kategoriler k ON b.KategoriID = k.KategoriID WHERE b.KullaniciID = ?";
    $stmt_butce = $pdo->prepare($sql_butce);
    $stmt_butce->execute([$guncel_ay, $guncel_yil, $kullanici_id]);
    while ($butce = $stmt_butce->fetch(PDO::FETCH_ASSOC)) {
        if ($butce['AylikLimit'] > 0 && $butce['BuAykiHarcama'] > $butce['AylikLimit']) {
            $butce_asimlari[] = ['KategoriAdi' => $butce['KategoriAdi'], 'Limit' => $butce['AylikLimit'], 'Harcama' => $butce['BuAykiHarcama'], 'Fark' => $butce['BuAykiHarcama'] - $butce['AylikLimit']];
        }
    }

    $stmt_hedefler = $pdo->prepare("SELECT HedefID, HedefAdi, HedeflenenMiktar, BirikenMiktar FROM Hedefler WHERE KullaniciID = :k_id ORDER BY OlusturmaTarihi DESC");
    $stmt_hedefler->execute(['k_id' => $kullanici_id]);
    $hedefler = $stmt_hedefler->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $hata_mesaji = "Veritabanı hatası: " . $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapor ve Analiz | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script> tailwind.config = { darkMode: 'class' } </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        .blob { position: absolute; filter: blur(50px); z-index: -1; opacity: 0.4; animation: float 8s ease-in-out infinite; }
        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, -20px) scale(1.1); } 100% { transform: translate(0, 0) scale(1); } }
        .glass-sidebar { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); }
        .dark .glass-sidebar { background: rgba(31, 41, 55, 0.9); }
        .fade-in { animation: fadeIn 0.8s ease-out forwards; opacity: 0; transform: translateY(10px); }
        @keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }

        /* --- ARKA PLAN RENK TANIMLARI --- */
        body.bg-gradient-ocean { background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%) !important; }
        body.bg-gradient-sunset { background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%) !important; }
        body.bg-gradient-forest { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important; }
        body.bg-gradient-lavender { background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%) !important; }

        html.dark body.bg-gradient-ocean { background: linear-gradient(135deg, #0c4a6e 0%, #075985 100%) !important; }
        html.dark body.bg-gradient-sunset { background: linear-gradient(135deg, #7c2d12 0%, #9a3412 100%) !important; }
        html.dark body.bg-gradient-forest { background: linear-gradient(135deg, #14532d 0%, #166534 100%) !important; }
        html.dark body.bg-gradient-lavender { background: linear-gradient(135deg, #4c1d95 0%, #5b21b6 100%) !important; }
        
        #sidebar { transition: transform 0.3s ease-in-out; }
        @media (max-width: 768px) { #sidebar.hidden-mobile { transform: translateX(-100%); } #sidebar.show-mobile { transform: translateX(0); } }
    </style>
    <script>
        const theme = localStorage.getItem('color-theme');
        if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 flex h-screen overflow-hidden relative transition-colors duration-300">

    <div class="blob bg-indigo-200 w-64 h-64 md:w-96 md:h-96 rounded-full top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
    <div class="blob bg-purple-200 w-64 h-64 md:w-96 md:h-96 rounded-full bottom-0 right-0 translate-x-1/2 translate-y-1/2"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 glass-sidebar z-50 flex flex-col justify-between h-full shadow-lg border-r border-gray-200/50 dark:border-gray-700 md:relative md:translate-x-0 hidden-mobile">
        <div class="h-20 flex items-center justify-between px-6 border-b border-gray-200/50 dark:border-gray-700">
            <a href="dashboard.php" class="flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 font-extrabold text-xl">
                <i data-lucide="graduation-cap"></i><span>CEP BÜYÜSÜ</span>
            </a>
            <button class="md:hidden text-gray-500" onclick="toggleSidebar()"><i data-lucide="x"></i></button>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto font-medium text-sm">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="layout-grid" class="w-5 h-5 mr-3"></i> Ana Panel</a>
            <a href="ekle.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="plus-circle" class="w-5 h-5 mr-3"></i> Hızlı Ekle</a>
            <a href="islemler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="list" class="w-5 h-5 mr-3"></i> İşlemler</a>
            <a href="raporlar.php" class="flex items-center px-4 py-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-xl shadow-sm"><i data-lucide="pie-chart" class="w-5 h-5 mr-3"></i> Raporlar</a>
            <a href="butceler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="shield-check" class="w-5 h-5 mr-3"></i> Bütçe Limitleri</a>
            <a href="hedefler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="target" class="w-5 h-5 mr-3"></i> Tasarruf Hedefleri</a>
            <a href="ai_asistan.php" class="flex items-center px-4 py-3 mt-4 text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg transform hover:scale-[1.02] transition-all"><i data-lucide="bot" class="w-5 h-5 mr-3"></i> AI Asistan</a>
            <a href="ayarlar.php" class="flex items-center px-4 py-3 mt-2 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="settings" class="w-5 h-5 mr-3"></i> Ayarlar</a>
            <div class="pt-4 mt-2 border-t border-gray-200/50 dark:border-gray-700 font-bold uppercase text-[10px] tracking-widest text-gray-400">
                <p class="px-4 mb-2">Fırsatlar</p>
                <a href="https://www.linkedin.com/company/staj-ve-burs-ilanlari" target="_blank" class="flex items-center px-4 py-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition-all"><i data-lucide="graduation-cap" class="w-4 h-4 mr-3"></i> Staj & Burs</a>
                <a href="https://www.kariyer.net/is-ilanlari/part+time-2" target="_blank" class="flex items-center px-4 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-xl transition-all"><i data-lucide="briefcase" class="w-4 h-4 mr-3"></i> Part Time İş</a>
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
                <h2 class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white">Raporlar 📊</h2>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button onclick="downloadPDF()" class="flex items-center bg-indigo-600 text-white px-3 md:px-4 py-2 rounded-lg font-bold text-[10px] md:text-sm shadow-lg active:scale-95 transition-all"><i data-lucide="download" class="w-4 h-4 md:mr-2"></i> <span class="hidden sm:inline">PDF İndir</span></button>
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300"><i id="theme-icon" data-lucide="moon" class="w-5 h-5"></i></button>
                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm flex-shrink-0"><?php echo strtoupper(substr($ad_soyad, 0, 1)); ?></div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 relative">
            <div id="report-content" class="max-w-7xl mx-auto fade-in pb-20">
                
                <?php if (!empty($butce_asimlari)): ?>
                <div class="bg-red-50/50 dark:bg-red-900/10 border border-red-100 dark:border-red-800/30 rounded-xl p-4 mb-8">
                    <h3 class="text-xs font-bold text-red-800 dark:text-red-300 mb-3 flex items-center uppercase tracking-widest"><i data-lucide="alert-triangle" class="w-4 h-4 mr-2"></i> Bütçe Aşımları</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($butce_asimlari as $asim): ?>
                            <div class="px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-red-200 dark:border-red-900 text-[10px] font-bold text-gray-700 dark:text-gray-300 shadow-sm"><span class="text-red-600 dark:text-red-400"><?php echo htmlspecialchars($asim['KategoriAdi']); ?>:</span> <?php echo number_format($asim['Fark'], 2, ',', '.'); ?> ₺ aşıldı</div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-white/90 dark:bg-gray-800/90 p-5 md:p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 mb-8">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-6 gap-4">
                        <h3 class="font-bold text-gray-800 dark:text-white flex items-center"><i data-lucide="trending-up" class="w-5 h-5 mr-2 text-red-500"></i> Harcama Eğilimi</h3>
                        <div class="flex bg-gray-100 dark:bg-gray-700 rounded-xl p-1 gap-1">
                            <?php foreach([1 => 'Bu Ay', 3 => '3 Ay', 6 => '6 Ay', 9 => '9 Ay'] as $val => $label): 
                                $active = ($secilen_periyot == $val) ? 'bg-white dark:bg-gray-600 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'; ?>
                                <a href="?period=<?php echo $val; ?>" class="px-3 py-1.5 text-[10px] font-black rounded-lg transition-all <?php echo $active; ?> uppercase"><?php echo $label; ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="w-full h-64 md:h-80">
                        <?php if (empty(array_filter($trend_verisi['data']))): ?>
                            <div class="h-full flex items-center justify-center text-gray-400 text-sm italic">Veri bulunamadı.</div>
                        <?php else: ?>
                            <canvas id="gunlukHarcamaChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white/90 dark:bg-gray-800/90 p-5 md:p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 mb-8">
                    <h3 class="font-bold text-gray-800 dark:text-white mb-6 flex items-center"><i data-lucide="pie-chart" class="w-5 h-5 mr-2 text-indigo-500"></i> Kategori Dağılımı</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                        <div class="w-full h-64 md:h-80">
                            <?php if (empty($kategori_dagilim_verisi['data'])): ?>
                                <div class="h-full flex items-center justify-center text-gray-400 text-sm italic">Henüz harcama yok.</div>
                            <?php else: ?>
                                <canvas id="kategoriDaginimChart"></canvas>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-3 max-h-80 overflow-y-auto pr-2 custom-scrollbar">
                            <?php foreach ($kategori_dagilim_verisi['kategoriler'] as $kategori): ?>
                                <div class="flex justify-between items-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-100 dark:border-gray-700">
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($kategori['KategoriAdi']); ?></span>
                                    <span class="text-xs font-black text-red-600 dark:text-red-400">-<?php echo number_format($kategori['ToplamMiktar'], 2, ',', '.'); ?> ₺</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white/90 dark:bg-gray-800/90 p-5 md:p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 mb-8">
                    <h3 class="font-bold text-gray-800 dark:text-white mb-6 flex items-center"><i data-lucide="target" class="w-5 h-5 mr-2 text-blue-500"></i> Tasarruf Hedefleri</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                        <?php if (empty($hedefler)): ?>
                            <p class="text-gray-400 text-sm italic col-span-full">Henüz hedef oluşturmadınız.</p>
                        <?php else: ?>
                            <?php foreach ($hedefler as $hedef): 
                                $yuzde = ($hedef['HedeflenenMiktar'] > 0) ? min(100, ($hedef['BirikenMiktar'] / $hedef['HedeflenenMiktar']) * 100) : 0; ?>
                                <div class="bg-gray-50 dark:bg-gray-700/30 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm">
                                    <h4 class="text-sm font-black text-gray-900 dark:text-white truncate mb-2 uppercase tracking-tight"><?php echo htmlspecialchars($hedef['HedefAdi']); ?></h4>
                                    <div class="flex justify-between text-[10px] font-bold text-blue-600 dark:text-blue-400 mb-2"><span><?php echo number_format($hedef['BirikenMiktar'], 2, ',', '.'); ?> ₺</span><span>%<?php echo number_format($yuzde, 1); ?></span></div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5 overflow-hidden"><div class="bg-blue-600 h-full transition-all duration-1000" style="width: <?php echo $yuzde; ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        function toggleSidebar() { const sb = document.getElementById('sidebar'); sb.classList.toggle('hidden-mobile'); sb.classList.toggle('show-mobile'); }

        const themeBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        function updateThemeIcon() {
            const isDark = document.documentElement.classList.contains('dark');
            themeIcon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
            lucide.createIcons();
        }
        updateThemeIcon();

        // TEMA VE ARKA PLAN KONTROLÜ (TÜM SAYFALARDA OLMALI)
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

        themeBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('color-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            updateThemeIcon();
        });

        // Grafik Verileri ve Çizimi
        const trendVeri = <?php echo json_encode($trend_verisi); ?>;
        const kategoriVeri = <?php echo json_encode($kategori_dagilim_verisi); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            const chartConfig = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#9ca3af', font: { size: 10, weight: 'bold' } } } }
            };

            const ctxGunluk = document.getElementById('gunlukHarcamaChart');
            if (ctxGunluk && trendVeri.data.length > 0) {
                new Chart(ctxGunluk, {
                    type: 'line',
                    data: { labels: trendVeri.labels, datasets: [{ label: 'Harcama (₺)', data: trendVeri.data, fill: true, borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.05)', tension: 0.4, pointRadius: 3 }] },
                    options: { ...chartConfig, scales: { y: { beginAtZero: true, grid: { color: 'rgba(156, 163, 175, 0.1)' }, ticks: { color: '#9ca3af', font: { size: 9 } } }, x: { grid: { display: false }, ticks: { color: '#9ca3af', font: { size: 9 } } } } }
                });
            }

            const ctxKategori = document.getElementById('kategoriDaginimChart');
            if (ctxKategori && kategoriVeri.data.length > 0) {
                new Chart(ctxKategori, {
                    type: 'doughnut',
                    data: { labels: kategoriVeri.labels, datasets: [{ data: kategoriVeri.data, backgroundColor: ['#6366f1', '#f43f5e', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'], borderWidth: 0 }] },
                    options: { ...chartConfig, plugins: { legend: { position: 'bottom' } } }
                });
            }
        });

        function downloadPDF() {
            window.scrollTo(0, 0);
            const element = document.getElementById('report-content');
            const btn = document.querySelector('button[onclick="downloadPDF()"]');
            if(btn) btn.style.visibility = 'hidden';

            html2canvas(element, { scale: 2, useCORS: true, backgroundColor: document.documentElement.classList.contains('dark') ? '#111827' : '#f9fafb' }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgWidth = 190;
                const imgHeight = canvas.height * imgWidth / canvas.width;
                pdf.text("CEP BÜYÜSÜ - Finansal Rapor", 105, 10, { align: 'center' });
                pdf.addImage(imgData, 'PNG', 10, 20, imgWidth, imgHeight);
                pdf.save('bütçe-raporu.pdf');
                if(btn) btn.style.visibility = 'visible';
            });
        }
    </script>
</body>
</html>