<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;
use App\Notifications\FeatureNew;
use App\Models\User;
use App\Models\InvCirc;
use Carbon\Carbon;

class FeatureAnnounce extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:feature-announce';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $features = [
            'theme_patterned' => [
                'icon'      => 'icon-paintbrush-vertical',
                'content'   => 'Tema bercorak kini telah hadir. Jadikan Calderamu lebih personal, klik disini untuk mengubah temamu.',
                'url'       => 'http://172.70.66.131/account/theme?mode=patterned',
            ],

            'comment_mentions' => [
                'icon'      => 'icon-message-circle',
                'content'   => 'Kini kamu dapat membalas atau menyebut seseorang dengan @ di kolom komentar. Pelajari lebih lanjut.',
                'post'      =>
                [
                    'title'     => 'Membalas dan menyebut di kolom komentar',
                    'content'   => '
                    Kini kamu dapat membalas atau menyebut seseorang dengan @ di kolom komentar. 
                    Notifikasi akan dikirimkan kepada orang tersebut yang kamu sebut atau balas komentarnya. 
                    Fitur ini berlaku di halaman apapun yang mengandung komentar (contoh: halaman Inventaris barang).
                    <br><br>
                    Untuk membalas komentar, klik tombol balas di bawah komentar yang ingin kamu balas. <br><br>
                    <img src="/announcements/comment_reply.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;"><br>
                    <img src="/announcements/comment_notification.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;"><br>
                    <br><br>
                    Untuk menyebut seseorang, ketikkan @ diikuti dengan nama atau nomor karyawan orang tersebut.<br><br>
                    <img src="/announcements/comment_mention.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;"><br>
                    ',
                ] 
            ],

            'inv_video_guide' => [
                'icon'      => 'icon-box',
                'content'   => 'Panduan video Inventaris kini tersedia. Pelajari lebih lanjut.',
                'post'      =>
                [
                    'title'     => 'Panduan video Inventaris',
                    'content'   => '
                    <img src="/announcements/inv_video_guide_nav_1.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    <img src="/announcements/inv_video_guide_nav_2.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br><br>
                    Kini kamu dapat menonton video panduan cara menggunakan sistem Inventaris. 
                    Akses melalui navigasi > Inventaris > Tonton panduan. Panduan ini berguna baik untuk pengguna lama yang ingin mengingat kembali atau untuk training pengguna baru.
                    ',
                ] 
            ],

            'inv_circ_user_edit' => [
                'icon'      => 'icon-box',
                'content'   => 'Kini kamu dapat mengubah pengguna pada sirkulasi yang telah dibuat. Pelajari lebih lanjut.',
                'post'      =>
                [
                    'title'     => 'Mengubah pengguna pada sirkulasi',
                    'content'   => '
                    <br>
                    <img src="/announcements/inv_circ_edit_user.png" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    Kamu dapat mengubah pengguna pada suatu sirkulasi selama sirkulasi tersebut masih tertunda.
                    ',
                ] 
            ],
            'inv_circ_bulk_with_item' => [
                'icon'      => 'icon-box',
                'content'   => 'Kini operasi massal sirkulasi memiliki mode dengan tambah barang secara langsung dan menyesuaikan dengan tabel CR. Pelajari lebih lanjut.',
                'post'      =>
                [
                    'title'     => 'Sirkulasi massal dengan barang baru',
                    'content'   => '
                    <img src="/announcements/inv_circ_bulk_with_item_1.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    <img src="/announcements/inv_circ_bulk_with_item_2.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br><br>
                    Kini kamu dapat melakukan operasi massal sirkulasi dan bila barang tidak ditemukan akan dibuat secara otomatis. Mode baru ini juga menyesuaikan dengan format tabel CR agar lebih mudah saat copy & paste. Akses pada halaman Sirkulasi > Operasi massal > Dengan barang baru. <br><br><b>Catatan:</b> Informasi barang yang dimasukkan (Nama, Deskripsi, UOM dan Harga satuan) hanya akan digunakan saat membuat barang baru jika barang dengan kode yang ditentukan tidak dapat ditemukan. Informasi tersebut tidak akan digunakan untuk memperbarui barang yang sudah ada.
                    ',
                ] 
            ],

            'inv_label_print' => [
                'icon'      => 'icon-box',
                'content'   => 'Kini kamu dapat mencetak label untuk visual barang. Pelajari lebih lanjut.',
                'post'      =>
                [
                    'title'     => 'Mencetak label',
                    'content'   => '
                    Kini kamu dapat mencetak label untuk visual barang. Saat ini terdapat dua ukuran label: kecil (6cm x 2.5cm) dan besar (8.5cm x 5.5cm). Buat sirkulasi catat pada barang-barang yang ingin dicetak labelnya. Masuk ke halaman sirkulasi dan gunakan filter yang dibutuhkan untuk mengerucutkan daftar. Klik menu "Cetak semua sebagai..." lalu pilih "Label kecil/besar" dan klik tombol "Cetak".
                    <br>
                    <img src="/announcements/inv_label_print_1.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    <img src="/announcements/inv_label_print_2.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    <img src="/announcements/inv_label_print_3.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    <img src="/announcements/inv_label_print_4.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    <img src="/announcements/inv_label_print_5.jpg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">

                    ',
                ] 
            ],

            'inv_specific_search' => [
                'icon'      => 'icon-box',
                'content'   => 'Kini kamu dapat mencari barang dengan nama, deskripsi, dan kode item secara spesifik.',
                'post'      =>
                [
                    'title'     => 'Pencarian spesifik',
                    'content'   => '
                    Kini kamu dapat mencari barnag dengan nama, deskripsi, dan kode item secara spesifik. Tekan tombol rantai untuk menyalakan dan mematikan fitur ini. Rantai lepas berarti kamu dapat mencari barang dengan informasi spesifik. Rantai tersambung berarti kamu mencari barang dengan cara klasik (mencari pada nama, deskripsi, dan kode sekaligus).
                    <br><br>
                    <img src="/announcements/inv_specific_1.jpeg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    <img src="/announcements/inv_specific_2.jpeg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    <br>
                    <img src="/announcements/inv_specific_3.jpeg" class="mx-auto border shadow rounded-md" style="max-width: 380px;">
                    ',
                ] 
            ],

            

            
        ];
        
        // Prepare the choices array with indices and content
        $choices = [];
        foreach ($features as $index => $feature) {
            $choices[$index] = $feature['icon'];
        }
        
        $feature_id = $this->choice('Select feature to announce', $choices);

        try {
            $feature = $features[$feature_id];
        } catch (\Throwable $th) {
            $this->error('Error: ' . $th->getMessage());
            return 1;
        }

        $user_selection = $this->choice('Select user to send feature announcement', [
            'superuser'         => 'Superuser account (user with id 1)',
            'active_users'      => 'All active users',
            'recent_users'      => 'All users with seen_at less than 6 months',
            'all_users'         => 'All users',
            'inventory_users'   => 'All inventory users by recent circulations'
        ]);


        $users = match ($user_selection) {
            'superuser'         => User::where('id', 1)->get(),
            'active_users'      => User::where('is_active', 1)->get(),
            'recent_users'      => User::where('seen_at', '>', now()->subMonths(6))->get(),
            'all_users'         => User::all(),
            'inventory_users'   => User::whereIn('id', $this->getInventoryUserIds())->get(),
            default             => null,
        };

        $url_or_post = isset($feature['url']) ? ('URL: ' . $feature['url']) : ( 'Post title: ' . $feature['post']['title'] );

        $this->info('Icon: '            . $feature['icon']);
        $this->info('Content: '         . $feature['content']);
        $this->info('URL/Post: '        . $url_or_post);
        $this->info('User selection: '  . $user_selection);
        $this->info('User count: '      . $users->count());

        if ($users === null) {
            $this->error('Invalid user selection.');
            return 1; // Exit with error code
        }

        if ($this->confirm('Do you want to send this feature announcement?')) {

            $this->info('Sending feature announcement...');

            if (!isset($feature['url'])) {
                $post = Announcement::create([
                    'title'     => $feature['post']['title'],
                    'content'   => $feature['post']['content'],
                ]);
                $feature['url'] = 'http://172.70.66.131/announcements/'.$post->id;
            }

            foreach ($users as $user) {
                $user->notify(new FeatureNew($feature));
            }
    
            $this->info('Feature announced.');
        
        }

    }

    public function getInventoryUserIds()
    {
        $user_ids = InvCirc::where('created_at', '>=', Carbon::now()->subMonths(12))
            ->distinct()
            ->pluck('user_id');  
        
        return $user_ids;
    }
}
