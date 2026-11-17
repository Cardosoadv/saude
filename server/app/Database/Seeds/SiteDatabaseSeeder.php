<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SiteDatabaseSeeder extends Seeder
{
    public function run()
    {
        // Run all seeds in order
        $this->call('Site\SiteNewsCategoriesSeeder');
        $this->call('Site\SiteNewsSeeder');
    }
}
