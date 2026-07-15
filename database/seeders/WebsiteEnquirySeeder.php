<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WebsiteEnquiry;
use Illuminate\Database\Seeder;

class WebsiteEnquirySeeder extends Seeder
{
    public function run(): void
    {
        WebsiteEnquiry::factory()->count(12)->create();
    }
}
