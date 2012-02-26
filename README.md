QueryBuilder Behavior
=====================================

An Object-Oriented way to build find queries.


Requirements
------------------------------

- CakePHP 2.0
- PHP 5.3 or later


Installation 
------------------------------

    cd app/Plugin
    git clone git://github.com/tkyk/cakephp-query-builder.git QueryBuilder

I recommend you to checkout a versioning tag rather than a development branch.

	cd app/Plugin/QueryBuilder
	git checkout x.y.z.w


The `finder` method
------------------------------

This behavior provides `finder` method, which encapsulates a `find` call and its options.

    $findAll = $Model->finder('all');
	$findFirst = $Model->finder('first');
	$findCustom = $Model->finder('custom_find');

You can set and update the options by assigning its properties.

    $findAll->fields = array('id', 'title');
    $findAll->conditions = array('Model.title like' => '%abc');
    $findAll->order = array('id ASC', 'created ASC');
    $findAll->limit = 10;

Then you can `invoke` the find operation.

    $results = $findAll->invoke();

And `Set::*` methods can be applied to the find results.

	// This is equivalent to Set::extract($Model->find('all', ...), '/Model/title')
	$titles = $findAll->invoke('extract', '/Model/title');


In addition to the property style assignments described above,
method chain style is also supported to set and update the options.

    $results = $Model->finder('all')
      ->fields('id', 'title')
      ->Model_title('like', '%abc')
      ->order('id ASC', 'created ASC')
      ->limit(10)
      ->invoke();


Pagination and Subquery
------------------------------

The similar methods `paginator` and `subquery` are provided for pagination and subqueries, respectively.

    // pagination
    $results = $Model->paginator($Controller->Paginator)
      ->fields('id', 'title')
      ->Model_title('like', '%abc')
      ->order('id ASC', 'created ASC')
      ->invoke();
    
     // subquery
     $subqueryStr = $Model->subquery('users', 'User2')
       ->fields('User2.id')
       ->User2_deleted(null)
       ->__toString();


