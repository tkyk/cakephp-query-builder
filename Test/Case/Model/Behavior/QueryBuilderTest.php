<?php

App::import('Behavior', 'QueryBuilder.QueryBuilder');
Mock::generate('Model', 'MockActsAsQueryBuilder', array('createQueryMethod'));
Mock::generate('Controller');
Mock::generate('QueryMethod');

class TestModelForQueryBuilderTestCase extends Model {
    var $useTable = false;
    var $actsAs = array('QueryBuilder.QueryBuilder');
    var $queryOptions
        = array('default' => array('limit' => 10),
                'more' => array('limit' => 100),
                'normal_order' => array('order' => 'created ASC'),
                'approved' => array('conditions'
                                    => array('status' => 'approved')));

    function limitDouble($f, $num) {
        $f->limit($num * 2);
    }

    function sortInCreatedAsc($f) {
        $f->order('created ASC');
    }

    function approved($f) {
        $f->_status('approved');
    }

    function combined($f) {
        $f->limitDouble(50)->sortInCreatedAsc()->approved();
    }
}

class QueryBuilderTestCase extends CakeTestCase {
    var $f;

    function startTest() {
        $this->f = new QueryBuilderBehavior();
    }

    function endTest() {
        ClassRegistry::flush();
    }

    function testInit() {
    }

    function testGetQueryOptions() {
        $model = new stdClass;
        $model->queryOptions
            = array('default' => array('fields' => a('id', 'title'),
                                       'limit' => 50),
                    'approved' => array('conditions'
                                        => array('status' => 'approved')));

        foreach($model->queryOptions as $name => $arr) {
            $this->assertEqual($arr,
                               $this->f->getQueryOptions($model, $name));
        }
    }

    function testGetQueryOptions_Error() {
        $key = 'no_such_key';

        $expectedError = $this->f->_missingOptionsError($key);

        $model = new MockActsAsQueryBuilder;
        $model->expectCallCount('cakeError', 3);
        $model->expect('cakeError', array('error', $expectedError));

        $this->assertNull($this->f->getQueryOptions($model, $key));

        $model->queryOptions = array('foo' => 'bar');
        $this->assertNull($this->f->getQueryOptions($model, $key));

        // found but not an array
        $model->queryOptions = array($key => null);
        $this->assertNull($this->f->getQueryOptions($model, $key));
    }

    function testCreateQueryMethod() {
        $model = new stdClass;
        $method = 'find';
        $args = array('all', 'custom');

        $finder = $this->f->createQueryMethod($model, $method, $args);
        $this->assertIsA($finder, 'QueryMethod');
        $this->assertIdentical($method, $finder->getMethod());
        $this->assertIdentical($model, $finder->getTarget());
        $this->assertIdentical($args, $finder->getAllArguments());
    }

    function testFinder() {
        $type = 'all';
        $returnObj = new stdClass;

        $model = new MockActsAsQueryBuilder;
        $model->expectOnce('createQueryMethod',
                           array('find', array($type)));
        $model->setReturnValue('createQueryMethod', $returnObj);

        $finder = $this->f->finder($model, $type);
        $this->assertIdentical($returnObj, $finder);
    }

    function testFinder_QueryOptions() {
        $type = 'all';

        $returnObj = new MockQueryMethod;
        $model = new MockActsAsQueryBuilder;

        // setup Mocks
        $model->queryOptions
            = array('common' => array('limit' => 50,
                                      'order' => 'id DESC',
                                      'conditions' => 'id NOT NULL'),
                    'approved' => array('conditions' => array('status' => 'approved'),
                                        'limit' => 100));
        $model->expectOnce('createQueryMethod',
                           array('find', array($type)));
        $model->setReturnValue('createQueryMethod', $returnObj);

        $returnObj->expectOnce('import', array(array_keys($model->queryOptions)));


        $finder = $this->f->finder($model, $type, 'common', 'approved');
        $this->assertIdentical($returnObj, $finder);
    }

    function testFinder_Attached() {
        $model = new TestModelForQueryBuilderTestCase();

        $f = $model->finder('all')
            ->fields('id', 'title')
            ->order('id ASC')
            ->User_id(3)
            ->User_created('>', '2010-01-01')
            ->limit(20);

        $this->assertIsA($f, 'QueryMethod');
        $this->assertIdentical(array('all'), $f->args);
        $this->assertIdentical(array('id', 'title'), $f->fields);
        $this->assertIdentical('id ASC', $f->order);
        $this->assertIdentical(array('User.id' => 3,
                                     'User.created >' => '2010-01-01'),
                               $f->conditions);
        $this->assertIdentical(20, $f->limit);

        $f2 = $model->finder('first', 'more', 'approved')
            ->conditions('title IS NOT NULL');
        $this->assertIsA($f2, 'QueryMethod');
        $this->assertIdentical(array('status' => 'approved',
                                     'title IS NOT NULL'),
                               $f2->conditions);
        $this->assertIdentical(100, $f2->limit);
        
    }

