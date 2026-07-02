<?php
// GÜVENLİK VE OTURUM
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: giris.php");
    exit;
}

$kullanici_id = $_SESSION['KullaniciID'];
$ad_soyad_session = $_SESSION['AdSoyad'] ?? 'Misafir';

// VERİTABANI BAĞLANTISI
$sunucu_adi = "localhost"; $kullanici_adi = "root"; $sifre_db = ""; $veritabani_adi = "butce_yonetim_db";
$mesaj = ""; $mesaj_turu = ""; 

try {
    $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT AdSoyad, Email, AylikGelir, SifreHash FROM Kullanicilar WHERE KullaniciID = :id");
    $stmt->execute(['id' => $kullanici_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // PROFİL GÜNCELLEME
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
        $yeni_ad = trim($_POST['ad_soyad']);
        $yeni_email = trim($_POST['email']);
        $yeni_gelir = floatval($_POST['aylik_gelir']);

        if ($yeni_ad && $yeni_email) {
            $updateStmt = $pdo->prepare("UPDATE Kullanicilar SET AdSoyad = ?, Email = ?, AylikGelir = ? WHERE KullaniciID = ?");
            if ($updateStmt->execute([$yeni_ad, $yeni_email, $yeni_gelir, $kullanici_id])) {
                $mesaj = "Profil bilgileriniz başarıyla güncellendi.";
                $mesaj_turu = "basari";
                $_SESSION['AdSoyad'] = $yeni_ad;
                $ad_soyad_session = $yeni_ad;
                $user['AdSoyad'] = $yeni_ad; $user['Email'] = $yeni_email; $user['AylikGelir'] = $yeni_gelir;
            } else {
                $mesaj = "Hata oluştu."; $mesaj_turu = "hata";
            }
        }
    }

    // ŞİFRE DEĞİŞTİRME
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
        $eski_sifre = $_POST['current_password'];
        $yeni_sifre = $_POST['new_password'];
        $yeni_sifre_tekrar = $_POST['confirm_password'];

        if (password_verify($eski_sifre, $user['SifreHash'])) {
            if ($yeni_sifre === $yeni_sifre_tekrar && strlen($yeni_sifre) >= 6) {
                $yeni_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE Kullanicilar SET SifreHash = ? WHERE KullaniciID = ?")->execute([$yeni_hash, $kullanici_id]);
                $mesaj = "Şifreniz değiştirildi."; $mesaj_turu = "basari";
            } else {
                $mesaj = "Şifreler uyuşmuyor veya çok kısa."; $mesaj_turu = "hata";
            }
        } else {
            $mesaj = "Mevcut şifre hatalı."; $mesaj_turu = "hata";
        }
    }
} catch (PDOException $e) { $mesaj = "Hata: " . $e->getMessage(); $mesaj_turu = "hata"; }
?>

