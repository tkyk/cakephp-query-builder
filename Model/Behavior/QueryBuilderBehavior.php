<?php
/**
 * QueryBuilder Behavior
 * providing a step-by-step interface to build find options.
 * 
 * <code>
 * // objectified find
 * $Model->finder('all')
 *   ->fields('id', 'title')
 *   ->Model_group_id(100)
 *   ->Model_title('LIKE', 'var')
 *   ->order('created DESC')
 *   ->limit(10)
 *   ->invoke();
 *
 * // objectified paginate
 * $Model->paginator($controller)
 *   ->fields('id', 'title')
 *   ->conditions('title IS NOT NULL')
 *   ->order('id ASC', 'title ASC')
 *   ->invoke();
 * 
 * // subquery object
 * $q = $Model->subquery('users', 'User2')
 *   ->fields('id')
 *   ->User2_created('>' $time);
 * </code>
 * 
 * @package QueryBuilder
 * @copyright Copyright 2010, Takayuki Miwa http://github.com/tkyk/
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

App::import('Lib', 'QueryBuilder.QueryOptions');

/**
 * QueryBuilderBehavior class
 * 
 * @package QueryBuilder
 */
class QueryBuilderBehavior extends ModelBehavior {

    /**
     * @var array
     */
    public $errorTemplate = array('code' => 500,
                                  'name' => 'Missing Query Options');

    /**
     * This method is public but not available through the Models.
     * 
     * @param string  config name
     */
    public function _missingOptionsError($name) {
        return $this->errorTemplate + array('message' => $name);
    }

    /**
     * Returns a query options array named $name
     * 
     * @param string  option name
     * @return array
     */
    public function getQueryOptions($model, $name) {
        if(!isset($model->queryOptions) ||
           !isset($model->queryOptions[$name]) ||
           !is_array($model->queryOptions[$name])) {
            $model->cakeError('error',
                              $this->_missingOptionsError($name));
            return;
        }
        return $model->queryOptions[$name];
    }

    /**
     * An 'Objectified' version of find method.
     * 
     * <code>
     * $finder = $Model->finder('all')->fields(...)->conditions(...);
     * 
     * // using query options
     * $finder = $Model->finder('all', 'approved', 'normal_order')->limit(...);
     * </code>
     * 
     * @param object  Model
     * @param string  find type
     * @return QueryMethod
     */
    public function finder($model, $type) {
        $finder = $model->createQueryMethod('find', array($type));
        if(func_num_args() > 2) {
            $args = func_get_args();
            $finder->import(array_slice($args, 2));
        }
        return $finder;
    }

    /**
     * Creates a new QueryMethod object bound to the $model.
     * 
     * @param object  Model
     * @param string  method name
     * @param array   arguments
     * @return QueryMethod
     */
    public function createQueryMethod($model, $method, $args=array()) {
        return new QueryMethod($model, $method, $args);
    }

    /**
     * Wrapper method for the Controller->paginate method.
     * 
     * @param object  Controller
     * @param array   find-compatible options array
     * @return mixed
     */
    public function execPaginate($model, $controller, $options=array()) {
        $controller->paginate[$model->alias] = $options;
        return $controller->paginate($model->alias);
    }

    /**
     * An 'objectified' version of execPaginate method.
     * 
     * @param object  Model
     * @param object  Controller
     * @return QueryMethod
     */
    public function paginator($model, $controller) {
        $paginator = $model->createQueryMethod('execPaginate', array($controller));
        if(func_num_args() > 2) {
            $args = func_get_args();
            $paginator->import(array_slice($args, 2));
        }
        return $paginator;
    }

    /**
     * Creates a new SubqueryExpression object.
     * 
     * @param object  Model
     * @param string  [optional] table name or alias
     * @param string  [optional] table name or alias
     * @return object  SubqueryExpression
     */
    public function subquery($model, $tableOrAlias=null, $tableOrAlias2=null) {
        $exp = new SubqueryExpression($model);
        if(is_string($tableOrAlias)) {
            $exp->tableOrAlias($tableOrAlias);
        }
        if(is_string($tableOrAlias2)) {
            $exp->tableOrAlias($tableOrAlias2);
        }
        return $exp;
    }
}

