<?php

App::import('Behavior', 'QueryBuilder.QueryBuilder');
App::uses('Model', 'Model');
App::uses('Controller', 'Controller');
App::uses('Router', 'Routing');

class TestBaseModelForDbQueryBuilderTestCase extends Model {
    var $recursive = -1;

    function insert() {
        $args = func_get_args();
        $rows = array();
        foreach($args as $str) {
            $pairs = explode(' ', $str);
            $row = array();
            foreach($pairs as $p) {
                list($key, $val) = explode(':', $p, 2);
                $row[$key] = $val;
            }
            $rows[] = array($this->alias => $row);
        }
        return $this->saveAll($rows);
    }
}

class TestCategoryForDbQueryBuilderTestCase
extends TestBaseModelForDbQueryBuilderTestCase {
    var $name = 'Category';
    var $actsAs = array('QueryBuilder.QueryBuilder');

    var $queryOptions =
        array('startsWithP' => array('conditions' => array('Category.name LIKE' => 'P%')),
              'flagOn' => array('conditions' => array('Category.flag' => 1)),
              'limit2' => array('limit' => 2));

    function startsWith($f, $char) {
        $f->Alias_name('like', "{$char}%");
    }

    function flagOn($f) {
        $f->Alias_flag(1);
    }

    function limit2($f) {
        $f->limit(2);
    }
}

class TestPostForDbQueryBuilderTestCase
extends TestBaseModelForDbQueryBuilderTestCase {
    var $name = 'Post';
    var $actsAs = array('Containable', 'QueryBuilder.QueryBuilder');
    var $belongsTo = array('Category');

    var $queryOptions =
        array('withCategory' => array('contain' => 'Category'));

    function withCategory($f) {
        $f->contain('Category');
    }
}

class TestControllerForQueryBuilderTestCase extends Controller {
	public $components = array('Paginator');
	public $paginate = array();
}

class DbQueryBuilderTestCase extends CakeTestCase {
    var $fixtures = array('plugin.query_builder.post', 'plugin.query_builder.category');
    var $Post, $Category;


    function setUp() {
		parent::setUp();
        $this->Category =
            ClassRegistry::init(array('class' => 'TestCategoryForDbQueryBuilderTestCase',
                                      'alias' => 'Category',
                                      'type' => 'Model'));

        $this->Post = 
            ClassRegistry::init(array('class' => 'TestPostForDbQueryBuilderTestCase',
                                      'alias' => 'Post',
                                      'type' => 'Model'));
		Router::connect('/:controller/:action/*');
    }

    function tearDown() {
        ClassRegistry::flush();
        unset($this->Post, $this->Category);
		parent::tearDown();
    }

	protected function _getController($url) {
		$parseEnvironment = false; // Do not use $_GET, $_POST, etc.
		$request = new CakeRequest($url, $parseEnvironment);
		$params = Router::parse($request->url);
		$request->addParams($params);

		$ctrl = new TestControllerForQueryBuilderTestCase($request);
		$ctrl->constructClasses();
		return $ctrl;
	}

    function _insertDefaultCategories() {
        $this->Category
            ->insert('name:PHP flag:1',
                     'name:Perl flag:0',
                     'name:Ruby flag:1',
                     'name:Python flag:1'
                     );
    }

    function testInit() {
        $this->assertSame(0, $this->Post->find('count'));
        $this->assertSame(0, $this->Category->find('count'));

        $this->assertTrue($this->Post->Behaviors->attached('QueryBuilder'));
        $this->assertTrue($this->Category->Behaviors->attached('QueryBuilder'));
    }

