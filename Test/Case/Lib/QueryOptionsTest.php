<?php

App::import('Lib', 'QueryBuilder.QueryOptions');
Mock::generate('Object');

class QueryOptionsTestCase extends CakeTestCase {
    var $options;

    function startTest() {
        $this->options = new QueryOptions();
    }

    function endTest() {
        ClassRegistry::flush();
    }

    function testInit() {
        $this->assertEqual(array(), $this->options->getOptions());
    }

    function testSetOption() {
        $a = $this->options;

        $params = array('order' => 'id DESC',
                        'limit' => 100,
                        'custom' => 'foobarzoo');
        foreach($params as $k => $v) {
            $this->assertIdentical($a, $a->setOption($k, $v));
        }
        $this->assertEqual($params, $a->getOptions());


        $params['order'] = 'created ASC';
        $a->setOption('order', $params['order']);
        $this->assertEqual($params, $a->getOptions());
    }

    function testSetOption_Array() {
        $a = $this->options;

        $params = array('order' => array('id DESC'),
                        'limit' => array(100),
                        'custom' => array('foobarzoo'));
        foreach($params as $k => $v) {
            $a->setOption($k, $v);
        }
        $expected = array('order' => $params['order'][0],
                          'limit' => $params['limit'][0],
                          'custom' => $params['custom'][0],);
        $this->assertEqual($expected, $a->getOptions());


        $arrOrder = array('created ASC', 'updated DESC');
        $a->setOption('order', $arrOrder);
        $expected['order'] = $arrOrder;
        $this->assertEqual($expected, $a->getOptions());


        $arrContain = array('User' => 'Group');
        $a->setOption('contain', $arrContain);
        $expected['contain'] = $arrContain;
        $this->assertEqual($expected, $a->getOptions());


        $arrContain = array('User');
        $a->setOption('contain', $arrContain);
        $expected['contain'] = $arrContain[0];
        $this->assertEqual($expected, $a->getOptions());


        $arrContain = array('User');
        $a->setOption('contain', $arrContain, false);
        $expected['contain'] = $arrContain;
        $this->assertEqual($expected, $a->getOptions());
    }

    function testClearOptions() {
        $a = $this->options;

        $this->assertIdentical($a, $a->clearOptions());
        $this->assertIdentical(array(), $a->getOptions());

        $a->setOption('key', 'value');

        $this->assertEqual(1, count($a->getOptions()));
        $this->assertIdentical($a, $a->clearOptions());
        $this->assertIdentical(array(), $a->getOptions());
    }

    function testAddOption() {
        $a = $this->options;

        $one = 'one is NULL';
        $this->assertIdentical($a, $a->addOption('conditions', $one));
        $expected = array('conditions' => $one);
        $this->assertEqual($expected, $a->getOptions());


        $two = 'two is not NULL';
        $a->addOption('conditions', $two);
        $expected = array('conditions' => array($one, $two));
        $this->assertEqual($expected, $a->getOptions());


        $three = 'three is not NULL';
        $a->addOption('conditions', $three);
        $expected = array('conditions' => array($one, $two, $three));
        $this->assertEqual($expected, $a->getOptions());
    }

    function testAddOption_Array() {
        $a = $this->options;

        $one = 'one is NULL';
        $a->addOption('conditions', $one);
        $expected = array('conditions' => $one);
        $this->assertEqual($expected, $a->getOptions());


        $two = array('two is not NULL', 'two = 1');
        $a->addOption('conditions', $two);
        $expected = array('conditions' => am(array($one), $two));
        $this->assertEqual($expected, $a->getOptions());


        $three = array('three' => 100);
        $a->addOption('conditions', $three);
        $expected['conditions'] = am(array($one), $two, $three);
        $this->assertEqual($expected, $a->getOptions());

        // clears
        $a->clearOptions();
        $this->assertEqual(array(), $a->getOptions());


        $a->addOption('conditions', $three);
        $expected['conditions'] = $three;
        $this->assertEqual($expected, $a->getOptions());


        $a->addOption('conditions', $one);
        $expected['conditions'] = am($three, array($one));
        $this->assertEqual($expected, $a->getOptions());

    }

