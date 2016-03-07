<?php

namespace Spatie\Relatable;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property \Illuminate\Support\Collection $related
 * @property \Illuminate\Support\Collection $relatables
 */
trait HasRelatedContent
{
    /** @var \Illuminate\Support\Collection|null */
    protected $relatableCache;

    public function relatables() : MorphMany
    {
        return $this->morphMany(Relatable::class, 'source');
    }

    /**
     * Returns a of all related models.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRelatedAttribute() : Collection
    {
        if ($this->relatableCache === null) {
            $this->loadRelated();
        }

        return $this->relatableCache;
    }

    public function loadRelated() : Collection
    {
        $this->load('relatables');

        return $this->relatableCache = $this->relatables
            ->groupBy(function (Relatable $relatable) {
                return $this->getActualClassNameForMorph($relatable->related_type);
            })
            ->flatMap(function (Collection $typeGroup, string $type) {
                return $type::whereIn('id', $typeGroup->pluck('related_id'))->get();
            });
    }

    public function hasRelated() : bool
    {
        return ! $this->related->isEmpty();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int $item
     * @param string|null $type
     *
     * @return \Spatie\Relatable\Relatable
     */
    public function relate($item, string $type = '') : Relatable
    {
        return Relatable::firstOrCreate(
            $this->getRelatableValues($item, $type)
        );
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int $item
     * @param string|null $type
     *
     * @return int
     */
    public function unrelate($item, string $type = '') : int
    {
        return Relatable::where($this->getRelatableValues($item, $type))->delete();
    }

    /**
     * The `$items` parameter can either contain an Eloquent collection of models, or an array
     * with the shape of [['id' => int, 'type' => string], ...].
     *
     * I'll gladly accept PR's optimizing queries here!
     *
     * @param \Illuminate\Database\Eloquent\Collection|array $items
     * @param bool $detaching
     */
    public function syncRelated($items, $detaching = true)
    {
        if ($items instanceof Collection) {
            $items = $items
                ->map(function (Model $item) : array {
                    return [
                        'type' => $item->getMorphClass(),
                        'id' => $item->getKey(),
                    ];
                });
        }

        $items = collect($items);

        $current = $this->relatables->map(function (Relatable $relatable) {
            return [
                'type' => $relatable->related_type,
                'id' => $relatable->related_id,
            ];
        });

        $items
            ->each(function (array $values) {
                $this->relate($values['id'], $values['type']);
            });

        if ($detaching) {
            $current
                ->filter(function (array $values) use ($items) {
                    return ! $items->contains($values);
                })
                ->each(function (array $values) {
                    $this->unrelate($values['id'], $values['type']);
                });
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int $item
     * @param string|null $type
     *
     * @return array
     */
    protected function getRelatableValues($item, string $type = '') : array
    {
        if (! $item instanceof Model && empty($type)) {
            throw new \InvalidArgumentException(
                'If an id is specified as an item, the type isn\'t allowed to be empty.'
            );
        }

        return [
            'source_id' => $this->getKey(),
            'source_type' => $this->getMorphClass(),
            'related_id' => $item instanceof Model ? $item->getKey() : $item,
            'related_type' => $item instanceof Model ? $item->getMorphClass() : $type,
        ];
    }
}
