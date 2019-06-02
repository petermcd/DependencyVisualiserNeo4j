<?php

namespace RockProfile\DependencyVisualiserNeo4j;

use GraphAware\Neo4j\Client\ClientBuilder;
use GraphAware\Neo4j\Client\ClientInterface;
use RockProfile\Package\Package;
use RockProfile\Storage\StorageInterface;
/**
 * Class Neo4j
 * @package RockProfile\Storage
 */
class Neo4j implements StorageInterface
{
    /**
     * Stores the Neo4j client.
     *
     * @var ClientInterface
     */
    private $client;

    /**
     * Array to store the generated queries.
     *
     * @var array
     */
    private $queries = array();

    /**
     * Neo4j constructor. Creates the client object with the given credentials.
     *
     * @param string $url
     * @param string $username
     * @param string $password
     */
    public function __construct(string $url, string $username, string $password)
    {
        $this->client = ClientBuilder::create()
            ->addConnection('bolt', 'bolt://' . $username . ':' . $password . '@' . $url)
            ->build();
    }

    /**
     * Generates and adds the query for the given package.
     *
     * @param string $id
     * @param Package $package
     */
    public function addRecord(string $id, Package $package):void {

        $query = 'CREATE (a'. $id .':'. $package->getType() .'{vendor: "' . $package->getVendor() . '", name:"' . $package->getName() . '", url: "' . $package->getURL() . '", version: "'. $package->getVersion() .'"})';
        $this->queries[] = $query;
    }

    /**
     * Generates and adds the query for the given relationship.
     *
     * @param array $relation
     */
    public function addRelation(array $relation): void{
        $query = 'CREATE (a' . $relation['package'] . ')-[r' . $relation['package'] . $relation['requires'] . ':Requires{version: "' . $relation['version'] . '", for: "'. $relation['for'] .'"}]->(a'. $relation['requires'] .')';
        $this->queries[] = $query;
    }

    /**
     * Currently adds executes all queries.
     */
    public function run():void
    {
        $fullQuery = '';
        foreach ($this->queries AS $query) {
            $fullQuery .= $query . "\r\n";
        }
        $this->client->run($fullQuery);
    }

    /**
     * To satisfy interface but not currently used.
     */
    public function disconnect():void {
    }
}