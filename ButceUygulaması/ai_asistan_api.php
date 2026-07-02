<?php

// AI ASİSTAN API 

// Hataları gizle (kullanıcıya yansımasın), logla
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Süre sınırını 10 dakikaya (600 saniye) çıkardık.
// Analiz işlemleri bazen uzun sürer, scriptin yarıda kesilmesini önler.
set_time_limit(600); 

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *"); 

session_start();

// GÜVENLİK KONTROLÜ
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(["error" => "Lütfen önce giriş yapın."]);
    exit;
}

$kullanici_id = $_SESSION['KullaniciID'];

// 2. VERİYİ AL
$json_input = file_get_contents('php://input');
$input = json_decode($json_input, true);

$userMessage = trim($input["message"] ?? "");
$mode = $input["mode"] ?? "genel"; 

if ($userMessage === "") {
    echo json_encode(["error" => "Boş mesaj gönderilemez."]);
    exit;
}


//  IP ADRESİ GÜNCELLEMESİ 
// Ekran görüntünde LM Studio'nun http://172.20.10.4:1234 adresinde çalıştığı görünüyor.
// Bağlantı hatasını önlemek için doğrudan bu IP'yi kullanıyoruz.
$apiUrl = "http://172.18.35.124:1234/v1/chat/completions"; 

// 3. BAĞLAM OLUŞTURMA (Veritabanı)
// Yapay zekaya kim olduğunu hatırlatalım
$systemPrompt = "Sen 'CEP BÜYÜSÜ' uygulamasının yardımsever, esprili ve Türkçe konuşan finans asistanısın. " .
                "Kullanıcıya ismiyle hitap et. Cevapların kısa, net ve motive edici olsun. " .
                "Asla kod veya teknik terim kullanma, bir arkadaş gibi konuş.";
$contextData = "";

if ($mode === "butce") {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=butce_yonetim_db;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Son 10 harcamayı çekip AI'a verelim
        $stmt = $pdo->prepare("
            SELECT k.KategoriAdi, i.Miktar, i.Tarih 
            FROM Islemler i 
            JOIN Kategoriler k ON i.KategoriID = k.KategoriID
            WHERE i.KullaniciID = ? AND i.Tip = 'Gider' 
            ORDER BY i.Tarih DESC LIMIT 10
        ");
        $stmt->execute([$kullanici_id]);
        $islemler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($islemler) {
            // Veriyi JSON formatında hazırlayıp promp'a ekliyoruz
            $contextData = "Kullanıcının Son Harcamaları (Analiz etmen için): " . json_encode($islemler, JSON_UNESCAPED_UNICODE);
            $systemPrompt .= " Sana verilen harcama verilerini analiz et. Gereksiz harcamaları nazikçe eleştir, tasarruf tüyoları ver.";
        } else {
            $contextData = "Kullanıcının henüz kayıtlı harcaması yok.";
        }
    } catch (Exception $e) {
        $contextData = "Veritabanı verisi alınamadı.";
    }
}

//  MESAJLARI HAZIRLA
$messages = [
    ["role" => "system", "content" => $systemPrompt . " " . $contextData],
    ["role" => "user", "content" => $userMessage . " (Lütfen Türkçe cevap ver)"]
];

//  LM STUDIO'YA BAĞLAN (CURL)
$ch = curl_init($apiUrl);

$payload = json_encode([
    "model" => "local-model", // Model adı fark etmez
    "messages" => $messages,
    "temperature" => 0.7,
    "max_tokens" => -1,
    "stream" => false
]);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Content-Length: " . strlen($payload)
    ],

    // Bunu 600 saniye (10 dakika) yaparak "Operation timed out" hatasını çözüyoruz.
    CURLOPT_TIMEOUT => 600, 
    CURLOPT_CONNECTTIMEOUT => 10, 
    CURLOPT_FAILONERROR => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);

curl_close($ch);

// HATA YAKALAMA (Kullanıcıya net bilgi verelim)
if ($curlErrno) {
    echo json_encode([
        "error" => "Bağlantı Sağlanamadı! ($curlError). Lütfen LM Studio'nun açık olduğundan, IP adresinin doğru olduğundan ($apiUrl) emin ol."
    ]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        "error" => "LM Studio Hatası (Kod: $httpCode). Model yüklenmemiş olabilir. Yanıt: " . substr($response, 0, 100) . "..."
    ]);
    exit;
}

//  CEVABI TEMİZLE VE GÖNDER
$decoded = json_decode($response, true);

if (isset($decoded['choices'][0]['message']['content'])) {
    $rawContent = $decoded['choices'][0]['message']['content'];
    // Düşünme balonlarını temizle (<think>...</think>)
    $cleanContent = preg_replace('/<think>.*?<\/think>/s', '', $rawContent);
    
    echo json_encode([
        "choices" => [
            ["message" => ["content" => trim($cleanContent)]]
        ]
    ]);
} else {
    echo json_encode(["error" => "Yapay zeka boş cevap döndürdü. Lütfen tekrar dene."]);
}
?>