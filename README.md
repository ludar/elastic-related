# elastic-related

Basic composer.json to use the package
```json
{
	"repositories": [
	{
		"type": "git",
		"url": "https://github.com/ludar/elastic-related.git"
	}	
	],
	"require": {
		"ludar/elastic-related": "dev-master"
	}
}
```

The package is a simple wrapper around elasticsearch's ```more_like_this``` query. It can be used to implement
"related things" stuff based on some **text** fields.

In the code samples below it is assumed
```php
$client = new ludar\ElasticRelated('my_index', 'my_doc_type');
```

### Sample usage

1) Setup the index
```php
$client->esSetupIndex(['tags']); // we only use one "tags" field for this example
```

> More methods to use along with ```esSetupIndex()```:
- bool ```esIndexExists()```
- ```esDropIndex()```


2) Index some documents. IDs can be numbers or strings.
```php
foreach ($docs as $doc) {
	$client->esIndex($doc['id'], [
		'tags' => implode(',', $doc['keywords']),
	]);
}
```

> More methods to deal with indexed documents:
- ```esDelete()```
- elastic_get_result ```esGet()```


3) Ask for related documents
```php
// It returns id => score array sorted by score desc
$related = $client->relatedTo($doc['id'], 'tags', 20);
```

### Multifield scenario 

Above is the most simple case: elastic index only consists of a single field.

If we need related stuff based on more than just one field we have two choices:
- either stick to the ```relatedTo()``` method like ```$client->relatedTo($doc['id'], ['field1', 'field2'], 20);```.
This way it works exactly like ```more_like_this``` on multiple fields.
- or use more sophisticated ```boostedRelatedTo()``` method which is backed by elastic ```dis_max``` query.

Here the difference is.

This time our index has three fields
```php
$client->esSetupIndex(['keywords', 'title', 'description']);
```

We fill it like this
```php
foreach ($docs as $doc) {
	$client->esIndex($doc['id'], [
		'keywords' => implode(',', $doc['keywords']),
		'title' => $doc['title'],
		'description' => $doc['description'],
	]);
}
```

And ask for related documents like this
```php
$related = $client->boostedRelatedTo($doc['id'], [
	[
		'fields' => 'keywords',
		'boost' => 4, // boost keywords matches the most
	],
	[
		'fields' => 'title',
		'boost' => 2, // boost title matches some more
	],
	[
		'fields' => 'description',
		//use default boost=1 for description matches
	]
], 20, 1);
```

The last agrument to ```boostedRelatedTo()``` (1 in the example above) is very important. It is mapped to
the ```tie_breaker``` parameter in elastic ```dis_max``` query. It additionally boosts docs with multifield matches.
Suitable boosts and tie breaker value are to be found by tries.


### Bulk indexing

Indexing docs with ```esIndex()``` might take too long. To fasten it up we can use either boring ```esBulk()``` method
or the hipstered ```ludar\ElasticBulkIndex``` wrapper class around it.

Use it like this
```php
$bulk = new ludar\ElasticBulkIndex($client, 100); // 2nd argument is the batch chunk size
foreach ($docs as $doc) {
	$bulk->index($doc['id'], [
	  // Same data you'd use in esIndex()
		'tags' => implode(',', $doc['tags']),
		'title' => $doc['title'],
	]);
}
$bulk->done(); // Finalize it
```
