<?php
// PHP KAYIT İŞLEMLERİ (KODUNUZ TAMAMEN KORUNDU)
$mesaj = '';
$mesaj_tipi = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sifre = $_POST['password'] ?? '';
    $aylik_gelir = floatval($_POST['aylik_gelir'] ?? 0.00);

    if (empty($ad_soyad) || empty($email) || empty($sifre)) {
        $mesaj = "Lütfen tüm zorunlu alanları doldurunuz.";
        $mesaj_tipi = 'hata';
    } elseif (strlen($sifre) < 6) {
        $mesaj = "Şifre en az 6 karakter olmalıdır.";
        $mesaj_tipi = 'hata';
    } else {
        $sunucu_adi = "localhost";
        $kullanici_adi = "root"; 
        $sifre_db = "";         
        $veritabani_adi = "butce_yonetim_db";

        try {
            $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT KullaniciID FROM Kullanicilar WHERE Email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $mesaj = "Bu e-posta adresi zaten kayıtlıdır.";
                $mesaj_tipi = 'hata';
            } else {
                $hashed_sifre = password_hash($sifre, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO Kullanicilar (AdSoyad, Email, SifreHash, AylikGelir) VALUES (:ad_soyad, :email, :sifre_hash, :aylik_gelir)");
                $stmt->bindParam(':ad_soyad', $ad_soyad);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':sifre_hash', $hashed_sifre);
                $stmt->bindParam(':aylik_gelir', $aylik_gelir);
                
                if ($stmt->execute()) {
                    $mesaj = "Hesabınız başarıyla oluşturuldu! Yönlendiriliyorsunuz...";
                    $mesaj_tipi = 'basari';
                    header("Refresh: 2; URL=giris.php");
                } else {
                    $mesaj = "Kayıt sırasında beklenmedik bir hata oluştu.";
                    $mesaj_tipi = 'hata';
                }
            }
        } catch (PDOException $e) {
            $mesaj = "Veritabanı hatası: İşlem gerçekleştirilemedi.";
            $mesaj_tipi = 'hata';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Aramıza Katıl | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        :root { font-family: 'Inter', sans-serif; }

        body {
            min-height: 100vh;
            min-height: -webkit-fill-available;
        }

        .blob {
            position: absolute;
            filter: blur(40px);
            z-index: -1;
            opacity: 0.4;
            animation: float 8s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes float {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(15px, -25px) scale(1.05); }
        }

        .fade-in-up { animation: fadeInUp 0.6s ease-out forwards; opacity: 0; transform: translateY(15px); }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }

        /* Mobil klavye açıldığında tasarımı korur ve input focus'ta zoomu engeller */
        input { font-size: 16px !important; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center relative overflow-x-hidden py-8 px-4">

    <div class="blob bg-purple-200 w-48 h-48 md:w-80 md:h-80 rounded-full top-0 left-0 -translate-x-1/4 -translate-y-1/4"></div>
    <div class="blob bg-indigo-200 w-64 h-64 md:w-96 md:h-96 rounded-full bottom-0 right-0 translate-x-1/4 translate-y-1/4"></div>

    <main class="w-full max-w-[420px] fade-in-up">
        
        <div class="bg-white/90 backdrop-blur-xl p-6 sm:p-8 rounded-3xl shadow-[0_20px_50px_rgba(79,70,229,0.1)] border border-white/60">
            
            <div class="text-center mb-6 sm:mb-8">
                <a href="ilksayfa.html" class="inline-flex items-center justify-center p-3 bg-indigo-50 rounded-2xl mb-4 transition-transform active:scale-95">
                    <i data-lucide="graduation-cap" class="w-8 h-8 text-indigo-600"></i>
                </a>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">Hesap Oluştur</h2>
                <p class="text-gray-500 text-xs sm:text-sm mt-1.5">Bütçeni yönetmeye ve hayallerine ulaşmaya başla.</p>
            </div>

            <?php if (!empty($mesaj)): ?>
                <div class="flex items-start p-3.5 mb-5 text-sm rounded-xl leading-tight <?php echo $mesaj_tipi === 'basari' ? 'text-green-800 bg-green-50 border border-green-100' : 'text-red-800 bg-red-50 border border-red-100'; ?>" role="alert">
                    <i data-lucide="<?php echo $mesaj_tipi === 'basari' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0"></i>
                    <span class="font-medium"><?php echo htmlspecialchars($mesaj); ?></span>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4 sm:space-y-5">
                
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest ml-1">Ad Soyad</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                            <i data-lucide="user" class="h-4 w-4 sm:h-5 sm:w-5"></i>
                        </div>
                        <input type="text" name="ad_soyad" required 
                               class="block w-full pl-11 pr-4 py-2.5 sm:py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 bg-gray-50/50 focus:bg-white transition-all outline-none text-sm" 
                               placeholder="Adınız Soyadınız" value="<?php echo htmlspecialchars($ad_soyad ?? ''); ?>">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest ml-1">E-posta</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                            <i data-lucide="mail" class="h-4 w-4 sm:h-5 sm:w-5"></i>
                        </div>
                        <input type="email" name="email" required 
                               class="block w-full pl-11 pr-4 py-2.5 sm:py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 bg-gray-50/50 focus:bg-white transition-all outline-none text-sm" 
                               placeholder="ornek@ogrenci.edu.tr" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest ml-1">Şifre</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                            <i data-lucide="lock" class="h-4 w-4 sm:h-5 sm:w-5"></i>
                        </div>
                        <input type="password" name="password" required 
                               class="block w-full pl-11 pr-4 py-2.5 sm:py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 bg-gray-50/50 focus:bg-white transition-all outline-none text-sm" 
                               placeholder="En az 6 karakter">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest ml-1">Ortalama Aylık Gelir</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 font-bold group-focus-within:text-indigo-500 transition-colors text-sm">₺</div>
                        <input type="number" step="0.01" name="aylik_gelir" 
                               class="block w-full pl-11 pr-4 py-2.5 sm:py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 bg-gray-50/50 focus:bg-white transition-all outline-none text-sm" 
                               placeholder="Örn: 5000" value="<?php echo htmlspecialchars($aylik_gelir ?? ''); ?>">
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg shadow-indigo-100 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] transition-all duration-200">
                    Hesabımı Oluştur
                </button>

            </form>

            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500">
                    Zaten bir hesabın var mı? 
                    <a href="giris.php" class="font-bold text-indigo-600 hover:text-indigo-700 transition-colors ml-1">Giriş Yap</a>
                </p>
            </div>

        </div>
        
        <div class="text-center mt-8 text-[11px] text-gray-400 font-medium uppercase tracking-widest">
            &copy; <?php echo date('Y'); ?> CEP BÜYÜSÜ &bull; TÜM HAKLARI SAKLIDIR
        </div>

    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>