<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    header("Location: index.php");
    exit;
}

include "config.php";
date_default_timezone_set("Asia/Jakarta");

// === Bagian proses scan (AJAX) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nisn'])) {
  $nisn = $_POST['nisn'];
  $tanggal = date("Y-m-d");
  $jam = date("H:i:s");
  $jam_disp = date("H:i");

  // Cek hari libur
  $cekLibur = mysqli_query($conn, "SELECT * FROM hari_libur WHERE tanggal='$tanggal'");
  if (mysqli_num_rows($cekLibur) > 0) {
    exit("⛔ Hari ini libur!");
  }

  // Cek siswa
  $siswa = mysqli_query($conn, "SELECT * FROM siswa WHERE nisn='$nisn'");
  if (mysqli_num_rows($siswa) == 0) {
    exit("❌ Siswa tidak ditemukan.");
  }
  $s = mysqli_fetch_assoc($siswa);

  // Cek absen
  $cekAbsen = mysqli_query($conn, "SELECT * FROM absensi WHERE siswa_id={$s['id']} AND tanggal='$tanggal'");

  $pesan = "";
  $msg   = "";
  if (mysqli_num_rows($cekAbsen) == 0) {
    // Absen masuk
    mysqli_query($conn, "INSERT INTO absensi (siswa_id, tanggal, jam, status) 
                         VALUES ({$s['id']}, '$tanggal', '$jam', 'H')");
    $msg   = "✅ Absen masuk: {$s['nama']} ({$s['kelas']})<br>🕒 Jam hadir: $jam_disp";
    $pesan = "Halo, Orang tua/wali dari {$s['nama']} (kelas {$s['kelas']}).\n\nTelah *HADIR* pada $tanggal pukul $jam_disp.";
  } else {
    $row = mysqli_fetch_assoc($cekAbsen);
    if (is_null($row['jam_pulang']) && $jam >= "09:00:00") {
      // Absen pulang
      mysqli_query($conn, "UPDATE absensi SET jam_pulang='$jam' WHERE id={$row['id']}");
      $msg   = "✅ Pulang berhasil: {$s['nama']} ({$s['kelas']})<br>🕒 Jam pulang: $jam_disp";
      $pesan = "Halo, Orang tua/wali dari {$s['nama']} (kelas {$s['kelas']}).\n\nTelah *PULANG* pada $tanggal pukul $jam_disp.";
    } else {
      // Sudah absen
      $msg = "ℹ️ {$s['nama']} sudah absen hari ini.<br>🕒 Jam hadir: {$row['jam']}";
      if (!is_null($row['jam_pulang'])) {
        $msg .= "<br>🕒 Jam pulang: {$row['jam_pulang']}";
      }
    }
  }

  // === Kirim WA otomatis ===
  $wa_status = "";
  if (!empty($pesan) && !empty($s['no_wa'])) {
    // Normalisasi nomor WA
    $no_wa = preg_replace('/[^0-9]/', '', $s['no_wa']);
    if (substr($no_wa, 0, 1) === "0") {
        $no_wa = "+62" . substr($no_wa, 1);
    } elseif (substr($no_wa, 0, 2) === "62") {
        $no_wa = "+" . $no_wa;
    } elseif (substr($no_wa, 0, 3) !== "+62") {
        $no_wa = "";
    }

    if (!empty($no_wa)) {
      // Ambil secret key dari profil_sekolah
      $secretKey = "";
      $qKey = mysqli_query($conn, "SELECT key_wa_sidobe FROM profil_sekolah LIMIT 1");
      if ($qKey && mysqli_num_rows($qKey) > 0) {
        $rowKey = mysqli_fetch_assoc($qKey);
        $secretKey = $rowKey['key_wa_sidobe'] ?? "";
      }

      if (!empty($secretKey)) {
        $data = [
          'phone'   => $no_wa,
          'message' => $pesan
        ];
        $ch = curl_init('https://api.sidobe.com/wa/v1/send-message');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json',
          'X-Secret-Key: ' . $secretKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);

        $resData = json_decode($response, true);
        if ($resData && isset($resData['is_success']) && $resData['is_success']) {
          $wa_status = "📲 WA terkirim ke $no_wa";
        } else {
          $wa_status = "⚠️ Gagal kirim WA.";
        }
      } else {
        $wa_status = "⚠️ Secret key WA tidak ditemukan.";
      }
    } else {
      $wa_status = "⚠️ Nomor WA tidak valid.";
    }
  }

  exit($msg . "<br>" . $wa_status);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Absensi Scanner QR</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { text-align:center; background:#f8f9fa; }
    .scanner-hint {
      margin-top: 20px;
      font-size: 1.4rem;
      font-weight: bold;
      color: #007bff;
      animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.1); opacity: 0.6; }
      100% { transform: scale(1); opacity: 1; }
    }
    .arrow-down {
      margin-top: 15px;
      font-size: 3rem;
      color: #dc3545;
      animation: bounce 1s infinite;
    }
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(10px); }
    }
    #result { max-height:300px; overflow-y:auto; margin-top:20px; }
  </style>
</head>
<body class="container mt-5">
  <h2>🔍 Scan QR / Barcode Siswa</h2>
  <a href="dashboard.php" class="btn btn-secondary mb-3">← Kembali</a>

  <div class="scanner-hint">Scan kartu atau ketik NISN lalu klik Kirim</div>
  <div class="arrow-down">⬇️</div>

  <form id="scanForm" method="POST" class="mb-3">
    <input type="text" name="nisn" id="nisnInput" class="form-control" placeholder="Scan atau ketik NISN di sini" autofocus>
    <button type="submit" class="btn btn-primary mt-2">Kirim</button>
  </form>

  <div id="result"></div>
  <audio id="beepSound" src="beep.mp3" preload="auto"></audio>

  <script>
    const input = document.getElementById("nisnInput");
    const form = document.getElementById("scanForm");
    const result = document.getElementById("result");
    const beep = document.getElementById("beepSound");

    setInterval(() => { input.focus(); }, 500);

    form.addEventListener("submit", function(e) {
      e.preventDefault();
      let formData = new FormData(form);
      fetch("", {
        method: "POST",
        body: formData
      })
      .then(res => res.text())
      .then(data => {
        let alertDiv = document.createElement("div");
        alertDiv.className = "alert alert-info mb-2";
        alertDiv.innerHTML = data;

        if (result.firstChild) {
          result.insertBefore(alertDiv, result.firstChild);
        } else {
          result.appendChild(alertDiv);
        }
        beep.play();
        input.value = "";
        input.focus();
      });
    });
  </script>
</body>
</html>
