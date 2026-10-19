<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionPreparation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Http\Requests\ActionRequest;
use Wtroiano\TableBuilder\Table;

final class ActionRequestContext
{
    public readonly ActionRequest $input;

    // Resolved in order by ResolveTable and ResolveAction.
    public Table $table;

    public Action $action;

    public bool $isAllSelection = false;

    /** @var array<array-key, mixed> */
    public array $requestedKeys = [];

    /** @var list<Model> */
    public array $models = [];

    public function __construct(public readonly Request $request)
    {
        $this->input = ActionRequest::fromRequest($request);
    }
}
