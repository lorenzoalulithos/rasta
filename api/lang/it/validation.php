<?php declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Messaggi di validazione
|--------------------------------------------------------------------------
|
| Frasi semplici, pensate per chi usa l'app dal telefono in cantiere.
| Solo le regole usate dalle API: si aggiungono quando servono.
|
*/

return [
    'accepted' => 'Il campo :attribute va accettato.',
    'array' => 'Il campo :attribute non è valido.',
    'between' => [
        'numeric' => 'Il campo :attribute deve essere tra :min e :max.',
        'string' => 'Il campo :attribute deve avere tra :min e :max caratteri.',
    ],
    'boolean' => 'Il campo :attribute deve essere sì o no.',
    'date' => 'Il campo :attribute non è una data valida.',
    'decimal' => 'Il campo :attribute deve avere :decimal decimali.',
    'email' => "L'email non sembra giusta.",
    'enum' => 'Il valore di :attribute non è tra quelli ammessi.',
    'exists' => 'Il valore di :attribute non esiste.',
    'gt' => [
        'numeric' => 'Il campo :attribute deve essere maggiore di :value.',
    ],
    'gte' => [
        'numeric' => 'Il campo :attribute deve essere almeno :value.',
    ],
    'in' => 'Il valore di :attribute non è tra quelli ammessi.',
    'integer' => 'Il campo :attribute deve essere un numero intero.',
    'lt' => [
        'numeric' => 'Il campo :attribute deve essere minore di :value.',
    ],
    'lte' => [
        'numeric' => 'Il campo :attribute non può superare :value.',
    ],
    'max' => [
        'array' => 'Il campo :attribute può avere al massimo :max elementi.',
        'numeric' => 'Il campo :attribute non può superare :max.',
        'string' => 'Il campo :attribute può avere al massimo :max caratteri.',
    ],
    'min' => [
        'array' => 'Il campo :attribute deve avere almeno :min elementi.',
        'numeric' => 'Il campo :attribute deve essere almeno :min.',
        'string' => 'Il campo :attribute deve avere almeno :min caratteri.',
    ],
    'numeric' => 'Il campo :attribute deve essere un numero.',
    'present' => 'Il campo :attribute deve esserci.',
    'required' => 'Il campo :attribute è obbligatorio.',
    'size' => [
        'string' => 'Il campo :attribute deve avere :size caratteri.',
    ],
    'string' => 'Il campo :attribute deve essere un testo.',
    'unique' => 'Questo :attribute è già usato.',
    'uuid' => 'Il campo :attribute non è valido.',

    /*
    |--------------------------------------------------------------------------
    | Nomi dei campi
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'address' => 'indirizzo',
        'city' => 'città',
        'coverage_strategy' => 'resa usata',
        'default_payment_terms' => 'condizioni di pagamento',
        'default_vat_rate' => 'IVA',
        'email' => 'email',
        'logo_path' => 'logo',
        'name' => 'nome',
        'notes' => 'note',
        'opened_package_ratio' => 'valore confezione aperta',
        'package_rounding' => 'arrotondamento confezioni',
        'password' => 'password',
        'phone' => 'telefono',
        'province' => 'provincia',
        'quote_footer_text' => 'testo in calce',
        'quote_validity_days' => 'validità del preventivo',
        'tax_code' => 'codice fiscale',
        'tax_regime' => 'regime fiscale',
        'vat_number' => 'partita IVA',
        'zip' => 'CAP',
    ],
];
