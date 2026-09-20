<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class LocationPicker extends Field
{
    protected string $view = 'filament.forms.components.location-picker';

    protected float | \Closure $defaultLatitude = -6.200000;
    protected float | \Closure $defaultLongitude = 106.816666;
    protected int | \Closure $defaultZoom = 13;

    public function defaultLatLng(float $lat, float $lng): static
    {
        $this->defaultLatitude = $lat;
        $this->defaultLongitude = $lng;

        return $this;
    }

    public function getDefaultLatitude(): float
    {
        return $this->evaluate($this->defaultLatitude);
    }

    public function getDefaultLongitude(): float
    {
        return $this->evaluate($this->defaultLongitude);
    }

    public function getDefaultZoom(): int
    {
        return $this->evaluate($this->defaultZoom);
    }
}