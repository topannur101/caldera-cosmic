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
                'icon'      => 'fa-solid fa-brush',
                'content'   => 'Tema bercorak kini telah hadir. Jadikan Calderamu lebih personal, klik disini untuk mengubah temamu.',
                'url'       => 'http://172.70.66.131/account/theme?mode=patterned',
            ],

            'comment_mentions' => [
                'icon'      => 'fa-solid fa-comment',
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
                'icon'      => 'fa-solid fa-cube',
                'content'   => 'Panduan video Inventaris kini tersedia. Pelajari lebih lanjut',
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
