<?php

namespace Tapp\FilamentGoogleAutocomplete\Concerns;

use Closure;
use Illuminate\Support\Arr;

trait HasGooglePlaceApi
{
    protected $googlePlaces;

    protected array $googleAddressFieldNames = [
        'street_number',
        'route',
        'locality',
        'administrative_area_level_2',
        'administrative_area_level_1',
        'country',
        'postal_code',
    ];

    protected array $apiNamingConventions = [
        'newApi' => [
            'longText' => 'longText',
            'shortText' => 'shortText',
            'googleAddressExtraFieldNames' => [
                'id',
                'nationalPhoneNumber',
                'internationalPhoneNumber',
                'formattedAddress',
                'displayName',
                'websiteUri',
            ],
        ],
        'originalApi' => [
            'longText' => 'long_name',
            'shortText' => 'short_name',
            'googleAddressExtraFieldNames' => [
                'place_id',
                'formatted_phone_number',
                'international_phone_number',
                'formatted_address',
                'name',
                'website',
            ],
        ],
    ];

    protected array $currentApiNamingConventions = [];

    protected bool|Closure $placesApiNew = false;

    protected bool|Closure $includePureServiceAreaBusinesses = false;

    protected string|array|Closure|null $countries = null;

    protected array|Closure|null $includedRegionCodes = null;

    protected string|Closure|null $language = null;

    protected string|Closure|null $location = null;

    protected string|array|Closure|null $locationBias = null;

    protected string|Closure|null $locationRestriction = null;

    protected int|Closure|null $offset = null;

    protected string|Closure|null $origin = null;

    protected int|Closure|null $radius = null;

    protected string|Closure|null $region = null;

    protected string|Closure|null $sessionToken = null;

    protected string|array|Closure|null $placeTypes = null;

    protected function getPlaceAutocomplete($search)
    {
        $this->setGoogleApi();
        $this->hydrateGoogleApiParams();

        if ($this->getPlacesApiNew()) {
            return $this->googlePlaces->autocomplete($search, false, ['*'], $this->params);
        }

        return $this->googlePlaces->placeAutocomplete($search, $this->params);
    }

    protected function getPlace(string $placeId)
    {
        $this->setGoogleApi();
        $this->hydrateGoogleApiParams();

        $detailParams = [];

        if ($this->getPlacesApiNew()) {
            $detailParams['languageCode'] = $this->params['languageCode'] ?? null;

            return $this->googlePlaces->placeDetails($placeId, ['*'], $detailParams);
        }

        $detailParams['language'] = $this->params['language'] ?? null;

        return $this->googlePlaces->placeDetails($placeId, $detailParams);
    }

    protected function getFormattedApiResults($data): array
    {
        $response = $data->collect();

        if ($this->getPlacesApiNew()) {
            $addressComponents = $response['addressComponents'];

            $latLngFields = [
                'latitude' => [
                    'long_name' => $response['location']['latitude'],
                    'short_name' => $response['location']['latitude'],
                ],
                'longitude' => [
                    'long_name' => $response['location']['longitude'],
                    'short_name' => $response['location']['longitude'],
                ],
            ];

            $extraFields = $response->toArray();
        } else {
            $result = $response['result'];

            $addressComponents = $result['address_components'];

            $latLngFields = $result['geometry']['location'];

            $latLngFields = [
                'latitude' => [
                    'long_name' => $latLngFields['lat'],
                    'short_name' => $latLngFields['lat'],
                ],
                'longitude' => [
                    'long_name' => $latLngFields['lng'],
                    'short_name' => $latLngFields['lng'],
                ],
            ];

            $extraFields = $result;
        }

        // array map with keys
        $addressFields = array_merge(...array_map(function ($key, $item) {
            return [
                $item['types'][0] => [
                    'long_name' => $item[$this->currentApiNamingConventions['longText']],
                    'short_name' => $item[$this->currentApiNamingConventions['shortText']],
                ],
            ];
        }, array_keys($addressComponents), $addressComponents));

        // array map with keys
        $extraFields = array_merge(...array_map(function ($key, $item) {
            if (in_array($key, $this->currentApiNamingConventions['googleAddressExtraFieldNames'])) {
                $item = $key === 'displayName' ? $item['text'] : $item;

                return [
                    $key => [
                        'long_name' => $item,
                        'short_name' => $item,
                    ],
                ];
            } else {
                return [];
            }
        }, array_keys($extraFields), $extraFields));

        return array_merge($addressFields, $extraFields, $latLngFields);
    }

