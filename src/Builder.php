<?php namespace Laraplus\Data;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $updated = 0;
        $values = $this->addUpdatedAtColumn($values);

        list($values, $i18nValues) = $this->filterValues($values);

        if($values) {
            $updated += $this->noTranslationsQuery()->update($values);
        }

        if($i18nValues) {
            $updated += $this->updateI18n($i18nValues);
        }

        return $updated;
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int  $amount
     * @param  array  $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->noTranslationsQuery()->increment($column, $amount, $extra);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int  $amount
     * @param  array  $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->noTranslationsQuery()->decrement($column, $amount, $extra);
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        list($values, $i18nValues) = $this->filterValues($values);

        if($this->query->insert($values)) {
            return $this->insertI18n($i18nValues, $values[$this->model->getKeyName()]);
        }
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        list($values, $i18nValues) = $this->filterValues($values);

        if($id = $this->query->insertGetId($values, $sequence)) {
            if($this->insertI18n($i18nValues, $id)) {
                return $id;
            }
        }

        return false;
    }


    /**
     * Filters translatable values from non-translatable.
     *
     * @param array $values
     * @return array
     */
    protected function filterValues(array $values)
    {
        $attributes = $this->model->translatableAttributes();

        $translatable = [];

        foreach($attributes as $key) {
            if(array_key_exists($key, $values)) {
                $translatable[$key] = $values[$key];

                unset($values[$key]);
            }
        }

        return [$values, $translatable];
    }

    /**
     * @param array $values
     * @param mixed $key
     * @return bool
     */
    protected function insertI18n(array $values, $key)
    {
        if(count($values) == 0) {
            return true;
        }

        $values[$this->model->getForeignKey()] = $key;
        $values[$this->model->getLocaleKey()] = $this->model->getLocale();

        return $this->i18nQuery()->insert($values);
    }

    /**
     * @param array $values
     * @return bool
     */
    protected function updateI18n(array $values)
    {
        if(count($values) == 0) {
            return true;
        }

        $query = $this->i18nQuery()
            ->whereOriginal($this->model->getForeignKey(), $this->model->getKey())
            ->whereOriginal($this->model->getLocaleKey(), $this->model->getLocale());

        if($query->exists()) {
            unset($values[$this->model->getLocaleKey()]);
            return $query->update($values);
        } else {
            return $this->insertI18n($values, $this->model->getKey());
        }
    }

    /**
     * Get the query builder instance for translation table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function i18nQuery()
    {
        $query = $this->getModel()->newQueryWithoutScopes()->getQuery();

        $query->from($this->model->getI18nTable());

        return $query;
    }

    /**
     * Get the delete query instance for translation table.
     *
     * @param bool $withGlobalScopes
     * @return \Illuminate\Database\Query\Builder
     */
    protected function i18nDeleteQuery($withGlobalScopes = true)
    {
        $subQuery = $withGlobalScopes ? $this->toBase() : $this->getQuery();
        $subQuery->select($this->model->getQualifiedKeyName());

        return $this->i18nQuery()->whereIn(
            $this->model->getForeignKey(), $subQuery->pluck($this->model->getKeyName())
        );
    }

    /**
     * Get the base query without translations
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function noTranslationsQuery()
    {
        return $this->withoutGlobalScope(TranslatableScope::class)->toBase();
    }
}
