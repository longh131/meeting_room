<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Reservation;

echo "Reservation records:\n";
echo "-------------------\n";

$reservations = Reservation::all();
foreach ($reservations as $reservation) {
    echo "ID: " . $reservation->id . "\n";
    echo "Title: " . $reservation->title . "\n";
    echo "Start Time: " . $reservation->start_time . "\n";
    echo "End Time: " . $reservation->end_time . "\n";
    echo "Created At: " . $reservation->created_at . "\n";
    echo "-------------------\n";
}
?>