    function testScope() {
        $model = new TestModelForQueryBuilderTestCase();

        $f = $model->finder('all')
            ->limitDouble(50)
            ->sortInCreatedAsc()
            ->approved()
            ->fields('id', 'title');

        $this->assertIsA($f, 'QueryMethod');
        $this->assertIdentical($model, $f->getScope());
        $this->assertIdentical(array('all'), $f->args);
        $this->assertIdentical(array('id', 'title'), $f->fields);
        $this->assertIdentical(100, $f->limit);
        $this->assertIdentical('created ASC', $f->order);
        $this->assertIdentical(array('status' => 'approved'),
                               $f->conditions);

        $f2 = $model->finder('all')->combined()->fields('id', 'title');
        $this->assertEqual($f->getOptions(), $f2->getOptions());
    }


    function testExecPaginate() {
        $c = new MockController();
        $model = new TestModelForQueryBuilderTestCase();

        $alias = $model->alias;
        $options = array('limit' => 50,
                         'order' => 'User.name ASC');

        $c->expectOnce('paginate', array($alias));
        $c->setReturnValue('paginate', array(1,2,3), array($alias));

        $prevPaginateArr = $c->paginate;
        $ret = $model->execPaginate($c, $options);
        $afterPaginateArr = $c->paginate;

        $this->assertIdentical(array(1,2,3), $ret);

        $this->assertIdentical($afterPaginateArr,
                               am($prevPaginateArr,
                                  array($alias => $options)));
    }

    function testPaginator() {
        $c = new MockController;
        $returnObj = new stdClass;

        $model = new MockActsAsQueryBuilder;
        $model->expectOnce('createQueryMethod',
                           array('execPaginate', array($c)));
        $model->setReturnValue('createQueryMethod', $returnObj);

        $finder = $this->f->paginator($model, $c);
        $this->assertIdentical($returnObj, $finder);
    }

    function testPaginator_queryOptions() {
        $c = new MockController;
        $returnObj = new MockQueryMethod;
        $model = new MockActsAsQueryBuilder;

        // setup Mocks
        $model->queryOptions
            = array('common' => array('limit' => 50,
                                      'order' => 'id DESC',
                                      'conditions' => 'id NOT NULL'),
                    'approved' => array('conditions' => array('status' => 'approved'),
                                        'limit' => 100));

        $model->expectOnce('createQueryMethod',
                           array('execPaginate', array($c)));
        $model->setReturnValue('createQueryMethod', $returnObj);

        $returnObj->expectOnce('import', array(array_keys($model->queryOptions)));

        $finder = $this->f->paginator($model, $c, 'common', 'approved');
        $this->assertIdentical($returnObj, $finder);
    }

    function testPaginator_usingQueryMethod() {
        $c = new MockController();
        $model = new TestModelForQueryBuilderTestCase();

        $alias = $model->alias;
        $options = array('limit' => 50,
                         'order' => 'User.name ASC',
                         'conditions' => array('User.title like' => 'abc%'));

        //setup Mock
        $ret = array(1,2,3);
        $c->expectOnce('paginate', array($alias));
        $c->setReturnValue('paginate', $ret, array($alias));

        //exec
        $prevPaginateArr = $c->paginate;
        $p = $model->paginator($c)
            ->limit(50)
            ->order('User.name ASC')
            ->User_title('like', 'abc%');
        $this->assertIdentical($model, $p->getTarget());
        $this->assertIdentical($model, $p->getScope());
        $result = $p->invoke();
        $afterPaginateArr = $c->paginate;

        $this->assertIdentical(array(1,2,3), $ret);

        $this->assertIdentical($afterPaginateArr,
                               am($prevPaginateArr,
                                  array($alias => $options)));
    }

    function testSubquery() {
        $model = new TestModelForQueryBuilderTestCase();

        $q = $model->subquery();
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertNull($q->table);
        $this->assertNull($q->alias);

        $q = $model->subquery('users', 'User2');
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertIdentical('users', $q->table);
        $this->assertIdentical('User2', $q->alias);

        $q = $model->subquery('User2', 'users');
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertIdentical('users', $q->table);
        $this->assertIdentical('User2', $q->alias);

        $q = $model->subquery('User2', 'User');
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertNull($q->table);
        $this->assertIdentical('User', $q->alias);

        $q = $model->subquery('users', 'groups_users');
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertIdentical('groups_users', $q->table);
        $this->assertNull($q->alias);

        $q = $model->subquery('User');
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertNull($q->table);
        $this->assertIdentical('User', $q->alias);

        $q = $model->subquery(null, 'User');
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertNull($q->table);
        $this->assertIdentical('User', $q->alias);

        $q = $model->subquery('groups_users');
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertIdentical('groups_users', $q->table);
        $this->assertNull($q->alias);

        $q = $model->subquery(null, 'groups_users');
        $this->assertIsA($q, 'SubqueryExpression');
        $this->assertIdentical('groups_users', $q->table);
        $this->assertNull($q->alias);

    }

}
