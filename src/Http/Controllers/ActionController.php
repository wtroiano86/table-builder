<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\ActionChunkPipeline;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\ActionExecutionResult;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\ActionPreparationPipeline;
use Wtroiano\TableBuilder\Query\QuerySource;
use Wtroiano\TableBuilder\Table;

final readonly class ActionController
{
    public function __construct(
        private ActionChunkPipeline $chunkPipeline,
        private ActionPreparationPipeline $preparationPipeline,
    ) {}

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $prepared = $this->preparationPipeline->prepare($request);
        $table = $prepared->table;
        $action = $prepared->action;

        $execution = new ActionExecutionResult();

        try {
            if ($prepared->isAllSelection) {
                abort_unless(
                    $this->executeAllModels($table, $action, $execution),
                    404,
                    'No matching records found.'
                );
            } else {
                $this->chunkPipeline->run($table, $action, $prepared->models, $execution);
            }

            $payload = $action->successResponse([
                'ok' => true,
                'status' => 'success',
                'action' => $action->keyName(),
                'processed' => $execution->processed,
                'skipped' => $execution->skipped,
                'results' => $execution->results,
            ]);

            if ($request->header('X-Inertia')) {
                return back()->with('table_action', $payload);
            }

            return response()->json($payload);
        } catch (Throwable $throwable) {
            throw_if($throwable instanceof HttpExceptionInterface || $throwable instanceof AuthorizationException || $throwable instanceof ValidationException, $throwable);

            report($throwable);

            $payload = $action->errorResponse($throwable, [
                'ok' => false,
                'status' => 'error',
                'action' => $action->keyName(),
                'processed' => $execution->processed,
                'skipped' => $execution->skipped,
                'message' => 'Action failed.',
            ]);

            if ($request->header('X-Inertia')) {
                return back()->withErrors([
                    'table_action' => is_array($payload) ? ($payload['message'] ?? 'Action failed.') : 'Action failed.',
                ]);
            }

            return response()->json($payload, 500);
        }
    }

    private function executeAllModels(Table $table, Action $action, ActionExecutionResult $execution): bool
    {
        $matched = false;
        $query = $table->query()->applyScopes()->withoutGlobalScopes();
        QuerySource::ensureIdentitySelected($query, $table);
        $keyName = $query->getModel()->getKeyName();
        $qualifiedKey = QuerySource::qualify($query, $keyName);

        if ($query->getQuery()->limit !== null || $query->getQuery()->offset !== null) {
            // Freeze membership before handlers can change the ordering or delete rows.
            $keys = [];
            if ($query->getQuery()->orders === null) {
                $query->orderBy($qualifiedKey);
            }

            $query->toBase()->chunk($action->chunkSize(), static function (BaseCollection $rows) use (&$keys, $keyName): void {
                array_push($keys, ...$rows->pluck($keyName)->all());
            });
            $query->getQuery()->limit = null;
            $query->getQuery()->offset = null;
            $query->whereIn($qualifiedKey, $keys);
        }

        $callback = function (Collection $chunk) use ($table, $action, $execution, &$matched): void {
            $models = array_values($chunk->all());

            $matched = true;
            $this->chunkPipeline->run($table, $action, $models, $execution);
        };

        if ($action->chunkStrategy() === 'chunkById') {
            $query->reorder()->chunkById($action->chunkSize(), $callback, $qualifiedKey, $keyName);
        } else {
            $query->chunk($action->chunkSize(), $callback);
        }

        return $matched;
    }
}
