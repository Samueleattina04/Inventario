<?php

return [
    /*
     * Mappa degli ID unità di misura dal database Access.
     * Chiave = valore numerico del campo UM/unimis in Access.
     * Valore = etichetta mostrata all'utente.
     *
     * Aggiorna i valori in base alla configurazione del tuo database.
     */
    'um_map' => [
        1 => 'PZ',
        2 => 'KG',
        3 => 'KG',  // confermato dal database (pistacchi, mandorle)
        4 => 'LT',
        5 => 'MT',
        6 => 'GR',
    ],
];
