<?php

namespace SpringImport\RestApiFilters\Filter;

class FieldsFilter extends AbstractFieldsFilter
{
    const FILTER_PARAMETER = 'fields';

    /**
     * {@inheritdoc}
     */
    protected function recursiveArrayCompare(array $array1, array $array2)
    {
        // @codingStandardsIgnoreStart
        //If the field in array2 (filter) is not present in array1 (response) it will be removed after intersect
        $arrayIntersect = array_intersect_key($array1, $array2);
        foreach ($arrayIntersect as $key => &$value) {
            if (is_array($value) && is_array($array2[$key])) {
                $value = $this->applyFilter($value, $array2[$key]);
            }
        }
        // @codingStandardsIgnoreEnd
        return $arrayIntersect;
    }
}
