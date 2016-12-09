<?php

$connection = new CouchbaseCluster();
$bucket = $connection->openBucket('testing');
$bucket->enableN1ql('http://127.0.0.1:8093');
$result = $bucket->query(CouchbaseN1qlQuery::fromString('SELECT * FROM testing WHERE type = "users"'));
var_dump($result->metrics['resultCount']);