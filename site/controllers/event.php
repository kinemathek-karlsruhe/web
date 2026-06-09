<?php

/**
 * Single Event controller (SPEC §2.3).
 */
return function ($page) {
    return [
        'isPast' => $page->isPast(),
    ];
};
