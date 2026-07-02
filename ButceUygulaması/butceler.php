<?php
// GÜVENLİK VE OTURUM (KODUNUZ KORUNDU)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: giris.php");
    exit;
}
$kullanici_id = $_SESSION['KullaniciID'];
$ad_soyad = htmlspecialchars($_SESSION['AdSoyad'] ?? 'Misafir');

$sunucu_adi = "localhost"; $kullanici_adi = "root"; $sifre_db = ""; $veritabani_adi = "butce_yonetim_db";
$mesaj = ""; $mesaj_turu = "";

try {
    $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['limit_kaydet'])) {
        $kat_id = (int)$_POST['kategori_id'];
        $miktar = (float)$_POST['limit_miktar'];
        $kontrol = $pdo->prepare("SELECT ButceID FROM Butceler WHERE KullaniciID = ? AND KategoriID = ?");
        $kontrol->execute([$kullanici_id, $kat_id]);
        $mevcut_butce = $kontrol->fetch(PDO::FETCH_ASSOC);
        
        if ($mevcut_butce) {
            $sql = "UPDATE Butceler SET AylikLimit = ? WHERE ButceID = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$miktar, $mevcut_butce['ButceID']]);
        } else {
            $sql = "INSERT INTO Butceler (KullaniciID, KategoriID, AylikLimit) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$kullanici_id, $kat_id, $miktar]);
        }
        $mesaj = "Bütçe limiti başarıyla kaydedildi.";
        $mesaj_turu = "basari";
    }
    
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['butce_sil'])) {
        $sil_id = (int)$_POST['butce_id'];
        $sql_del = "DELETE FROM Butceler WHERE ButceID = ? AND KullaniciID = ?";
        $stmt_del = $pdo->prepare($sql_del);
        $stmt_del->execute([$sil_id, $kullanici_id]);
        $mesaj = "Bütçe limiti kaldırıldı.";
        $mesaj_turu = "basari";
    }

    $ay_baslangici = date('Y-m-01');
    $ay_sonu = date('Y-m-t');

    $sql = "
        SELECT k.KategoriID, k.KategoriAdi, COALESCE(b.AylikLimit, 0) as LimitMiktar, b.ButceID,
            COALESCE((SELECT SUM(Miktar) FROM Islemler WHERE KategoriID = k.KategoriID AND KullaniciID = :kid AND Tip = 'Gider' AND Tarih BETWEEN :bas AND :son), 0) as Harcanan
        FROM Kategoriler k
        LEFT JOIN Butceler b ON k.KategoriID = b.KategoriID AND b.KullaniciID = :kid
        WHERE k.Tip = 'Gider' AND k.KategoriAdi != 'Birikim' AND (k.KullaniciID = :kid OR k.KullaniciID IS NULL)
        ORDER BY b.AylikLimit DESC, k.KategoriAdi
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['kid' => $kullanici_id, 'bas' => $ay_baslangici, 'son' => $ay_sonu]);
    $butceler = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $mesaj = "Hata: " . $e->getMessage(); $mesaj_turu = "hata"; }
?>

