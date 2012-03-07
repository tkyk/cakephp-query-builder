<?php

App::import('Behavior', 'QueryBuilder.QueryBuilder');
App::uses('Model', 'Model');
App::uses('Controller', 'Controller');
App::uses('Component', 'Controller');
App::uses('PaginatorComponent', 'Controller/Component');
/*
Mock::generate('Model', 'MockActsAsQueryBuilder', array('createQueryMethod'));
Mock::generate('Controller');
Mock::generate('QueryMethod');
 */

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
    public $f;
	public $mockActor, $mockQueryMethod, $mockComponent;

	/*
	 * Generating a class who has all the public methods defined in the Behavior
	 */
	private static function _generateActorClass() {
		$code = '
class ModelActingAsQueryBuilderStub {
	public $alias = "ModelStub";
	%s
}
';
		$methods = get_class_methods('QueryBuilderBehavior');
		$methodCode = "";
		foreach($methods as $m) {
			if(!preg_match('/^_/', $m)) {
				$methodCode.= sprintf("function %s(){}\n", $m);
			}
		}
		$code = sprintf($code, $methodCode);
		return $code;
	}

	public static function setUpBeforeClass() {
		eval(self::_generateActorClass());
	}

    function setUp() {
		parent::setUp();
        $this->f = new QueryBuilderBehavior();
		$this->mockQueryMethod = $this->getMockBuilder('QueryMethod')
			->disableOriginalConstructor()
			->getMock();
		$this->mockActor = $this->getMock('ModelActingAsQueryBuilderStub');
		$this->mockComponent = $this->getMockBuilder('PaginatorComponent')
			->disableOriginalConstructor()
			->getMock();
    }

    function tearDown() {
        ClassRegistry::flush();
		parent::tearDown();
    }

    function testInit() {
    }

    function testGetQueryOptions() {
        $model = new stdClass;
        $model->queryOptions
            = array('default' => array('fields' => array('id', 'title'),
                                       'limit' => 50),
                    'approved' => array('conditions'
                                        => array('status' => 'approved')));

        foreach($model->queryOptions as $name => $arr) {
            $this->assertEquals($arr,
                               $this->f->getQueryOptions($model, $name));
        }
    }

    function _testGetQueryOptions_Error($key, $opts) {
		$model = new TestModelForQueryBuilderTestCase();
		$model->queryOptions = $opts;

		try {
			$this->f->getQueryOptions($model, $key);
		}
		catch(QueryBuilderMissingNamedOptionsException $e) {
			$this->assertRegExp("/$key/", strval($e));
			$this->assertRegExp("/" . get_class($model) . "/", strval($e));
			return;
		}
		$this->fail("An expected exception has not been thrown.");
    }

	function testGetQueryOptions_InvalidOptionValue() {
		$key = "exist_but_noarr";
		$this->_testGetQueryOptions_Error(
			$key,
			array($key => "yyy")
		);
	}

	function testGetQueryOptions_MissingError() {
		$this->_testGetQueryOptions_Error("no_such_key", array("xxx" => "yyy"));
	}

    function testCreateQueryMethod() {
        $model = new stdClass;
        $method = 'find';
        $args = array('all', 'custom');

        $finder = $this->f->createQueryMethod($model, $method, $args);
        $this->assertInstanceOf('QueryMethod', $finder);
        $this->assertSame($method, $finder->getMethod());
        $this->assertSame($model, $finder->getTarget());
        $this->assertSame($args, $finder->getAllArguments());
    }

    function testFinder() {
        $type = 'all';
        $returnObj = new stdClass;

        $model = $this->mockActor;
		$model->expects($this->once())
			->method('createQueryMethod')
			->with('find', array($type))
			->will($this->returnValue($returnObj));

        $finder = $this->f->finder($model, $type);
        $this->assertSame($returnObj, $finder);
    }

    function testFinder_QueryOptions() {
        $type = 'all';

        $returnObj = $this->mockQueryMethod;
        $model = $this->mockActor;

        // setup Mocks
        $model->queryOptions
            = array('common' => array('limit' => 50,
                                      'order' => 'id DESC',
                                      'conditions' => 'id NOT NULL'),
                    'approved' => array('conditions' => array('status' => 'approved'),
                                        'limit' => 100));
		$model->expects($this->once())
			->method('createQueryMethod')
			->with('find', array($type))
			->will($this->returnValue($returnObj));
		$returnObj->expects($this->once())
			->method('import')
			->with(array_keys($model->queryOptions));

        $finder = $this->f->finder($model, $type, 'common', 'approved');
        $this->assertSame($returnObj, $finder);
    }

    function testFinder_Attached() {
        $model = new TestModelForQueryBuilderTestCase();

        $f = $model->finder('all')
            ->fields('id', 'title')
            ->order('id ASC')
            ->User_id(3)
            ->User_created('>', '2010-01-01')
            ->limit(20);

        $this->assertInstanceOf('QueryMethod', $f);
        $this->assertSame(array('all'), $f->args);
        $this->assertSame(array('id', 'title'), $f->fields);
        $this->assertSame('id ASC', $f->order);
        $this->assertSame(array('User.id' => 3,
                                     'User.created >' => '2010-01-01'),
                               $f->conditions);
        $this->assertSame(20, $f->limit);

        $f2 = $model->finder('first', 'more', 'approved')
            ->conditions('title IS NOT NULL');
        $this->assertInstanceOf('QueryMethod', $f2);
        $this->assertSame(array('status' => 'approved',
                                     'title IS NOT NULL'),
                               $f2->conditions);
        $this->assertSame(100, $f2->limit);
        
    }

    function testScope() {
        $model = new TestModelForQueryBuilderTestCase();

        $f = $model->finder('all')
            ->limitDouble(50)
            ->sortInCreatedAsc()
            ->approved()
            ->fields('id', 'title');

        $this->assertInstanceOf('QueryMethod', $f);
        $this->assertSame($model, $f->getScope());
        $this->assertSame(array('all'), $f->args);
        $this->assertSame(array('id', 'title'), $f->fields);
        $this->assertSame(100, $f->limit);
        $this->assertSame('created ASC', $f->order);
        $this->assertSame(array('status' => 'approved'),
                               $f->conditions);

        $f2 = $model->finder('all')->combined()->fields('id', 'title');
        $this->assertEquals($f->getOptions(), $f2->getOptions());
    }


    function testExecPaginate() {
        $c = $this->mockComponent;
        $model = new TestModelForQueryBuilderTestCase();

        $alias = $model->alias;
        $options = array('limit' => 50,
                         'order' => 'User.name ASC');

		$c->expects($this->once())
			->method('paginate')
			->with($alias)
			->will($this->returnValue(array(1,2,3)));

        $prevPaginateArr = $c->settings;
        $ret = $model->execPaginate($c, $options);
        $afterPaginateArr = $c->settings;

        $this->assertSame(array(1,2,3), $ret);

        $this->assertSame($afterPaginateArr,
                               am($prevPaginateArr,
                                  array($alias => $options)));
    }

    function testPaginator() {
        $c = $this->mockComponent;
        $returnObj = new stdClass;

        $model = $this->mockActor;
		$model->expects($this->once())
			->method('createQueryMethod')
			->with('execPaginate', array($c))
			->will($this->returnValue($returnObj));

        $finder = $this->f->paginator($model, $c);
        $this->assertSame($returnObj, $finder);
    }

    function testPaginator_queryOptions() {
        $c = $this->mockComponent;
        $returnObj = $this->mockQueryMethod;
        $model = $this->mockActor;

        // setup Mocks
        $model->queryOptions
            = array('common' => array('limit' => 50,
                                      'order' => 'id DESC',
                                      'conditions' => 'id NOT NULL'),
                    'approved' => array('conditions' => array('status' => 'approved'),
                                        'limit' => 100));

		$model->expects($this->once())
			->method('createQueryMethod')
			->with('execPaginate', array($c))
			->will($this->returnValue($returnObj));
		$returnObj->expects($this->once())
			->method('import')
			->with(array_keys($model->queryOptions));

        $finder = $this->f->paginator($model, $c, 'common', 'approved');
        $this->assertSame($returnObj, $finder);
    }

    function testPaginator_usingQueryMethod() {
        $c = $this->mockComponent;
        $model = new TestModelForQueryBuilderTestCase();

        $alias = $model->alias;
        $options = array('limit' => 50,
                         'order' => 'User.name ASC',
                         'conditions' => array('User.title like' => 'abc%'));

        //setup Mock
        $ret = array(1,2,3);
		$c->expects($this->once())
			->method('paginate')
			->with($alias)
			->will($this->returnValue($ret));

        //exec
        $prevPaginateArr = $c->settings;
        $p = $model->paginator($c)
            ->limit(50)
            ->order('User.name ASC')
            ->User_title('like', 'abc%');
        $this->assertSame($model, $p->getTarget());
        $this->assertSame($model, $p->getScope());
        $result = $p->invoke();
        $afterPaginateArr = $c->settings;

        $this->assertSame(array(1,2,3), $ret);

        $this->assertSame($afterPaginateArr,
                               am($prevPaginateArr,
                                  array($alias => $options)));
    }

    function testSubquery() {
        $model = new TestModelForQueryBuilderTestCase();

        $q = $model->subquery();
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertNull($q->table);
        $this->assertNull($q->alias);

        $q = $model->subquery('users', 'User2');
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertSame('users', $q->table);
        $this->assertSame('User2', $q->alias);

        $q = $model->subquery('User2', 'users');
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertSame('users', $q->table);
        $this->assertSame('User2', $q->alias);

        $q = $model->subquery('User2', 'User');
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertNull($q->table);
        $this->assertSame('User', $q->alias);

        $q = $model->subquery('users', 'groups_users');
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertSame('groups_users', $q->table);
        $this->assertNull($q->alias);

        $q = $model->subquery('User');
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertNull($q->table);
        $this->assertSame('User', $q->alias);

        $q = $model->subquery(null, 'User');
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertNull($q->table);
        $this->assertSame('User', $q->alias);

        $q = $model->subquery('groups_users');
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertSame('groups_users', $q->table);
        $this->assertNull($q->alias);

        $q = $model->subquery(null, 'groups_users');
        $this->assertInstanceOf('SubqueryExpression', $q);
        $this->assertSame('groups_users', $q->table);
        $this->assertNull($q->alias);

    }

}
