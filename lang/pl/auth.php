<?php

declare(strict_types=1);

return [
    'failed' => 'Błędny login lub hasło.',
    'password' => 'Hasło jest nieprawidłowe.',
    'password_current_mismatch' => 'Podane hasło nie jest zgodne z aktualnym hasłem.',
    'password_policy' => [
        'no_three_identical_consecutive_characters' => 'Hasło nie może zawierać trzech takich samych znaków z rzędu.',
        'not_based_on_user_data' => 'Hasło nie może być oparte o dane konta.',
        'not_recently_used' => 'To hasło było niedawno używane. Wybierz inne hasło.',
    ],
    'rate_limited' => 'Zbyt wiele prób. Odczekaj chwilę i spróbuj ponownie.',
    'session_conflict' => 'To konto jest już aktywne w innej sesji. Kontynuuj tutaj, aby zakończyć poprzednią sesję, albo anuluj logowanie.',
    'throttle' => 'Za dużo nieudanych prób logowania. Proszę spróbować za :seconds sekund.',
];
