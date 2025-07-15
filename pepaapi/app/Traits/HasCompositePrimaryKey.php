<?php

namespace App\Traits; // *** Adjust this to match your model namespace! ***

use Illuminate\Database\Eloquent\Builder;

trait HasCompositePrimaryKey
{
    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }


    public static function find2(array $ids)
    {
        $modelClass = self::class;
        $model = new $modelClass();
        $keys = $model->primaryKey;
        return $model->where(function($query) use($ids, $keys) {
            foreach ($keys as $idx => $key) {
                if (isset($ids[$key])) {
                    $query->where($key, $ids[$key]);
                } else {
                    $query->whereNull($key);
                }
            }
        })->first();
    }

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
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
        /*
        
        foreach ($this->getKeyName() as $key) {
            if ($this->$key)
                $query->where($key, '=', $this->$key);
            else
                throw new Exception(__METHOD__ . 'Missing part of the primary key: ' . $key);
        }

        return $query;*/
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