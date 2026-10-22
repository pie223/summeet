<?php
/**
 * Seeds a handful of demo users and hikes on first run.
 * Demo accounts all use the password: summeet123
 */
function seed_demo_data(PDO $pdo): void
{
    $password = password_hash('summeet123', PASSWORD_DEFAULT);

    $users = [
        ['marta_trails',  'Marta',   29, 'woman', -40, 'dating', 'Weekend addict of ridgelines and cold huts. Slow on climbs, fast with the thermos.'],
        ['leo_summits',   'Leo',     34, 'man',     10, 'meeting', 'Just looking for people to actually keep pace with me. Trail runner, occasional scrambler.'],
        ['sofia_w',       'Sofia',   26, 'woman',    0, 'dating', 'New to the mountains, big on snacks and views. Open to slow, chatty hikes.'],
        ['dan_ridge',     'Dan',     41, 'man',     55, 'meeting', 'Long-distance solo hiker most of the year, happy for company on shorter local routes.'],
        ['iris_vega',     'Iris',    31, 'nonbinary', -70, 'dating', 'Plant nerd who hikes slow and stops for every wildflower. Looking for patient company.'],
    ];

    $stmt = $pdo->prepare("INSERT INTO users
        (username, password_hash, display_name, age, gender, political_orientation, intent, bio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $ids = [];
    foreach ($users as $u) {
        $stmt->execute([$u[0], $password, $u[1], $u[2], $u[3], $u[4], $u[5], $u[6]]);
        $ids[$u[0]] = $pdo->lastInsertId();
    }

    // Small hand-picked routes (real-ish coordinates around the Italian Alps)
    // so the map view has something to draw on first run.
    $routes = [
        'sunrise'   => [[45.7930, 6.9700], [45.7985, 6.9790], [45.8050, 6.9850]],
        'scramble'  => [[45.5150, 7.2700], [45.5250, 7.2800], [45.5350, 7.2900]],
        'lake_loop' => [[46.6950, 12.0850], [46.6985, 12.0910], [46.7000, 12.0950], [46.6970, 12.0980]],
        'traverse'  => [[46.6900, 12.4100], [46.6970, 12.4230], [46.7040, 12.4320], [46.7100, 12.4400]],
        'wildflower'=> [[45.4700, 7.2000], [45.4780, 7.2100], [45.4850, 7.2200]],
    ];

    $hikes = [
        [$ids['marta_trails'], '(Demo) Sunrise ridge walk', 'Monte Bianco foothills', 'Aosta Valley, IT', 'Steep pre-dawn climb to catch the sunrise from the ridge, then a long day out. Good fitness needed. Bring layers, it is cold at the top.', 'hard', date('Y-m-d', strtotime('+9 days')), 3, 4, json_encode($routes['sunrise'])],
        [$ids['leo_summits'], '(Demo) Fast & light scramble', 'Gran Paradiso', 'Aosta Valley, IT', 'Looking for 1-2 people who can keep a brisk pace. Short, easy scrambling near the top, nothing technical.', 'moderate', date('Y-m-d', strtotime('+14 days')), 3, 3, json_encode($routes['scramble'])],
        [$ids['sofia_w'], '(Demo) Easy lake loop + picnic', 'Lago di Braies area', 'South Tyrol, IT', 'Flat, scenic, and slow. Perfect if you want conversation more than a workout. Picnic halfway.', 'easy', date('Y-m-d', strtotime('+5 days')), 3, 6, json_encode($routes['lake_loop'])],
        [$ids['dan_ridge'], '(Demo) Two-day traverse', 'Dolomiti di Sesto', 'South Tyrol, IT', 'Overnight in a rifugio, full traverse the next morning. Need people comfortable with multi-day gear.', 'hard', date('Y-m-d', strtotime('+21 days')), 3, 5, json_encode($routes['traverse'])],
        [$ids['iris_vega'], '(Demo) Wildflower wander', 'Parco Nazionale Gran Paradiso', 'Piedmont, IT', 'Slow botanical hike, lots of stops. Great for a first date-ish kind of hike or just a calm morning.', 'easy', date('Y-m-d', strtotime('+7 days')), 3, 4, json_encode($routes['wildflower'])],
    ];

    $stmt2 = $pdo->prepare("INSERT INTO hikes
        (creator_id, title, mountain_name, region, description, difficulty, hike_date, min_participants, max_participants, route_points)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($hikes as $h) {
        $stmt2->execute($h);
    }
}