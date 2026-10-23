<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Demo;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Excel;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\ActionColumn;
use Wtroiano\TableBuilder\Columns\BadgeColumn;
use Wtroiano\TableBuilder\Columns\BooleanColumn;
use Wtroiano\TableBuilder\Columns\DateColumn;
use Wtroiano\TableBuilder\Columns\NumericColumn;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\TableBuilder;
use Wtroiano\TableBuilder\TableRegistry;
use Wtroiano\TableBuilder\Tables\TableEmptyState;

final class DemoTables
{
    public static function register(): void
    {
        TableRegistry::define('demo-customers', static fn (): TableBuilder => self::base('customers', Customer::class)
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name', label: 'Customer', sortable: true, searchable: true),
                TextColumn::make('company', sortable: true, searchable: true),
                TextColumn::make('email', searchable: true, visible: false),
                BadgeColumn::make('plan', sortable: true)->colors(['Enterprise' => 'purple', 'Pro' => 'blue', 'Starter' => 'gray']),
                BooleanColumn::make('active')->trueLabel('Active')->falseLabel('Inactive'),
                NumericColumn::make('revenue', label: 'Revenue', sortable: true)->precision(2)->prefix('$'),
                DateColumn::make('joined', sortable: true)->format('M j, Y'),
                ActionColumn::make(toggleable: false, alignment: 'right'),
            ])
            ->actions([
                Action::make('Deactivate')->key('deactivate')->asBulkAction()
                    ->icon('user-minus')->showLabel(false)->variant('outline')->variantColor('amber')
                    ->confirm('Deactivate customers?', 'These customers will be marked inactive. Reset the demo to restore them.', 'Deactivate', 'Cancel')
                    ->handle(static fn (Model $model): bool => $model->update(['active' => false])),
                Action::make('Delete')->key('delete')->asBulkAction()
                    ->icon('trash')->showLabel(false)->variant('outline')->variantColor('red')
                    ->confirm('Delete customers?', 'Remove these customers from the demo. Reset the demo to restore them.', 'Delete', 'Keep customers')
                    ->handle(static fn (Model $model): ?bool => $model->delete()),
            ]));

        TableRegistry::define('demo-invoices', static fn (): TableBuilder => self::base('invoices', Invoice::class)
            ->defaultSort('-number')
            ->columns([
                TextColumn::make('number', label: 'Invoice', sortable: true, searchable: true),
                TextColumn::make('company', label: 'Customer', sortable: true, searchable: true),
                BadgeColumn::make('status', sortable: true)->colors(['Paid' => 'green', 'Pending' => 'blue', 'Overdue' => 'red', 'Draft' => 'gray']),
                NumericColumn::make('amount', sortable: true)->precision(2)->prefix('$'),
                DateColumn::make('issued', sortable: true)->format('M j, Y'),
                DateColumn::make('due', sortable: true)->format('M j, Y'),
                ActionColumn::make(toggleable: false, alignment: 'right'),
            ])
            ->actions([
                Action::make('Mark paid')->key('mark-paid')->asBulkAction()
                    ->icon('check')->variant('outline')->variantColor('green')
                    ->confirm('Mark invoices paid?', 'The selected invoices will be marked paid.', 'Mark paid', 'Cancel')
                    ->handle(static fn (Model $model): bool => $model->update(['status' => 'Paid'])),
            ]));
    }

    /** @param class-string<Model> $model */
    private static function base(string $name, string $model): TableBuilder
    {
        // This isolated, loopback-only demo deliberately allows its sample data.
        return TableBuilder::make($name)->resource($model)->authorize(true)
            ->perPage(8)->perPageOptions([8, 16, 24])->stickyHeader()
            ->exports([
                Export::make('Download Excel', $name.'.xlsx')->key('xlsx')->limitToFilteredRows(),
                Export::make('Download CSV', $name.'.csv', Excel::CSV)->key('csv')->limitToFilteredRows(),
                Export::make('Export selected', $name.'-selected.xlsx')->key('selected')->limitToFilteredRows()->limitToSelectedRows(),
            ])
            ->emptyState(TableEmptyState::make('No matching '.$name, 'Try another search or reset the demo to restore the sample data.'));
    }
}
