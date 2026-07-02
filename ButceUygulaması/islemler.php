<?php
// GÜVENLİK KONTROLÜ VE OTURUM BAŞLATMA (KODUNUZ KORUNDU)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: giris.php");
    exit;
}
$kullanici_id = $_SESSION['KullaniciID'];
$ad_soyad = htmlspecialchars($_SESSION['AdSoyad'] ?? 'Misafir');

$sunucu_adi = "localhost"; $kullanici_adi = "root"; $sifre_db = ""; $veritabani_adi = "butce_yonetim_db";
$hata_mesaji = ''; $basari_mesaji = ''; $islemler = []; $kategoriler = [];
$toplam_gelir = 0.00; $toplam_gider = 0.00; $net_bakiye = 0.00;

try {
    $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sil_id'])) {
        $sil_id = (int)$_POST['sil_id'];
        $sql_delete = "DELETE FROM Islemler WHERE IslemID = :islem_id AND KullaniciID = :kullanici_id";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute(['islem_id' => $sil_id, 'kullanici_id' => $kullanici_id]);
        if ($stmt_delete->rowCount() > 0) { $basari_mesaji = "İşlem başarıyla silindi."; } 
        else { $hata_mesaji = "İşlem silinemedi veya bu işlem size ait değil."; }
    }

    $filtre_tip = $_GET['tip'] ?? '';
    $filtre_kategori = (int)($_GET['kategori_id'] ?? 0);
    $filtre_tarih_bas = $_GET['tarih_bas'] ?? '';
    $filtre_tarih_bit = $_GET['tarih_bit'] ?? '';

    $stmt_kat = $pdo->prepare("SELECT KategoriID, KategoriAdi FROM Kategoriler WHERE KullaniciID = ? OR KullaniciID IS NULL ORDER BY KategoriAdi");
    $stmt_kat->execute([$kullanici_id]);
    $kategoriler = $stmt_kat->fetchAll(PDO::FETCH_ASSOC);

    $sql_where_kosullari = ["i.KullaniciID = :kullanici_id"];
    $sql_parametreler = ['kullanici_id' => $kullanici_id];

    if (!empty($filtre_tip)) { $sql_where_kosullari[] = "i.Tip = :tip"; $sql_parametreler['tip'] = $filtre_tip; }
    if ($filtre_kategori > 0) { $sql_where_kosullari[] = "i.KategoriID = :kategori_id"; $sql_parametreler['kategori_id'] = $filtre_kategori; }
    if (!empty($filtre_tarih_bas)) { $sql_where_kosullari[] = "i.Tarih >= :tarih_bas"; $sql_parametreler['tarih_bas'] = $filtre_tarih_bas; }
    if (!empty($filtre_tarih_bit)) { $sql_where_kosullari[] = "i.Tarih <= :tarih_bit"; $sql_parametreler['tarih_bit'] = $filtre_tarih_bit; }

    $where_sorgusu = implode(' AND ', $sql_where_kosullari);

    $sql_islemler = "SELECT i.IslemID, i.Tarih, i.Aciklama, k.KategoriAdi, i.Tip, i.Miktar FROM Islemler i JOIN Kategoriler k ON i.KategoriID = k.KategoriID WHERE $where_sorgusu ORDER BY i.Tarih DESC, i.IslemID DESC";
    $stmt_islemler = $pdo->prepare($sql_islemler);
    $stmt_islemler->execute($sql_parametreler);
    $islemler = $stmt_islemler->fetchAll(PDO::FETCH_ASSOC);

    $sql_toplamlar = "SELECT Tip, SUM(Miktar) as Toplam FROM Islemler i WHERE $where_sorgusu GROUP BY Tip";
    $stmt_toplamlar = $pdo->prepare($sql_toplamlar);
    $stmt_toplamlar->execute($sql_parametreler);
    while ($row = $stmt_toplamlar->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Tip'] == 'Gelir') { $toplam_gelir = $row['Toplam'] ?? 0.00; } 
        elseif ($row['Tip'] == 'Gider') { $toplam_gider = $row['Toplam'] ?? 0.00; }
    }
    $net_bakiye = $toplam_gelir - $toplam_gider;

} catch (PDOException $e) { $hata_mesaji = "Veritabanı hatası: " . $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İşlemler | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
    <script src="Nunito-VariableFont_wght-normal.js"></script>
    <script> tailwind.config = { darkMode: 'class' } </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        .blob { position: absolute; filter: blur(50px); z-index: -1; opacity: 0.4; animation: float 8s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(30px, -50px) scale(1.1); } 66% { transform: translate(-20px, 20px) scale(0.9); } }
        .glass-sidebar { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.5); }
        .dark .glass-sidebar { background: rgba(31, 41, 55, 0.9); }
        .fade-in { animation: fadeIn 0.6s ease-out forwards; opacity: 0; transform: translateY(10px); }
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
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto font-medium">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="layout-grid" class="w-5 h-5 mr-3"></i> Ana Panel</a>
            <a href="ekle.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="plus-circle" class="w-5 h-5 mr-3"></i> Hızlı Ekle</a>
            <a href="islemler.php" class="flex items-center px-4 py-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-xl shadow-sm"><i data-lucide="list" class="w-5 h-5 mr-3"></i> İşlemler</a>
            <a href="raporlar.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="pie-chart" class="w-5 h-5 mr-3"></i> Raporlar</a>
            <a href="butceler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="shield-check" class="w-5 h-5 mr-3"></i> Bütçe Limitleri</a>
            <a href="hedefler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="target" class="w-5 h-5 mr-3"></i> Tasarruf Hedefleri</a>
            <a href="ai_asistan.php" class="flex items-center px-4 py-3 mt-4 text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg transform hover:scale-[1.02] transition-all"><i data-lucide="bot" class="w-5 h-5 mr-3"></i> AI Asistan</a>
            <a href="ayarlar.php" class="flex items-center px-4 py-3 mt-2 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="settings" class="w-5 h-5 mr-3"></i> Ayarlar</a>
            <div class="pt-4 mt-2 border-t border-gray-200/50 dark:border-gray-700">
                <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Fırsatlar</p>
                <a href="https://www.linkedin.com/company/staj-ve-burs-ilanlari/posts/?feedView=all" target="_blank" class="flex items-center px-4 py-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition-all text-sm font-semibold"><i data-lucide="graduation-cap" class="w-4 h-4 mr-3"></i> Staj & Burs</a>
                <a href="https://www.kariyer.net/is-ilanlari/part+time" target="_blank" class="flex items-center px-4 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-xl transition-all text-sm font-semibold"><i data-lucide="briefcase" class="w-4 h-4 mr-3"></i> Part Time İş</a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200/50 dark:border-gray-700">
            <a href="cikis.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors text-sm font-bold"><i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Çıkış Yap</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="h-20 bg-white/60 dark:bg-gray-800/60 backdrop-blur-md border-b border-gray-200/50 dark:border-gray-700 flex justify-between items-center px-4 md:px-8 z-10 transition-colors">
            <div class="flex items-center">
                <button class="md:hidden p-2 mr-3 bg-white dark:bg-gray-700 rounded-lg shadow-sm" onclick="toggleSidebar()"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <h2 class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white">İşlemler</h2>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300"><i id="theme-icon" data-lucide="moon" class="w-5 h-5"></i></button>
                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm flex-shrink-0"><?php echo strtoupper(substr($ad_soyad, 0, 1)); ?></div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 relative">
            <div class="max-w-7xl mx-auto fade-in pb-20">
                <div class="flex flex-col sm:flex-row sm:items-center mb-8 gap-4">
                    <div class="flex items-center">
                        <div class="bg-white dark:bg-gray-700 p-2 rounded-lg shadow-sm mr-3">
                            <i data-lucide="history" class="w-6 h-6 md:w-8 md:h-8 text-indigo-600 dark:text-indigo-400"></i>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white tracking-tight">İşlem Geçmişi</h1>
                    </div>
                </div>

                <?php if (!empty($basari_mesaji)): ?> <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-4 text-sm font-semibold flex items-center shadow-sm"><i data-lucide="check-circle" class="w-5 h-5 mr-2"></i> <?php echo $basari_mesaji; ?></div> <?php endif; ?>
                <?php if (!empty($hata_mesaji)): ?> <div class="bg-red-100 text-red-700 p-4 rounded-xl mb-4 text-sm font-semibold flex items-center shadow-sm"><i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i> <?php echo $hata_mesaji; ?></div> <?php endif; ?>

                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm p-4 md:p-6 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 mb-8">
                    <form action="islemler.php" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">BAŞLANGIÇ</label>
                            <input type="date" name="tarih_bas" value="<?php echo htmlspecialchars($filtre_tarih_bas); ?>" class="w-full p-2 bg-gray-50/50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">BİTİŞ</label>
                            <input type="date" name="tarih_bit" value="<?php echo htmlspecialchars($filtre_tarih_bit); ?>" class="w-full p-2 bg-gray-50/50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">KATEGORİ</label>
                            <select name="kategori_id" class="w-full p-2 bg-gray-50/50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg text-sm outline-none appearance-none focus:ring-2 focus:ring-indigo-500">
                                <option value="0">Tümü</option>
                                <?php foreach ($kategoriler as $kategori): ?>
                                    <option value="<?php echo $kategori['KategoriID']; ?>" <?php echo ($filtre_kategori == $kategori['KategoriID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($kategori['KategoriAdi']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">TÜR</label>
                            <select name="tip" class="w-full p-2 bg-gray-50/50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg text-sm outline-none appearance-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Tümü</option>
                                <option value="Gider" <?php echo ($filtre_tip == 'Gider') ? 'selected' : ''; ?>>Gider</option>
                                <option value="Gelir" <?php echo ($filtre_tip == 'Gelir') ? 'selected' : ''; ?>>Gelir</option>
                            </select>
                        </div>
                        <div class="md:col-span-3 flex gap-2">
                            <button type="submit" class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs transition-all flex justify-center items-center shadow-md active:scale-95"><i data-lucide="filter" class="w-4 h-4 mr-2"></i> Filtrele</button>
                            <a href="islemler.php" class="flex-1 py-2 text-center bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold rounded-lg text-xs hover:bg-gray-200 transition-all flex justify-center items-center">Temizle</a>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-8">
                    <div class="bg-white/80 dark:bg-gray-800/80 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 flex justify-between items-center">
                        <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Gelir</p><p class="text-xl md:text-2xl font-black text-green-600">+<?php echo number_format($toplam_gelir, 2, ',', '.'); ?> ₺</p></div>
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-xl text-green-600"><i data-lucide="trending-up" class="w-6 h-6"></i></div>
                    </div>
                    <div class="bg-white/80 dark:bg-gray-800/80 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 flex justify-between items-center">
                        <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Gider</p><p class="text-xl md:text-2xl font-black text-red-600">-<?php echo number_format($toplam_gider, 2, ',', '.'); ?> ₺</p></div>
                        <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-xl text-red-600"><i data-lucide="trending-down" class="w-6 h-6"></i></div>
                    </div>
                    <div class="bg-white/80 dark:bg-gray-800/80 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 flex justify-between items-center sm:col-span-2 lg:col-span-1">
                        <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Bakiye</p><p class="text-xl md:text-2xl font-black <?php echo ($net_bakiye >= 0) ? 'text-indigo-600' : 'text-red-600'; ?>"><?php echo number_format($net_bakiye, 2, ',', '.'); ?> ₺</p></div>
                        <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl text-indigo-600"><i data-lucide="wallet" class="w-6 h-6"></i></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden transition-all">
                    <div class="flex flex-col sm:flex-row justify-between items-center p-6 gap-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-bold text-gray-800 dark:text-white flex items-center"><i data-lucide="list" class="w-5 h-5 mr-2 text-indigo-500"></i> Liste</h3>
                        <button onclick="generatePDF()" class="w-full sm:w-auto flex justify-center items-center bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-xs shadow-lg active:scale-95 transition-all"><i data-lucide="download" class="w-4 h-4 mr-2"></i> PDF İndir</button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table id="islemler-tablosu" class="min-w-full text-left">
                            <thead class="bg-gray-50/50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">TARİH</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">KATEGORİ</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">AÇIKLAMA</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">MİKTAR</th>
                                    <th class="px-6 py-4 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <?php foreach ($islemler as $islem): ?>
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-all group">
                                        <td class="px-6 py-4 text-xs font-semibold text-gray-600 dark:text-gray-300"><?php echo date('d.m.Y', strtotime($islem['Tarih'])); ?></td>
                                        <td class="px-6 py-4"><span class="px-3 py-1 rounded-full text-[10px] font-bold <?php echo ($islem['Tip'] == 'Gelir') ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'; ?>"><?php echo htmlspecialchars($islem['KategoriAdi']); ?></span></td>
                                        <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400 max-w-[150px] truncate"><?php echo htmlspecialchars($islem['Aciklama']); ?></td>
                                        <td class="px-6 py-4 text-xs font-black <?php echo ($islem['Tip'] == 'Gelir') ? 'text-green-600' : 'text-red-600'; ?>"><?php echo ($islem['Tip'] == 'Gelir' ? '+' : '-'); ?> <?php echo number_format($islem['Miktar'], 2, ',', '.'); ?> ₺</td>
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" action="islemler.php?<?php echo http_build_query($_GET); ?>" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                                <input type="hidden" name="sil_id" value="<?php echo $islem['IslemID']; ?>">
                                                <button type="submit" class="p-2 text-gray-300 hover:text-red-600 transition-colors active:scale-90"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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

        // PDF Ayarları (KODUNUZ KORUNDU)
        let nunitoAdded = false;
        function addNunitoFont(doc) {
            if (!nunitoAdded && typeof font !== 'undefined') {
                doc.addFileToVFS("Nunito-Regular.ttf", font);
                doc.addFont("Nunito-Regular.ttf", "Nunito", "normal");
                nunitoAdded = true;
            }
            doc.setFont("Nunito", "normal");
        }

        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            addNunitoFont(doc);
            doc.setFontSize(18);
            doc.text("İşlem Geçmişi - CEP BÜYÜSÜ", 14, 20);
            const now = new Date();
            const turkceTarih = now.toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' });
            doc.setFontSize(10);
            doc.text("Tarih: " + turkceTarih, 14, 28);
            doc.autoTable({
                html: '#islemler-tablosu',
                startY: 35,
                theme: 'striped',
                styles: { font: "Nunito", fontSize: 9, cellPadding: 3 },
                headStyles: { fillColor: [79, 70, 229] },
                columnStyles: { 4: { cellWidth: 0 } },
                didParseCell: function (data) { if (data.column.index === 4) { data.cell.text = ''; } }
            });
            doc.save('islemler.pdf');
        }
    </script>
</body>
</html>