    function testAddOption_Array_SingleElement() {
        $a = $this->options;

        $one = 'id IS NULL';
        $or = array('OR' => array('created IS NULL', 'b' => 123));


        $expected = array('conditions' => null);

        // --- enable unwrap
        // without array
        $a->addOption('conditions', $one);
        $expected['conditions'] = $one;
        $this->assertEqual($expected, $a->getOptions());

        $a->addOption('conditions', $or);
        $expected['conditions'] = am(array($one), $or);
        $this->assertEqual($expected, $a->getOptions());

        // with array == without array
        $a->clearOptions();
        $a->addOption('conditions', a($one));
        $expected['conditions'] = $one;
        $this->assertEqual($expected, $a->getOptions());

        $a->addOption('conditions', a($or));
        $expected['conditions'] = am(array($one), $or);
        $this->assertEqual($expected, $a->getOptions());

        // -- disable unwrapping
        // without array
        $a->clearOptions();
        $a->addOption('conditions', $one, false);
        $expected['conditions'] = $one;
        $this->assertEqual($expected, $a->getOptions());

        $a->addOption('conditions', $or, false);
        $expected['conditions'] = am(a($one), $or);
        $this->assertEqual($expected, $a->getOptions());

        // with array
        $a->clearOptions();
        $a->addOption('conditions', a($one), false);
        $expected['conditions'] = a($one);
        $this->assertEqual($expected, $a->getOptions());

        $a->addOption('conditions', a($or), false);
        $expected['conditions'] = am(a($one), a($or));
        $this->assertEqual($expected, $a->getOptions());

    }

    function testRemoveOption() {
        $this->_testRemoveOption(true);
    }
    function testOverloadedUnset() {
        $this->_testRemoveOption(false);
    }

    function _testRemoveOption($useMethod) {
        $a = $this->options;
        $options = array('conditions' => 'deleted is null',
                         'order' => array('id asc', 'created desc'));

        $this->assertEqual(array(), $a->getOptions());

        $a->setOption('conditions', $options['conditions']);
        $a->setOption('order', $options['order']);
        $this->assertEqual($options, $a->getOptions());

        if($useMethod) {
            $this->assertIdentical($a,
                                   $a
                                   ->removeOption('conditions')
                                   ->removeOption('order')
                                   ->removeOption('no_such_key'));
        } else {
            $this->assertTrue(isset($a->conditions));
            unset($a->conditions);
            $this->assertFalse(isset($a->conditions));

            $this->assertTrue(isset($a->order));
            unset($a->order);
            $this->assertFalse(isset($a->order));

            $this->assertFalse(isset($a->no_such_key));
            unset($a->no_such_key);
            $this->assertFalse(isset($a->no_such_key));
        }

        $this->assertEqual(array(), $a->getOptions());
    }

    function testAddCondition() {
        $a = $this->options;

        $this->assertIdentical($a, $a->addCondition('id', 5));
        $a->addCondition('Foo.active', true);
        $a->addCondition('group_id', array(1,2,3));

        $this->assertEqual(array('conditions'
                                 => array('id' => 5,
                                          'Foo.active' => true,
                                          'group_id' => array(1,2,3))),
                           $a->getOptions());


        //override
        $a->addCondition('id', 100);
        $this->assertEqual(array('conditions'
                                 => array('id' => 100,
                                          'Foo.active' => true,
                                          'group_id' => array(1,2,3))),
                           $a->getOptions());
    }

    function testAddCondition_Operator() {
        $a = $this->options;

        $a->addCondition('id', '>', 5);
        $a->addCondition('Foo.active', '!=', true);
        $a->addCondition('group_id', 'NOT IN',  array(1,2,3));

        $this->assertEqual(array('conditions'
                                 => array('id >' => 5,
                                          'Foo.active !=' => true,
                                          'group_id NOT IN' => array(1,2,3))),
                           $a->getOptions());
        
    }

    function testAddCondition_Null() {
        $a = $this->options;

        $a->addCondition('id', null);
        $a->addOption('conditions', 'Foo.active IS NOT NULL');
        $a->addOption('conditions', 'group_id IS NULL');

        $this->assertEqual(array('conditions'
                                 => array('id IS NULL',
                                          'Foo.active IS NOT NULL',
                                          'group_id IS NULL')),
                           $a->getOptions());
        
    }

