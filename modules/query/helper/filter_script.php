<?php

class filter_script extends query_base_model
{
    protected
        $parents = array(
            'filter',
            'filters',
            'and',
            'not',
            'or',
            'must_filter',
            'must_not_filter',
            'should_filter'
        ),
        $type = 'filter',
        $name = 'script';

    protected function constructBody()
    {
        $this->createHeader();
        $this->createMinus();

        $this->createNestDiv('script');
        $this->createLabel('script');
        $this->createTextInput('setval', 'longtext');
        $this->createCloseDiv();

        $this->createNestDiv('_cache');
        $this->createLabel('_cache');
        $this->createSelect('setval', array('' => 'default', 'true' => 'true', 'false' => 'false'));
        $this->createCloseDiv();

        return $this->output();
    }

}
