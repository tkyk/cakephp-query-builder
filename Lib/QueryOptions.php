<?php
/**
 * QueryOptions class
 * representing the find-style option array.
 * 
 * <code>
 * //fluent interface
 *   $finder->fields('id', 'title')
 *          ->Post_title('LIKE', '%foo')
 *          ->order('id ASC')
 *          ->limit(30);
 * 
 * //property interface (for more complex cases)
 *   $finder->fields = array('id', 'title');
 *   $finder->conditions = array(...);
 *   $finder->conditions['Post.title LIKE'] = '%foo';
 * </code>
 * 
 * @package QueryBuilder
 * @copyright Copyright 2010, Takayuki Miwa http://github.com/tkyk/
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */
class QueryOptions extends Object {

    /**
     * @var array
     */
    protected $_options = array();

    /**
     * @var array  key => unwrapArray
     */
    protected $_appendKeys = array('conditions' => true,
                                   'joins' => false);

    /**
     * @var array  key => any
     */
    protected $_definedKeys
        = array('recursive' => true,
                'fields' => true,
                'order' => true,
                'group' => true,
                'limit' => true,
                'page' => true,
                'offset' => true,
                'callbacks' => true,
                'contain' => true,);

    /**
     * Checks if given key is already defined.
     * 
     * @param string
     * @return boolean
     */
    public function isKeyDefined($key) {
        return array_key_exists($key, $this->_options)
            || array_key_exists($key, $this->_definedKeys)
            || array_key_exists($key, $this->_appendKeys);
    }

    /**
     * Called from __call when the method is not a defined keys.
     * 
     * @param string  method name
     * @param array   arguments to __call
     * @return object
     */
    protected function _keyNotDefined($method, $args) {
        return $this;
    }

    /**
     * Returns current options
     * 
     * @return array 
     */
    public function getOptions() {
        return $this->_options;
    }

    /**
     * Returns the only element
     * if $values is numeric array and contain the only one element.
     * 
     * @param array
     * @return mixed
     */
    protected function _unwrapArray($values) {
        return (is_array($values) && count($values) == 1
                && array_key_exists(0, $values))
            ? $values[0] : $values;
    }

    /**
     * Sets an option associated with $key.
     * 
     * @param string   option key
     * @param mixed    option value
     * @param boolean  
     * @return object  QueryOptions
     */
    public function setOption($key, $values, $unWrap=true) {
        $this->_options[$key] = $unWrap ? $this->_unwrapArray($values) : $values;
        return $this;
    }

    /**
     * Clears all the options.
     * 
     * @return object QueryOptions
     */
    public function clearOptions() {
        $this->_options = array();
        return $this;
    }

    /**
     * Appends an option $values to the current one whose key is $key.
     * 
     * @param string  option key
     * @param mixed   option value
     * @return object  QueryOptions
     */
    public function addOption($key, $values, $unWrap=true) {
        if(!array_key_exists($key, $this->_options)) {
            return $this->setOption($key, $values, $unWrap);
        }

        if(!is_array($this->_options[$key])) {
            $this->_options[$key] = array($this->_options[$key]);
        }

        if(is_array($values)) {
            $values = $unWrap ? $this->_unwrapArray($values) : $values;
            $this->_options[$key] = am($this->_options[$key], $values);
        } else {
            $this->_options[$key][] = $values;
        }
        return $this;
    }

    /**
     * Removes the specified key from options.
     * 
     * @param string
     */
    public function removeOption($key) {
        if(array_key_exists($key, $this->_options)) {
            unset($this->_options[$key]);
        }
        return $this;
    }

    /**
     * Adds an condition to options[conditions].
     * 
     * @param string  $key
     * @param mixed   $value or $operator
     * @param mixed   [Optional] $value if the second parameter is $operator
     * @return object  QueryOptions
     */
    public function addCondition($key, $value) {
        if(func_num_args() == 3) {
            list($key, $op, $value) = func_get_args();
            $key = "$key $op";
        } elseif(is_null($value)) {
            return $this->addOption('conditions', "$key IS NULL");
        }
        return $this->addOption('conditions', array($key => $value));
    }

