<?php
$hata_mesaji = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $sifre = $_POST['password'] ?? '';

    if (empty($email) || empty($sifre)) {
        $hata_mesaji = "Lütfen e-posta ve şifrenizi giriniz.";
    } else {
        $sunucu_adi = "localhost";
        $kullanici_adi = "root"; 
        $sifre_db = "";         
        $veritabani_adi = "butce_yonetim_db";

        try {
            $pdo = new PDO("mysql:host=$sunucu_adi;dbname=$veritabani_adi;charset=utf8mb4", $kullanici_adi, $sifre_db);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT KullaniciID, AdSoyad, SifreHash FROM Kullanicilar WHERE Email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($kullanici) {
                if (password_verify($sifre, $kullanici['SifreHash'])) {
                    session_start();
                    $_SESSION['loggedin'] = true;
                    $_SESSION['KullaniciID'] = $kullanici['KullaniciID'];
                    $_SESSION['AdSoyad'] = $kullanici['AdSoyad'];
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $hata_mesaji = "Hatalı şifre girdiniz.";
                }
            } else {
                $hata_mesaji = "Bu e-posta adresi ile kayıtlı bir kullanıcı bulunamadı.";
            }
        } catch (PDOException $e) {
            $hata_mesaji = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Giriş Yap | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        :root { font-family: 'Inter', sans-serif; }

        body {
            /* Mobil tarayıcı adres çubuğu sorununu önlemek için */
            min-height: 100vh;
            min-height: -webkit-fill-available;
        }

        .blob {
            position: absolute;
            filter: blur(40px);
            z-index: -1;
            opacity: 0.4;
            animation: float 8s ease-in-out infinite;
            pointer-events: none; /* Tıklamaları engellemek için */
        }
        
        /* Mobilde performansı artırmak için animasyonu basitleştirdik */
        @keyframes float {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(20px, -30px) scale(1.05); }
        }

        .fade-in-up { 
            animation: fadeInUp 0.6s ease-out forwards; 
            opacity: 0; 
            transform: translateY(15px); 
        }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }

        /* Mobil cihazlarda input focus olduğunda zoom yapmasını engeller */
        input { font-size: 16px !important; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center relative overflow-x-hidden p-4">

    <div class="blob bg-indigo-200 w-48 h-48 md:w-80 md:h-80 rounded-full top-0 left-0 -translate-x-1/4 -translate-y-1/4"></div>
    <div class="blob bg-purple-200 w-64 h-64 md:w-96 md:h-96 rounded-full bottom-0 right-0 translate-x-1/4 translate-y-1/4"></div>

    <main class="w-full max-w-[400px] fade-in-up">
        
        <div class="bg-white/90 backdrop-blur-xl p-6 sm:p-8 rounded-3xl shadow-[0_20px_50px_rgba(79,70,229,0.1)] border border-white/60">
            
            <div class="text-center mb-6 sm:mb-8">
                <a href="ilksayfa.html" class="inline-flex items-center justify-center p-3 bg-indigo-50 rounded-2xl mb-4 transition-transform active:scale-95 group">
                    <i data-lucide="graduation-cap" class="w-8 h-8 text-indigo-600 transition-transform group-hover:rotate-12"></i>
                </a>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">Tekrar Hoş Geldin!</h2>
                <p class="text-gray-500 text-xs sm:text-sm mt-1.5">Finansal sihrine kaldığın yerden devam et.</p>
            </div>

            <?php if (!empty($hata_mesaji)): ?>
                <div class="flex items-start p-3.5 mb-5 text-sm text-red-800 border border-red-100 rounded-xl bg-red-50/50 animate-shake" role="alert">
                    <i data-lucide="alert-circle" class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0"></i>
                    <span class="font-medium leading-tight"><?php echo htmlspecialchars($hata_mesaji); ?></span>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4 sm:space-y-5">
                
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest ml-1">E-posta</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                            <i data-lucide="mail" class="h-5 w-5"></i>
                        </div>
                        <input type="email" name="email" required 
                               class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 bg-gray-50/50 focus:bg-white transition-all outline-none text-sm placeholder:text-gray-300" 
                               placeholder="ornek@ogrenci.edu.tr">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest ml-1">Şifre</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                            <i data-lucide="lock" class="h-5 w-5"></i>
                        </div>
                        <input type="password" name="password" required 
                               class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 bg-gray-50/50 focus:bg-white transition-all outline-none text-sm placeholder:text-gray-300" 
                               placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 rounded-xl shadow-lg shadow-indigo-200 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] transition-all duration-200">
                    Giriş Yap
                    <i data-lucide="log-in" class="ml-2 w-4 h-4"></i>
                </button>

            </form>

            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500">
                    Henüz hesabın yok mu? 
                    <a href="kaydol.php" class="font-bold text-indigo-600 hover:text-indigo-700 transition-colors inline-block ml-1">Hemen Kaydol</a>
                </p>
            </div>

        </div>
        
        <div class="text-center mt-8 text-[11px] text-gray-400 font-medium uppercase tracking-widest">
            &copy; <?php echo date('Y'); ?> CEP BÜYÜSÜ &bull; ÖĞRENCİ DOSTU
        </div>

    </main>

    <script>
        // İkonları oluştur
        lucide.createIcons();
    </script>
</body>
</html>