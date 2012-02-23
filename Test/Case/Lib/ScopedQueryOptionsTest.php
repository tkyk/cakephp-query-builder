<?php
App::import('Lib', 'QueryBuilder.QueryOptions');

class __ScopeObject extends Object {
    public $method1Args, $method2Args;

    function method1($options) {
        $this->method1Args = func_get_args();
    }

    function method2($options, $arg1, $arg2) {
        $this->method2Args = func_get_args();
    }
}


class ScopedQueryOptionsTest extends CakeTestCase {
    var $options, $scope;

    function startTest($m) {
        $this->scope = new __ScopeObject();
        $this->options = new ScopedQueryOptions($this->scope);
    }

    function testGetScope() {
        $this->assertIdentical($this->scope, $this->options->getScope());
    }

    function testCall() {
        $a = $this->options;

        $ret = $a->method1();
        $this->assertIdentical($a, $ret);
        $this->assertIdentical($this->scope->method1Args, array($a));

        $ret = $a->method2(1, 'aa');
        $this->assertIdentical($a, $ret);
        $this->assertIdentical($this->scope->method2Args, array($a, 1, 'aa'));
    }
}
