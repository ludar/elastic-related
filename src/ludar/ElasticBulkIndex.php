<?php

namespace ludar;

// Bulk indexing helper
// Sample usage:
//	$client = new ElasticRelated('index', 'type');
//	$bulk = new ElasticBulkIndex($client, 100);
//	foreach ($docs as $doc) {
//		$bulk->index($doc['id'], [
//			'tags' => implode(',', $doc['tags']),
//			'title' => $doc['title'],
//		]);
//	}
//	$bulk->done();
class ElasticBulkIndex {

	private $client, $chunk, $param, $n;
	private $callbackIssue = null;
	private $refresh = "false";

	// @chunk int bulk chunk size
	public function __construct(ElasticRelated &$client, $chunk, $callbackIssue = null, $refresh = "false") {
		$this->client = $client;
		$this->chunk = $chunk;
		$this->refresh = $refresh;
		$this->reset();

		if (is_callable($callbackIssue)) {
			$this->callbackIssue = $callbackIssue;
		}
	}

	public function reset() {
		$this->param = [];
		$this->n = 0;
	}

	// Index one document
	// @id doc id
	// @data array of field=>value
	public function index($id, $data) {
		// Collect data ...
		$this->param[] = [
			'index' => [
				'_id' => $id,
			]
		];
		$this->param[] = $data;

		// ... until we have $this->chunk documents
		if (++$this->n >= $this->chunk)
			$this->issue();
	}

	// Bulk index collected docs
	protected function issue() {
		if (!$this->param)
			return;

		$result = $this->client->esBulk($this->param, $this->refresh);
		if (!is_null($this->callbackIssue)) {
			call_user_func($this->callbackIssue, $result);
		}

		$this->reset();
	}

	// Just a public alias with a reasonable name
	public function done() {
		$this->issue();
	}

}
