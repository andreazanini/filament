<?php

namespace Filament\Tables\Filters;

use Closure;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class ToggleFilter extends BaseFilter
{
    use Concerns\HasOptions;
    use Concerns\HasRelationship;

    protected string | Closure | null $attribute = null;

    protected bool | Closure $isMultiple = false;

    protected bool | Closure $isGrouped = false;

    protected bool | Closure $isNative = true;

    protected bool | Closure $isStatic = false;

    protected bool | Closure $isSearchable = false;

    protected array | Closure $extraAttributes = [];

    protected int | Closure $optionsLimit = 50;

    protected bool | Closure | null $isSearchForcedCaseInsensitive = null;

    protected ?Closure $getOptionLabelFromRecordUsing = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indicateUsing(function (ToggleFilter $filter, array $state): array {
            if ($filter->isMultiple()) {
                if (blank($state['values'] ?? null)) {
                    return [];
                }

                if ($filter->queriesRelationships()) {
                    $relationshipQuery = $filter->getRelationshipQuery();

                    $labels = $relationshipQuery
                        ->when(
                            $filter->getRelationship() instanceof \Znck\Eloquent\Relations\BelongsToThrough,
                            fn (Builder $query) => $query->distinct(),
                        )
                        ->when(
                            $this->getRelationshipKey(),
                            fn (Builder $query, string $relationshipKey) => $query->whereIn($relationshipKey, $state['values']),
                            fn (Builder $query) => $query->whereKey($state['values'])
                        )
                        ->pluck($relationshipQuery->qualifyColumn($filter->getRelationshipTitleAttribute()))
                        ->all();
                } else {
                    $labels = Arr::only($filter->getOptions(), $state['values']);
                }

                if (! count($labels)) {
                    return [];
                }

                $labels = collect($labels)->join(', ', ' & ');

                $indicator = $filter->getIndicator();

                if (! $indicator instanceof Indicator) {
                    $indicator = Indicator::make("{$indicator}: {$labels}");
                }

                return [$indicator];
            }

            if (blank($state['value'] ?? null)) {
                return [];
            }

            if ($filter->queriesRelationships()) {
                $label = $filter->getRelationshipQuery()
                    ->when(
                        $this->getRelationshipKey(),
                        fn (Builder $query, string $relationshipKey) => $query->where($relationshipKey, $state['value']),
                        fn (Builder $query) => $query->whereKey($state['value'])
                    )
                    ->first()
                    ?->getAttributeValue($filter->getRelationshipTitleAttribute());
            } else {
                $label = $filter->getOptions()[$state['value']] ?? null;
            }

            if (blank($label)) {
                return [];
            }

            $indicator = $filter->getIndicator();

            if (! $indicator instanceof Indicator) {
                $indicator = Indicator::make("{$indicator}: {$label}");
            }

            return [$indicator];
        });

        $this->resetState(['value' => null]);
    }

    public function getActiveCount(): int
    {
        $state = $this->getState();

        return filled($this->isMultiple() ? ($state['values'] ?? []) : ($state['value'] ?? null)) ? 1 : 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(Builder $query, array $data = []): Builder
    {
        if ($this->evaluate($this->isStatic)) {
            return $query;
        }

        if ($this->hasQueryModificationCallback()) {
            return parent::apply($query, $data);
        }

        $isMultiple = $this->isMultiple();

        $values = $isMultiple ?
            $data['values'] ?? null :
            $data['value'] ?? null;

        if (blank(Arr::first(
            Arr::wrap($values),
            fn ($value) => filled($value),
        ))) {
            return $query;
        }

        if (! $this->queriesRelationships()) {
            return $query->{$isMultiple ? 'whereIn' : 'where'}(
                $this->getAttribute(),
                $values,
            );
        }

        return $query->whereHas(
            $this->getRelationshipName(),
            function (Builder $query) use ($isMultiple, $values) {
                if ($this->modifyRelationshipQueryUsing) {
                    $query = $this->evaluate($this->modifyRelationshipQueryUsing, [
                        'query' => $query,
                    ]) ?? $query;
                }

                if ($relationshipKey = $this->getRelationshipKey($query)) {
                    return $query->{$isMultiple ? 'whereIn' : 'where'}(
                        $relationshipKey,
                        $values,
                    );
                }

                return $query->whereKey($values);
            },
        );
    }

    public function attribute(string | Closure | null $name): static
    {
        $this->attribute = $name;

        return $this;
    }

    /**
     * @deprecated Use `attribute()` instead.
     */
    public function column(string | Closure | null $name): static
    {
        $this->attribute($name);

        return $this;
    }

    public function static(bool | Closure $condition = true): static
    {
        $this->isStatic = $condition;

        return $this;
    }

    public function multiple(bool | Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function grouped(bool | Closure $condition = true): static
    {
        $this->isGrouped = $condition;

        return $this;
    }

    public function extraAttributes(array | Closure $condition = []): static
    {
        $this->extraAttributes = $condition;

        return $this;
    }

    public function getAttribute(): string
    {
        return $this->evaluate($this->attribute) ?? $this->getName();
    }

    /**
     * @deprecated Use `getAttribute()` instead.
     */
    public function getColumn(): string
    {
        return $this->getAttribute();
    }

    public function forceSearchCaseInsensitive(bool | Closure | null $condition = true): static
    {
        $this->isSearchForcedCaseInsensitive = $condition;

        return $this;
    }

    public function isSearchForcedCaseInsensitive(): ?bool
    {
        return $this->evaluate($this->isSearchForcedCaseInsensitive);
    }

    public function getFormField(): ToggleButtons
    {
        $field = ToggleButtons::make($this->isMultiple() ? 'values' : 'value')
            ->label($this->getLabel())
            ->extraAttributes($this->extraAttributes,true)
            ->multiple($this->isMultiple());

        if($this->isGrouped)
            $field->grouped();

        if ($this->queriesRelationships()) {
            $field
                ->relationship(
                    $this->getRelationshipName(),
                    $this->getRelationshipTitleAttribute(),
                    $this->modifyRelationshipQueryUsing,
                )
                ->forceSearchCaseInsensitive($this->isSearchForcedCaseInsensitive());
        } else {
            $field->options($this->getOptions());
        }

        if ($this->getOptionLabelUsing) {
            $field->getOptionLabelUsing($this->getOptionLabelUsing);
        }

        if ($this->getOptionLabelsUsing) {
            $field->getOptionLabelsUsing($this->getOptionLabelsUsing);
        }

        if ($this->getOptionLabelFromRecordUsing) {
            $field->getOptionLabelFromRecordUsing($this->getOptionLabelFromRecordUsing);
        }

        if ($this->getSearchResultsUsing) {
            $field->getSearchResultsUsing($this->getSearchResultsUsing);
        }

        if (filled($defaultState = $this->getDefaultState())) {
            $field->default($defaultState);
        }

        return $field;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function isGrouped(): bool
    {
        return (bool) $this->evaluate($this->isGrouped);
    }

    
    public function getExtraAttributes(): array
    {
        return (array) $this->evaluate($this->extraAttributes);
    }

    public function getOptionLabelFromRecordUsing(?Closure $callback): static
    {
        $this->getOptionLabelFromRecordUsing = $callback;

        return $this;
    }
}
