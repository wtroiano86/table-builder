<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Actions;

use ArgumentCountError;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use JsonSerializable;
use Wtroiano\TableBuilder\Actions\Concerns\ConfiguresAction;
use Wtroiano\TableBuilder\Actions\Concerns\ExecutesAction;
use Wtroiano\TableBuilder\Actions\Concerns\SerializesAction;
use Wtroiano\TableBuilder\Concerns\HasFactoryAlias;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Support\Url;

final class Action implements JsonSerializable
{
    use Conditionable;
    use ConfiguresAction;
    use ExecutesAction;
    use HasFactoryAlias;
    use SerializesAction;

    private string|Closure $name;

    private ?string $key = null;

    /**
     * @var string|Url|Closure|array<array-key, mixed>|null
     */
    private string|Url|Closure|array|null $url = null;

    private ?Closure $handle = null;

    private bool $authorize = true;

    private bool $disabled = false;

    private bool $hidden = false;

    private bool $bulk = false;

    private bool $onlyBulk = false;

    private int $chunkSize = 100;

    private string $chunkStrategy = 'chunkById';

    private bool $collectResults = true;

    private ?Closure $before = null;

    private ?Closure $after = null;

    private ?Closure $success = null;

    private ?Closure $error = null;

    private ?string $icon = null;

    private ?string $tooltip = null;

    private bool $showLabel = true;

    private ?string $variant = null;

    private ?string $variantColor = null;

    private ?string $class = null;

    /**
     * @var bool|array<array-key, mixed>|Closure
     */
    private bool|array|Closure $confirm = false;

    /**
     * @var array<array-key, mixed>
     */
    private array $meta = [];

    /**
     * @var array<array-key, mixed>
     */
    private array $data = [];

    public function __construct(string|callable $name)
    {
        $this->name = is_string($name) ? $name : Closure::fromCallable($name);
    }

    public static function make(string|callable $name): self
    {
        throw_if(func_num_args() > 1, ArgumentCountError::class, 'Use fluent methods for additional factory configuration.');

        if (is_string($name) && $name === '') {
            throw TableBuilderException::actionNameRequired();
        }

        return new self($name);
    }

    public function keyName(): string
    {
        return $this->key ?? (is_string($this->name) ? Str::slug($this->name) : 'action');
    }
}
