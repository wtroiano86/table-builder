<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

final class ImageColumn extends Column
{
    protected function __construct(string $name)
    {
        parent::__construct($name);

        $this->image($name);
    }

    public function imageWidth(int|string $width): self
    {
        return $this->meta([
            'imageWidth' => $width,
        ]);
    }

    public function imageHeight(int|string $height): self
    {
        return $this->meta([
            'imageHeight' => $height,
        ]);
    }

    public function shape(string $shape): self
    {
        return $this->meta([
            'shape' => $shape,
        ]);
    }

    public function rounded(): self
    {
        return $this->shape('rounded');
    }

    public function circle(): self
    {
        return $this->shape('circle');
    }

    public function fit(string $fit): self
    {
        return $this->meta([
            'fit' => $fit,
        ]);
    }

    public function alt(string $alt): self
    {
        return $this->meta([
            'alt' => $alt,
        ]);
    }

    public function placeholder(string $placeholder): self
    {
        return $this->meta([
            'placeholder' => $placeholder,
        ]);
    }
}
