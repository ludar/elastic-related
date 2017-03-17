<?php

namespace ludar;

use Elasticsearch\ClientBuilder;

class ElasticRelated {

	private $index, $type, $client;

	// @index string index name
	// @type string document type
	// @hosts string|array elastic nodes' hosts. 127.0.0.1:9200 by default
	public function __construct($index, $type, $hosts = '') {
		$this->index = $index;
		$this->type = $type;

		$builder = ClientBuilder::create();

		if ($hosts) {
			if (!is_array($hosts)) {
				$hosts = [$hosts];
			}

			$builder->setHosts($hosts);
		}

		$this->client = $builder->build();
	}

	public function esIndexExists() {
		return $this->client->indices()->exists([
					'index' => $this->index,
		]);
	}

	public function esDropIndex() {
		return $this->client->indices()->delete([
					'index' => $this->index,
		]);
	}

	// @fields list of fields to be indexed
	public function esSetupIndex($fields, $extraFieldsMapping = [], $settings = []) {
		$properties = [];
		foreach ($fields as $field) {
			//mlt queries only work on string + term_vector indices
			$properties[$field] = [
				'type' => 'string',
				'term_vector' => 'yes',
			];
		}

		$properties = array_merge($properties, $extraFieldsMapping);

		$body = [
			'mappings' => [
				$this->type => [
					'properties' => $properties,
					'_all' => [
						'enabled' => false, // This saves some space. We don't use it anyways.
					],
				]
			],
		];

		if (count($settings) > 0) {
			$body['settings'] = $settings;
		}

		return $this->client->indices()->create([
					'index' => $this->index,
					'body' => $body,
		]);
	}

	// Index a single document
	// @fields key=>val array of fields
	public function esIndex($id, $fields) {
		return $this->client->index([
					'index' => $this->index,
					'type' => $this->type,
					'id' => $id,
					'body' => $fields,
		]);
	}

	// Bulk index documents
	public function esBulk(&$param) {
		return $this->client->bulk([
					'index' => $this->index,
					'type' => $this->type,
					'body' => $param,
		]);
	}

	// Remove a document from the index
	public function esDelete($id) {
		return $this->client->delete([
					'index' => $this->index,
					'type' => $this->type,
					'id' => $id,
		]);
	}

	// Get indexed data by document @id
	public function esGet($id) {
		return $this->client->get([
					'index' => $this->index,
					'type' => $this->type,
					'id' => $id,
		]);
	}

	// Find similar documents with a single request by fields values
	// @id document id of "like what"
	// @fields string|list of strings
	// @size number of results to return
	public function esMoreLikeThis($id, $fields, $size = 10) {
		return $this->client->search([
					'index' => $this->index,
					'type' => $this->type,
					'_source' => false,
					'body' => [
						'query' => [
							'more_like_this' => [
								'fields' => is_array($fields) ? $fields : [$fields],
								'like' => [
									'_index' => $this->index,
									'_type' => $this->type,
									'_id' => $id,
								]
								,
								'min_term_freq' => 1,
								'min_doc_freq' => 1,
							],
						],
						'size' => $size,
					],
						]
		);
	}

	// Find similar documents with combined boosted requests by fields values
	// https://www.elastic.co/guide/en/elasticsearch/reference/2.x/query-dsl-mlt-query.html#query-dsl-mlt-query
	// @id document id of "like what"
	// @fields array of [
	//			'fields' => string|array, //required
	//			'boost' => int // optional
	//		]
	// @size number of results to return
	// @tieBreaker float. Use it to boost documents matching more fields
	public function esMoreLikeThisBoosted($id, $fields, $size = 10, $tieBreaker = 0) {
		$template = [
			'like' => [
				'_index' => $this->index,
				'_type' => $this->type,
				'_id' => $id,
			],
			'min_term_freq' => 1,
			'min_doc_freq' => 1,
		];

		$queries = [];

		foreach ($fields as $_fields) {
			$mlt = array_merge($template, [
				'fields' => is_array($_fields['fields']) ? $_fields['fields'] : [$_fields['fields']],
				'boost' => isset($_fields['boost']) ? $_fields['boost'] : 1,
			]);

			$queries[] = [ 'more_like_this' => $mlt];
		}

		return $this->client->search([
					'index' => $this->index,
					'type' => $this->type,
					'_source' => false,
					'body' => [
						'query' => [
							'dis_max' => [
								'queries' => $queries,
								'tie_breaker' => $tieBreaker,
							],
						],
						'size' => $size,
					],
						]
		);
	}

	// Convert esMoreLikeThis*() result to id=>score
	public function _mltToArray($related) {
		$result = [];

		if (isset($related['hits'])) {
			$related = & $related['hits'];
			if (isset($related['hits'])) {
				foreach ($related['hits'] as $hit) {
					$result[$hit['_id']] = $hit['_score'];
				}
			}
		}

		return $result;
	}

	// Produces id=>score output for esMoreLikeThis()
	public function relatedTo($id, $fields, $size = 10) {
		return $this->_mltToArray($this->esMoreLikeThis($id, $fields, $size));
	}

	// Produces id=>score output for esMoreLikeThisBoosted()
	public function boostedRelatedTo($id, $fields, $size = 10, $tieBreaker = 0) {
		return $this->_mltToArray($this->esMoreLikeThisBoosted($id, $fields, $size, $tieBreaker));
	}

}
