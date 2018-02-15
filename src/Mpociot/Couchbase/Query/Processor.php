<?php namespace Mpociot\Couchbase\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    /**
     * Process the results of a "select" query.
     *
     * @param  Builder $query
     * @param  \stdClass $results
     * @return \stdClass
     */
    public function processSelectWithMeta(Builder $query, $results)
    {
        return $results;
    }
}
