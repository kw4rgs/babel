<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * php artisan db:seed --class=UsersTableSeeder
     * @return void
     */
    public function run()
    {
        $users = User::create([
            'name' => 'admin', 
            'email' => 'czurita.westnet@gmail.com',
            'password' => Hash::make('123456')
        ]);
    }
}