    function testFinder() {
        $C = $this->Category;
        $this->_insertDefaultCategories();

        $this->assertSame(4, $C->finder('count')->invoke());
        $this->assertSame(4, $C->find('count'));

        $ret = $C->finder('first')->Category_name('PHP')->invoke();
        $this->assertEquals('PHP', $ret['Category']['name']);
        $this->assertEquals(1, $ret['Category']['flag']);
        

        $ret = $C->finder('all')
            ->Category_name('like', 'P%')
            ->order('id DESC')
            ->invoke('extract', '/Category/name');
        $this->assertEquals(array('Python', 'Perl', 'PHP'), $ret);

        $ret = $C->finder('list')
            ->fields_Category('name', 'flag')
            ->addCondition('CHAR_LENGTH(name)', 4)
            ->Alias_flag(array(0,1))
            ->order('name DESC')
            ->invoke();
        $this->assertEquals(array('Ruby' => 1, 'Perl' => 0), $ret);

        $ret = $C->finder('all')
            ->fields('Category.flag', 'COUNT(*) AS cnt')
            ->group('flag')
            ->order('flag ASC')
            ->limit(1)
            ->invoke();
        
        $this->assertEquals(array(array('Category' => array('flag' => 0),
                                       0 => array('cnt' => 1))),
                           $ret);


        $counter = $C->finder('count');
        $this->assertSame(1, $counter->Category_flag(0)->invoke());
        $counter->conditions['Category.flag'] = 1;
        $this->assertSame(3, $counter->invoke());
        $this->assertSame(2, $counter->_name('like', 'P%')->invoke());
    }

    function testPaginator() {
        $C = $this->Category;
        foreach(range('A', 'Z') as $i => $char) {
            $C->create(array('name' => $char,
                             'flag' => $i % 3 == 0));
            $C->save();
        }
        $this->assertEquals(26, $C->find('count'));

        $cntl = $this->_getController('/foo/bar');
        $cntl->loadModel('Category');

        $paginator = $C->paginator($cntl->Paginator)
            ->limit(3)
            ->order('name DESC');
        $ret = $paginator->invoke('extract', '/Category/name');
        $this->assertEquals(array('Z', 'Y', 'X'), $ret);


		$cntl->request->params['named']['page'] = 2;
        $ret = $paginator->invoke('extract', '/Category/name');
        $this->assertEquals(array('W', 'V', 'U'), $ret);


        $ret = $C->paginator($cntl->Paginator)
            ->Alias_flag(1)
            ->limit(3)
            ->order('name ASC')
            ->invoke('extract', '/Category/name');
        $this->assertEquals(array('J', 'M', 'P'), $ret);
    }

    function testSubquery() {
        $C = $this->Category;
        $P = $this->Post;

        $this->_insertDefaultCategories();
        $ids = $C->finder('list')->fields('name', 'id')->invoke();

        $P->insert("title:CakePHP category_id:{$ids['PHP']} created:2010-01-01",
                   "title:Symfony category_id:{$ids['PHP']} created:2010-01-02",
                   "title:RubyOnRails category_id:{$ids['Ruby']} created:2010-01-03",
                   "title:Sinatra category_id:{$ids['Ruby']} created:2010-01-04",
                   "title:XXXXX created:2010-01-04 deleted:2010-01-05",
                   "title:Django category_id:{$ids['Python']} created:2010-01-04"
                   );

        // Schalar operand
        $phpCate = $C->subquery('categories', 'Category')
            ->fields('Category.id')
            ->Alias_name('PHP')
            ->limit(1);

        $cnt = $P->finder('count')
            ->Post_category_id($phpCate)
            ->invoke();
        $this->assertEquals(2, $cnt);


        // IN
        $phpOrPython = $C->subquery('categories', 'Category')
            ->fields('Category.id')
            ->Alias_name(array('PHP', 'Python'));

        $cnt = $P->finder('count')
            ->conditions("Post.category_id IN ". $phpOrPython)
            ->invoke();
        $this->assertEquals(3, $cnt);


        // ALL and joins
        $phpCreated = $P->subquery('posts', 'Post2')
            ->fields('Post2.created')
            ->joins(array('table' => 'categories',
                          'alias' => 'Category',
                          'type' => 'LEFT',
                          'conditions' => 'Post2.category_id = Category.id'))
            ->Category_name('PHP');
        
        $newerThanPHPPosts = $P->finder('all')
            ->Alias_deleted(null)
            ->conditions("Post.created > ALL ". $phpCreated)
            ->order('Post.title ASC')
            ->invoke('extract', '/Post/title');

        $this->assertEquals(array('Django', 'RubyOnRails', 'Sinatra'), $newerThanPHPPosts);


        // Correlated subqueries using EXISTS
        $hasPostsCategories = $C->finder('all')
            ->fields('DISTINCT Category.name')
            ->conditions('EXISTS '.
                         $P->subquery('posts', 'Post')
                         ->fields('*')
                         ->conditions('Post.category_id = Category.id'))
            ->order('Category.name ASC')
            ->invoke('extract', '/Category/name');

        $this->assertEquals(array('PHP', 'Python', 'Ruby'), $hasPostsCategories);


        // Subqueries in FROM clause
        $query = $P->subquery('OuterQuery')
            ->fields('MAX(cnt_col) AS max_posts');

        $query->table =
            $P->subquery('posts', 'InnerQuery')
            ->fields('COUNT(*) AS cnt_col')
            ->InnerQuery_deleted(null)
            ->group('InnerQuery.category_id')
            ->__toString();
        $ret = $C->query($query->toSql());

        $this->assertEquals(array(array(0 => array('max_posts' => 2))),
                           $ret);
    }

