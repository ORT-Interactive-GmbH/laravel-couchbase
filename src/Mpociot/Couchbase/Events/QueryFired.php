<?php
/**
 * Created by PhpStorm.
 * User: lukas.quast
 * Date: 01.03.18
 * Time: 10:52
 */

namespace Mpociot\Couchbase\Events;


class QueryFired
{
    /** @var string */
    protected $query;

    /** @var array */
    protected $options;

    /**
     * QueryFired constructor.
     * @param string $query
     * @param array $options
     */
    public function __construct(string $query, array $options)
    {
        $this->query = $query;
        $this->options = $options;
    }

    public function getQuery() {
        return $this->query;
    }

    public function getPositionalParams() {
        return isset($this->options['positionalParams']) ? $this->options['positionalParams'] : [];
    }

    public function getConsistency() {
        return isset($this->options['consistency']) ? $this->options['consistency'] : [];
    }

    public function isSuccessful() {
        return isset($this->options['isSuccessful']) && $this->options['isSuccessful'];
    }
}