    public function placesApiNew(bool|Closure $placesApiNew = true): static
    {
        $this->placesApiNew = $placesApiNew;

        return $this;
    }

    public function getPlacesApiNew(): bool
    {
        return $this->evaluate($this->placesApiNew);
    }

    public function includePureServiceAreaBusinesses(bool|Closure $includePureServiceAreaBusinesses = false): static
    {
        $this->includePureServiceAreaBusinesses = $includePureServiceAreaBusinesses;

        return $this;
    }

    public function getIncludePureServiceAreaBusinesses(): bool
    {
        return $this->evaluate($this->includePureServiceAreaBusinesses);
    }

    protected function setGoogleApi()
    {
        $googleClass = 'SKAgarwal\GoogleApi\Places\GooglePlaces';

        $this->currentApiNamingConventions = $this->apiNamingConventions['originalApi'];

        if ($this->getPlacesApiNew()) {
            $googleClass = 'SKAgarwal\GoogleApi\PlacesNew\GooglePlaces';

            $this->currentApiNamingConventions = $this->apiNamingConventions['newApi'];
        }

        $this->googlePlaces = $googleClass::make(
            key: config('filament-google-autocomplete-field.api-key'),
            verifySSL: config('filament-google-autocomplete-field.verify-ssl'),
            throwOnErrors: config('filament-google-autocomplete-field.throw-on-errors'),
        );
    }

    /**
     * Merges evaluated Google Places API parameters into {@see $params}.
     * Call before autocomplete or place-details requests so {@see Closure} options resolve with the correct utility injection (e.g. Get).
     */
    protected function hydrateGoogleApiParams(): void
    {
        foreach ([
            'includePureServiceAreaBusinesses',
            'components',
            'includedRegionCodes',
            'languageCode',
            'language',
            'location',
            'locationBias',
            'locationbias',
            'locationRestriction',
            'locationrestriction',
            'inputOffset',
            'offset',
            'origin',
            'radius',
            'regionCode',
            'region',
            'sessionToken',
            'sessiontoken',
            'includedPrimaryTypes',
            'types',
        ] as $key) {
            unset($this->params[$key]);
        }

        $isNew = $this->getPlacesApiNew();

        $this->params['includePureServiceAreaBusinesses'] = $this->getIncludePureServiceAreaBusinesses();

        if ($this->countries !== null) {
            $countries = $this->evaluate($this->countries);
            $this->params['components'] = $countries !== null
                ? $this->getFormattedCountries(Arr::wrap($countries))
                : null;
        }

        if ($this->includedRegionCodes !== null) {
            $includedRegionCodes = $this->evaluate($this->includedRegionCodes);
            if ($includedRegionCodes !== null) {
                $this->params['includedRegionCodes'] = Arr::wrap($includedRegionCodes);
            }
        }

        if ($this->language !== null) {
            $language = $this->evaluate($this->language);
            if ($isNew) {
                $this->params['languageCode'] = $language;
            } else {
                $this->params['language'] = $language;
            }
        }

        if ($this->location !== null) {
            $this->params['location'] = $this->evaluate($this->location);
        }

        if ($this->locationBias !== null) {
            $locationBias = $this->evaluate($this->locationBias);
            if ($isNew) {
                $this->params['locationBias'] = $locationBias;
            } else {
                $this->params['locationbias'] = $locationBias;
            }
        }

        if ($this->locationRestriction !== null) {
            $locationRestriction = $this->evaluate($this->locationRestriction);
            if ($isNew) {
                $this->params['locationRestriction'] = $locationRestriction;
            } else {
                $this->params['locationrestriction'] = $locationRestriction;
            }
        }

        if ($this->offset !== null) {
            $offset = $this->evaluate($this->offset);
            if ($isNew) {
                $this->params['inputOffset'] = $offset;
            } else {
                $this->params['offset'] = $offset;
            }
        }

        if ($this->origin !== null) {
            $this->params['origin'] = $this->evaluate($this->origin);
        }

        if ($this->radius !== null) {
            $this->params['radius'] = $this->evaluate($this->radius);
        }

        if ($this->region !== null) {
            $region = $this->evaluate($this->region);
            if ($isNew) {
                $this->params['regionCode'] = $region;
            } else {
                $this->params['region'] = $region;
            }
        }

        if ($this->sessionToken !== null) {
            $sessionToken = $this->evaluate($this->sessionToken);
            if ($isNew) {
                $this->params['sessionToken'] = $sessionToken;
            } else {
                $this->params['sessiontoken'] = $sessionToken;
            }
        }

        if ($this->placeTypes !== null) {
            $placeTypes = $this->evaluate($this->placeTypes);
            $wrapped = Arr::wrap($placeTypes);
            if ($isNew) {
                $this->params['includedPrimaryTypes'] = $wrapped;
            } else {
                $this->params['types'] = $this->getFormattedPlaceTypes($wrapped);
            }
        }
    }