    function testQueryOptions() {
        $C = $this->Category;
        $P = $this->Post;

        $this->_insertDefaultCategories();
        $ids = $C->finder('list')->fields('name', 'id')->invoke();
        $P->insert("title:PHP5.3 created:2010-02-01 category_id:{$ids['PHP']}",
                   "title:Python3 created:2010-02-02 category_id:{$ids['Python']}");

        // This causes a cakeError to prevent users from executing queries without enough conditions.
        //$C->finder('all', 'no_such_option');

        $ret = $C->finder('all', 'startsWithP')
            ->order('name ASC')
            ->invoke('extract', '/Category/name');
        $this->assertEquals(array('Perl', 'PHP', 'Python'), $ret);

        $ret = $C->finder('all', 'startsWithP', 'flagOn')
            ->order('name ASC')
            ->invoke('extract', '/Category/name');
        $this->assertEquals(array('PHP', 'Python'), $ret);

        $ret = $C->finder('all', 'startsWithP')
            ->order('name ASC')
            ->import('limit2')
            ->invoke('extract', '/Category/name');
        $this->assertEquals(array('Perl', 'PHP'), $ret);

        
        $ret = $P->finder('all', 'withCategory')->order('Post.created DESC')->invoke();
        $expected = array(array('Category' => array('id' => $ids['Python'],
                                                'name' => 'Python'),
                            'Post' => array('title' => 'Python3',
                                            'created' => '2010-02-02')),
                      array('Category' => array('id' => $ids['PHP'],
                                                'name' => 'PHP'),
                            'Post' => array('title' => 'PHP5.3',
                                            'created' => '2010-02-01'))
                      );
        foreach($expected as $i => $row) {
            foreach($row as $model => $values) {
                foreach($values as $k => $v) {
                    $this->assertEquals($v, $ret[$i][$model][$k]);
                }
            }
        }

    }

    function testScope() {
        $C = $this->Category;
        $P = $this->Post;

        $this->_insertDefaultCategories();
        $ids = $C->finder('list')->fields('name', 'id')->invoke();
        $P->insert("title:PHP5.3 created:2010-02-01 category_id:{$ids['PHP']}",
                   "title:Python3 created:2010-02-02 category_id:{$ids['Python']}");

        // This causes a cakeError to prevent users from executing queries without enough conditions.
        //$C->finder('all', 'no_such_option');

        $ret = $C->finder('all')
            ->startsWith('P')
            ->order('name ASC')
            ->invoke('extract', '/Category/name');
        $this->assertEquals(array('Perl', 'PHP', 'Python'), $ret);

        $ret = $C->finder('all')
            ->startsWith('P')
            ->flagOn()
            ->order('name ASC')
            ->invoke('extract', '/Category/name');
        $this->assertEquals(array('PHP', 'Python'), $ret);

        $ret = $C->finder('all')
            ->startsWith('P')
            ->order('name ASC')
            ->limit2()
            ->invoke('extract', '/Category/name');
        $this->assertEquals(array('Perl', 'PHP'), $ret);

        
        $ret = $P->finder('all')->withCategory()->order('Post.created DESC')->invoke();
        $expected = array(array('Category' => array('id' => $ids['Python'],
                                                'name' => 'Python'),
                            'Post' => array('title' => 'Python3',
                                            'created' => '2010-02-02')),
                      array('Category' => array('id' => $ids['PHP'],
                                                'name' => 'PHP'),
                            'Post' => array('title' => 'PHP5.3',
                                            'created' => '2010-02-01'))
                      );
        foreach($expected as $i => $row) {
            foreach($row as $model => $values) {
                foreach($values as $k => $v) {
                    $this->assertEquals($v, $ret[$i][$model][$k]);
                }
            }
        }

    }


}
