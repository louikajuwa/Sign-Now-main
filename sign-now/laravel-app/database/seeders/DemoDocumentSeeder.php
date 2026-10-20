<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Field;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@signnow.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );

        $draftDoc = Document::factory()
            ->for($admin, 'user')
            ->create(['title' => 'Employment Agreement', 'status' => 'draft']);

        Field::factory()->count(3)->for($draftDoc)->create();

        $readyDoc = Document::factory()
            ->for($admin, 'user')
            ->ready()
            ->create(['title' => 'NDA - Project Alpha']);

        Field::factory()->count(4)->for($readyDoc)->create();
        Field::factory()->signature()->for($readyDoc)->create();

        Recipient::factory()
            ->count(2)
            ->sent()
            ->for($readyDoc, 'document')
            ->create();

        $completedDoc = Document::factory()
            ->for($admin, 'user')
            ->completed()
            ->create(['title' => 'Vendor Contract - Completed']);

        Field::factory()->count(3)->for($completedDoc)->create();

        Recipient::factory()
            ->signed()
            ->for($completedDoc, 'document')
            ->create();

        Document::factory()
            ->for($admin, 'user')
            ->template()
            ->create(['title' => 'Standard NDA Template']);

        Document::factory()
            ->for($admin, 'user')
            ->count(5)
            ->create();
    }
}
