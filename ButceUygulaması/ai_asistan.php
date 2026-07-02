<?php
// GÜVENLİK VE OTURUM (KODUNUZ KORUNDU)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: giris.php");
    exit;
}

$kullanici_id = $_SESSION['KullaniciID'];
$ad_soyad = htmlspecialchars($_SESSION['AdSoyad'] ?? 'Misafir');

$toplam_gelir = "3000"; 
try {
    $pdo = new PDO("mysql:host=localhost;dbname=butce_yonetim_db;charset=utf8mb4", "root", "");
    $stmt = $pdo->prepare("SELECT SUM(Miktar) FROM Islemler WHERE KullaniciID = ? AND Tip = 'Gelir' AND Tarih >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute([$kullanici_id]);
    $sonuc = $stmt->fetchColumn();
    if($sonuc > 0) { $toplam_gelir = number_format($sonuc, 0, '', ''); }
} catch (Exception $e) { }
?>

<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AI Asistan | CEP BÜYÜSÜ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script> tailwind.config = { darkMode: 'class' } </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        :root { font-family: 'Inter', sans-serif; }
        
        body { min-height: 100vh; min-height: -webkit-fill-available; }
        .blob { position: absolute; filter: blur(50px); z-index: -1; opacity: 0.4; animation: float 8s ease-in-out infinite; }
        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, -20px) scale(1.1); } 100% { transform: translate(0, 0) scale(1); } }
        .glass-sidebar { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.5); }
        .dark .glass-sidebar { background: rgba(31, 41, 55, 0.9); }
        .fade-in { animation: fadeIn 0.6s ease-out forwards; opacity: 0; transform: translateY(10px); }
        @keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }
        .chat-scroll::-webkit-scrollbar { width: 4px; }
        .chat-scroll::-webkit-scrollbar-thumb { background-color: rgba(156, 163, 175, 0.3); border-radius: 10px; }

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

    <div class="blob bg-purple-200 w-64 h-64 md:w-96 md:h-96 rounded-full top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
    <div class="blob bg-indigo-200 w-64 h-64 md:w-96 md:h-96 rounded-full bottom-0 right-0 translate-x-1/2 translate-y-1/2"></div>

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
            <a href="hedefler.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="target" class="w-5 h-5 mr-3"></i> Tasarruf Hedefleri</a>
            <a href="ai_asistan.php" class="flex items-center px-4 py-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-xl font-semibold shadow-sm transition-all"><i data-lucide="bot" class="w-5 h-5 mr-3"></i> AI Asistan</a>
            <a href="ayarlar.php" class="flex items-center px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700 hover:text-indigo-600 rounded-xl transition-all"><i data-lucide="settings" class="w-5 h-5 mr-3"></i> Ayarlar</a>

            <div class="pt-4 mt-2 border-t border-gray-200/50 dark:border-gray-700 font-bold uppercase tracking-widest text-[10px]">
                <p class="px-4 text-gray-400 mb-2">Fırsatlar</p>
                <a href="https://www.kariyer.net/is-ilanlari/part+time-2" target="_blank" class="flex items-center px-4 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-xl transition-all group">
                    <i data-lucide="briefcase" class="w-4 h-4 mr-3"></i> Part Time İş
                </a>
                <a href="https://www.linkedin.com/company/staj-ve-burs-ilanlari" target="_blank" class="flex items-center px-4 py-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition-all group">
                    <i data-lucide="graduation-cap" class="w-4 h-4 mr-3"></i> Staj & Burs
                </a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200/50 dark:border-gray-700">
            <a href="cikis.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors font-bold text-xs"><i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Çıkış Yap</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="h-16 md:h-20 bg-white/60 dark:bg-gray-800/60 backdrop-blur-md border-b border-gray-200/50 dark:border-gray-700 flex justify-between items-center px-4 md:px-8 z-10 transition-colors">
            <div class="flex items-center">
                <button class="md:hidden p-2 mr-3 bg-white dark:bg-gray-700 rounded-lg shadow-sm" onclick="toggleSidebar()"><i data-lucide="menu" class="w-6 h-6"></i></button>
                <h2 class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white">AI Asistan 🤖</h2>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300"><i id="theme-icon" data-lucide="moon" class="w-5 h-5"></i></button>
                <div class="h-8 w-8 md:h-10 md:w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm flex-shrink-0"><?php echo strtoupper(substr($ad_soyad, 0, 1)); ?></div>
            </div>
        </header>

        <main class="flex-1 flex flex-col p-3 md:p-6 overflow-hidden relative">
            <div class="max-w-4xl mx-auto w-full h-full flex flex-col fade-in">
                
                <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button onclick="quickPrompt('butce','Son 30 gündeki harcamalarımı analiz et ve tasarruf önerileri ver.')" 
                            class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800 text-[11px] sm:text-xs font-bold hover:bg-green-100 transition-all shadow-sm">
                        <i data-lucide="wallet" class="w-3 h-3"></i> Bütçe Analizi
                    </button>
                    <button onclick="quickPrompt('genel','Gelirim <?php echo $toplam_gelir; ?> TL. Öğrenci için ideal harcama dağılımı önerir misin?')" 
                            class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800 text-[11px] sm:text-xs font-bold hover:bg-blue-100 transition-all shadow-sm">
                        <i data-lucide="pie-chart" class="w-3 h-3"></i> Harçlık Planı
                    </button>
                </div>

                <div class="flex-1 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-2xl md:rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 flex flex-col overflow-hidden mb-2">
                    <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white/50 dark:bg-gray-800/50">
                        <h1 class="text-xs font-bold text-gray-500 uppercase tracking-widest flex items-center gap-2"><i data-lucide="message-square" class="w-4 h-4 text-indigo-500"></i> Sohbet</h1>
                        <span id="statusText" class="text-[10px] font-bold text-indigo-500 animate-pulse hidden uppercase">Asistan yazıyor...</span>
                    </div>

                    <div id="chat-box" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50/30 dark:bg-gray-900/30 chat-scroll"></div>

                    <div class="p-3 md:p-4 border-t border-gray-100 dark:border-gray-700 bg-white/50 dark:bg-gray-800/50">
                        <div class="flex gap-2 items-center">
                            <select id="modeSelect" class="hidden sm:block w-32 text-[11px] font-bold uppercase rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="genel">Genel</option>
                                <option value="butce">Bütçe</option>
                            </select>
                            <div class="flex-1 relative">
                                <input id="messageInput" 
                                       class="w-full pl-4 pr-12 py-3 md:py-3.5 text-sm rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all shadow-inner" 
                                       placeholder="Bir şeyler sor..."
                                       onkeypress="if(event.key === 'Enter') sendMessage()">
                                <button onclick="sendMessage()" 
                                        class="absolute right-1.5 top-1/2 transform -translate-y-1/2 p-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition-all shadow-md active:scale-90">
                                    <i data-lucide="send" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
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
        
        function applySavedSettings() {
            // Dark Mode
            const theme = localStorage.getItem('color-theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
                themeIcon.setAttribute('data-lucide', 'sun');
            } else {
                document.documentElement.classList.remove('dark');
                themeIcon.setAttribute('data-lucide', 'moon');
            }

            // Arka Plan
            const currentBg = localStorage.getItem('app-background');
            const classesToRemove = ['bg-gradient-ocean', 'bg-gradient-sunset', 'bg-gradient-forest', 'bg-gradient-lavender'];
            document.body.classList.remove(...classesToRemove);
            if(currentBg && currentBg !== 'default') {
                document.body.classList.add(currentBg);
            }
            lucide.createIcons();
        }
        
        applySavedSettings();

        themeBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('color-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            applySavedSettings();
        });

        // Chat Logic
        const chatBox = document.getElementById('chat-box');
        const statusText = document.getElementById('statusText');
        
        function addMessage(text, from = 'ai') {
            const wrap = document.createElement('div');
            wrap.className = "flex " + (from === 'user' ? "justify-end" : "justify-start") + " fade-in";
            const bubble = document.createElement('div');
            const baseClass = "px-4 py-2.5 rounded-2xl max-w-[90%] md:max-w-[80%] text-sm leading-relaxed shadow-sm whitespace-pre-wrap ";
            if (from === 'user') {
                bubble.className = baseClass + "bg-indigo-600 text-white rounded-br-none";
            } else {
                bubble.className = baseClass + "bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 border border-gray-100 dark:border-gray-600 rounded-bl-none";
            }
            bubble.textContent = text;
            wrap.appendChild(bubble);
            chatBox.appendChild(wrap);
            chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
        }

        addMessage("Merhaba! 👋 Ben yapay zeka asistanınım. Bütçeni analiz edebilir veya sana tasarruf önerileri verebilirim. Nasıl yardımcı olabilirim?", "ai");

        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const modeSelect = document.getElementById('modeSelect');
            const text = input.value.trim();
            if (!text) return;
            const mode = modeSelect.value || "genel";
            addMessage(text, 'user');
            input.value = "";
            statusText.classList.remove('hidden');

            try {
                const res = await fetch('ai_asistan_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: text, mode: mode })
                });
                const data = await res.json();
                statusText.classList.add('hidden');
                if (data.error) {
                    addMessage("⚠️ Hata: Sunucuya ulaşılamadı. LM Studio'nun açık olduğundan emin ol.", "ai");
                    return;
                }
                const aiText = data.choices?.[0]?.message?.content || "Cevap alınamadı.";
                addMessage(aiText, 'ai');
            } catch (e) {
                statusText.classList.add('hidden');
                addMessage("🚫 Bağlantı hatası! Yapay zeka motoruna ulaşılamadı.", "ai");
            }
        }

        function quickPrompt(mode, text) {
            document.getElementById('modeSelect').value = mode;
            document.getElementById('messageInput').value = text;
            sendMessage();
        }
    </script>
</body>
</html>