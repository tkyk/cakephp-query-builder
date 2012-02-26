<?php

App::import('Lib', 'QueryBuilder.QueryOptions');
App::import('Datasource', 'DboSource');
App::uses('Model', 'Model');
/*
Mock::generate('Model');
Mock::generate('DboSource');
 */

class TestModelForSubqueryExpressionTestCase extends Model {
    var $useTable = false;
    var $actsAs = array('QueryBuilder.QueryBuilder');

    function limitDouble($f, $num) {
        $f->limit($num * 2);
    }
}

class SubqueryExpressionTestCase extends CakeTestCase {
    var $model, $dbo;
    var $q;

	private $_beforeErrorLevel;

    function setUp() {
		parent::setUp();
		//disable E_STRICT warnigs related to Mock
		$this->_beforeErrorLevel = error_reporting();
		error_reporting(E_ALL & ~E_STRICT);

		$this->dbo = $this->getMockBuilder('DboSource')
			->disableOriginalConstructor()
			->getMock();
		$this->model = $this->getMockBuilder('Model')
			->disableOriginalConstructor()
			->getMock();
		$this->model->expects($this->any())
			->method('getDataSource')
			->will($this->returnValue($this->dbo));
        $this->q = new SubqueryExpression($this->model);
    }

	function tearDown() {
		error_reporting($this->_beforeErrorLevel);
		parent::tearDown();
	}

    function testInit() {
        $this->assertInstanceOf('QueryOptions', $this->q);
        $this->assertTrue(isset($this->q->type));
        $this->assertEquals('expression', $this->q->type);
    }

    function testGetAlias() {
        $q = $this->q;
        $this->assertSame("", $q->getAlias());

        $q->tableOrAlias('QueryName');
        $this->assertSame('QueryName', $q->getAlias());

        $q->Alias_id(3);
        $this->assertSame(array('QueryName.id' => 3),
                               $q->conditions);
    }

    function test_toSql_toString_value() {
        $options = array('table' => 'users',
                         'alias' => 'User2',
                         'fields' => 'User2.id',
                         'limit' => 10,
                         'conditions' => array('User2.status' => 'A'));
        $expectedOptions = am($this->q->subqueryDefaults,
                              $options,
                              array('fields' => array($options['fields'])));

        $sql = 'SELECT User2.id FROM users2 AS User2 ....';

		$this->dbo->expects($this->exactly(3))
			->method('buildStatement')
			->with($expectedOptions, $this->model)
			->will($this->returnValue($sql));
        $this->q
            ->table('users')
            ->alias('User2')
            ->fields('User2.id')
            ->limit(10)
            ->User2_status('A');

        $this->assertEquals($sql, $this->q->toSql());
        $this->assertEquals("(". $sql .")", $this->q->__toString());
        $this->assertEquals("(". $sql .")", $this->q->value);
    }

    function testTableOrAlias() {
        $q = $this->q;

        $this->assertNull($q->table);
        $this->assertNull($q->alias);

        $this->assertSame($q, $q->tableOrAlias('users'));
        $this->assertSame('users', $q->table);
        $this->assertNull($q->alias);

        $this->assertSame($q, $q->tableOrAlias('groups_users'));
        $this->assertSame('groups_users', $q->table);
        $this->assertNull($q->alias);

        $this->assertSame($q, $q->tableOrAlias('GroupsUser'));
        $this->assertSame('groups_users', $q->table);
        $this->assertSame('GroupsUser', $q->alias);

        
    }

    function testScope() {
        $m = new TestModelForSubqueryExpressionTestCase;
        $q = new SubqueryExpression($m);

        $this->assertSame($q, $q->limitDouble(100));
        $this->assertSame(200, $q->limit);
    }

}