    function testConditions() {
        $a = $this->options;

        $a  ->conditions('id = 1',
                         'Foo.active IS NOT NULL')
            ->conditions('group_id IS NULL')
            ->conditions(array('created >=' => '2010-01-01'));

        $this->assertEqual(array('conditions'
                                 => array('id = 1',
                                          'Foo.active IS NOT NULL',
                                          'group_id IS NULL',
                                          'created >=' => '2010-01-01')),
                           $a->getOptions());
        
    }

    function testFields() {
        $a = $this->options;
        
        $a  ->fields('a', 'b', 'c')
            ->fields(array('d', 'e', 'f'));

        $this->assertEqual(array('fields'
                                 => array('a', 'b', 'c', 'd', 'e', 'f')),
                           $a->getOptions());

        $a->clearOptions();
        $this->assertEqual(array('fields' => array('a')),
                           $a->fields('a')->getOptions());
        
    }

    function testModelFields() {
        $a = $this->options;
        
        $a  ->modelFields('Post', a('id', 'foo_bar', 'col AS alias'))
            ->modelFields('Post', a('(1)', 'SUM(e)', 'Group.name', "'('"));

        $this->assertEqual(array('fields'
                                 => array('Post.id', 'Post.foo_bar', 'Post.col AS alias',
                                          '(1)', 'SUM(e)', 'Group.name', "'('")),
                           $a->getOptions());
        
    }

    function testJoin() {
        $a = $this->options;

        $join1 = array('type' => 'inner',
                       'alias' => 'User',
                       'table' => 'users',
                       'conditions' => array('A.user_id = User.id'));
        $join2 = array('type' => 'left',
                       'alias' => 'Group',
                       'table' => 'groups',
                       'conditions' => 'User.group_id = Group.id');
        
        $a->joins($join1, $join2);

        $expected = array('joins'
                          => array($join1, $join2));

        $this->assertEqual($expected, $a->getOptions());

        $a->clearOptions();

        $a->joins($join1)->joins($join2);
        $this->assertEqual($expected, $a->getOptions());
    }

    function testCall_SetOption() {
        $a = $this->options;

        $values = array('recursive' => true,
                        'fields' => true,
                        'order' => true,
                        'group' => true,
                        'limit' => true,
                        'page' => true,
                        'offset' => true,
                        'callbacks' => true,
                        'contain' => true,);

        foreach($values as $k => $v) {
            $this->assertIdentical($a, $a->{$k}($v));
        }

        $expected = $values;
        $this->assertEqual($expected, $a->getOptions());
    }

    function testGetAlias() {
        $a = $this->options;

        // always ""
        $this->assertIdentical("", $a->getAlias());

        $a->Alias_id(3);
        $this->assertEqual(array('id' => 3), $a->conditions);
    }

    function testIsKeyDefined_and_Call() {
        $a = $this->options;

        // predefined keys
        $this->assertTrue($a->isKeyDefined('recursive'));
        $a->recursive('a');
        $this->assertIdentical('a', $a->recursive);

        // custom keys
        $this->assertFalse($a->isKeyDefined('custom'));
        $this->assertIdentical($a, $a->custom('foo'));
        $this->assertNull($a->custom);
        //__get creates key
        $this->assertTrue($a->isKeyDefined('custom'));

        // 
        $a->custom2 = 'foo';
        $this->assertTrue($a->isKeyDefined('custom2'));
        $this->assertIdentical('foo', $a->custom2);
        $this->assertIdentical($a, $a->custom2('bar'));
        $this->assertIdentical('bar', $a->custom2);
    }

    function testCall_Condition() {
        $a = $this->options;

        $a  ->Post_id(3)
            ->Post_foo_bar(null)
            ->Group_id('>', 3)
            ->Group_name('!=', "xxx")
            ->conditions(array('or' => array('a is null',
                                             'a' => '')))
            ->_id(a(1,2,3))
            ->_title_field("NOT IN", a('xxx', 'yyy'));

        $conditions = array('Post.id' => 3,
                            'Post.foo_bar IS NULL',
                            'Group.id >' => 3,
                            'Group.name !=' => "xxx",
                            'or' => array('a is null',
                                          'a' => ''),
                            'id' => array(1,2,3),
                            'title_field NOT IN' => array('xxx', 'yyy'));

        $expected = compact('conditions');
        $this->assertEqual($expected, $a->getOptions());

    }

