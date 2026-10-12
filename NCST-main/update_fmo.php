<?php
$u = \App\Models\User::where('email', 'fmo@ncst.edu.ph')->first();
if ($u) {
    $u->role = 'faculty';
    $u->save();
    echo "FMO Role Updated to faculty\n";
} else {
    echo "FMO user not found\n";
}
