<?php

class PanelContext
{
    private $vars;

    public function __construct(array $vars = [])
    {
        $this->vars = $vars;
    }

    public function __get($name)
    {
        return $this->vars[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->vars[$name]);
    }
}
