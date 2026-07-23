<?php

use Kinemathek\Kinemathek;

/**
 * Events listing controller — upcoming non-film Events only, grouped by day
 * for the Monatsblatt listing (same shape as the program controller's view
 * data; the grouping/labeling block is deliberately a copy — it wants to be a
 * Kinemathek::groupByDay() helper once the plugin unfreezes).
 */
return function ($site, $page, $kirby) {
    $now = Kinemathek::now();
    $results = Kinemathek::events()
        ->filter(fn ($event) => ($event->timestamp() ?? 0) >= $now)
        ->sortBy(fn ($event) => $event->timestamp(), 'asc');

    $days = [];
    foreach ($results as $item) {
        if (!$ts = $item->timestamp()) {
            continue;
        }
        $days[date('Y-m-d', $ts)][] = $item;
    }
    ksort($days);

    $locale = $kirby->language()?->locale(LC_ALL) ?? 'de_DE';
    $locale = explode('.', $locale)[0];

    $dayMeta   = [];
    $prevMonth = null;
    foreach (array_keys($days) as $key) {
        $ts    = strtotime($key . ' 12:00');
        $month = (int)date('n', $ts);
        $dayMeta[$key] = [
            'dow'        => rtrim(Kinemathek::localDate($ts, 'dow'), '.'),
            'num'        => (int)date('j', $ts),
            // month name is always carried (client filtering re-picks the first
            // visible day per month); monthStart marks the full-list transition
            // so the no-JS view still shows one marker per month.
            'month'      => Kinemathek::localDate($ts, 'month'),
            'monthStart' => $prevMonth !== $month,
            'detailDate' => Kinemathek::localDate($ts, 'detail'),
        ];
        $prevMonth = $month;
    }

    return [
        'days'     => $days,
        'dayMeta'  => $dayMeta,
        'todayKey' => date('Y-m-d'),
    ];
};
