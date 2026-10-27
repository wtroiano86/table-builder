<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns\Concerns;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;
use Wtroiano\TableBuilder\Support\Image;
use Wtroiano\TableBuilder\Support\Url;

trait ResolvesColumnMedia
{
    protected string|Url|Closure|null $url = null;

    protected string|Closure|null $image = null;

    protected ?Closure $configureImage = null;

    public function url(string|Url|callable|null $url): static
    {
        if ($url === null) {
            return $this;
        }

        $this->url = is_callable($url) && ! is_string($url)
            ? Closure::fromCallable($url)
            : $url;

        return $this;
    }

    /**
     * @return string|array<array-key, mixed>|null
     */
    public function resolveUrl(mixed $record): string|array|null
    {
        if ($this->url === null) {
            return null;
        }

        $url = $this->url instanceof Closure
            ? ($this->url)($record, new Url())
            : $this->url;

        if ($url instanceof Url) {
            return $url->toArray();
        }

        return is_string($url) || is_array($url) ? $url : null;
    }

    public function image(string|callable|null $image, ?callable $configure = null): static
    {
        if ($image !== null) {
            $this->image = is_callable($image) && ! is_string($image)
                ? Closure::fromCallable($image)
                : $image;
        }

        if ($configure !== null) {
            $this->configureImage = Closure::fromCallable($configure);
        }

        return $this;
    }

    /**
     * @return array{
     *     url: ?string,
     *     urls: list<string>,
     *     icon: ?string,
     *     size: string,
     *     width: ?int,
     *     height: ?int,
     *     rounded: bool,
     *     position: string,
     *     class: string,
     *     alt: string,
     *     title: string,
     *     limit: ?int
     * }|null
     */
    public function resolveImage(mixed $record): ?array
    {
        if ($this->image === null && ! $this->configureImage instanceof Closure) {
            return null;
        }

        $image = new Image();

        if (is_string($this->image)) {
            $value = data_get($record, $this->image);

            if (is_string($value) || is_array($value)) {
                $image->url(is_array($value) ? array_values(array_filter($value, is_string(...))) : $value);
            }
        }

        if ($this->image instanceof Closure) {
            $resolved = $this->invokeImageCallback($this->image, $record, $image);

            if ($resolved instanceof Image) {
                $image = $resolved;
            } elseif (is_string($resolved) || is_array($resolved)) {
                $image->url(is_array($resolved) ? array_values(array_filter($resolved, is_string(...))) : $resolved);
            }
        }

        if ($this->configureImage instanceof Closure) {
            $configured = $this->invokeImageCallback($this->configureImage, $record, $image);

            if ($configured instanceof Image) {
                $image = $configured;
            }
        }

        return $image->toArray();
    }

    private function invokeImageCallback(Closure $callback, mixed $record, Image $image): mixed
    {
        $reflection = new ReflectionFunction($callback);
        $parameters = $reflection->getParameters();

        if ($parameters === []) {
            return $callback();
        }

        $firstType = $parameters[0]->getType();

        if ($firstType instanceof ReflectionNamedType && ! $firstType->isBuiltin() && $firstType->getName() === Image::class) {
            return count($parameters) === 1
                ? $callback($image)
                : $callback($image, $record);
        }

        if (count($parameters) === 1) {
            return $callback($record);
        }

        return $callback($record, $image);
    }
}
