<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds for all essential system roles and administrative accounts.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@ncst.edu.ph',
                'school_id' => 'ADMIN-001',
                'role' => 'admin',
                'department' => 'ADMIN',
                'password' => Hash::make('admin12345'),
                'is_suspended' => false,
            ],
            [
                'name' => 'ToolsRoomAdmin',
                'email' => 'ToolsRoomAdmin@ncst.edu.ph',
                'school_id' => 'STAFF-001',
                'role' => 'staff',
                'department' => null,
                'password' => Hash::make('admin12345'),
                'is_suspended' => false,
            ],
            [
                'name' => 'Facilities Management Office',
                'email' => 'fmo@ncst.edu.ph',
                'school_id' => 'FMO-Admin',
                'role' => 'faculty',
                'department' => 'FMO',
                'password' => Hash::make('admin12345'),
                'is_suspended' => false,
            ],
            [
                'name' => 'Office of Student Affairs',
                'email' => 'osa@ncst.edu.ph',
                'school_id' => 'OSA-001',
                'role' => 'faculty',
                'department' => 'OSA',
                'password' => Hash::make('admin12345'),
                'is_suspended' => false,
            ],
            [
                'name' => 'Office of the Campus Manager',
                'email' => 'ocm@ncst.edu.ph',
                'school_id' => 'OCM-001',
                'role' => 'faculty',
                'department' => 'OCM',
                'password' => Hash::make('admin12345'),
                'is_suspended' => false,
            ],
            [
                'name' => 'Office of Safety and Security',
                'email' => 'oss@ncst.edu.ph',
                'school_id' => 'OSS-001',
                'role' => 'faculty',
                'department' => 'OSS',
                'password' => Hash::make('admin12345'),
                'is_suspended' => false,
            ],
        ];

        foreach ($accounts as $data) {
            User::updateOrCreate(
                ['school_id' => $data['school_id']],
                $data
            );
        }
    }
}
