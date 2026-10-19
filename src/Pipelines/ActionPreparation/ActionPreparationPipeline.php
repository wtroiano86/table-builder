<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionPreparation;

use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\Pipes\ResolveAction;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\Pipes\ResolveSelection;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\Pipes\ResolveTable;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\Pipes\ValidateBulkSelection;

final class ActionPreparationPipeline
{
    public function prepare(Request $request): ActionRequestContext
    {
        /** @var ActionRequestContext $context */
        $context = new Pipeline()
            ->send(new ActionRequestContext($request))
            ->through([
                new ResolveTable(),
                new ResolveAction(),
                new ValidateBulkSelection(),
                new ResolveSelection(),
            ])
            ->thenReturn();

        return $context;
    }
}
