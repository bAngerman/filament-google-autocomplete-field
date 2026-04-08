<?php

use Tapp\FilamentGoogleAutocomplete\Forms\Components\GoogleAutocomplete;

it('hydrates countries from a closure without casting errors', function () {
    config(['filament-google-autocomplete-field.api-key' => 'test']);

    $component = GoogleAutocomplete::make('address')
        ->countries(fn () => 'DE');

    $hydrate = new ReflectionMethod($component, 'hydrateGoogleApiParams');
    $hydrate->setAccessible(true);
    $hydrate->invoke($component);

    $paramsProp = new ReflectionProperty($component, 'params');
    $paramsProp->setAccessible(true);
    $params = $paramsProp->getValue($component);

    expect($params['components'])->toBe('country:DE');
});

it('hydrates language from a closure for the legacy places api', function () {
    config(['filament-google-autocomplete-field.api-key' => 'test']);

    $component = GoogleAutocomplete::make('address')
        ->language(fn () => 'fr');

    $hydrate = new ReflectionMethod($component, 'hydrateGoogleApiParams');
    $hydrate->setAccessible(true);
    $hydrate->invoke($component);

    $paramsProp = new ReflectionProperty($component, 'params');
    $paramsProp->setAccessible(true);
    $params = $paramsProp->getValue($component);

    expect($params['language'])->toBe('fr');
});

it('hydrates place types from a closure for the legacy places api', function () {
    config(['filament-google-autocomplete-field.api-key' => 'test']);

    $component = GoogleAutocomplete::make('address')
        ->placeTypes(fn () => 'establishment');

    $hydrate = new ReflectionMethod($component, 'hydrateGoogleApiParams');
    $hydrate->setAccessible(true);
    $hydrate->invoke($component);

    $paramsProp = new ReflectionProperty($component, 'params');
    $paramsProp->setAccessible(true);
    $params = $paramsProp->getValue($component);

    expect($params['types'])->toBe('establishment');
});

it('hydrates included region codes from a closure for the new places api', function () {
    config(['filament-google-autocomplete-field.api-key' => 'test']);

    $component = GoogleAutocomplete::make('address')
        ->placesApiNew()
        ->includedRegionCodes(fn () => ['US', 'CA']);

    $hydrate = new ReflectionMethod($component, 'hydrateGoogleApiParams');
    $hydrate->setAccessible(true);
    $hydrate->invoke($component);

    $paramsProp = new ReflectionProperty($component, 'params');
    $paramsProp->setAccessible(true);
    $params = $paramsProp->getValue($component);

    expect($params['includedRegionCodes'])->toBe(['US', 'CA']);
});
