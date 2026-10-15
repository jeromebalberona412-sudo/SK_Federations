<?php

use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

it('accepts an official name of exactly 150 characters', function () {
    $name = str_repeat('A', 150);

    $validator = Validator::make(
        ['first_name' => $name, 'middle_name' => $name, 'last_name' => $name],
        officialNameRules(),
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects an official name of 151 characters', function () {
    $validator = Validator::make(
        ['first_name' => str_repeat('A', 151)],
        officialNameRules(),
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('first_name'))->toBeTrue();
});

it('accepts official names with spaces hyphens and apostrophes', function () {
    $validator = Validator::make(
        [
            'first_name' => "ANNA MARIE",
            'middle_name' => "D'ANGELO-CRUZ",
            'last_name' => 'DE LA CRUZ',
        ],
        officialNameRules(),
    );

    expect($validator->passes())->toBeTrue();
});

function officialNameRules(): array
{
    $pattern = '/^(?!\s)[A-Z.\-\']+(?: [A-Z.\-\']+)*$/u';

    return [
        'first_name' => ['required', 'string', 'min:2', 'max:150', 'regex:'.$pattern],
        'middle_name' => ['nullable', 'string', 'min:2', 'max:150', 'regex:'.$pattern],
        'last_name' => ['required', 'string', 'min:2', 'max:150', 'regex:'.$pattern],
    ];
}
