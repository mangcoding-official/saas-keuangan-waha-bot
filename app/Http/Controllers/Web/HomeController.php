<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $brandName = 'MACAU';

        return view('web.home', [
            'page' => [
                'title' => 'Catat keuangan lebih praktis lewat WhatsApp',
                'description' => 'Catat pemasukan, pengeluaran, dan transfer melalui percakapan WhatsApp. Semua transaksi tersusun rapi dan dapat dipantau melalui dashboard yang intuitif.',
                'eyebrow' => 'Terpercaya & aman',
                'brand' => $brandName,
                'nav' => [
                    ['label' => 'Fitur', 'href' => '#fitur'],
                    ['label' => 'Cara Kerja', 'href' => '#cara-kerja'],
                    ['label' => 'FAQ', 'href' => '#footer'],
                ],
                'heroStats' => [
                    'label' => 'Pengguna aktif setiap hari',
                    'value' => '10.000+',
                ],
                'heroMessages' => [
                    'prompt' => 'Halo! Mau catat apa hari ini? Ketik: nominal dan kategori',
                    'reply' => 'Beli kopi 25000',
                ],
                'balance' => 'Rp1.250.000',
                'features' => [
                    [
                        'title' => 'Integrasi WhatsApp',
                        'description' => 'Tidak perlu install aplikasi tambahan. Cukup simpan nomor MACAU Bot dan mulai mencatat lewat chat harianmu.',
                        'variant' => 'wide',
                        'tone' => 'blue-soft',
                    ],
                    [
                        'title' => 'Pantau Saldo Real-time',
                        'description' => 'Update saldo otomatis setiap kali transaksi berhasil dicatat di sistem.',
                        'variant' => 'tall',
                        'tone' => 'blue-solid',
                    ],
                    [
                        'title' => 'Kolaborasi Tim/Keluarga',
                        'description' => 'Satu akun untuk bersama. Catat keuangan rumah tangga atau bisnis kecil jadi lebih transparan.',
                        'variant' => 'card',
                        'tone' => 'white',
                    ],
                    [
                        'title' => 'Cek Riwayat Instan',
                        'description' => 'Tanya "Riwayat hari ini" di WhatsApp, dan sistem akan menampilkan daftar transaksi lengkap dalam hitungan detik.',
                        'variant' => 'wide',
                        'tone' => 'green-soft',
                        'items' => [
                            ['label' => 'Beli Pulsa', 'amount' => '-Rp50rb', 'tone' => 'expense'],
                            ['label' => 'Gajian', 'amount' => '+Rp8jt', 'tone' => 'income'],
                        ],
                    ],
                ],
                'steps' => [
                    [
                        'number' => '1',
                        'title' => 'Daftar Akun',
                        'description' => 'Hubungkan nomor WhatsApp-mu dengan platform MACAU Bot.',
                    ],
                    [
                        'number' => '2',
                        'title' => 'Mulai Chat',
                        'description' => 'Kirim pesan seperti "Makan siang 25000" langsung ke bot.',
                    ],
                    [
                        'number' => '3',
                        'title' => 'Pantau Laporan',
                        'description' => 'Lihat grafik pengeluaran dan ringkasan keuangan di dashboard web.',
                    ],
                ],
                'ctaBenefits' => [
                    'Dukungan 24/7',
                ],
                'footer' => [
                    'description' => 'Solusi cerdas pencatatan keuangan harian melalui WhatsApp. Transparan, cepat, dan mudah digunakan untuk rumah tangga maupun bisnis kecil.',
                    'companyLinks' => ['Tentang Kami', 'Kontak'],
                    'productLinks' => ['Fitur'],
                ],
            ],
        ]);
    }
}
