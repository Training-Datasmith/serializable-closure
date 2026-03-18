<?php

declare(strict_types=1);

namespace Tests\Fixtures;

class Route
{
    public static function make()
    {
        return function (Model $model) {
            return __METHOD__;
        };
    }
}
