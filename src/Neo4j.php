<?php

namespace RockProfile\DependencyVisualiserNeo4j;

use GraphAware\Common\Result\Result;
use GraphAware\Neo4j\Client\ClientBuilder;
use GraphAware\Neo4j\Client\ClientInterface;
use RockProfile\Package\Node;
use RockProfile\Package\Package;
use RockProfile\Package\Record;
use RockProfile\Package\Relationship;
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
     * @var Result
     */
    private $queryResult = null;

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
        $this->queryResult = $this->client->run($fullQuery);
        return;
    }

    /**
     * Retrieves the dependency graph. If vendor and name are supplied the paths from the package to the dependency are
     * graphed.
     *
     * @param string $vendor
     * @param string $package
     * @return array
     */
    public function getDependencies(string $vendor = '', string $package = ''): array
    {
        $this->populateAllDependency();
        if(strlen($vendor) > 0 && strlen($package) > 0){
            $this->populateDependency($vendor, $package);
        }
        $this->run();
        return $this->parseGraphRecords($this->queryResult->records());
    }

    /**
     * Stores the query for all dependencies
     */
    private function populateAllDependency(){
        $this->queries = array('MATCH (n)-[r]->(m)
                WHERE not(n.name = "php" or m.name = "php")
                RETURN {id: id(n), package: n.vendor + "\\\\" + n.name, name: n.name, vendor: n.vendor, url: n.url, version: n.version, type:head(labels(n)), size:size((n)<--())} as source,
                {id: id(r), version: r.version, for: r.for, size: size(()-[]->()<-[r]-())} as relationships,
                {id: id(m), package: m.vendor + "\\\\" + m.name, name: m.name, vendor: m.vendor, url: m.url, version: m.version, type:head(labels(m)), size:size((m)<--())} as target');
    }

    /**
     * Stores the query for a particular dependency
     *
     * @param string $vendor
     * @param string $package
     */
    private function populateDependency(string $vendor, string $package){
        $this->queries = array('MATCH p=(start)-[rel:Requires*1..10]->(end) WHERE head(labels(start)) = "Project" AND end.vendor = "' . $vendor . '" AND end.name = "' . $package . '"
                WITH NODES(p) AS nodes
                UNWIND nodes AS n
                UNWIND nodes AS m
                MATCH path = (n)-[r]->(m)
                RETURN {id: id(n), package: n.vendor + "\\\\" + n.name, name: n.name, vendor: n.vendor, url: n.url, version: n.version, type:head(labels(n)), size:size((n)<--())} as source,
                {id: id(r), version: r.version, for: r.for, size: size(()-[]->()<-[r]-())} as relationships,
                {id: id(m), package: m.vendor + "\\\\" + m.name, name: m.name, vendor: m.vendor, url: m.url, version: m.version, type:head(labels(m)), size:size((m)<--())} as target')
        ;
    }

    /**
     * Parses response into a format that is easily output
     *
     * @param $records
     * @return array
     */
    private function parseGraphRecords($records):array{
        $results = array();
        for($i = 0; $i < count($records); $i++){
            $source = $this->buildNode($records[$i]->values()[0]);
            $relationship = $this->buildRelationship($records[$i]->values()[1]);
            $target = $this->buildNode($records[$i]->values()[2]);
            $record = new Record($source, $relationship, $target);
            $results[] = $record;
        }
        return $results;
    }

    /**
     * Builds a relationship object
     *
     * @param array $relationship
     * @return Relationship
     */
    private function buildRelationship(array $relationship): Relationship{
        $relationship = new Relationship(
            $relationship['id'],
            $relationship['size'],
            $relationship['version'],
            $relationship['for']
        );
        return $relationship;
    }

    /**
     * Builds a node object
     *
     * @param array $node
     * @return Node
     */
    private function buildNode(array $node): Node{
        $node = new Node(
            $node['id'],
            $node['size'],
            $node['vendor'],
            $node['name'],
            $node['type'],
            $node['version'],
            $node['url']
        );
        return $node;
    }

    /**
     * To satisfy interface but not currently used.
     */
    public function disconnect():void {
    }
}