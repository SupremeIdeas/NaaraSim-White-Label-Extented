<?php

return [
    // Payout status shown to users (Addendum C §5-D). Never a rule name or score.
    'generic_reason' => 'malipo hayakuweza kukamilika',
    'status' => [
        'being_checked' => 'Inakaguliwa — kwa kawaida dakika chache',
        'security_hold' => 'Zuio la usalama hadi :time — hakuna unachohitaji kufanya',
        'queued' => 'Imewekwa kwenye foleni — tunaandaa fedha, hakuna hatua inayohitajika',
        'extra_check' => 'Ukaguzi wa ziada unaendelea — tutakujulisha',
        'sent' => 'Imetumwa kwa :provider — inaweza kuchukua hadi siku :days',
        'paid' => 'Imefika kwenye akaunti yako ya :provider',
        'returned' => 'Imerudishwa kwenye salio lako — :reason',
        'bounced' => 'Imerudi kutoka benki baada ya kutumwa — pesa ziko tena kwenye salio lako. Tafadhali angalia taarifa zako za malipo',
    ],
    'not_me' => [
        'title' => 'Malipo yamesitishwa',
        'body' => 'Tumesitisha malipo kwenye akaunti yako na tumeijulisha timu yetu. Hakuna kitakachotumwa hadi utakaposikia kutoka kwetu.',
        'cancelled' => '{1} Uondoaji 1 uliokuwa unasubiri umeghairiwa na pesa ziko tena kwenye salio lako.|[2,*] Uondoaji :count uliokuwa unasubiri umeghairiwa na pesa ziko tena kwenye salio lako.',
        'reset' => 'Weka upya nenosiri langu',
    ],
    'cancel' => [
        'button' => 'Ghairi',
        'confirm' => 'Ghairi uondoaji huu? Pesa zitarudi mara moja kwenye salio lako.',
        'done' => 'Uondoaji umeghairiwa — pesa ziko tena kwenye salio lako.',
        'too_late' => 'Tayari umetumwa, hauwezi kughairiwa sasa.',
    ],
    'step_up' => [
        'title' => 'Thibitisha ni wewe',
        'body' => 'Kwa usalama wako, thibitisha msimbo kabla ya kuongeza au kubadilisha akaunti ya malipo.',
        'send' => 'Nitumie msimbo',
        'enter' => 'Weka msimbo wa tarakimu 6',
        'verify' => 'Thibitisha',
        'ok' => 'Imethibitishwa — unaweza kuendelea kwa dakika :minutes zijazo.',
        'bad' => 'Msimbo huo haukufanya kazi. Jaribu tena.',
        'sent' => 'Msimbo umetumwa kwa barua pepe yako.',
        'authenticator' => 'Tumia msimbo kutoka kwenye programu yako ya uthibitishaji.',
    ],
];