<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script> tailwind.config = { darkMode: 'class' } </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        .blob { position: absolute; filter: blur(50px); z-index: -1; opacity: 0.4; animation: float 8s ease-in-out infinite; }
        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, -20px) scale(1.1); } 100% { transform: translate(0, 0) scale(1); } }
        .glass-sidebar { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.5); }
        .dark .glass-sidebar { background: rgba(31, 41, 55, 0.9); }
        .fade-in { animation: fadeIn 0.6s ease-out forwards; opacity: 0; transform: translateY(10px); }
        @keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }

        /* ARKA PLANLAR (Kesin Değişim İçin !important) */
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

    <div class="blob bg-indigo-100 w-96 h-96 rounded-full top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
    <div class="blob bg-pink-100 w-96 h-96 rounded-full bottom-0 right-0 translate-x-1/2 translate-y-1/2"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 glass-sidebar z-50 flex flex-col justify-between h-full shadow-lg border-r border-gray-200/50 dark:border-gray-700 md:relative md:translate-x-0 hidden-mobile">
        <div class="h-20 flex items-center justify-between px-6 border-b border-gray-200/50 dark:border-gray-700">
            <a href="dashboard.php" class="flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 font-extrabold text-xl">
                <i data-lucide="graduation-cap"></i><span>CEP BÜYÜSÜ</span>
            </a>
            <button class="md:hidden text-gray-500" onclick="toggleSidebar()"><i data-lucide="x"></i></button>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto text-sm font-medium">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="layout-grid" class="w-5 h-5 mr-3"></i> Ana Panel</a>
            <a href="ekle.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="plus-circle" class="w-5 h-5 mr-3"></i> Hızlı Ekle</a>
            <a href="islemler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="list" class="w-5 h-5 mr-3"></i> İşlemler</a>
            <a href="raporlar.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="pie-chart" class="w-5 h-5 mr-3"></i> Raporlar</a>
            <a href="butceler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="shield-check" class="w-5 h-5 mr-3"></i> Bütçe Limitleri</a>
            <a href="hedefler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="target" class="w-5 h-5 mr-3"></i> Tasarruf Hedefleri</a>
            <a href="ai_asistan.php" class="flex items-center px-4 py-3 mt-4 text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg transform hover:scale-[1.02] transition-all"><i data-lucide="bot" class="w-5 h-5 mr-3"></i> AI Asistan</a>
            <a href="ayarlar.php" class="flex items-center px-4 py-3 mt-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-xl font-bold shadow-sm transition-all"><i data-lucide="settings" class="w-5 h-5 mr-3"></i> Ayarlar</a>

            <div class="pt-4 mt-2 border-t border-gray-200/50 dark:border-gray-700">
                <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Fırsatlar</p>
                <a href="https://www.kariyer.net/is-ilanlari/part+time-2" target="_blank" class="flex items-center px-4 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-xl transition-all"><i data-lucide="briefcase" class="w-4 h-4 mr-3"></i> Part Time İş</a>
                <a href="https://www.linkedin.com/company/staj-ve-burs-ilanlari" target="_blank" class="flex items-center px-4 py-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition-all"><i data-lucide="graduation-cap" class="w-4 h-4 mr-3"></i> Staj & Burs</a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200/50 dark:border-gray-700">
            <a href="cikis.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors font-bold text-xs"><i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Çıkış Yap</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="h-20 bg-white/60 dark:bg-gray-800/60 backdrop-blur-md border-b border-gray-200/50 dark:border-gray-700 flex justify-between items-center px-4 md:px-8 z-10 transition-colors">
            <div class="flex items-center">
                <button class="md:hidden p-2 mr-3 bg-white dark:bg-gray-700 rounded-lg shadow-sm" onclick="toggleSidebar()"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <h2 class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white">Hesap Ayarları ⚙️</h2>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300"><i id="theme-icon" data-lucide="moon" class="w-5 h-5"></i></button>
                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm flex-shrink-0"><?php echo strtoupper(substr($ad_soyad_session, 0, 1)); ?></div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 relative">
            <div class="max-w-4xl mx-auto fade-in space-y-6 md:space-y-8 pb-20">

                <?php if ($mesaj): ?>
                    <div class="p-4 rounded-xl border flex items-center text-sm <?php echo $mesaj_turu == 'basari' ? 'bg-green-50 border-green-200 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/30 dark:text-red-300'; ?>">
                        <i data-lucide="<?php echo $mesaj_turu == 'basari' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                        <span><?php echo $mesaj; ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm p-6 md:p-8 rounded-3xl shadow-lg border border-gray-100 dark:border-gray-700 transition-all">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center uppercase tracking-tight"><i data-lucide="palette" class="w-6 h-6 mr-3 text-purple-500"></i> Görünüm & Tema</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <button onclick="setAppBackground('default', event)" class="theme-btn p-3 rounded-2xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 hover:ring-2 hover:ring-indigo-500 transition-all text-xs font-bold text-gray-700 dark:text-gray-200 flex flex-col items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-gray-200 border-2 border-white shadow-sm"></div> Klasik
                        </button>
                        <button onclick="setAppBackground('bg-gradient-ocean', event)" class="theme-btn p-3 rounded-2xl border border-blue-200 bg-blue-50 dark:bg-sky-900/30 hover:ring-2 hover:ring-blue-500 transition-all text-xs font-bold text-blue-800 dark:text-blue-200 flex flex-col items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-blue-400 border-2 border-white shadow-sm"></div> Okyanus
                        </button>
                        <button onclick="setAppBackground('bg-gradient-sunset', event)" class="theme-btn p-3 rounded-2xl border border-orange-200 bg-orange-50 dark:bg-orange-900/30 hover:ring-2 hover:ring-orange-500 transition-all text-xs font-bold text-orange-800 dark:text-orange-200 flex flex-col items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-orange-400 border-2 border-white shadow-sm"></div> Sunset
                        </button>
                        <button onclick="setAppBackground('bg-gradient-lavender', event)" class="theme-btn p-3 rounded-2xl border border-purple-200 bg-purple-50 dark:bg-purple-900/30 hover:ring-2 hover:ring-purple-500 transition-all text-xs font-bold text-purple-800 dark:text-purple-200 flex flex-col items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-purple-400 border-2 border-white shadow-sm"></div> Lavanta
                        </button>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm p-6 md:p-8 rounded-3xl shadow-lg border border-gray-100 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center uppercase tracking-tight"><i data-lucide="user" class="w-6 h-6 mr-3 text-indigo-500"></i> Profil Bilgileri</h3>
                    <form method="POST" action="ayarlar.php" class="space-y-6">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <div><label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Ad Soyad</label>
                                <input type="text" name="ad_soyad" value="<?php echo htmlspecialchars($user['AdSoyad']); ?>" required class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700 dark:text-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                            </div>
                            <div><label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">E-posta</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700 dark:text-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Aylık Ortalama Gelir (₺)</label>
                                <input type="number" step="0.01" name="aylik_gelir" value="<?php echo htmlspecialchars($user['AylikGelir']); ?>" class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700 dark:text-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                            </div>
                        </div>
                        <button type="submit" class="w-full md:w-auto px-10 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-lg active:scale-95 transition-all">GÜNCELLE</button>
                    </form>
                </div>

                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm p-6 md:p-8 rounded-3xl shadow-lg border border-gray-100 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center uppercase tracking-tight"><i data-lucide="lock" class="w-6 h-6 mr-3 text-red-500"></i> Şifre Değiştir</h3>
                    <form method="POST" action="ayarlar.php" class="space-y-6">
                        <input type="hidden" name="change_password" value="1">
                        <div><label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Mevcut Şifre</label>
                            <input type="password" name="current_password" required placeholder="•••••••" class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700 dark:text-white focus:ring-4 focus:ring-red-500/10 outline-none">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <div><label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Yeni Şifre</label>
                                <input type="password" name="new_password" required placeholder="En az 6 karakter" class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700 dark:text-white focus:ring-4 focus:ring-red-500/10 outline-none">
                            </div>
                            <div><label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Tekrar</label>
                                <input type="password" name="confirm_password" required placeholder="•••••••" class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700 dark:text-white focus:ring-4 focus:ring-red-500/10 outline-none">
                            </div>
                        </div>
                        <button type="submit" class="w-full md:w-auto px-10 py-3.5 bg-gray-900 dark:bg-gray-700 hover:bg-black text-white font-black rounded-2xl shadow-lg active:scale-95 transition-all">ŞİFREYİ DEĞİŞTİR</button>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <a href="ai_asistan.php" class="fixed bottom-6 right-6 z-40 flex items-center justify-center">
        <span class="absolute h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
        <div class="relative h-12 w-12 flex items-center justify-center rounded-full bg-indigo-600 shadow-xl shadow-indigo-500/50"><i data-lucide="bot" class="h-6 w-6 text-white"></i></div>
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

        function setAppBackground(bgClass, e) {
            // Sınıfları temizle
            document.body.classList.remove('bg-gradient-ocean', 'bg-gradient-sunset', 'bg-gradient-forest', 'bg-gradient-lavender');
            
            // Yeni sınıfı ekle
            if (bgClass !== 'default') document.body.classList.add(bgClass);
            
            // Kaydet
            localStorage.setItem('app-background', bgClass);
            
            // Buton stilini güncelle
            document.querySelectorAll('.theme-btn').forEach(btn => btn.classList.remove('ring-2', 'ring-indigo-500', 'ring-offset-2'));
            if(e) e.currentTarget.classList.add('ring-2', 'ring-indigo-500', 'ring-offset-2');
        }

        // Başlangıçta arka planı yükle
        const currentBg = localStorage.getItem('app-background');
        if(currentBg && currentBg !== 'default') document.body.classList.add(currentBg);
    </script>
</body>
</html>