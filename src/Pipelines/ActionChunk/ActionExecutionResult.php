<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionChunk;

final class ActionExecutionResult
{
    /** @var list<mixed> */
    public array $results = [];

    public int $processed = 0;

    public int $skipped = 0;
}
