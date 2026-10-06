<?php

/*
 * RP-01 · Posición presupuestal. Umbrales del semáforo como fracción del vigente consumido:
 * VERDE < amarillo; AMARILLO entre amarillo y rojo; ROJO >= rojo.
 */
return [
    'traffic_light' => [
        'yellow' => (float) env('BUDGET_POSITION_YELLOW', 0.80),
        'red' => (float) env('BUDGET_POSITION_RED', 1.00),
    ],

    // Meses cerrados que promedia la proyección de cierre.
    'projection_months' => 3,
];