    public function countries(array|string|Closure|null $countries): static
    {
        $this->countries = $countries === null ? [] : $countries;

        return $this;
    }

    public function getCountries(): string|array|null
    {
        if ($this->countries === null) {
            return null;
        }

        return $this->evaluate($this->countries);
    }

    public function includedRegionCodes(array|Closure|null $includedRegionCodes): static
    {
        $this->includedRegionCodes = $includedRegionCodes === null ? [] : $includedRegionCodes;

        return $this;
    }

    public function getIncludedRegionCodes(): ?array
    {
        if ($this->includedRegionCodes === null) {
            return null;
        }

        return $this->evaluate($this->includedRegionCodes);
    }

    public function language(string|Closure|null $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language === null ? null : $this->evaluate($this->language);
    }

    public function location(string|Closure|null $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location === null ? null : $this->evaluate($this->location);
    }

    public function locationBias(string|array|Closure|null $locationBias): static
    {
        $this->locationBias = $locationBias;

        return $this;
    }

    public function getLocationBias(): null|string|array
    {
        return $this->locationBias === null ? null : $this->evaluate($this->locationBias);
    }

    public function locationRestriction(string|Closure|null $locationRestriction): static
    {
        $this->locationRestriction = $locationRestriction;

        return $this;
    }

    public function getLocationRestriction(): ?string
    {
        return $this->locationRestriction === null ? null : $this->evaluate($this->locationRestriction);
    }

    public function offset(int|Closure|null $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    public function getOffset(): ?int
    {
        return $this->offset === null ? null : $this->evaluate($this->offset);
    }

    public function origin(string|Closure|null $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin === null ? null : $this->evaluate($this->origin);
    }

    public function radius(int|Closure|null $radius): static
    {
        $this->radius = $radius;

        return $this;
    }

    public function getRadius(): ?int
    {
        return $this->radius === null ? null : $this->evaluate($this->radius);
    }

    public function region(string|Closure|null $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region === null ? null : $this->evaluate($this->region);
    }

    public function sessionToken(string|Closure|null $sessionToken): static
    {
        $this->sessionToken = $sessionToken;

        return $this;
    }

    public function getSessionToken(): ?string
    {
        return $this->sessionToken === null ? null : $this->evaluate($this->sessionToken);
    }

    public function placeTypes(array|string|Closure|null $placeTypes): static
    {
        $this->placeTypes = $placeTypes === null ? [] : $placeTypes;

        return $this;
    }

    public function getPlaceTypes(): string|array|null
    {
        if ($this->placeTypes === null) {
            return null;
        }

        return $this->evaluate($this->placeTypes);
    }
}