    /**
     * Appends fields with $model prefix.
     * 
     * @param string  model name
     * @param array   field names
     * @return object QueryOptions
     */
    public function modelFields($model, $args) {
        $fields = array();
        foreach($args as $f) {
            if(strpos($f, '(') !== false || strpos($f, '.') !== false) {
                $fields[] = $f;
            } else {
                $fields[] = $model .".". $f;
            }
        }
        return $this->fields($fields);
    }

    /**
     * Imports options from array
     * Deprecated: use merge instead.
     * 
     * @deprecated
     * @see QueryOptions::merge
     * @param array
     * @return object QueryOptions
     */
    public function importArray($arr) {
        return $this->merge($arr);
    }

    /**
     * Merges other QueryOptions or arrays
     *
     * @param array or QueryOptions
     * @return object QueryOptions
     */
    public function merge() {
        $args = func_get_args();
        foreach($args as $opts) {
            if($opts instanceof QueryOptions) {
                $opts = $opts->getOptions();
            }
            foreach($opts as $k => $v) {
                $this->{$k}($v);
            }
        }
        return $this;
    }

    /**
     * Updates the 'fields' option
     * ensuring that the 'fields' option is always an array.
     * 
     * @return object  QueryOptions
     */
    public function fields() {
        $args = func_get_args();
        $this->addOption('fields', $args, true);
        if(!is_array($this->_options['fields'])) {
            $this->_options['fields'] = array($this->_options['fields']);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getAlias() {
        return "";
    }

    /**
     * __call; Magic method
     * 
     * @param string  method name
     * @param array   arguments to the method
     * @return object
     */
    public function __call($method, $args) {
        switch(true) {
        case preg_match('/^([A-Z][a-zA-Z0-9]*)?_([a-zA-Z0-9_]+)$/', $method, $m):
            $alias = $m[1];
            if($alias == 'Alias') {
                $alias = strval($this->getAlias());
            }
            $model = empty($alias) ? "" : $alias .".";
            $field = $model . $m[2];
            return count($args) == 2 ? 
                $this->addCondition($field, $args[0], $args[1]) :
                $this->addCondition($field, $args[0]);

        case preg_match('/^fields_([A-Z][a-zA-Z0-9]*)$/', $method, $m):
            return $this->modelFields($m[1], $args);

        case $this->isKeyDefined($method):
            if(isset($this->_appendKeys[$method])) {
                return $this->addOption($method, $args, $this->_appendKeys[$method]);
            } else {
                return $this->setOption($method, $args);
            }
        }
        return $this->_keyNotDefined($method, $args);
    }

    /**
     * __unset; Magic method
     * same as removeOption
     * 
     * @param string  $key
     */
    public function __unset($key) {
        $this->removeOption($key);
    }

    /**
     * __isset; Magic Method
     *
     * @param string  option key
     * @return boolean
     */
    public function __isset($key) {
        return isset($this->_options[$key]);
    }

    /**
     * __set; Magic method
     * same as setOption($key, $value, false);
     * 
     * @param string  option key
     * @param mixed   option value
     */
    public function __set($key, $value) {
        $this->setOption($key, $value, false);
    }

    /**
     * __get; Magic method
     *
     * @param string  option key
     * @return mixed  reference
     */
    public function &__get($key) {
        if(!array_key_exists($key, $this->_options)) {
            $this->_options[$key] = null;
        }
        return $this->_options[$key];
    }
}


/**
 * ScopedQueryOptions
 * 
 */
class ScopedQueryOptions extends QueryOptions {

    /**
     * @var object  Object
     */
    protected $_scope;

    /**
     * @param object  Object
     */
    public function __construct($scope) {
        $this->_scope = $scope;
    }

    /**
     * @return object
     */
    public function getScope() {
        return $this->_scope;
    }

    /**
     * @override
     */
    protected function _keyNotDefined($method, $args) {
        array_unshift($args, $this);
        $this->_scope->dispatchMethod($method, $args);
        return $this;
    }
}


/**
 * QueryMethod class
 * representing the 'find' method bound to the Model.
 * 
 * @package Finder
 */
class QueryMethod extends ScopedQueryOptions {
    /**
     * @var object  Model; acts as QueryBuilder
     */
    protected $_target;

    /**
     * @var string
     */
    protected $_method;

    /**
     * @var array
     */
    public $args;

    /**
     * String (class name) 'Set' or 
     * Mock object for testing.
     * 
     * @var mixed  object or string
     */
    public $receiverSet = 'Set';

    /**
     * Constructor
     *
     * @param object  receiver Model
     * @param string  method name
     * @param array
     */
    public function __construct($target, $method, $args=array()) {
        $this->_target = $target;
        $this->_method = $method;
        $this->args = $args;
        parent::__construct($this->_target);
    }

    /**
     * @return object
     */
    public function getTarget() {
        return $this->_target;
    }

    /**
     * @return string
     */
    public function getMethod() {
        return $this->_method;
    }

    /**
     * Returns model alias
     * 
     * @override
     * @return string
     */
    public function getAlias() {
        return $this->_target->alias;
    }
    
    /**
     * @return object QueryMethod
     */
    public function args() {
        $args = func_get_args();
        $this->args = $args;
        return $this;
    }

    /**
     * Invokes bound method and returns the result.
     * If $setMethod is supplied and the result is an array,
     * applies Set::$setMethod to it.
     * 
     * <code>
     * $finder->invoke();
     * $finder->invoke('extract', '/User/id');
     * </code>
     * 
     * @param string  method name of Set
     * @return mixed
     */
    public function invoke($setMethod=null) {
        $result = $this->_target->dispatchMethod($this->_method,
                                                 $this->getAllArguments());
        if(!is_array($result) || is_null($setMethod)) {
            return $result;
        }
        $args = func_get_args();
        array_shift($args);
        array_unshift($args, $result);
        return call_user_func_array(array($this->receiverSet, $setMethod),
                                    $args);
    }

    /**
     * Returns current arguments.
     * 
     * @return array
     */
    public function getAllArguments() {
        $args = $this->args;
        if(!empty($this->_options)) {
            array_push($args, $this->_options);
        }
        return $args;
    }

    /**
     * Prints current args and return $this.
     * 
     * @param callable
     * @return QueryMethod
     */
    public function printArgs($func='pr') {
        call_user_func($func, $this->getAllArguments());
        return $this;
    }

    /**
     * Imports query options from the Model.
     * Deprecated: use dynamic scoping instead.
     * 
     * @deprecated
     * @param array
     * @return object QueryMethod
     */
    public function import($arr) {
        if(func_num_args() == 1 && is_array($arr)) {
            $args = $arr;
        } else {
            $args = func_get_args();
        }
        foreach($args as $name) {
            $this->merge($this->_target->getQueryOptions($name));
        }
        return $this;
    }
  
}

/**
 * SubqueryExpression class
 *
 * @package QueryBuilder
 */
class SubqueryExpression extends ScopedQueryOptions {

    /**
     * @var string
     */
    public $type = 'expression';

    /**
     * @var object  Model
     */
    protected $_model;

    /**
     * @var array
     */
    public $subqueryDefaults
        = array('fields' => array(),
                'table' => null,
                'alias' => null,
                'limit' => null,
                'offset' => null,
                'joins' => array(),
                'conditions' => array(),
                'order' => null,
                'group' => null);

    /**
     * Constructor
     * 
     * @var object  Model
     */
    public function __construct($model) {
        $this->_model = $model;
        $this->_definedKeys = $this->subqueryDefaults;
        parent::__construct($this->_model);
    }

    /**
     * Sets table or alias depending on the first character of the name.
     * 
     * @param string
     * @return object  SubqueryExpression
     */
    public function tableOrAlias($name) {
        return preg_match('/^[a-z]/', $name) ?
            $this->table($name) : $this->alias($name);
    }

    /**
     * @override
     * @return string
     */
    public function getAlias() {
        return isset($this->alias) ? $this->alias : "";
    }

    /**
     * Returns SQL represention of this expression.
     * 
     * @return string
     */
    public function toSql() {
        $dbo = $this->_model->getDataSource();
        $options = am($this->subqueryDefaults, $this->_options);
        $sql = $dbo->buildStatement($options, $this->_model);
        return $sql;        
    }

    /**
     * Returns string represention of the expression.
     * 
     * @return value
     */
    public function __toString() {
        return "(". $this->toSql() .")";
    }

    /**
     * Overridden __get; calls __toString if $key is 'value'.
     * 
     * @override
     * @return mixed
     */
    public function &__get($key) {
        if($key === 'value') {
            $str = $this->__toString();
            return $str;
        } else {
            $ref =& parent::__get($key);
            return $ref;
        }
    }
}

