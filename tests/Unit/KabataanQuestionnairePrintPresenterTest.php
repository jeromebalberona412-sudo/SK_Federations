<?php

use App\Modules\KabataanMonitoring\Support\KabataanQuestionnairePrintPresenter;

it('maps KK monitoring form data to the Officials print questionnaire layout', function () {
    $sheet = KabataanQuestionnairePrintPresenter::fromForm([
        'respondent_number' => '07',
        'date' => '09/07/2026',
        'last_name' => 'dela cruz',
        'first_name' => 'juan',
        'middle_name' => 'santos',
        'suffix' => 'Jr.',
        'region' => 'Region IV-A (CALABARZON)',
        'province' => 'Laguna',
        'city' => 'Santa Cruz',
        'barangay' => 'Poblacion',
        'purok_zone' => 'Zone 1',
        'age' => '18',
        'birthday' => '2008-01-15',
        'email' => 'juan@example.com',
        'contact_number' => '09123456789',
        'sex' => 'Male',
        'civil_status' => 'Single',
        'education' => 'High School Level',
        'kk_reason' => 'There was no KK Assembly',
        'signature_name' => 'Juan Santos Dela Cruz Jr.',
        'signature' => null,
    ], 'https://example.test/logo.png');

    expect($sheet['respondentNumber'])->toBe('07')
        ->and($sheet['lastName'])->toBe('DELA CRUZ')
        ->and($sheet['firstName'])->toBe('JUAN')
        ->and($sheet['birthday'])->toBe('01/15/2008')
        ->and($sheet['barangayLogoUrl'])->toBe('https://example.test/logo.png')
        ->and($sheet['isChecked']('sex', 'Male'))->toBeTrue()
        ->and($sheet['isChecked']('sex', 'Female'))->toBeFalse()
        ->and($sheet['isChecked']('education', 'High school level'))->toBeTrue()
        ->and($sheet['isChecked']('kk_reason', 'There was no KK Assembly Meeting'))->toBeTrue();
});

it('treats empty and dash form values as blank print fields', function () {
    $sheet = KabataanQuestionnairePrintPresenter::fromForm([
        'middle_name' => '—',
        'suffix' => 'None',
        'birthday' => '—',
        'email' => '',
    ]);

    expect($sheet['middleName'])->toBe('')
        ->and($sheet['suffix'])->toBe('')
        ->and($sheet['birthday'])->toBe('')
        ->and($sheet['email'])->toBe('');
});