<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bütçe Limitleri | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script> tailwind.config = { darkMode: 'class' } </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        .blob { position: absolute; filter: blur(50px); z-index: -1; opacity: 0.4; animation: float 8s ease-in-out infinite; }
        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, -20px) scale(1.1); } 100% { transform: translate(0, 0) scale(1); } }
        .glass-sidebar { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.5); }
        .dark .glass-sidebar { background: rgba(31, 41, 55, 0.9); }
        .fade-in { animation: fadeIn 0.6s ease-out forwards; opacity: 0; transform: translateY(10px); }
        @keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }
        
        #sidebar { transition: transform 0.3s ease-in-out; }
        @media (max-width: 768px) {
            #sidebar.hidden-mobile { transform: translateX(-100%); }
            #sidebar.show-mobile { transform: translateX(0); }
        }
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

    <div class="blob bg-red-200 w-64 h-64 md:w-96 md:h-96 rounded-full top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
    <div class="blob bg-orange-200 w-64 h-64 md:w-96 md:h-96 rounded-full bottom-0 right-0 translate-x-1/2 translate-y-1/2"></div>

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
            <a href="butceler.php" class="flex items-center px-4 py-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-xl font-semibold transition-all shadow-sm"><i data-lucide="shield-check" class="w-5 h-5 mr-3"></i> Bütçe Limitleri</a>
            <a href="hedefler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="target" class="w-5 h-5 mr-3"></i> Tasarruf Hedefleri</a>
            <a href="ai_asistan.php" class="flex items-center px-4 py-3 mt-4 text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg transform hover:scale-[1.02] transition-all"><i data-lucide="bot" class="w-5 h-5 mr-3"></i> AI Asistan</a>
            <a href="ayarlar.php" class="flex items-center px-4 py-3 mt-2 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 rounded-xl transition-all"><i data-lucide="settings" class="w-5 h-5 mr-3"></i> Ayarlar</a>
            <div class="pt-4 mt-2 border-t border-gray-200/50 dark:border-gray-700">
                <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Fırsatlar</p>
                <a href="https://www.kariyer.net/is-ilanlari/part+time-2?tpst=4&cp=2" target="_blank" class="flex items-center px-4 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-xl transition-all group">
                    <i data-lucide="briefcase" class="w-4 h-4 mr-3 text-purple-500 group-hover:scale-110 transition-transform"></i>
                    <span class="text-sm font-medium">Part Time İş</span>
                </a>
                <a href="https://www.linkedin.com/company/staj-ve-burs-ilanlari" target="_blank" class="flex items-center px-4 py-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition-all group">
                    <i data-lucide="graduation-cap" class="w-4 h-4 mr-3 text-blue-500 group-hover:scale-110 transition-transform"></i>
                    <span class="text-sm font-medium">Staj & Burs</span>
                </a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200/50 dark:border-gray-700">
            <a href="cikis.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors text-sm font-medium"><i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Çıkış Yap</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="h-20 bg-white/60 dark:bg-gray-800/60 backdrop-blur-md border-b border-gray-200/50 dark:border-gray-700 flex justify-between items-center px-4 md:px-8 z-10 transition-colors">
            <div class="flex items-center">
                <button class="md:hidden p-2 mr-3 bg-white dark:bg-gray-700 rounded-lg shadow-sm" onclick="toggleSidebar()"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <h2 class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white truncate max-w-[180px] sm:max-w-none">Aylık Bütçe Planı 🛡️</h2>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300"><i id="theme-icon" data-lucide="moon" class="w-5 h-5"></i></button>
                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm flex-shrink-0"><?php echo strtoupper(substr($ad_soyad, 0, 1)); ?></div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 relative">
            <div class="max-w-5xl mx-auto fade-in pb-20">
                
                <?php if ($mesaj): ?>
                    <div class="p-4 mb-6 rounded-xl border flex items-center text-sm <?php echo $mesaj_turu == 'basari' ? 'bg-green-50 border-green-200 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/30 dark:text-red-300'; ?>">
                        <i data-lucide="<?php echo $mesaj_turu == 'basari' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 mr-2"></i>
                        <span><?php echo $mesaj; ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm p-5 md:p-6 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 mb-8">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center"><i data-lucide="plus" class="w-5 h-5 mr-2 text-indigo-500"></i> Yeni Limit Belirle</h3>
                    <form method="POST" action="butceler.php" class="flex flex-col sm:flex-row gap-4 items-end">
                        <div class="w-full">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">KATEGORİ</label>
                            <select name="kategori_id" class="w-full p-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 appearance-none">
                                <?php foreach ($butceler as $b): ?>
                                    <option value="<?php echo $b['KategoriID']; ?>"><?php echo htmlspecialchars($b['KategoriAdi']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">AYLIK LİMİT (TL)</label>
                            <input type="number" step="0.01" name="limit_miktar" placeholder="Örn: 2000" required class="w-full p-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <button type="submit" name="limit_kaydet" class="w-full sm:w-auto px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-md active:scale-95">Kaydet</button>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                    <?php foreach ($butceler as $b): 
                        $limit = (float)$b['LimitMiktar'];
                        $harcanan = (float)$b['Harcanan'];
                        $yuzde = ($limit > 0) ? ($harcanan / $limit) * 100 : 0;
                        $kalan = $limit - $harcanan;
                        
                        if ($limit <= 0) {
                            $durumText = "Limit Yok"; $durumClass = "text-gray-400"; $barColor = "bg-gray-300 dark:bg-gray-600"; $limitText = "Limit Belirlenmemiş"; $barWidth = 0;
                        } else {
                            $limitText = "Limit: " . number_format($limit, 0, ',', '.') . " ₺";
                            $barWidth = min($yuzde, 100);
                            if ($kalan < 0) {
                                $durumText = "Aşıldı: " . number_format(abs($kalan), 0, ',', '.') . " ₺"; $durumClass = "text-red-600 font-bold"; $barColor = "bg-red-500";
                            } else {
                                $durumText = "Kalan: " . number_format($kalan, 0, ',', '.') . " ₺"; $durumClass = "text-green-600 font-bold"; $barColor = ($yuzde >= 80) ? 'bg-yellow-500' : 'bg-green-500';
                            }
                        }
                    ?>
                    <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm p-5 md:p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition-all group">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($b['KategoriAdi']); ?></h4>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"><?php echo $limitText; ?></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <span class="block text-xs <?php echo $durumClass; ?>"><?php echo $durumText; ?></span>
                                </div>
                                <?php if($limit > 0): ?>
                                <form method="POST" action="butceler.php" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="butce_id" value="<?php echo $b['ButceID']; ?>">
                                    <button type="submit" name="butce_sil" class="text-gray-300 hover:text-red-500 transition-colors p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="relative w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="absolute top-0 left-0 h-full <?php echo $barColor; ?> transition-all duration-1000" style="width: <?php echo $barWidth; ?>%"></div>
                        </div>
                        <div class="flex justify-between mt-3 text-[10px] font-black uppercase tracking-tighter">
                            <span class="text-gray-500">Harcanan: <?php echo number_format($harcanan, 0, ',', '.'); ?> ₺</span>
                            <span class="<?php echo $yuzde > 100 ? 'text-red-500' : 'text-indigo-500'; ?>"><?php echo ($limit > 0) ? '%' . number_format($yuzde, 0) : ''; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </main>
    </div>
    
    <a href="ai_asistan.php" class="fixed bottom-6 right-6 z-40 flex items-center justify-center">
        <span class="absolute h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
        <div class="relative h-12 w-12 flex items-center justify-center rounded-full bg-indigo-600 shadow-xl"><i data-lucide="bot" class="h-6 w-6 text-white"></i></div>
    </a>

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
        themeBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('color-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            updateThemeIcon();
        });

        const currentBg = localStorage.getItem('app-background');
        if(currentBg && currentBg !== 'default') document.body.classList.add(currentBg);
    </script>
</body>
</html>