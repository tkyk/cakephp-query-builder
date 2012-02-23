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
        $this->assertIsA($this->q, 'QueryOptions');
        $this->assertTrue(isset($this->q->type));
        $this->assertEqual('expression', $this->q->type);
    }

    function testGetAlias() {
        $q = $this->q;
        $this->assertIdentical("", $q->getAlias());

        $q->tableOrAlias('QueryName');
        $this->assertIdentical('QueryName', $q->getAlias());

        $q->Alias_id(3);
        $this->assertIdentical(array('QueryName.id' => 3),
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

        $this->assertEqual($sql, $this->q->toSql());
        $this->assertEqual("(". $sql .")", $this->q->__toString());
        $this->assertEqual("(". $sql .")", $this->q->value);
    }

    function testTableOrAlias() {
        $q = $this->q;

        $this->assertNull($q->table);
        $this->assertNull($q->alias);

        $this->assertIdentical($q, $q->tableOrAlias('users'));
        $this->assertIdentical('users', $q->table);
        $this->assertNull($q->alias);

        $this->assertIdentical($q, $q->tableOrAlias('groups_users'));
        $this->assertIdentical('groups_users', $q->table);
        $this->assertNull($q->alias);

        $this->assertIdentical($q, $q->tableOrAlias('GroupsUser'));
        $this->assertIdentical('groups_users', $q->table);
        $this->assertIdentical('GroupsUser', $q->alias);

        
    }

    function testScope() {
        $m = new TestModelForSubqueryExpressionTestCase;
        $q = new SubqueryExpression($m);

        $this->assertIdentical($q, $q->limitDouble(100));
        $this->assertIdentical(200, $q->limit);
    }

}
