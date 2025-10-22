<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    header("Location: index.php");
    exit;
}

include "../config.php";
date_default_timezone_set("Asia/Jakarta");

// === Ambil Token WA Sidobe ===
$q = mysqli_query($conn, "SELECT key_wa_sidobe FROM profil_sekolah WHERE id=1");
if (!$q) {
    die("<h3 style='color:red;'>❌ Query gagal:</h3><pre>" . mysqli_error($conn) . "</pre>");
}

$row = mysqli_fetch_assoc($q);
if (!$row || empty($row['key_wa_sidobe'])) {
    die("<h3 style='color:red;'>⚠️ Token WA Sidobe belum diset di tabel profil_sekolah (id=1).</h3>");
}
$token = trim($row['key_wa_sidobe']); // X-Secret-Key

// === Proses Kirim WA ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pesan'])) {
    $pesan = trim($_POST['pesan']);
    $delay = isset($_POST['delay']) ? intval($_POST['delay']) : rand(2, 5);

    $siswaQ = mysqli_query($conn, "SELECT nama, kelas, no_wa FROM siswa WHERE no_wa <> ''");
    if (!$siswaQ) {
        die("<h3 style='color:red;'>❌ Query siswa gagal:</h3><pre>" . mysqli_error($conn) . "</pre>");
    }

    ob_implicit_flush(true);
    ob_end_flush();

    $count = 0;
    while ($s = mysqli_fetch_assoc($siswaQ)) {
        $count++;

        $pesanFinal = str_replace(
            ["{nama}", "{kelas}"],
            [$s['nama'], $s['kelas']],
            $pesan
        );

        $nomor = preg_replace('/[^0-9]/', '', $s['no_wa']);
        if (substr($nomor, 0, 1) === '0') {
            $nomor = '+62' . substr($nomor, 1);
        } elseif (substr($nomor, 0, 2) !== '62') {
            $nomor = '+62' . $nomor;
        } else {
            $nomor = '+' . $nomor;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.sidobe.com/wa/v1/send-message",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "X-Secret-Key: $token",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "phone" => $nomor,
                "message" => $pesanFinal
            ])
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo "❌ Gagal kirim ke {$s['nama']} ($nomor): $err<br>";
        } else {
            $data = json_decode($response, true);
            if (!empty($data['is_success']) && strtolower($data['data']['status'] ?? '') === 'success') {
                echo "✅ Pesan terkirim ke {$s['nama']} ($nomor)<br>";
            } else {
                $msg = json_encode($data, JSON_UNESCAPED_UNICODE);
                echo "⚠️ Gagal kirim ke {$s['nama']} ($nomor): $msg<br>";
            }
        }

        flush();
        sleep($delay);
    }

    echo "<hr><b>Selesai! Total pesan diproses: $count</b>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Kirim WA Massal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* Spinner CSS */
    .spinner {
      display: inline-block;
      width: 2rem;
      height: 2rem;
      border: 3px solid rgba(0, 0, 0, 0.1);
      border-top: 3px solid #0d6efd;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-right: 10px;
    }

    @keyframes spin {
      100% { transform: rotate(360deg); }
    }

    #loading {
      display: none;
      margin-top: 20px;
      text-align: center;
      color: #0d6efd;
      font-weight: 500;
    }
  </style>
</head>
<body class="container py-4">
  <h2>📢 Kirim WA Massal ke Orang Tua Siswa</h2>
  <a href="../modif.php" class="btn btn-secondary mb-3">← Kembali</a>

  <form method="POST" onsubmit="showLoading()">
    <div class="mb-3">
      <label for="pesan" class="form-label">Format Pesan</label>
      <textarea name="pesan" id="pesan" rows="5" class="form-control" placeholder="Yth. Orang Tua/Wali Siswa {nama} dari kelas {kelas}, besok masuk pukul 07.00 WIB." required></textarea>
      <div class="form-text">Gunakan <b>{nama}</b> dan <b>{kelas}</b> untuk otomatis diganti dengan nama dan kelas siswa.</div>
    </div>

    <div class="mb-3">
      <label for="delay" class="form-label">Jeda antar pesan (detik)</label>
      <input type="number" name="delay" id="delay" class="form-control" value="3" min="1" max="30">
    </div>

    <p>Pastikan orang tua / wali sudah save contact.</p>
    <button type="submit" class="btn btn-primary">🚀 Kirim Pesan Massal</button>

    <!-- Loading Section -->
    <div id="loading">
      <div class="spinner"></div>
      <span>⏳ Mohon tunggu, jangan tutup halaman ini...</span>
    </div>
  </form>

  <script>
    function showLoading() {
      document.getElementById('loading').style.display = 'block';
    }
  </script>
</body>
</html>