    function testCall_Fields() {
        $a = $this->options;

        $a  ->fields_Post('id', 'foo_bar', 'title AS t', 'SUM(x)')
            ->fields('id', 'foo_bar');

        $fields = array('Post.id',
                        'Post.foo_bar',
                        'Post.title AS t',
                        'SUM(x)',
                        'id',
                        'foo_bar');

        $expected = compact('fields');
        $this->assertEqual($expected, $a->getOptions());

    }

    function testOverloadedSet() {
        $this->_testOverloadedSet(false);
    }
    function testOverloadedSet_setOption() {
        $this->_testOverloadedSet(true);
    }

    function _testOverloadedSet($useMethod) {
        $a = $this->options;

        $fields = array('id', 'title', 'body');
        $limit = 100;
        $conditions = array('name IS NULL');

        if($useMethod) {
            $a
                ->setOption('fields', $fields, false)
                ->setOption('limit', $limit, false)
                ->setOption('conditions', $conditions, false);
        } else {
            $a->fields = $fields;
            $a->limit = $limit + 100;
            $a->limit = $limit;
            $a->conditions = $conditions;
        }

        $expected = compact('fields', 'limit', 'conditions');
        $this->assertEqual($expected, $a->getOptions());
    }

    function testOverloadedGet() {
        $a = $this->options;

        $fields = array('id', 'title', 'body');
        $limit = 100;
        $conditions = array('name IS NULL');

        $a  ->setOption('fields', $fields, false)
            ->setOption('limit', $limit, false)
            ->setOption('conditions', $conditions, false);

        $this->assertIdentical($fields, $a->fields);
        $this->assertIdentical($limit, $a->limit);
        $this->assertIdentical($conditions, $a->conditions);

        // key is automatically created
        $this->assertNull($a->no_such_key);

        $expected = am(compact('fields', 'limit', 'conditions'),
                       array('no_such_key' => null));
        $this->assertEqual($expected, $a->getOptions());


        //update
        $a->conditions[] = 'title IS NOT NULL';
        $a->conditions['body LIKE'] = 'foo%';

        $expected['conditions']
            = am($conditions,
                 array('title IS NOT NULL', 'body LIKE' => 'foo%'));
        $this->assertEqual($expected, $a->getOptions());
    }

    function testImportArray() {
        $a = $this->options;
        
        $fields = array('id', 'title', 'body');
        $limit = 100;
        $conditions = array('name IS NULL');

        $a->importArray(compact('fields', 'limit', 'conditions'));

        $expected = compact('fields', 'limit', 'conditions');
        $this->assertEqual($expected, $a->getOptions());

        $a->importArray(array('conditions' => array('id >' => 3)));

        $conditions['id >'] = 3;
        $expected = compact('fields', 'limit', 'conditions');
        $this->assertEqual($expected, $a->getOptions());
        
    }

    function testMerge() {
        $a = $this->options;

        $a->fields = array('a', 'b', 'c');
        $a->group = "User.id";
        $a->jointo = 'Post';
        $a->conditions = array('User.id' => array(1,2,3));

        $b = new QueryOptions();
        $b->fields = array('d', 'e', 'f');
        $b->limit = 100;
        $b->conditions = array('Post.published_at IS NOT NULL',
                               'Post.published_at <=' => '2010-01-01');

        $c = array('limit' => 200,
                   'order' => 'Post.updated DESC',
                   'conditions' => array('User.active' => 1));

        $beforeB = $b->getOptions();
        $this->assertIdentical($a, $a->merge($b, $c));
        $afterB = $b->getOptions();
        $this->assertIdentical($beforeB, $afterB);

        $this->assertIdentical(array('a', 'b', 'c', 'd', 'e', 'f'),
                               $a->fields);
        $this->assertIdentical('Post', $a->jointo);
        $this->assertIdentical('User.id', $a->group);
        $this->assertIdentical(200, $a->limit);
        $this->assertIdentical(array('User.id' => array(1,2,3),
                                     'Post.published_at IS NOT NULL',
                                     'Post.published_at <=' => '2010-01-01',
                                     'User.active' => 1),
                               $a->conditions);
        
    }

}
