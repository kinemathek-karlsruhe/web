<?php

use Kinemathek\Kinemathek;

/**
 * Bereichsseite controller — the upcoming program restricted to the page's
 * categories, grouped by day for the shared Monatsblatt listing. (Grouping
 * block mirrors the program/events controllers — wants to be a
 * Kinemathek::groupByDay() helper once the plugin unfreezes.)
 */
return function ($site, $page, $kirby) {
    $categories = Kinemathek::splitField($page->categories());
    $results = $categories === []
        ? new \Kirby\Cms\Pages([])
        : $site->program(['categories' => $categories]);

    $days = [];
    foreach ($results as $item) {
        if (!$ts = $item->timestamp()) {
            continue;
        }
        $days[date('Y-m-d', $ts)][] = $item;
    }
    ksort($days);

    $dayMeta   = [];
    $prevMonth = null;
    foreach (array_keys($days) as $key) {
        $ts    = strtotime($key . ' 12:00');
        $month = (int)date('n', $ts);
        $dayMeta[$key] = [
            'dow'        => rtrim(Kinemathek::localDate($ts, 'dow'), '.'),
            'num'        => (int)date('j', $ts),
            'month'      => $prevMonth === $month ? null : Kinemathek::localDate($ts, 'month'),
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
