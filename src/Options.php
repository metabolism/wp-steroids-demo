<?php

class Options  implements ArrayAccess, JsonSerializable
{
    /**
     * Magic method to get property
     *
     * @param $name
     * @return bool
     */
    public function __get($name) {

        return $this->get($name);
    }

    public function __isset($id) {

        //todo:
        return true;
    }

    public function jsonSerialize(): array
    {
        $data = [];

        if( function_exists('get_field') )
            $data = get_fields('option');

        return $data;
    }


    public function get($name)
    {
        if( function_exists('get_field') ){

            //get options using acf
            return get_field($name, 'option');
        }
        elseif( function_exists('carbon_get_theme_option') ){

            //get options using carbon fields
            return carbon_get_theme_option($name);
        }

        return false;
    }

    /**
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return (bool)$this->get($offset);
    }

    /**
     * @param $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value)
    {
        // TODO: Implement offsetSet() method.
    }

    /**
     * @param mixed $offset
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset)
    {
        // TODO: Implement offsetUnset() method.
    }
}
