<?php
$user = \App\Models\User::firstOrNew(['email' => 'fmo@ncst.edu.ph']);
$user->name = 'Facilities Management Office';
$user->school_id = 'FMO-Admin';
$user->password = bcrypt('fmo12345');
$user->role = 'staff';
$user->department = 'FMO';
$user->save();
echo "FMO User Created/Updated\n";
