<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class CompositeKeysModel extends Model
{
    /**
     * Set the keys for a select query.
     *
     * @param Builder $query
     * @return Builder
     */
    protected function setKeysForSelectQuery($query)
    {
        dd($query);
        $aa = parent::setKeysForSelectQuery($query);
        dd($aa);
        return $aa;
    }

    /**
     * Get the primary key value for a select query.
     *
     * @return mixed
     */
    protected function getKeyForSelectQuery()
    {
        $aa = parent::getKeyForSelectQuery();
        dd($aa);
        return $aa;
    }

    // FIXME: better design
    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $initial
     * @param  array  $values
     * @return Model|static
     */
    public static function updateOrNew(array $attributes, array $initial = [], array $values = [])
    {
        return tap(self::firstOrNew($attributes, $initial), function ($instance) use ($values) {
            $instance->fill($values)->save();
        });
    }

    /**
     * Set the keys for a save update query.
     * This is a fix for tables with composite keys
     * TODO: Investigate this later on
     *
     * @param  Builder  $query
     * @return Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if(!is_array($keys)){
            return parent::setKeysForSaveQuery($query);
        }

        foreach($keys as $keyName){
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     *
     * @param mixed $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if(is_null($keyName)){
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }
}
