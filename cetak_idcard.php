<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    header("Location: index.php");
    exit;
}

include 'config.php';
require 'fpdf/fpdf.php';

// Ambil data profil sekolah
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT logo, background_kartu, nama_sekolah, alamat FROM profil_sekolah LIMIT 1"));

$logo_path = !empty($profil['logo']) ? __DIR__ . '/uploads/' . $profil['logo'] : null;
$bg_path   = !empty($profil['background_kartu']) ? __DIR__ . '/uploads/' . $profil['background_kartu'] : null;

$nama_sekolah   = $profil['nama_sekolah'] ?? '';
$alamat_sekolah = $profil['alamat'] ?? '';

// Ambil data siswa
$result = mysqli_query($conn, "SELECT * FROM siswa ORDER BY kelas ASC, nama ASC");

class PDF extends FPDF
{
    public $logo_path;
    public $bg_path;

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 10, 'Aplikasi lainnya unduh di: www.tasadmin.id', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->logo_path = $logo_path;
$pdf->bg_path   = $bg_path;
$pdf->SetAutoPageBreak(false);

// === Ukuran kartu ===
$card_width  = 95;
$card_height = 124;
$margin_x    = 7;
$margin_y    = 10;
$spacing_x   = 5;
$spacing_y   = 5;

$page_width  = 210;
$page_height = 297;

$cards_per_row   = floor(($page_width  - 2 * $margin_x + $spacing_x) / ($card_width  + $spacing_x));
$cards_per_col   = floor(($page_height - 2 * $margin_y + $spacing_y) / ($card_height + $spacing_y));
$cards_per_page  = $cards_per_row * $cards_per_col;

$x = $margin_x;
$y = $margin_y;
$count = 0;

while ($data = mysqli_fetch_assoc($result)) {
    if ($count % $cards_per_page == 0) {
        $pdf->AddPage();
        $x = $margin_x;
        $y = $margin_y;
    }

    // === Background ===
    if ($pdf->bg_path && file_exists($pdf->bg_path)) {
        $pdf->Image($pdf->bg_path, $x, $y, $card_width, $card_height);
    } else {
        $pdf->Rect($x, $y, $card_width, $card_height);
    }

    // Koordinat tengah kartu
    $center_x = $x + $card_width / 2;

    // === Logo (center atas) ===
    if ($pdf->logo_path && file_exists($pdf->logo_path)) {
        $logo_w = 10;
        $logo_h = 10;
        $pdf->Image($pdf->logo_path, $center_x - ($logo_w / 2), $y + 4, $logo_w, $logo_h);
    }

    // Geser Y setelah logo
    $current_y = $y + 15;

    // === Nama sekolah ===
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetXY($x, $current_y);
    $pdf->Cell($card_width, 6, $nama_sekolah, 0, 1, 'C');

    // === Alamat sekolah ===
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY($x, $current_y + 7);
    $pdf->MultiCell($card_width, 4, $alamat_sekolah, 0, 'C');

    // Geser Y untuk data siswa
    $current_y += 15;

    // === Nama siswa ===
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY($x, $current_y);
    $pdf->Cell($card_width, 6, $data['nama'], 0, 1, 'C');

    // === NIS ===
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY($x, $current_y + 7);
    $pdf->Cell($card_width, 5, 'NIS : ' . $data['nis'], 0, 1, 'C');

    // === NISN ===
    $pdf->SetXY($x, $current_y + 13);
    $pdf->Cell($card_width, 5, 'NISN: ' . $data['nisn'], 0, 1, 'C');

    // === Kelas ===
    $pdf->SetXY($x, $current_y + 19);
    $pdf->Cell($card_width, 5, 'Kelas: ' . $data['kelas'], 0, 1, 'C');

    // === Foto siswa (tengah bawah, di atas QR) ===
    $foto_path = "uploads/" . ($data['foto_siswa'] ?: "foto_pp.jpg");
    if (file_exists($foto_path)) {
        $foto_w = 25;
        $foto_h = 31;
        $pdf->Image($foto_path, $center_x - ($foto_w / 2), $y + $card_height - 68, $foto_w, $foto_h);
    }

    // === QR Code (paling bawah tengah) ===
    $qr_path = "assets/qr/" . $data['nisn'] . ".png";
    if (file_exists($qr_path)) {
        $qr_size = 30;
        $pdf->Image($qr_path, $center_x - ($qr_size / 2), $y + $card_height - 35, $qr_size, $qr_size);
    } else {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetXY($x, $y + $card_height - 15);
        $pdf->Cell($card_width, 5, 'QR Missing', 0, 1, 'C');
    }

    // === Posisi kartu berikutnya ===
    if (($x + $card_width + $spacing_x) > ($page_width - $margin_x)) {
        $x = $margin_x;
        $y += $card_height + $spacing_y;
    } else {
        $x += $card_width + $spacing_x;
    }

    $count++;
}

$pdf->Output('I', 'id_card.pdf');
