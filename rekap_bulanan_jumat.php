<?php
include 'config.php';

$kelas = $_GET['kelas'] ?? '';
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

$kelasList = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas");
$jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

// Ambil siswa hanya yang aktif
$siswaQuery = "SELECT * FROM siswa WHERE status='aktif'";
if ($kelas != '') {
  $siswaQuery .= " AND kelas = '$kelas'";
}
$siswaQuery .= " ORDER BY nama";
$siswaResult = mysqli_query($conn, $siswaQuery);

// Ambil data absensi hanya siswa aktif
$absensi = [];
$absensiQuery = "SELECT a.*, s.nis, s.nama FROM absensi a 
                 JOIN siswa s ON a.siswa_id = s.id 
                 WHERE MONTH(a.tanggal) = '$bulan' 
                   AND YEAR(a.tanggal) = '$tahun'
                   AND s.status='aktif'";
if ($kelas != '') {
  $absensiQuery .= " AND s.kelas = '$kelas'";
}
$resultAbsensi = mysqli_query($conn, $absensiQuery);

while ($row = mysqli_fetch_assoc($resultAbsensi)) {
  $sid = $row['siswa_id'];
  $tgl = (int)date('j', strtotime($row['tanggal']));
  $absensi[$sid][$tgl] = $row['status'];
}

// Ambil daftar hari libur dari database
$libur = [];
$queryLibur = mysqli_query($conn, "SELECT tanggal FROM hari_libur");
while ($row = mysqli_fetch_assoc($queryLibur)) {
  $libur[] = $row['tanggal'];
}

// Ambil data profil sekolah
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT kepala_sekolah, nip_kepala FROM profil_sekolah LIMIT 1"));

// Ambil data wali kelas
$wali_nama = '....................................';
$wali_nip = '........................';
if ($kelas != '') {
    $qWali = mysqli_query($conn, "SELECT nama_wali, nip_wali FROM wali_kelas WHERE kelas = '$kelas' LIMIT 1");
    if ($w = mysqli_fetch_assoc($qWali)) {
        $wali_nama = $w['nama_wali'];
        $wali_nip = $w['nip_wali'];
    }
}

// Tanggal terakhir bulan ini
$tanggal_terakhir = date("j F Y", strtotime("$tahun-$bulan-" . cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun)));
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Absensi Bulanan Per Siswa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; font-size: 12px; }
    th, td { border: 1px solid #000; text-align: center; padding: 3px; }
    select, button { padding: 5px; margin: 5px 0; }
    thead th { background: #eee; }
    .minggu { color: red; }
    .dot-red { color: red; font-weight: bold; }
    .alpa { color: red; font-weight: bold; }
  </style>
</head>
<body>
  <h2>Rekap Absensi Bulanan Per Siswa</h2>

  <form method="get">
    <label>Kelas:
      <select name="kelas">
        <option value="">Semua</option>
        <?php while ($k = mysqli_fetch_assoc($kelasList)) {
          $sel = ($k['kelas'] == $kelas) ? 'selected' : '';
          echo "<option value='{$k['kelas']}' $sel>{$k['kelas']}</option>";
        } ?>
      </select>
    </label>

    <label>Bulan:
      <select name="bulan">
        <?php for ($b = 1; $b <= 12; $b++) {
          $sel = ($b == $bulan) ? 'selected' : '';
          echo "<option value='$b' $sel>" . date('F', mktime(0, 0, 0, $b, 10)) . "</option>";
        } ?>
      </select>
    </label>

    <label>Tahun:
      <input type="number" name="tahun" value="<?= $tahun ?>" style="width: 80px;">
    </label>

    <button type="submit">Tampilkan</button>
    <a href="cetak_absen.php?kelas=<?= $kelas ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" style="padding:5px 10px; background:#28a745; color:#fff; text-decoration:none; border-radius:4px;">Cetak / Simpan PDF</a>
	  <a href="dashboard.php" 
   style="padding:5px 10px; background:#6c757d; color:#fff; text-decoration:none; border-radius:4px;">
   ⬅ Kembali ke Dashboard
</a>
  </form>

  <table>
    <thead>
      <tr>
        <th rowspan="2">No</th>
        <th rowspan="2">NIS</th>
        <th rowspan="2">Nama</th>
        <th colspan="<?= $jumlahHari ?>">Tanggal</th>
        <th colspan="4">Rekap</th>
      </tr>
      <tr>
        <?php
        for ($i = 1; $i <= $jumlahHari; $i++) {
          $tanggal = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-" . str_pad($i, 2, '0', STR_PAD_LEFT);
          $day = date('w', strtotime($tanggal)); // 5 = Jumat
          $class = ($day == 5) ? 'minggu' : '';
          echo "<th class='$class'>$i</th>";
        }
        ?>
        <th>H</th><th>S</th><th>I</th><th>A</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $no = 1;
      while ($siswa = mysqli_fetch_assoc($siswaResult)) {
        $sid = $siswa['id'];
        echo "<tr>";
        echo "<td>$no</td>";
        echo "<td>{$siswa['nis']}</td>";
        echo "<td>{$siswa['nama']}</td>";

        $countH = $countS = $countI = $countA = 0;

        for ($i = 1; $i <= $jumlahHari; $i++) {
          $val = $absensi[$sid][$i] ?? '';
          $tanggal = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-" . str_pad($i, 2, '0', STR_PAD_LEFT);
          $day = date('w', strtotime($tanggal));

          if ($val == '') {
            if ($day == 5 || in_array($tanggal, $libur)) {
              echo "<td><span class='dot-red'>&bull;</span></td>";
            } else {
              echo "<td></td>";
            }
          } else {
            if ($val == 'H') {
              echo "<td>&bull;</td>";
              $countH++;
            } elseif ($val == 'A') {
              echo "<td class='alpa'>A</td>";
              $countA++;
            } elseif ($val == 'S') {
              echo "<td>S</td>";
              $countS++;
            } elseif ($val == 'I') {
              echo "<td>I</td>";
              $countI++;
            } else {
              echo "<td>$val</td>";
            }
          }
        }

        echo "<td>$countH</td><td>$countS</td><td>$countI</td><td>$countA</td>";
        echo "</tr>";
        $no++;
      }
      ?>
    </tbody>
  </table>

  <br><br>
  <table style="width:100%; border:0; font-size:14px; text-align:center;">
    <tr>
      <td style="width:50%;">
        Mengetahui,<br>
        Kepala Sekolah<br><br><br><br>
        <u><?= $profil['kepala_sekolah'] ?? '....................................' ?></u><br>
        NIP. <?= $profil['nip_kepala'] ?? '........................' ?>
      </td>
      <td style="width:50%;">
        <?= $tanggal_terakhir ?><br>
        Wali Kelas <?= $kelas != '' ? $kelas : '(Semua Kelas)' ?><br><br><br><br>
        <u><?= $wali_nama ?></u><br>
        NIP. <?= $wali_nip ?>
      </td>
    </tr>
  </table>
</body>
</html>
