<?php

return [
    // Payout status shown to users (Addendum C §5-D). Never a rule name or score.
    'generic_reason' => "le paiement n'a pas pu être effectué",
    'status' => [
        'being_checked' => 'Vérification en cours — généralement quelques minutes',
        'security_hold' => 'Blocage de sécurité jusqu\'au :time — rien à faire de votre part',
        'queued' => 'En file — nous préparons les fonds, aucune action requise',
        'extra_check' => 'Vérification supplémentaire en cours — nous vous préviendrons',
        'sent' => 'Envoyé à :provider — peut prendre jusqu\'à :days jours',
        'paid' => 'Livré sur votre compte :provider',
        'returned' => 'Retourné sur votre solde — :reason',
        'bounced' => 'Renvoyé par la banque après l\'envoi — l\'argent est revenu sur votre solde. Vérifiez vos informations de paiement',
    ],
    'not_me' => [
        'title' => 'Paiements suspendus',
        'body' => 'Nous avons suspendu les paiements sur votre compte et prévenu notre équipe. Rien ne sera envoyé tant que vous n\'aurez pas de nos nouvelles.',
        'cancelled' => '{1} 1 retrait en attente a été annulé et l\'argent est revenu sur votre solde.|[2,*] :count retraits en attente ont été annulés et l\'argent est revenu sur votre solde.',
        'reset' => 'Réinitialiser mon mot de passe',
    ],
    'cancel' => [
        'button' => 'Annuler',
        'confirm' => 'Annuler ce retrait ? L\'argent revient immédiatement sur votre solde.',
        'done' => 'Retrait annulé — l\'argent est revenu sur votre solde.',
        'too_late' => 'Il a déjà été envoyé, il ne peut plus être annulé.',
    ],
    'step_up' => [
        'title' => 'Confirmez que c\'est bien vous',
        'body' => 'Pour votre sécurité, confirmez un code avant d\'ajouter ou de modifier un compte de paiement.',
        'send' => 'Envoyez-moi un code',
        'enter' => 'Saisissez le code à 6 chiffres',
        'verify' => 'Vérifier',
        'ok' => 'Vérifié — vous pouvez continuer pendant :minutes minutes.',
        'bad' => 'Ce code n\'a pas fonctionné. Réessayez.',
        'sent' => 'Code envoyé par e-mail.',
        'authenticator' => 'Utilisez le code de votre application d\'authentification.',
    ],
];
