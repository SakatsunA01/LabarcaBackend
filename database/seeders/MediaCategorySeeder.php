<?php

namespace Database\Seeders;

use App\Models\MediaCategory;
use Illuminate\Database\Seeder;

class MediaCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'pista_audio',   'name' => 'Pista de audio',  'icon' => '🎵'],
            ['slug' => 'videoclip',     'name' => 'Videoclip',       'icon' => '🎬'],
            ['slug' => 'lyric_video',   'name' => 'Lyric video',     'icon' => '📝'],
            ['slug' => 'flyer',         'name' => 'Flyer',           'icon' => '🖼️'],
            ['slug' => 'foto_prensa',   'name' => 'Foto de prensa',  'icon' => '📸'],
            ['slug' => 'demo',          'name' => 'Demo',            'icon' => '🎙️'],
            ['slug' => 'backing_track', 'name' => 'Backing track',   'icon' => '🎹'],
            ['slug' => 'otro',          'name' => 'Otro',            'icon' => '📁'],
        ];

        foreach ($categories as $cat) {
            MediaCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
